<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ControllerPairing;
use App\Models\IoTController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class ControllerReportController extends Controller
{
    private const SYSTEM_CURRENT_TIME_PIN_ID = '0195f7e0-0000-7000-8000-000000000002';

    private function evaluateScenarioCondition(string $operator, ?float $sourceValue, float $threshold): bool
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

    private function resolveControllerTimeSeconds(string $controllerId): float
    {
        $timeZone = DB::table('controller_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.controller_id', $controllerId)
            ->orderByRaw("CASE WHEN cu.role = 'owner' THEN 0 ELSE 1 END")
            ->value('u.time_zone');

        $tz = is_string($timeZone) && in_array($timeZone, \DateTimeZone::listIdentifiers(), true)
            ? $timeZone
            : 'Europe/Moscow';

        $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
        return ((int) $now->format('H') * 3600) + ((int) $now->format('i') * 60) + (int) $now->format('s');
    }

    private function isCsvMode(Request $request): bool
    {
        $formatHeader = strtolower((string) $request->header('X-SmartHome-Format', ''));
        if ($formatHeader === 'csv') {
            return true;
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        return str_contains($contentType, 'text/csv') || str_contains($contentType, 'text/plain');
    }

    private function parseCsvPayload(string $raw): array
    {
        $result = [];
        $tokens = preg_split('/[;\r\n]+/', $raw) ?: [];

        foreach ($tokens as $token) {
            $part = trim($token);
            if ($part === '') {
                continue;
            }

            $eqPos = strpos($part, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($part, 0, $eqPos));
            $value = trim(substr($part, $eqPos + 1));
            if ($key === '') {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function normalizeCsvReadings(array $payload): array
    {
        $rows = [];
        foreach ($payload as $key => $raw) {
            if ($key === 'controller_id') {
                continue;
            }

            $pin = $this->normalizePin((string) $key);
            $value = $this->toNumber($raw);
            if ($pin === '' || $value === null) {
                continue;
            }

            $rows[] = ['pin' => $pin, 'value' => $value];
        }

        return $rows;
    }

    private function buildCsvResponse(array $result, ?ControllerPairing $activePairing): string
    {
        $parts = [
            'send_interval_seconds=' . (string) ((int) ($result['send_interval_seconds'] ?? 5)),
        ];

        $digitalOutputs = is_array($result['digital_outputs'] ?? null) ? $result['digital_outputs'] : [];
        foreach ($digitalOutputs as $pin => $value) {
            $parts[] = strtolower((string) $pin) . '=' . (((int) $value) > 0 ? '1' : '0');
        }

        if ($activePairing?->code !== null) {
            $parts[] = 'pairing_code=' . $activePairing->code;
        }
        if ($activePairing?->expires_at !== null) {
            $parts[] = 'pairing_code_expires_at=' . $activePairing->expires_at->toIso8601String();
        }

        return implode(';', $parts);
    }

    private function normalizePin(string $pin): string
    {
        return strtoupper(trim($pin));
    }

    private function toNumber(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function isDigitalPin(string $pin): bool
    {
        return preg_match('/^RELAY_[0-9]+$/i', $pin) === 1;
    }

    private function isAnalogPin(string $pin): bool
    {
        return preg_match('/^A[0-9]+$/i', $pin) === 1
            || in_array(strtolower($pin), ['air_temperature', 'air_humidity'], true);
    }

    private function defaultPinConfig(string $pin): array
    {
        $normalizedPin = strtoupper(trim($pin));
        $normalizedLower = strtolower($normalizedPin);
        $isDigital = $this->isDigitalPin($normalizedPin);
        $isAnalog = $this->isAnalogPin($normalizedPin);

        $known = [
            'RELAY_1' => ['label' => 'Реле 1 (полив/линия 1)', 'digital_style' => 'power', 'unit' => null, 'show_on_chart' => 0, 'chart_range_hours' => 1],
            'RELAY_2' => ['label' => 'Реле 2 (полив/линия 2)', 'digital_style' => 'power', 'unit' => null, 'show_on_chart' => 0, 'chart_range_hours' => 1],
            'RELAY_3' => ['label' => 'Реле 3 (вентиляция/линия 3)', 'digital_style' => 'power', 'unit' => null, 'show_on_chart' => 0, 'chart_range_hours' => 1],
            'RELAY_4' => ['label' => 'Реле 4 (освещение/линия 4)', 'digital_style' => 'power', 'unit' => null, 'show_on_chart' => 0, 'chart_range_hours' => 1],
            'SOIL_MOISTURE_RAW' => ['label' => 'Влажность почвы (сырой ADC)', 'digital_style' => 'sensor_humidity', 'unit' => 'ADC', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'LIGHT_LEVEL_RAW' => ['label' => 'Уровень освещенности (сырой ADC)', 'digital_style' => 'sensor_light', 'unit' => 'ADC', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'TANK_LEVEL_RAW' => ['label' => 'Уровень воды в баке (сырой ADC)', 'digital_style' => 'sensor_level', 'unit' => 'ADC', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'WATER_PRESSURE_RAW' => ['label' => 'Давление воды (сырой ADC)', 'digital_style' => 'sensor_pressure', 'unit' => 'ADC', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'ANALOG_SPARE_1_RAW' => ['label' => 'Аналоговый вход A4 (резерв)', 'digital_style' => 'sensor', 'unit' => 'ADC', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'ANALOG_SPARE_2_RAW' => ['label' => 'Аналоговый вход A5 (резерв)', 'digital_style' => 'sensor', 'unit' => 'ADC', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'AIR_HUMIDITY' => ['label' => 'Влажность воздуха', 'digital_style' => 'sensor_humidity', 'unit' => '%', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'AIR_TEMPERATURE' => ['label' => 'Температура воздуха', 'digital_style' => 'sensor_temperature', 'unit' => '°C', 'show_on_chart' => 1, 'chart_range_hours' => 24],
        ];

        $mapped = $known[$normalizedPin] ?? null;
        $label = $mapped['label'] ?? $normalizedPin;
        $unit = $mapped['unit'] ?? null;
        $digitalStyle = $mapped['digital_style'] ?? ($isDigital ? 'power' : 'sensor');
        $showOnChart = (int) ($mapped['show_on_chart'] ?? ($isAnalog ? 1 : 0));
        $chartRangeHours = (int) ($mapped['chart_range_hours'] ?? ($isAnalog ? 24 : 1));

        if (! $mapped) {
            if ($isDigital) {
                $label = 'Цифровой порт ' . $normalizedPin;
            } elseif ($normalizedLower === 'air_temperature') {
                $label = 'Температура воздуха';
                $unit = '°C';
            } elseif ($normalizedLower === 'air_humidity') {
                $label = 'Влажность воздуха';
                $unit = '%';
            } elseif ($isAnalog) {
                $label = 'Аналоговый порт ' . $normalizedPin;
                $unit = 'ADC';
            }
        }

        return [
            'pin' => $normalizedPin,
            'label' => $label,
            'unit' => $unit,
            'average_interval_minutes' => 5,
            'digital_style' => $digitalStyle,
            'invert_digital_logic' => 0,
            'desired_digital_value' => $isDigital ? 0 : null,
            'power_on_duration_seconds' => null,
            'show_on_chart' => $showOnChart,
            'chart_range_hours' => $chartRangeHours,
            'enable_scenario' => 1,
        ];
    }

    private function normalizeReportReadings(array $payload): array
    {
        $rows = [];

        if (isset($payload['readings']) && is_array($payload['readings']) && count($payload['readings']) > 0) {
            foreach ($payload['readings'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $pin = $this->normalizePin((string) ($item['pin'] ?? ''));
                $value = $this->toNumber($item['value'] ?? null);
                if ($pin !== '' && $value !== null) {
                    $rows[] = ['pin' => $pin, 'value' => $value];
                }
            }
            return $rows;
        }
        return $rows;
    }

    private function applyScenarioDesiredDigitalValue(string $controllerId): void
    {
        $rows = $this->findTargetRows($controllerId);

        if ($rows->count() === 0) {
            return;
        }

        $scenarioTargetRows = $rows
            ->filter(static fn ($row) => ((int) ($row->enable_scenario ?? 0)) > 0)
            ->values();

        if ($scenarioTargetRows->count() === 0) {
            return;
        }

        $firstControllerId = null;
        foreach ($rows as $row) {
            if (isset($row->controller_id)) {
                $firstControllerId = (string) $row->controller_id;
                break;
            }
        }
        if (! is_string($firstControllerId) || $firstControllerId === '') {
            return;
        }

        $sourcePinRows = DB::table('pin')
            ->where('controller_id', $firstControllerId)
            ->select(['id', 'value'])
            ->get();
        $sourceValueByPinId = [];
        foreach ($sourcePinRows as $sourceRow) {
            $sourceValueByPinId[(string) $sourceRow->id] = is_numeric($sourceRow->value) ? (float) $sourceRow->value : null;
        }

        $controllerCurrentTimeSeconds = $this->resolveControllerTimeSeconds($firstControllerId);

        $targetIds = [];
        foreach ($scenarioTargetRows as $row) {
            $targetIds[] = (string) $row->id;
        }

        $scenarioRows = DB::table('scenario')
            ->whereIn('pin_id', $targetIds)
            ->select(['id', 'pin_id'])
            ->get();

        $scenarioByTargetPinId = [];
        $scenarioIds = [];
        foreach ($scenarioRows as $scenario) {
            $scenarioId = (string) $scenario->id;
            $targetPinId = (string) $scenario->pin_id;
            $scenarioIds[] = $scenarioId;
            if (! isset($scenarioByTargetPinId[$targetPinId])) {
                $scenarioByTargetPinId[$targetPinId] = [];
            }
            $scenarioByTargetPinId[$targetPinId][] = $scenarioId;
        }

        $conditionsByScenarioId = [];
        if (count($scenarioIds) > 0) {
            $conditionRows = DB::table('scenario_condition')
                ->whereIn('scenario_id', $scenarioIds)
                ->select(['scenario_id', 'pin_id', 'operator', 'threshold'])
                ->get();
            foreach ($conditionRows as $condition) {
                $scenarioId = (string) $condition->scenario_id;
                if (! isset($conditionsByScenarioId[$scenarioId])) {
                    $conditionsByScenarioId[$scenarioId] = [];
                }
                $conditionsByScenarioId[$scenarioId][] = $condition;
            }
        }

        foreach ($scenarioTargetRows as $targetPinRow) {
            $targetPinId = (string) $targetPinRow->id;
            $scenarioIds = $scenarioByTargetPinId[$targetPinId] ?? [];
            if (count($scenarioIds) === 0) {
                continue;
            }

            $pinScenarioResult = false;
            foreach ($scenarioIds as $scenarioId) {
                $scenarioConditions = $conditionsByScenarioId[$scenarioId] ?? [];
                if (count($scenarioConditions) === 0) {
                    continue;
                }

                $scenarioTrue = true;
                foreach ($scenarioConditions as $condition) {
                    $sourcePinId = (string) $condition->pin_id;
                    $sourcePinValue = $sourceValueByPinId[$sourcePinId] ?? null;

                    if ($sourcePinId === self::SYSTEM_CURRENT_TIME_PIN_ID) {
                        $sourcePinValue = $controllerCurrentTimeSeconds;
                    }

                    $threshold = is_numeric($condition->threshold) ? (float) $condition->threshold : 0.0;
                    if (! $this->evaluateScenarioCondition((string) $condition->operator, $sourcePinValue, $threshold)) {
                        $scenarioTrue = false;
                        break;
                    }
                }

                if ($scenarioTrue) {
                    $pinScenarioResult = true;
                    break;
                }
            }

            DB::table('pin')
                ->where('id', $targetPinId)
                ->update([
                    'desired_digital_value' => $pinScenarioResult ? 1 : 0,
                    'desired_digital_updated_at' => now(),
                ]);
        }
    }
    
    private function findTargetRows(string $controllerId): Collection
    {
        return DB::table('pin')
            ->where('controller_id', $controllerId)
            ->where('digital_style', 'power')
            ->whereNotNull('desired_digital_value')
            ->select(['id', 'controller_id', 'pin', 'desired_digital_value', 'enable_scenario', 'invert_digital_logic'])
            ->get();
    }

    public function __invoke(Request $request): JsonResponse|Response
    {
        $csvMode = $this->isCsvMode($request);
        $validated = [];
        $controllerId = '';

        if ($csvMode) {
            $payload = $this->parseCsvPayload((string) $request->getContent());
            $controllerId = (string) ($payload['controller_id'] ?? '');
            if (! Uuid::isValid($controllerId)) {
                return response('error=bad_request;message=controller_id uuid is required', 400, [
                    'Content-Type' => 'text/plain; charset=utf-8',
                ]);
            }
            $readings = $this->normalizeCsvReadings($payload);
        } else {
            $validated = $request->validate([
                'controller_id' => ['required', 'string', 'uuid'],
                'readings' => ['nullable', 'array'],
                'readings.*.pin' => ['required_with:readings', 'string'],
                'readings.*.value' => ['required_with:readings'],
            ]);
            $controllerId = (string) $validated['controller_id'];
            $readings = $this->normalizeReportReadings($validated);
        }

        $ip = (string) ($request->ip() ?? 'unknown');

        if (count($readings) === 0) {
            if ($csvMode) {
                return response('error=empty_readings;message=No valid sensor readings provided', 400, [
                    'Content-Type' => 'text/plain; charset=utf-8',
                ]);
            }

            return response()->json([
                'error' => 'empty_readings',
                'message' => 'No valid sensor readings provided',
            ], 400);
        }

        $result = DB::transaction(function () use ($controllerId, $ip, $readings): array {
            $exists = IoTController::query()->where('id', $controllerId)->exists();

            if (! $exists) {
                IoTController::query()->create([
                    'id' => $controllerId,
                    'name' => 'controller-' . substr($controllerId, 0, 8),
                    'discription' => 'Auto-registered from ' . $ip,
                    'send_interval_seconds' => 5,
                    'status' => 'unclaimed',
                    'last_seen_at' => now(),
                ]);
            } else {
                IoTController::query()
                    ->where('id', $controllerId)
                    ->update(['last_seen_at' => now()]);
            }

            $controller = DB::table('controller')
                ->where('id', $controllerId)
                ->first(['id', 'send_interval_seconds']);

            $existingPins = DB::table('pin')
                ->where('controller_id', $controllerId)
                ->select(['id', 'pin', 'digital_style', 'invert_digital_logic'])
                ->orderBy('pin')
                ->get();

            $pinIdByName = [];
            $pinStyleByName = [];
            $pinInvertByName = [];
            foreach ($existingPins as $pinRow) {
                $name = $this->normalizePin((string) $pinRow->pin);
                $pinIdByName[$name] = (string) $pinRow->id;
                $pinStyleByName[$name] = (string) ($pinRow->digital_style ?? '');
                $pinInvertByName[$name] = ((int) ($pinRow->invert_digital_logic ?? 0)) > 0;
            }

            foreach ($readings as $reading) {
                $pinName = $this->normalizePin((string) $reading['pin']);
                if ($pinName === '' || isset($pinIdByName[$pinName])) {
                    continue;
                }

                $cfg = $this->defaultPinConfig($pinName);
                $pinId = Uuid::uuid7()->toString();
                DB::table('pin')->insert([
                    'id' => $pinId,
                    'controller_id' => $controllerId,
                    'pin' => $cfg['pin'],
                    'label' => $cfg['label'],
                    'unit' => $cfg['unit'],
                    'average_interval_minutes' => $cfg['average_interval_minutes'],
                    'digital_style' => $cfg['digital_style'],
                    'invert_digital_logic' => $cfg['invert_digital_logic'],
                    'desired_digital_value' => $cfg['desired_digital_value'],
                    'desired_digital_updated_at' => null,
                    'power_on_duration_seconds' => $cfg['power_on_duration_seconds'],
                    'show_on_chart' => $cfg['show_on_chart'],
                    'chart_range_hours' => $cfg['chart_range_hours'],
                    'enable_scenario' => $cfg['enable_scenario'],
                ]);
                $pinIdByName[$pinName] = $pinId;
                $pinStyleByName[$pinName] = (string) $cfg['digital_style'];
                $pinInvertByName[$pinName] = ((int) ($cfg['invert_digital_logic'] ?? 0)) > 0;
            }

            foreach ($readings as $reading) {
                $pinName = $this->normalizePin((string) $reading['pin']);
                $pinId = $pinIdByName[$pinName] ?? null;
                if (! $pinId) {
                    continue;
                }

                DB::table('pin_data')->insert([
                    'id' => Uuid::uuid7()->toString(),
                    'pin_id' => $pinId,
                    'value' => (float) $reading['value'],
                    'created_at' => now(),
                ]);

                $isPowerPin = (($pinStyleByName[$pinName] ?? '') === 'power');
                $isInvertedPin = (bool) ($pinInvertByName[$pinName] ?? false);
                $incomingValue = (float) $reading['value'];

                $updateData = [
                    'value' => $incomingValue,
                    'value_updated_at' => now(),
                ];

                // Контроллер отдает физическое (wire) состояние.
                // В БД храним логическое состояние (без инверсии).
                if ($isPowerPin) {
                    $wireValue = ((int) round($incomingValue)) > 0 ? 1 : 0;
                    $logicalValue = $isInvertedPin ? (1 - $wireValue) : $wireValue;
                    $updateData['value'] = $logicalValue;
                }

                DB::table('pin')
                    ->where('id', $pinId)
                    ->update($updateData);
            }
            
            // 1) Вычисление желаемых состояний для сценарных пинов и запись в БД
            $this->applyScenarioDesiredDigitalValue($controllerId);

            // 2) Формирование ответа: читаем уже сохраненные значения из БД
            $targetRows = $this->findTargetRows($controllerId);

            $digitalOutputs = [];
            foreach ($targetRows as $row) {
                $desired = (((int) $row->desired_digital_value) > 0) ? 1 : 0;
                $invertDigitalLogic = ((int) ($row->invert_digital_logic ?? 0)) > 0;

                $wireDesired = $invertDigitalLogic ? (1 - $desired) : $desired;
                $pinKey = strtolower($this->normalizePin((string) $row->pin));
                $digitalOutputs[$pinKey] = ((int) $wireDesired) > 0 ? 1 : 0;
            }

            return [
                'send_interval_seconds' => max(1, (int) (($controller->send_interval_seconds ?? 5))),
                'digital_outputs' => $digitalOutputs,
            ];
        });

        $activePairing = ControllerPairing::query()
            ->where('controller_id', $controllerId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if ($activePairing && $activePairing->displayed_at === null) {
            $activePairing->displayed_at = now();
            $activePairing->save();
        }

        if ($csvMode) {
            return response(
                $this->buildCsvResponse($result, $activePairing),
                200,
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        }

        return response()->json([
            'send_interval_seconds' => $result['send_interval_seconds'],
            'digital_outputs' => $result['digital_outputs'],
            'pairing_code' => $activePairing?->code,
            'pairing_code_expires_at' => $activePairing?->expires_at?->toIso8601String(),
        ]);
    }
}
