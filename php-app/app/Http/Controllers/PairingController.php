<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ControllerPaired;
use App\Models\ControllerPairing;
use App\Models\ControllerRegistrationAttempt;
use App\Models\IoTController;
use App\Services\Billing\PlanLimitService;
use App\Services\Report\PinValueTransformer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PairingController extends Controller
{
    private const SYSTEM_CONTROLLER_ID = '0195f7e0-0000-7000-8000-000000000001';
    private const SYSTEM_CURRENT_TIME_PIN_ID = '0195f7e0-0000-7000-8000-000000000002';
    private const DEFAULT_TIME_ZONE = 'Europe/Moscow';
    private const REPORT_TIMELINE_STEP_SECONDS = 5;
    private const REPORT_TIMELINE_LENGTH = 17280;

    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly PinValueTransformer $pinValueTransformer,
    ) {
    }

    private function generateUniqueCode(array &$takenCodes): string
    {
        for ($attempt = 0; $attempt < 10000; $attempt++) {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (! isset($takenCodes[$code])) {
                $takenCodes[$code] = true;

                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate unique pairing code');
    }

    private function activeRegistrationAndPairingCodes(array $excludeRegistrationAttemptIds = []): array
    {
        $query = ControllerRegistrationAttempt::query()
            ->where('status', 'pending')
            ->where('expires_at', '>', now());

        if (count($excludeRegistrationAttemptIds) > 0) {
            $query->whereNotIn('id', $excludeRegistrationAttemptIds);
        }

        $takenCodes = $query
            ->pluck('code')
            ->mapWithKeys(fn ($code) => [(string) $code => true])
            ->all();

        ControllerPairing::query()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->pluck('code')
            ->each(function ($code) use (&$takenCodes): void {
                $takenCodes[(string) $code] = true;
            });

        return $takenCodes;
    }

    private function generateRegistrationCode(array &$takenCodes, ?string $avoidCode = null): string
    {
        if ($avoidCode !== null && $avoidCode !== '') {
            $takenCodes[$avoidCode] = true;
        }

        return $this->generateUniqueCode($takenCodes);
    }

    private function createControllerFromRegistrationAttempt(ControllerRegistrationAttempt $registrationAttempt, mixed $user): array
    {
        $controllerId = (string) Str::uuid();
        $now = now();
        $deviceUid = (string) $registrationAttempt->device_uid;
        $controllerName = 'ESP ' . strtoupper(substr($deviceUid, -6));
        $apiToken = Str::random(64);

        IoTController::query()->create([
            'id' => $controllerId,
            'user_id' => (int) $user->id,
            'api_token_hash' => hash('sha256', $apiToken),
            'pending_api_token' => $apiToken,
            'api_token_generated_at' => $now,
            'name' => $controllerName,
            'discription' => 'Registered from device_uid: ' . $deviceUid,
            'send_interval_seconds' => IoTController::MIN_INTERVAL_SECONDS,
            'status' => 'active',
            'last_seen_at' => $registrationAttempt->last_seen_at ?? $now,
            'claimed_at' => $now,
        ]);

        $registrationAttempt->status = 'claimed';
        $registrationAttempt->registered_controller_id = $controllerId;
        $registrationAttempt->claimed_at = $now;
        $registrationAttempt->save();

        ControllerRegistrationAttempt::query()
            ->where('device_uid', $deviceUid)
            ->whereIn('status', ['pending', 'challenge_pending'])
            ->where('id', '!=', (string) $registrationAttempt->id)
            ->update(['status' => 'expired']);

        event(new ControllerPaired($controllerId, (string) $user->id));

        return [
            'status' => 200,
            'data' => [
                'ok' => true,
                'controller_id' => $controllerId,
                'owner_user_id' => (string) $user->id,
                'claimed_at' => optional($registrationAttempt->claimed_at)->toIso8601String(),
            ],
        ];
    }

    public function unclaimed(): JsonResponse
    {
        $rows = IoTController::query()
            ->where('status', 'unclaimed')
            ->whereDoesntHave('users')
            ->orderByDesc('last_seen_at')
            ->limit(100)
            ->get(['id', 'name', 'discription', 'last_seen_at', 'created_at']);

        return response()->json(['controllers' => $rows]);
    }

    public function myControllers(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = DB::table('controller as c')
            ->leftJoin(
                DB::raw('(SELECT controller_id, COUNT(*) AS pin_count FROM pin GROUP BY controller_id) p'),
                'p.controller_id',
                '=',
                'c.id'
            )
            ->where('c.user_id', '=', (int) $user->id)
            ->where('c.id', '!=', self::SYSTEM_CONTROLLER_ID)
            ->select([
                'c.id',
                'c.name',
                'c.discription',
                'c.status',
                'c.send_interval_seconds',
                'c.last_seen_at',
                'c.claimed_at',
                DB::raw('COALESCE(p.pin_count, 0) AS pin_count'),
                DB::raw("'owner' AS role"),
            ])
            ->orderBy('c.name')
            ->get();

        return response()->json(['controllers' => $rows]);
    }

    public function myControllerPins(Request $request, string $controllerId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }

        $user = $request->user();

        $isOwner = DB::table('controller')
            ->where('id', $controllerId)
            ->where('user_id', (int) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        $pins = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->orderBy('pin')
            ->select($this->pinSelectColumns([
                'id',
                'controller_id',
                'pin',
                'label',
                'unit',
                'chart_range_hours',
                'digital_style',
                'value',
                'value_updated_at',
                'desired_digital_value',
                'desired_digital_updated_at',
                'show_on_chart',
                'show_on_report',
                'is_monitored',
                'external_enabled',
                'enable_scenario',
            ], true))
            ->get();

        $pins = $pins->map(function (object $pin): object {
            $rawValue = is_numeric($pin->value ?? null) ? (float) $pin->value : null;
            $pin->value = $this->pinValueTransformer->transform($pin, $rawValue);
            $pin->unit = $this->pinValueTransformer->resolveUnit($pin);

            return $pin;
        });

        return response()->json([
            'controller_id' => $controllerId,
            'pins' => $pins,
        ]);
    }

    public function myReportPins(Request $request): JsonResponse
    {
        $user = $request->user();

        $pins = DB::table('pin as p')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', (int) $user->id)
            ->where('p.digital_style', 'power')
            ->where('p.controller_id', '!=', self::SYSTEM_CONTROLLER_ID)
            ->orderByDesc('p.show_on_report')
            ->orderBy('p.pin')
            ->get(['p.id', 'p.pin', 'p.label']);

        return response()->json([
            'pins' => $pins->map(fn (object $p) => [
                'pin_id' => (string) $p->id,
                'pin' => (string) $p->pin,
                'label' => (string) ($p->label ?? $p->pin),
            ])->values()->all(),
        ]);
    }

    public function myControllerPowerEvents(Request $request, string $controllerId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }

        $user = $request->user();

        $isOwner = DB::table('controller')
            ->where('id', $controllerId)
            ->where('user_id', (int) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        $timeZone = $this->resolveUserTimeZone($user);

        $dayStartLocal = Carbon::now($timeZone)->startOfDay();
        $dayEndLocalExclusive = $dayStartLocal->copy()->addDay();
        $dayStartUtc = $dayStartLocal->copy()->utc();
        $dayEndUtcExclusive = $dayEndLocalExclusive->copy()->utc();
        $nowUtc = Carbon::now('UTC');

        $controllerSendInterval = (int) (DB::table('controller')
            ->where('id', $controllerId)
            ->value('send_interval_seconds') ?? IoTController::MIN_INTERVAL_SECONDS);
        $stepSeconds = min(
            IoTController::MAX_INTERVAL_SECONDS,
            max(IoTController::MIN_INTERVAL_SECONDS, $controllerSendInterval)
        );

        $powerPins = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->where('digital_style', 'power')
            ->orderBy('pin')
            ->get(['id', 'pin', 'label']);

        if ($powerPins->isEmpty()) {
            return response()->json([
                'controller_id' => $controllerId,
                'date' => $dayStartLocal->format('Y-m-d'),
                'time_zone' => $timeZone,
                'timeline_start' => $dayStartLocal->toIso8601String(),
                'timeline_end' => $dayEndLocalExclusive->toIso8601String(),
                'rows' => [],
            ]);
        }

        $powerPinIds = $powerPins->pluck('id')->map(fn ($id) => (string) $id)->all();

        $sampleRows = DB::table('pin_data as pd')
            ->join('pin as p', 'p.id', '=', 'pd.pin_id')
            ->where('p.controller_id', $controllerId)
            ->where('pd.created_at', '>=', $dayStartUtc)
            ->where('pd.created_at', '<', $dayEndUtcExclusive)
            ->orderBy('pd.created_at')
            ->get(['pd.created_at']);

        $rawSampleTimestamps = [];
        foreach ($sampleRows as $sampleRow) {
            $utc = Carbon::parse((string) $sampleRow->created_at, 'UTC');
            $key = $utc->format('Y-m-d H:i:s');
            if (! isset($rawSampleTimestamps[$key])) {
                $rawSampleTimestamps[$key] = $utc;
            }
        }
        $rawSampleTimestamps = array_values($rawSampleTimestamps);

        if (count($rawSampleTimestamps) > 1) {
            $diffFrequency = [];
            for ($i = 1; $i < count($rawSampleTimestamps); $i++) {
                $diff = $rawSampleTimestamps[$i - 1]->diffInSeconds($rawSampleTimestamps[$i], false);
                if ($diff <= 0 || $diff > 3600) {
                    continue;
                }
                $diffFrequency[$diff] = ($diffFrequency[$diff] ?? 0) + 1;
            }
            if (count($diffFrequency) > 0) {
                arsort($diffFrequency);
                $observedStep = (int) array_key_first($diffFrequency);
                $stepSeconds = max(IoTController::MIN_INTERVAL_SECONDS, $observedStep);
            }
        }

        $sampleTimestamps = [];
        for ($cursor = $dayStartUtc->copy(); $cursor->lt($dayEndUtcExclusive); $cursor->addSeconds($stepSeconds)) {
            $sampleTimestamps[] = $cursor->copy();
        }
        if (count($sampleTimestamps) === 0) {
            $sampleTimestamps[] = $dayStartUtc->copy();
        }

        $scenarioRows = DB::table('scenario')
            ->whereIn('pin_id', $powerPinIds)
            ->get(['id', 'pin_id']);
        $scenarioIds = $scenarioRows->pluck('id')->map(fn ($id) => (string) $id)->all();

        $conditions = collect();
        if (count($scenarioIds) > 0) {
            $conditions = DB::table('scenario_condition')
                ->whereIn('scenario_id', $scenarioIds)
                ->get(['scenario_id', 'pin_id', 'operator', 'threshold']);
        }

        $scenarioByPinId = [];
        foreach ($scenarioRows as $scenarioRow) {
            $targetPinId = (string) $scenarioRow->pin_id;
            $scenarioByPinId[$targetPinId] ??= [];
            $scenarioByPinId[$targetPinId][] = (string) $scenarioRow->id;
        }

        $conditionsByScenarioId = [];
        $sourcePinIds = [];
        foreach ($conditions as $condition) {
            $scenarioId = (string) $condition->scenario_id;
            $conditionsByScenarioId[$scenarioId] ??= [];
            $conditionsByScenarioId[$scenarioId][] = $condition;
            $conditionPinId = (string) $condition->pin_id;
            if ($conditionPinId !== self::SYSTEM_CURRENT_TIME_PIN_ID) {
                $sourcePinIds[] = $conditionPinId;
            }
        }
        $sourcePinIds = array_values(array_unique(array_merge($sourcePinIds, $powerPinIds)));
        $sourceMaxAgeSeconds = max(60, (int) config('smarthome.pin_data_average_interval_minutes', 5) * 60);

        $normalizeValue = static function (string $pinId, ?float $rawValue): ?float {
            return $rawValue;
        };

        $seriesByPinId = [];
        foreach ($sourcePinIds as $pinId) {
            $seriesByPinId[$pinId] = [];

            $rows = DB::table('pin_data')
                ->where('pin_id', $pinId)
                ->where('created_at', '>=', $dayStartUtc)
                ->where('created_at', '<', $dayEndUtcExclusive)
                ->orderBy('created_at')
                ->get(['created_at', 'value']);

            foreach ($rows as $row) {
                if (! is_numeric($row->value)) {
                    continue;
                }
                $seriesByPinId[$pinId][] = [
                    'ts' => Carbon::parse((string) $row->created_at, 'UTC'),
                    'value' => $normalizeValue($pinId, (float) $row->value),
                ];
            }
        }

        $pointersByPinId = [];
        foreach ($seriesByPinId as $pinId => $series) {
            $pointersByPinId[$pinId] = 0;
            if (count($series) === 0) {
                continue;
            }
            while (
                ($pointersByPinId[$pinId] + 1) < count($series)
                && $series[$pointersByPinId[$pinId] + 1]['ts']->lessThanOrEqualTo($dayStartUtc)
            ) {
                $pointersByPinId[$pinId]++;
            }
        }

        $valueAt = function (
            string $pinId,
            Carbon $timestampUtc
        ) use (&$seriesByPinId, &$pointersByPinId, $sourceMaxAgeSeconds): ?float {
            $series = $seriesByPinId[$pinId] ?? [];
            if (count($series) === 0) {
                return null;
            }

            $idx = (int) ($pointersByPinId[$pinId] ?? 0);
            while (($idx + 1) < count($series) && $series[$idx + 1]['ts']->lessThanOrEqualTo($timestampUtc)) {
                $idx++;
            }
            $pointersByPinId[$pinId] = $idx;

            $valuePointTs = $series[$idx]['ts'] ?? null;
            if (! ($valuePointTs instanceof Carbon)) {
                return null;
            }
            if ($valuePointTs->greaterThan($timestampUtc)) {
                return null;
            }
            if ($valuePointTs->diffInSeconds($timestampUtc, false) > $sourceMaxAgeSeconds) {
                return null;
            }

            return isset($series[$idx]['value']) ? (float) $series[$idx]['value'] : null;
        };

        $evaluateCondition = static function (string $operator, ?float $sourceValue, float $threshold): bool {
            if ($sourceValue === null) {
                return false;
            }
            return match (strtolower(trim($operator))) {
                'gt' => $sourceValue > $threshold,
                'gte' => $sourceValue >= $threshold,
                'lt' => $sourceValue < $threshold,
                'lte' => $sourceValue <= $threshold,
                'eq' => abs($sourceValue - $threshold) < 0.000001,
                'ne' => abs($sourceValue - $threshold) >= 0.000001,
                default => false,
            };
        };

        $rowsPayload = [];
        foreach ($powerPins as $powerPin) {
            $pinId = (string) $powerPin->id;
            $scenarioIdList = $scenarioByPinId[$pinId] ?? [];
            $factIntervals = [];
            $planIntervals = [];

            $factRows = DB::table('pin_data')
                ->where('pin_id', $pinId)
                ->where('created_at', '>=', $dayStartUtc)
                ->where('created_at', '<', $dayEndUtcExclusive)
                ->orderBy('created_at')
                ->get(['created_at', 'value']);

            for ($i = 0; $i < count($factRows); $i++) {
                $factStartTs = Carbon::parse((string) $factRows[$i]->created_at, 'UTC');
                if ($factStartTs->greaterThanOrEqualTo($nowUtc)) {
                    break;
                }
                $nextFactTs = ($i + 1) < count($factRows)
                    ? Carbon::parse((string) $factRows[$i + 1]->created_at, 'UTC')
                    : $dayEndUtcExclusive->copy();
                $factEndTs = $nextFactTs->copy();
                if ($factEndTs->greaterThan($nowUtc)) {
                    $factEndTs = $nowUtc->copy();
                }
                if ($factEndTs->lessThanOrEqualTo($factStartTs)) {
                    continue;
                }

                $normalizedFactValue = $normalizeValue($pinId, is_numeric($factRows[$i]->value) ? (float) $factRows[$i]->value : null);
                $factOn = $normalizedFactValue !== null && ((int) round($normalizedFactValue)) > 0;
                if (! $factOn) {
                    continue;
                }

                $factIntervals[] = [
                    'start' => $factStartTs->copy()->setTimezone($timeZone)->toIso8601String(),
                    'end' => $factEndTs->copy()->setTimezone($timeZone)->toIso8601String(),
                ];
            }

            for ($i = 0; $i < count($sampleTimestamps); $i++) {
                $startTs = $sampleTimestamps[$i];
                $endTs = $sampleTimestamps[$i + 1] ?? $dayEndUtcExclusive;
                if ($endTs->lessThanOrEqualTo($startTs)) {
                    continue;
                }

                $planState = null;

                $pickHigherPriorityState = static function (?string $current, ?string $candidate): ?string {
                    if ($candidate === null) {
                        return $current;
                    }
                    $priority = [
                        'none' => 0,
                        'green' => 1,
                        'yellow' => 2,
                        'red' => 3,
                    ];
                    $currentPriority = $priority[$current ?? 'none'] ?? 0;
                    $candidatePriority = $priority[$candidate] ?? 0;
                    return $candidatePriority >= $currentPriority ? $candidate : $current;
                };

                foreach ($scenarioIdList as $scenarioId) {
                    $scenarioConditions = $conditionsByScenarioId[$scenarioId] ?? [];
                    if (count($scenarioConditions) === 0) {
                        continue;
                    }

                    $allTrue = true;
                    $timeConditionsAllTrue = true;
                    $hasCurrentTimeCondition = false;

                    foreach ($scenarioConditions as $condition) {
                        $sourcePinId = (string) $condition->pin_id;
                        if ($sourcePinId === self::SYSTEM_CURRENT_TIME_PIN_ID) {
                            $hasCurrentTimeCondition = true;
                            $localTime = $startTs->copy()->setTimezone($timeZone);
                            $sourceValue = ((int) $localTime->format('H') * 3600) + ((int) $localTime->format('i') * 60) + (int) $localTime->format('s');
                        } else {
                            $sourceValue = $valueAt($sourcePinId, $startTs);
                        }

                        $threshold = is_numeric($condition->threshold) ? (float) $condition->threshold : 0.0;
                        $conditionTrue = $evaluateCondition((string) $condition->operator, $sourceValue, $threshold);
                        if (! $conditionTrue) {
                            $allTrue = false;
                            if ($sourcePinId === self::SYSTEM_CURRENT_TIME_PIN_ID) {
                                $timeConditionsAllTrue = false;
                            }
                        }
                    }

                    $scenarioState = null;
                    if ($hasCurrentTimeCondition) {
                        if ($timeConditionsAllTrue) {
                            $scenarioState = $allTrue ? 'green' : 'yellow';
                        }
                    } elseif ($allTrue) {
                        $scenarioState = 'red';
                    }

                    $planState = $pickHigherPriorityState($planState, $scenarioState);
                }

                if ($planState !== null) {
                    $planIntervals[] = [
                        'start' => $startTs->copy()->setTimezone($timeZone)->toIso8601String(),
                        'end' => $endTs->copy()->setTimezone($timeZone)->toIso8601String(),
                        'state' => $planState,
                    ];
                }
            }

            $rowsPayload[] = [
                'pin_id' => $pinId,
                'pin' => (string) $powerPin->pin,
                'label' => (string) ($powerPin->label ?? $powerPin->pin),
                'fact' => $factIntervals,
                'plan' => $planIntervals,
            ];
        }

        return response()->json([
            'controller_id' => $controllerId,
            'date' => $dayStartLocal->format('Y-m-d'),
            'time_zone' => $timeZone,
            'timeline_start' => $dayStartLocal->toIso8601String(),
            'timeline_end' => $dayEndLocalExclusive->toIso8601String(),
            'rows' => $rowsPayload,
        ]);
    }

    public function myReport(Request $request): JsonResponse
    {
        $user = $request->user();

        $timeZone = $this->resolveUserTimeZone($user);

        $dayStartLocal = Carbon::now($timeZone)->startOfDay();
        $dayEndLocalExclusive = $dayStartLocal->copy()->addDay();
        $dayStartUtc = $dayStartLocal->copy()->utc();
        $dayEndUtcExclusive = $dayEndLocalExclusive->copy()->utc();

        $selectedPinId = trim((string) $request->query('pin_id', ''));
        if ($selectedPinId === '') {
            return response()->json(['error' => 'validation_error', 'message' => 'pin_id is required'], 422);
        }

        $selectedPin = DB::table('pin as p')
            ->where('p.id', $selectedPinId)
            ->where('p.digital_style', 'power')
            ->where('p.controller_id', '!=', self::SYSTEM_CONTROLLER_ID)
            ->whereExists(function ($q) use ($user) {
                $q->selectRaw('1')
                    ->from('controller as c')
                    ->whereColumn('c.id', 'p.controller_id')
                    ->where('c.user_id', (int) $user->id);
            })
            ->first(['p.id', 'p.pin', 'p.label', 'p.controller_id']);

        if (! $selectedPin) {
            return response()->json(['error' => 'forbidden', 'message' => 'Pin is not available for report.'], 403);
        }

        $timelineLength = self::REPORT_TIMELINE_LENGTH;
        $timelineStepSeconds = self::REPORT_TIMELINE_STEP_SECONDS;
        $factTimeline = array_fill(0, $timelineLength, null);
        $planTimeline = array_fill(0, $timelineLength, 0);

        $pinId = (string) $selectedPin->id;
        $previousFactValue = DB::table('pin_data')
            ->where('pin_id', $pinId)
            ->where('created_at', '<', $dayStartUtc)
            ->whereIn('value', [0, 1])
            ->orderByDesc('created_at')
            ->value('value');
        if (is_numeric($previousFactValue)) {
            $factTimeline[0] = ((int) $previousFactValue) > 0 ? 1 : 0;
        }

        $factRows = DB::table('pin_data')
            ->where('pin_id', $pinId)
            ->where('created_at', '>=', $dayStartUtc)
            ->where('created_at', '<', $dayEndUtcExclusive)
            ->whereIn('value', [0, 1])
            ->orderBy('created_at')
            ->get(['created_at', 'value']);

        foreach ($factRows as $row) {
            $sec = $this->secondOffsetFromDayStart(
                (string) $row->created_at,
                $dayStartUtc,
                $timelineLength,
                $timelineStepSeconds
            );
            if ($sec !== null) {
                $factTimeline[$sec] = ((int) $row->value) > 0 ? 1 : 0;
            }
        }
        $this->forwardFillTimeline($factTimeline);
        $nowLocal = Carbon::now($timeZone);
        $currentTimelineIndex = (int) floor(
            max(0, $dayStartLocal->diffInSeconds($nowLocal, false)) / max(1, $timelineStepSeconds)
        );
        $currentTimelineIndex = min($timelineLength - 1, max(0, $currentTimelineIndex));
        for ($i = $currentTimelineIndex + 1; $i < $timelineLength; $i++) {
            $factTimeline[$i] = null;
        }

        $scenarioRows = DB::table('scenario')
            ->where('pin_id', $pinId)
            ->get(['id']);
        $scenarioIds = $scenarioRows->pluck('id')->map(fn ($id) => (string) $id)->all();

        $conditionsByScenarioId = [];
        $sourcePinIds = [];
        if (count($scenarioIds) > 0) {
            $conditions = DB::table('scenario_condition')
                ->whereIn('scenario_id', $scenarioIds)
                ->get(['scenario_id', 'pin_id', 'operator', 'threshold']);
            foreach ($conditions as $condition) {
                $scenarioId = (string) $condition->scenario_id;
                $conditionsByScenarioId[$scenarioId] ??= [];
                $conditionsByScenarioId[$scenarioId][] = $condition;
                $condPinId = (string) $condition->pin_id;
                if ($condPinId !== self::SYSTEM_CURRENT_TIME_PIN_ID) {
                    $sourcePinIds[] = $condPinId;
                }
            }
        }

        $sourcePinIds = array_values(array_unique($sourcePinIds));
        if (count($sourcePinIds) > 0) {
            $sourcePinIds = DB::table('pin as p')
                ->join('controller as c', 'c.id', '=', 'p.controller_id')
                ->where('c.user_id', (int) $user->id)
                ->whereIn('p.id', $sourcePinIds)
                ->pluck('p.id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        $sourceTimelineByPin = [];
        foreach ($sourcePinIds as $sourcePinId) {
            $line = array_fill(0, $timelineLength, null);
            $previousSourceValue = DB::table('pin_data')
                ->where('pin_id', $sourcePinId)
                ->where('created_at', '<', $dayStartUtc)
                ->orderByDesc('created_at')
                ->value('value');
            if (is_numeric($previousSourceValue)) {
                $line[0] = (float) $previousSourceValue;
            }

            $rows = DB::table('pin_data')
                ->where('pin_id', $sourcePinId)
                ->where('created_at', '>=', $dayStartUtc)
                ->where('created_at', '<', $dayEndUtcExclusive)
                ->orderBy('created_at')
                ->get(['created_at', 'value']);
            foreach ($rows as $row) {
                if (! is_numeric($row->value)) {
                    continue;
                }
                $sec = $this->secondOffsetFromDayStart(
                    (string) $row->created_at,
                    $dayStartUtc,
                    $timelineLength,
                    $timelineStepSeconds
                );
                if ($sec !== null) {
                    $line[$sec] = (float) $row->value;
                }
            }
            $this->forwardFillTimeline($line);
            $sourceTimelineByPin[$sourcePinId] = $line;
        }

        foreach (range(0, $timelineLength - 1) as $timelineIndex) {
            $secondOfDay = $timelineIndex * $timelineStepSeconds;
            $evaluatedScenarios = 0;
            $falseScenarios = 0;
            $trueScenarios = 0;

            foreach ($scenarioIds as $scenarioId) {
                $scenarioConditions = $conditionsByScenarioId[$scenarioId] ?? [];
                if (count($scenarioConditions) === 0) {
                    continue;
                }

                $scenarioTrue = true;
                foreach ($scenarioConditions as $condition) {
                    $condPinId = (string) $condition->pin_id;
                    $sourceValue = null;
                    if ($condPinId === self::SYSTEM_CURRENT_TIME_PIN_ID) {
                        $sourceValue = (float) $secondOfDay;
                    } elseif (isset($sourceTimelineByPin[$condPinId])) {
                        $sourceValue = $sourceTimelineByPin[$condPinId][$timelineIndex] ?? null;
                        $sourceValue = is_numeric($sourceValue) ? (float) $sourceValue : null;
                    }

                    $threshold = is_numeric($condition->threshold) ? (float) $condition->threshold : 0.0;
                    if (! $this->evaluateReportCondition((string) $condition->operator, $sourceValue, $threshold)) {
                        $scenarioTrue = false;
                        break;
                    }
                }

                $evaluatedScenarios++;
                if (! $scenarioTrue) {
                    $falseScenarios++;
                } else {
                    $trueScenarios++;
                }
            }

            if ($evaluatedScenarios === 0 || $trueScenarios === 0) {
                $planTimeline[$timelineIndex] = 0;
            } elseif ($falseScenarios === 0) {
                $planTimeline[$timelineIndex] = 1;
            } else {
                $planTimeline[$timelineIndex] = 2;
            }
        }
        $this->forwardFillTimeline($planTimeline);

        $factIntervals = $this->clipIntervalsAtNow($this->timelineToIntervals(
            $factTimeline,
            $dayStartLocal,
            $timeZone,
            $timelineStepSeconds,
            fn (int $v) => $v === 1,
            static fn () => null
        ), $nowLocal, $timeZone);

        $rowsPayload = [[
            'pin_id' => $pinId,
            'pin' => (string) $selectedPin->pin,
            'label' => (string) ($selectedPin->label ?? $selectedPin->pin),
            'fact' => $factIntervals,
            'plan' => $this->timelineToIntervals(
                $planTimeline,
                $dayStartLocal,
                $timeZone,
                $timelineStepSeconds,
                fn (int $v) => $v > 0,
                static fn (int $v) => $v === 1 ? 'green' : 'yellow'
            ),
        ]];

        return response()->json([
            'date' => $dayStartLocal->format('Y-m-d'),
            'time_zone' => $timeZone,
            'timeline_start' => $dayStartLocal->toIso8601String(),
            'timeline_end' => $dayEndLocalExclusive->toIso8601String(),
            'rows' => $rowsPayload,
        ]);
    }

    private function secondOffsetFromDayStart(
        string $createdAtUtc,
        Carbon $dayStartUtc,
        int $timelineLength,
        int $timelineStepSeconds
    ): ?int
    {
        $secondsFromStart = $dayStartUtc->diffInSeconds(Carbon::parse($createdAtUtc, 'UTC'), false);
        if ($secondsFromStart < 0) {
            return null;
        }

        $index = (int) floor($secondsFromStart / max(1, $timelineStepSeconds));
        if ($index < 0 || $index >= $timelineLength) {
            return null;
        }

        return $index;
    }

    private function resolveUserTimeZone(object $user): string
    {
        $timeZone = (string) ($user->time_zone ?: self::DEFAULT_TIME_ZONE);
        if (! in_array($timeZone, \DateTimeZone::listIdentifiers(), true)) {
            return self::DEFAULT_TIME_ZONE;
        }

        return $timeZone;
    }

    /**
     * @param array<int, int|float|null> $timeline
     */
    private function forwardFillTimeline(array &$timeline): void
    {
        $last = $timeline[0] ?? 0;
        foreach ($timeline as $i => $value) {
            if ($value === null) {
                $timeline[$i] = $last;
            } else {
                $last = $value;
            }
        }
    }

    private function evaluateReportCondition(string $operator, ?float $sourceValue, float $threshold): bool
    {
        if ($sourceValue === null) {
            return false;
        }
        return match (strtolower(trim($operator))) {
            'gt' => $sourceValue > $threshold,
            'gte' => $sourceValue >= $threshold,
            'lt' => $sourceValue < $threshold,
            'lte' => $sourceValue <= $threshold,
            'eq' => abs($sourceValue - $threshold) < 0.000001,
            'ne' => abs($sourceValue - $threshold) >= 0.000001,
            default => false,
        };
    }

    /**
     * @param array<int, array{start:string,end:string,state?:string}> $intervals
     * @return array<int, array{start:string,end:string,state?:string}>
     */
    private function clipIntervalsAtNow(array $intervals, Carbon $nowLocal, string $timeZone): array
    {
        $result = [];
        foreach ($intervals as $interval) {
            $start = Carbon::parse((string) $interval['start'])->setTimezone($timeZone);
            $end = Carbon::parse((string) $interval['end'])->setTimezone($timeZone);

            if ($start->greaterThanOrEqualTo($nowLocal)) {
                continue;
            }

            if ($end->greaterThan($nowLocal)) {
                $interval['end'] = $nowLocal->copy()->setTimezone($timeZone)->toIso8601String();
            }

            $result[] = $interval;
        }

        return $result;
    }

    /**
     * @param array<int, int|float|null> $timeline
     * @param callable(int):bool $isOn
     * @param callable(int):(?string) $stateResolver
     * @return array<int, array{start:string,end:string,state?:string}>
     */
    private function timelineToIntervals(
        array $timeline,
        Carbon $dayStartLocal,
        string $timeZone,
        int $timelineStepSeconds,
        callable $isOn,
        callable $stateResolver
    ): array
    {
        $intervals = [];
        $len = count($timeline);
        $start = null;
        $state = null;

        for ($i = 0; $i < $len; $i++) {
            $value = (int) ($timeline[$i] ?? 0);
            $on = $isOn($value);
            $newState = $on ? $stateResolver($value) : null;

            if ($start === null && $on) {
                $start = $i;
                $state = $newState;
                continue;
            }

            if ($start !== null) {
                if (! $on || $newState !== $state) {
                    $payload = [
                        'start' => $dayStartLocal->copy()->addSeconds($start * $timelineStepSeconds)->setTimezone($timeZone)->toIso8601String(),
                        'end' => $dayStartLocal->copy()->addSeconds($i * $timelineStepSeconds)->setTimezone($timeZone)->toIso8601String(),
                    ];
                    if ($state !== null) {
                        $payload['state'] = $state;
                    }
                    $intervals[] = $payload;
                    $start = $on ? $i : null;
                    $state = $on ? $newState : null;
                }
            }
        }

        if ($start !== null) {
            $payload = [
                'start' => $dayStartLocal->copy()->addSeconds($start * $timelineStepSeconds)->setTimezone($timeZone)->toIso8601String(),
                'end' => $dayStartLocal->copy()->addDay()->setTimezone($timeZone)->toIso8601String(),
            ];
            if ($state !== null) {
                $payload['state'] = $state;
            }
            $intervals[] = $payload;
        }

        return $intervals;
    }

    public function myControllerPinChartData(Request $request, string $controllerId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }

        $user = $request->user();

        $isOwner = DB::table('controller')
            ->where('id', $controllerId)
            ->where('user_id', (int) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        $timeZone = $this->resolveUserTimeZone($user);

        $pins = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->where('digital_style', 'like', 'sensor%')
            ->where('show_on_chart', 1)
            ->orderBy('pin')
            ->select($this->pinSelectColumns(['id', 'pin', 'chart_range_hours', 'digital_style', 'unit'], true))
            ->get();

        $result = [];
        $avgMinutes = max(1, (int) config('smarthome.pin_data_average_interval_minutes', 5));
        foreach ($pins as $pin) {
            $pinId = (string) $pin->id;
            $rangeHours = max(1, (int) ($pin->chart_range_hours ?? 24));

            $rows = DB::select(
                'SELECT
                    FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(created_at) / (? * 60)) * (? * 60)) AS bucket_at,
                    AVG(value) AS avg_value
                 FROM pin_data
                 WHERE pin_id = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                 GROUP BY bucket_at
                 ORDER BY bucket_at ASC',
                [$avgMinutes, $avgMinutes, $pinId, $rangeHours]
            );

            $points = [];
            foreach ($rows as $row) {
                $transformed = $this->pinValueTransformer->transform($pin, is_numeric($row->avg_value) ? (float) $row->avg_value : null);
                if ($transformed === null) {
                    continue;
                }
                $bucketAt = Carbon::parse((string) $row->bucket_at, 'UTC')
                    ->setTimezone($timeZone)
                    ->format('Y-m-d H:i:s');
                $points[] = [
                    'at' => $bucketAt,
                    'value' => $transformed,
                ];
            }

            $result[$pinId] = [
                'pin' => (string) $pin->pin,
                'average_interval_minutes' => $avgMinutes,
                'chart_range_hours' => $rangeHours,
                'points' => $points,
            ];
        }

        return response()->json([
            'controller_id' => $controllerId,
            'time_zone' => $timeZone,
            'charts' => $result,
        ]);
    }

    public function updateMyControllerPinSettings(Request $request, string $controllerId, string $pinId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }
        if (! Str::isUuid($pinId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'pin_id must be UUID'], 422);
        }

        $user = $request->user();

        $isOwner = DB::table('controller')
            ->where('id', $controllerId)
            ->where('user_id', (int) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        $pin = DB::table('pin')
            ->where('id', $pinId)
            ->where('controller_id', $controllerId)
            ->first();

        if (! $pin) {
            return response()->json(['error' => 'not_found', 'message' => 'Pin not found'], 404);
        }

        $rules = [
            'label' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'chart_range_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'show_on_chart' => ['required', 'boolean'],
            'show_on_report' => ['required', 'boolean'],
            'is_monitored' => ['required', 'boolean'],
            'external_enabled' => ['required', 'boolean'],
        ];
        $isHumiditySensor = (string) ($pin->digital_style ?? '') === 'sensor_humidity';
        if ($this->hasMoistureColumns() && $isHumiditySensor) {
            $rules['moisture_raw_dry'] = ['nullable', 'numeric'];
            $rules['moisture_raw_wet'] = ['nullable', 'numeric'];
            $rules['moisture_show_percent'] = ['sometimes', 'boolean'];
        }
        $validated = $request->validate($rules);

        $updatePayload = [
            'label' => (string) $validated['label'],
            'unit' => isset($validated['unit']) && trim((string) $validated['unit']) !== '' ? (string) $validated['unit'] : null,
            'chart_range_hours' => (int) $validated['chart_range_hours'],
            'show_on_chart' => ! empty($validated['show_on_chart']) ? 1 : 0,
            'show_on_report' => ! empty($validated['show_on_report']) ? 1 : 0,
            'is_monitored' => ! empty($validated['is_monitored']) ? 1 : 0,
            'external_enabled' => ! empty($validated['external_enabled']) ? 1 : 0,
        ];
        if ($this->hasMoistureColumns() && $isHumiditySensor) {
            if (array_key_exists('moisture_raw_dry', $validated)) {
                $updatePayload['moisture_raw_dry'] = $validated['moisture_raw_dry'] !== '' && $validated['moisture_raw_dry'] !== null
                    ? (float) $validated['moisture_raw_dry']
                    : null;
            }
            if (array_key_exists('moisture_raw_wet', $validated)) {
                $updatePayload['moisture_raw_wet'] = $validated['moisture_raw_wet'] !== '' && $validated['moisture_raw_wet'] !== null
                    ? (float) $validated['moisture_raw_wet']
                    : null;
            }
            if (array_key_exists('moisture_show_percent', $validated)) {
                $updatePayload['moisture_show_percent'] = ! empty($validated['moisture_show_percent']) ? 1 : 0;
            }
        }

        DB::table('pin')
            ->where('id', $pinId)
            ->where('controller_id', $controllerId)
            ->update($updatePayload);

        $updatedPin = DB::table('pin')
            ->where('id', $pinId)
            ->where('controller_id', $controllerId)
            ->first($this->pinSelectColumns([
                'id',
                'controller_id',
                'pin',
                'label',
                'unit',
                'chart_range_hours',
                'digital_style',
                'value',
                'value_updated_at',
                'desired_digital_value',
                'show_on_chart',
                'show_on_report',
                'is_monitored',
                'external_enabled',
            ], true));

        if ($updatedPin) {
            $rawValue = is_numeric($updatedPin->value ?? null) ? (float) $updatedPin->value : null;
            $updatedPin->value = $this->pinValueTransformer->transform($updatedPin, $rawValue);
            $updatedPin->unit = $this->pinValueTransformer->resolveUnit($updatedPin);
        }

        return response()->json([
            'ok' => true,
            'pin' => $updatedPin,
        ]);
    }

    /**
     * @param array<int,string> $baseColumns
     * @return array<int,string>
     */
    private function pinSelectColumns(array $baseColumns, bool $withMoisture = false): array
    {
        $columns = $baseColumns;
        if ($withMoisture && $this->hasMoistureColumns()) {
            $columns[] = 'moisture_raw_dry';
            $columns[] = 'moisture_raw_wet';
            $columns[] = 'moisture_show_percent';
        }

        return $columns;
    }

    private function hasMoistureColumns(): bool
    {
        return Schema::hasColumn('pin', 'moisture_raw_dry')
            && Schema::hasColumn('pin', 'moisture_raw_wet')
            && Schema::hasColumn('pin', 'moisture_show_percent');
    }

    public function updateMyControllerPinDesiredDigitalValue(Request $request, string $controllerId, string $pinId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }
        if (! Str::isUuid($pinId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'pin_id must be UUID'], 422);
        }

        $user = $request->user();

        $isOwner = DB::table('controller')
            ->where('id', $controllerId)
            ->where('user_id', (int) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        $validated = $request->validate([
            'desired_digital_value' => ['required', 'integer', 'in:0,1'],
        ]);

        $pin = DB::table('pin')
            ->where('id', $pinId)
            ->where('controller_id', $controllerId)
            ->first(['id', 'controller_id', 'pin', 'label', 'digital_style', 'desired_digital_value']);

        if (! $pin) {
            return response()->json(['error' => 'not_found', 'message' => 'Pin not found'], 404);
        }

        if ((string) ($pin->digital_style ?? '') !== 'power') {
            return response()->json(['error' => 'validation_error', 'message' => 'Only power pins can be switched'], 422);
        }

        DB::table('pin')
            ->where('id', $pinId)
            ->where('controller_id', $controllerId)
            ->update([
                'desired_digital_value' => (int) $validated['desired_digital_value'],
                'desired_digital_updated_at' => now(),
                'last_on_command_sent_at' => null,
                'enable_scenario' => 0,
            ]);

        $updatedPin = DB::table('pin')
            ->where('id', $pinId)
            ->where('controller_id', $controllerId)
            ->first([
                'id',
                'controller_id',
                'pin',
                'label',
                'digital_style',
                'desired_digital_value',
                'desired_digital_updated_at',
                'last_on_command_sent_at',
                'enable_scenario',
            ]);

        return response()->json([
            'ok' => true,
            'pin' => $updatedPin,
        ]);
    }

    public function updateMyControllerSettings(Request $request, string $controllerId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }

        $user = $request->user();
        $effectivePlan = $this->planLimitService->resolveEffectivePlanForUser($user);
        $minimumSendIntervalSeconds = max(
            IoTController::MIN_INTERVAL_SECONDS,
            (int) ($effectivePlan?->min_report_interval_seconds ?? 0)
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'discription' => ['nullable', 'string', 'max:2000'],
            'send_interval_seconds' => [
                'required',
                'integer',
                'min:' . $minimumSendIntervalSeconds,
                'max:' . IoTController::MAX_INTERVAL_SECONDS,
            ],
        ], [
            'send_interval_seconds.min' => __(
                'Send interval is below the minimum allowed for your plan (:seconds sec).',
                ['seconds' => $minimumSendIntervalSeconds]
            ),
        ]);

        $isOwner = DB::table('controller')
            ->where('id', $controllerId)
            ->where('user_id', (int) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        DB::table('controller')
            ->where('id', $controllerId)
            ->update([
                'name' => (string) $validated['name'],
                'discription' => isset($validated['discription']) && trim((string) $validated['discription']) !== ''
                    ? (string) $validated['discription']
                    : null,
                'send_interval_seconds' => (int) $validated['send_interval_seconds'],
                'updated_at' => now(),
            ]);

        $controller = DB::table('controller')
            ->where('id', $controllerId)
            ->first(['id', 'name', 'discription', 'send_interval_seconds', 'status', 'last_seen_at', 'claimed_at']);

        return response()->json([
            'ok' => true,
            'controller' => $controller,
        ]);
    }

    public function startAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $allowance = $this->planLimitService->controllerAttachAllowance($user);
        if (! $allowance['allowed']) {
            return response()->json([
                'error' => 'plan_limit_exceeded',
                'message' => 'Controller limit reached for effective plan.',
                'used' => $allowance['used'],
                'max' => $allowance['max'],
            ], 409);
        }

        $payload = DB::transaction(function () use ($user) {
            $controllers = DB::table('controller as c')
                ->whereNull('c.user_id')
                ->where('c.status', 'unclaimed')
                ->orderByDesc('c.last_seen_at')
                ->select('c.id')
                ->lockForUpdate()
                ->get();

            if ($controllers->isEmpty()) {
                return [
                    'status' => 404,
                    'data' => [
                        'error' => 'not_found',
                        'message' => 'No free controllers available for pairing',
                    ],
                ];
            }

            $controllerIds = $controllers->pluck('id')->map(fn ($id) => (string) $id)->all();

            ControllerPairing::query()
                ->whereIn('controller_id', $controllerIds)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            $takenCodes = ControllerPairing::query()
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->pluck('code')
                ->map(fn ($code) => (string) $code)
                ->flip()
                ->all();

            $created = [];
            foreach ($controllerIds as $controllerId) {
                $code = $this->generateUniqueCode($takenCodes);

                $pairing = ControllerPairing::query()->create([
                    'id' => (string) Str::uuid(),
                    'controller_id' => $controllerId,
                    'user_id' => (string) $user->id,
                    'code' => $code,
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes(5),
                ]);

                $created[] = [
                    'controller_id' => $controllerId,
                    'pairing_code' => $pairing->code,
                    'expires_at' => optional($pairing->expires_at)->toIso8601String(),
                ];
            }

            return [
                'status' => 200,
                'data' => [
                    'ok' => true,
                    'created_count' => count($created),
                    'pairings' => $created,
                ],
            ];
        });

        return response()->json($payload['data'], $payload['status']);
    }

    public function confirmByCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
            'registration_token' => ['nullable', 'string', 'max:96'],
        ]);

        $user = $request->user();

        $allowance = $this->planLimitService->controllerAttachAllowance($user);
        if (! $allowance['allowed']) {
            return response()->json([
                'error' => 'plan_limit_exceeded',
                'message' => 'Controller limit reached for effective plan.',
                'used' => $allowance['used'],
                'max' => $allowance['max'],
            ], 409);
        }

        $result = DB::transaction(function () use ($validated, $user) {
            ControllerRegistrationAttempt::query()
                ->whereIn('status', ['pending', 'challenge_pending'])
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $registrationToken = trim((string) ($validated['registration_token'] ?? ''));
            if ($registrationToken !== '') {
                $registrationAttempt = ControllerRegistrationAttempt::query()
                    ->where('registration_token_hash', hash('sha256', $registrationToken))
                    ->where('requested_user_id', (string) $user->id)
                    ->where('challenge_code', (string) $validated['code'])
                    ->where('status', 'challenge_pending')
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()
                    ->first();

                if (! $registrationAttempt) {
                    return ['status' => 404, 'data' => ['error' => 'challenge_not_found', 'message' => 'Active controller challenge for this code not found']];
                }

                return $this->createControllerFromRegistrationAttempt($registrationAttempt, $user);
            }

            $registrationAttempts = ControllerRegistrationAttempt::query()
                ->where('code', (string) $validated['code'])
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->get();

            if ($registrationAttempts->count() > 1) {
                $excludeIds = $registrationAttempts->pluck('id')->map(fn ($id) => (string) $id)->all();
                $takenCodes = $this->activeRegistrationAndPairingCodes($excludeIds);
                foreach ($registrationAttempts as $registrationAttempt) {
                    $registrationAttempt->code = $this->generateRegistrationCode($takenCodes, (string) $validated['code']);
                    $registrationAttempt->challenge_code = null;
                    $registrationAttempt->registration_token_hash = null;
                    $registrationAttempt->requested_user_id = null;
                    $registrationAttempt->challenge_started_at = null;
                    $registrationAttempt->expires_at = now()->addMinutes(10);
                    $registrationAttempt->save();
                }

                return [
                    'status' => 409,
                    'data' => [
                        'error' => 'registration_code_collision',
                        'message' => 'Several controllers use this code. New codes were sent to displays. Enter the new code from your controller.',
                        'new_code_required' => true,
                    ],
                ];
            }

            if ($registrationAttempts->count() === 1) {
                $registrationAttempt = $registrationAttempts->first();
                $registrationToken = Str::random(48);
                $takenCodes = $this->activeRegistrationAndPairingCodes([(string) $registrationAttempt->id]);
                $challengeCode = $this->generateRegistrationCode($takenCodes, (string) $registrationAttempt->code);

                $registrationAttempt->status = 'challenge_pending';
                $registrationAttempt->requested_user_id = (string) $user->id;
                $registrationAttempt->challenge_code = $challengeCode;
                $registrationAttempt->registration_token_hash = hash('sha256', $registrationToken);
                $registrationAttempt->challenge_started_at = now();
                $registrationAttempt->expires_at = now()->addMinutes(10);
                $registrationAttempt->save();

                return [
                    'status' => 202,
                    'data' => [
                        'ok' => false,
                        'challenge_required' => true,
                        'registration_token' => $registrationToken,
                        'message' => 'Enter the new code shown on the controller display.',
                    ],
                ];
            }

            $pairing = ControllerPairing::query()
                ->where('user_id', (string) $user->id)
                ->where('code', (string) $validated['code'])
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (! $pairing) {
                return ['status' => 404, 'data' => ['error' => 'pairing_not_found', 'message' => 'Active pairing session for this code not found']];
            }

            $controller = IoTController::query()->lockForUpdate()->find((string) $pairing->controller_id);
            if (! $controller) {
                return ['status' => 404, 'data' => ['error' => 'not_found', 'message' => 'Controller not found']];
            }

            if ($controller->user_id !== null && (int) $controller->user_id !== (int) $user->id) {
                return ['status' => 409, 'data' => ['error' => 'already_claimed', 'message' => 'Controller already has another owner']];
            }

            $controller->user_id = (int) $user->id;

            $pairing->status = 'claimed';
            $pairing->claimed_at = now();
            $pairing->save();

            ControllerPairing::query()
                ->where('controller_id', (string) $pairing->controller_id)
                ->where('status', 'pending')
                ->where('id', '!=', (string) $pairing->id)
                ->update(['status' => 'expired']);

            $controller->status = 'active';
            $controller->claimed_at = now();
            $controller->save();

            event(new ControllerPaired((string) $pairing->controller_id, (string) $user->id));

            return [
                'status' => 200,
                'data' => [
                    'ok' => true,
                    'controller_id' => (string) $pairing->controller_id,
                    'owner_user_id' => (string) $user->id,
                    'claimed_at' => optional($pairing->claimed_at)->toIso8601String(),
                ],
            ];
        });

        return response()->json($result['data'], $result['status']);
    }

    public function start(Request $request, string $controllerId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }

        $user = $request->user();

        $allowance = $this->planLimitService->controllerAttachAllowance($user);
        if (! $allowance['allowed']) {
            return response()->json([
                'error' => 'plan_limit_exceeded',
                'message' => 'Controller limit reached for effective plan.',
                'used' => $allowance['used'],
                'max' => $allowance['max'],
            ], 409);
        }

        $payload = DB::transaction(function () use ($controllerId, $user) {
            $controller = IoTController::query()->lockForUpdate()->find($controllerId);
            if (! $controller) {
                return ['status' => 404, 'data' => ['error' => 'not_found', 'message' => 'Controller not found']];
            }

            if ($controller->user_id !== null || $controller->status === 'active') {
                return ['status' => 409, 'data' => ['error' => 'already_claimed', 'message' => 'Controller already has an owner']];
            }

            $active = ControllerPairing::query()
                ->where('controller_id', $controllerId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            if ($active && $active->user_id !== (string) $user->id) {
                return ['status' => 409, 'data' => ['error' => 'pairing_in_progress', 'message' => 'Pairing is already in progress for this controller']];
            }

            ControllerPairing::query()
                ->where('controller_id', $controllerId)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $pairing = ControllerPairing::query()->create([
                'id' => (string) Str::uuid(),
                'controller_id' => $controllerId,
                'user_id' => (string) $user->id,
                'code' => $code,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(5),
            ]);

            return [
                'status' => 200,
                'data' => [
                    'ok' => true,
                    'controller_id' => $controllerId,
                    'pairing_code' => $pairing->code,
                    'expires_at' => optional($pairing->expires_at)->toIso8601String(),
                ],
            ];
        });

        return response()->json($payload['data'], $payload['status']);
    }

    public function confirm(Request $request, string $controllerId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
        ]);

        $user = $request->user();

        $allowance = $this->planLimitService->controllerAttachAllowance($user);
        if (! $allowance['allowed']) {
            return response()->json([
                'error' => 'plan_limit_exceeded',
                'message' => 'Controller limit reached for effective plan.',
                'used' => $allowance['used'],
                'max' => $allowance['max'],
            ], 409);
        }

        $result = DB::transaction(function () use ($controllerId, $validated, $user) {
            $controller = IoTController::query()->lockForUpdate()->find($controllerId);
            if (! $controller) {
                return ['status' => 404, 'data' => ['error' => 'not_found', 'message' => 'Controller not found']];
            }

            $pairing = ControllerPairing::query()
                ->where('controller_id', $controllerId)
                ->where('user_id', (string) $user->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->first();

            if (! $pairing) {
                return ['status' => 404, 'data' => ['error' => 'pairing_not_found', 'message' => 'Active pairing session not found']];
            }

            if ($pairing->code !== (string) $validated['code']) {
                return ['status' => 422, 'data' => ['error' => 'invalid_code', 'message' => 'Invalid pairing code']];
            }

            $controller->user_id = (int) $user->id;

            $pairing->status = 'claimed';
            $pairing->claimed_at = now();
            $pairing->save();

            ControllerPairing::query()
                ->where('controller_id', $controllerId)
                ->where('status', 'pending')
                ->where('id', '!=', $pairing->id)
                ->update(['status' => 'expired']);

            $controller->status = 'active';
            $controller->claimed_at = now();
            $controller->save();

            event(new ControllerPaired($controllerId, (string) $user->id));

            return [
                'status' => 200,
                'data' => [
                    'ok' => true,
                    'controller_id' => $controllerId,
                    'owner_user_id' => (string) $user->id,
                    'claimed_at' => optional($pairing->claimed_at)->toIso8601String(),
                ],
            ];
        });

        return response()->json($result['data'], $result['status']);
    }
}
