<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ControllerPaired;
use App\Models\ControllerPairing;
use App\Models\IoTController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PairingController extends Controller
{
    private const SYSTEM_CONTROLLER_ID = '0195f7e0-0000-7000-8000-000000000001';
    private const SYSTEM_CURRENT_TIME_PIN_ID = '0195f7e0-0000-7000-8000-000000000002';

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
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $rows = DB::table('controller as c')
            ->join('controller_user as cu', 'cu.controller_id', '=', 'c.id')
            ->leftJoin(
                DB::raw('(SELECT controller_id, COUNT(*) AS pin_count FROM pin GROUP BY controller_id) p'),
                'p.controller_id',
                '=',
                'c.id'
            )
            ->where('cu.user_id', '=', (string) $user->id)
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
                'cu.role',
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
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $isOwner = DB::table('controller_user')
            ->where('controller_id', $controllerId)
            ->where('user_id', (string) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        $pins = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->orderBy('pin')
            ->select([
                'id',
                'controller_id',
                'pin',
                'label',
                'unit',
                'chart_range_hours',
                'digital_style',
                'invert_digital_logic',
                'value',
                'value_updated_at',
                'desired_digital_value',
                'desired_digital_updated_at',
                'show_on_chart',
                'is_monitored',
                'enable_scenario',
            ])
            ->get();

        return response()->json([
            'controller_id' => $controllerId,
            'pins' => $pins,
        ]);
    }

    public function myControllerPowerEvents(Request $request, string $controllerId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $isOwner = DB::table('controller_user')
            ->where('controller_id', $controllerId)
            ->where('user_id', (string) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        $timeZone = (string) ($user->time_zone ?: 'Europe/Moscow');
        if (! in_array($timeZone, \DateTimeZone::listIdentifiers(), true)) {
            $timeZone = 'Europe/Moscow';
        }

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

        $pinMetaRows = DB::table('pin')
            ->whereIn('id', $sourcePinIds)
            ->get(['id', 'digital_style', 'invert_digital_logic']);
        $pinMetaById = [];
        foreach ($pinMetaRows as $metaRow) {
            $pinMetaById[(string) $metaRow->id] = [
                'digital_style' => (string) ($metaRow->digital_style ?? ''),
                'invert_digital_logic' => ((int) ($metaRow->invert_digital_logic ?? 0)) > 0,
            ];
        }

        $normalizeValue = static function (string $pinId, ?float $rawValue) use ($pinMetaById): ?float {
            if ($rawValue === null) {
                return null;
            }
            $meta = $pinMetaById[$pinId] ?? null;
            if (! $meta) {
                return $rawValue;
            }

            $isPowerPin = ((string) ($meta['digital_style'] ?? '') === 'power');
            $isInverted = (bool) ($meta['invert_digital_logic'] ?? false);
            if (! $isPowerPin || ! $isInverted) {
                return $rawValue;
            }

            $wireValue = ((int) round($rawValue)) > 0 ? 1 : 0;
            return (float) (1 - $wireValue);
        };

        $seriesByPinId = [];
        foreach ($sourcePinIds as $pinId) {
            $seriesByPinId[$pinId] = [];

            $before = DB::table('pin_data')
                ->where('pin_id', $pinId)
                ->where('created_at', '<', $dayStartUtc)
                ->orderByDesc('created_at')
                ->first(['created_at', 'value']);

            if ($before && is_numeric($before->value)) {
                $seriesByPinId[$pinId][] = [
                    'ts' => $dayStartUtc->copy(),
                    'value' => $normalizeValue($pinId, (float) $before->value),
                ];
            }

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

        $valueAt = function (string $pinId, Carbon $timestampUtc) use (&$seriesByPinId, &$pointersByPinId): ?float {
            $series = $seriesByPinId[$pinId] ?? [];
            if (count($series) === 0) {
                return null;
            }

            $idx = (int) ($pointersByPinId[$pinId] ?? 0);
            while (($idx + 1) < count($series) && $series[$idx + 1]['ts']->lessThanOrEqualTo($timestampUtc)) {
                $idx++;
            }
            $pointersByPinId[$pinId] = $idx;

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

                $isCurrentWindow = $startTs->lessThanOrEqualTo($nowUtc) && $endTs->greaterThan($nowUtc);
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
                    $hasMissingData = false;

                    foreach ($scenarioConditions as $condition) {
                        $sourcePinId = (string) $condition->pin_id;
                        if ($sourcePinId === self::SYSTEM_CURRENT_TIME_PIN_ID) {
                            $hasCurrentTimeCondition = true;
                            $localTime = $startTs->copy()->setTimezone($timeZone);
                            $sourceValue = ((int) $localTime->format('H') * 3600) + ((int) $localTime->format('i') * 60) + (int) $localTime->format('s');
                        } else {
                            $sourceValue = $valueAt($sourcePinId, $startTs);
                            if ($sourceValue === null) {
                                $hasMissingData = true;
                                break;
                            }
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

                    if ($hasMissingData) {
                        continue;
                    }

                    $scenarioState = null;
                    if ($hasCurrentTimeCondition) {
                        if ($timeConditionsAllTrue) {
                            $scenarioState = $allTrue ? 'green' : 'yellow';
                        }
                    } elseif ($allTrue && $isCurrentWindow) {
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

    public function myControllerPinChartData(Request $request, string $controllerId): JsonResponse
    {
        if (! Str::isUuid($controllerId)) {
            return response()->json(['error' => 'validation_error', 'message' => 'controller_id must be UUID'], 422);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $isOwner = DB::table('controller_user')
            ->where('controller_id', $controllerId)
            ->where('user_id', (string) $user->id)
            ->exists();

        if (! $isOwner) {
            return response()->json(['error' => 'forbidden', 'message' => 'Controller is not linked to current user'], 403);
        }

        $timeZone = (string) ($user->time_zone ?: 'Europe/Moscow');
        if (! in_array($timeZone, \DateTimeZone::listIdentifiers(), true)) {
            $timeZone = 'Europe/Moscow';
        }

        $pins = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->where('digital_style', 'like', 'sensor%')
            ->where('show_on_chart', 1)
            ->orderBy('pin')
            ->select(['id', 'pin', 'chart_range_hours'])
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
                $bucketAt = Carbon::parse((string) $row->bucket_at, 'UTC')
                    ->setTimezone($timeZone)
                    ->format('Y-m-d H:i:s');
                $points[] = [
                    'at' => $bucketAt,
                    'value' => (float) $row->avg_value,
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
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $isOwner = DB::table('controller_user')
            ->where('controller_id', $controllerId)
            ->where('user_id', (string) $user->id)
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

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'chart_range_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'invert_digital_logic' => ['required', 'boolean'],
            'show_on_chart' => ['required', 'boolean'],
            'is_monitored' => ['required', 'boolean'],
        ]);

        DB::table('pin')
            ->where('id', $pinId)
            ->where('controller_id', $controllerId)
            ->update([
                'label' => (string) $validated['label'],
                'unit' => isset($validated['unit']) && trim((string) $validated['unit']) !== '' ? (string) $validated['unit'] : null,
                'chart_range_hours' => (int) $validated['chart_range_hours'],
                'invert_digital_logic' => ! empty($validated['invert_digital_logic']) ? 1 : 0,
                'show_on_chart' => ! empty($validated['show_on_chart']) ? 1 : 0,
                'is_monitored' => ! empty($validated['is_monitored']) ? 1 : 0,
            ]);

        $updatedPin = DB::table('pin')
            ->where('id', $pinId)
            ->where('controller_id', $controllerId)
            ->first([
                'id',
                'controller_id',
                'pin',
                'label',
                'unit',
                'chart_range_hours',
                'digital_style',
                'invert_digital_logic',
                'value',
                'value_updated_at',
                'desired_digital_value',
                'show_on_chart',
                'is_monitored',
            ]);

        return response()->json([
            'ok' => true,
            'pin' => $updatedPin,
        ]);
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
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $isOwner = DB::table('controller_user')
            ->where('controller_id', $controllerId)
            ->where('user_id', (string) $user->id)
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
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'discription' => ['nullable', 'string', 'max:2000'],
            'send_interval_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);

        $isOwner = DB::table('controller_user')
            ->where('controller_id', $controllerId)
            ->where('user_id', (string) $user->id)
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
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $payload = DB::transaction(function () use ($user) {
            $controllers = DB::table('controller as c')
                ->leftJoin('controller_user as cu', 'cu.controller_id', '=', 'c.id')
                ->whereNull('cu.controller_id')
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
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $result = DB::transaction(function () use ($validated, $user) {
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

            $ownedByOther = DB::table('controller_user')
                ->where('controller_id', (string) $pairing->controller_id)
                ->where('user_id', '!=', (string) $user->id)
                ->exists();

            if ($ownedByOther) {
                return ['status' => 409, 'data' => ['error' => 'already_claimed', 'message' => 'Controller already has another owner']];
            }

            DB::table('controller_user')->updateOrInsert(
                [
                    'controller_id' => (string) $pairing->controller_id,
                    'user_id' => (string) $user->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

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
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $payload = DB::transaction(function () use ($controllerId, $user) {
            $controller = IoTController::query()->lockForUpdate()->find($controllerId);
            if (! $controller) {
                return ['status' => 404, 'data' => ['error' => 'not_found', 'message' => 'Controller not found']];
            }

            $owned = DB::table('controller_user')
                ->where('controller_id', $controllerId)
                ->exists();

            if ($owned || $controller->status === 'active') {
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
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
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

            DB::table('controller_user')->updateOrInsert(
                [
                    'controller_id' => $controllerId,
                    'user_id' => (string) $user->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

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
