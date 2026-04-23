<?php

declare(strict_types=1);

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class ControllerPinProvisioningService
{
    private function normalizePin(string $pin): string
    {
        return strtoupper(trim($pin));
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
            'SOIL_MOISTURE_RAW' => ['label' => 'Влажность почвы (сырой ADC)', 'digital_style' => 'sensor_humidity', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'LIGHT_LEVEL_RAW' => ['label' => 'Уровень освещенности (сырой ADC)', 'digital_style' => 'sensor_light', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'TANK_LEVEL_RAW' => ['label' => 'Уровень воды в баке (сырой ADC)', 'digital_style' => 'sensor_level', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'WATER_PRESSURE_RAW' => ['label' => 'Давление воды (сырой ADC)', 'digital_style' => 'sensor_pressure', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'ANALOG_SPARE_1_RAW' => ['label' => 'Аналоговый вход A4 (резерв)', 'digital_style' => 'sensor', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'ANALOG_SPARE_2_RAW' => ['label' => 'Аналоговый вход A5 (резерв)', 'digital_style' => 'sensor', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'AIR_HUMIDITY' => ['label' => 'Влажность воздуха', 'digital_style' => 'sensor_humidity', 'unit' => 'percent', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'AIR_TEMPERATURE' => ['label' => 'Температура воздуха', 'digital_style' => 'sensor_temperature', 'unit' => 'celsius', 'show_on_chart' => 1, 'chart_range_hours' => 24],
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
                $unit = 'celsius';
            } elseif ($normalizedLower === 'air_humidity') {
                $label = 'Влажность воздуха';
                $unit = 'percent';
            } elseif ($isAnalog) {
                $label = 'Аналоговый порт ' . $normalizedPin;
                $unit = 'adc';
            }
        }

        return [
            'pin' => $normalizedPin,
            'label' => $label,
            'unit' => $unit,
            'digital_style' => $digitalStyle,
            'desired_digital_value' => $isDigital ? 0 : null,
            'show_on_chart' => $showOnChart,
            'show_on_report' => 1,
            'is_monitored' => 0,
            'chart_range_hours' => $chartRangeHours,
            'enable_scenario' => 1,
        ];
    }

    /**
     * @param array<int, array{pin:string, value:float|int}> $readings
     * @return array{
     *   pinIdByName: array<string,string>,
     *   pinStyleByName: array<string,string>
     * }
     */
    public function ensurePinsAndBuildMaps(string $controllerId, array $readings): array
    {
        $existingPins = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->select(['id', 'pin', 'digital_style'])
            ->orderBy('pin')
            ->get();

        $pinIdByName = [];
        $pinStyleByName = [];
        foreach ($existingPins as $pinRow) {
            $name = $this->normalizePin((string) $pinRow->pin);
            $pinIdByName[$name] = (string) $pinRow->id;
            $pinStyleByName[$name] = (string) ($pinRow->digital_style ?? '');
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
                'digital_style' => $cfg['digital_style'],
                'desired_digital_value' => $cfg['desired_digital_value'],
                'desired_digital_updated_at' => null,
                'show_on_chart' => $cfg['show_on_chart'],
                'show_on_report' => $cfg['show_on_report'],
                'is_monitored' => $cfg['is_monitored'],
                'chart_range_hours' => $cfg['chart_range_hours'],
                'enable_scenario' => $cfg['enable_scenario'],
            ]);
            $pinIdByName[$pinName] = $pinId;
            $pinStyleByName[$pinName] = (string) $cfg['digital_style'];
        }

        return [
            'pinIdByName' => $pinIdByName,
            'pinStyleByName' => $pinStyleByName,
        ];
    }
}
