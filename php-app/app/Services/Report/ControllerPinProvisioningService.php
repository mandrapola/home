<?php

declare(strict_types=1);

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class ControllerPinProvisioningService
{
    /**
     * @return array<string, array{label:string,digital_style:string,unit:string|null,show_on_chart:int,chart_range_hours:int}>
     */
    private function knownPinConfigs(): array
    {
        return [
            'RELAY_1' => ['label' => 'Реле 1 (полив/линия 1)', 'digital_style' => 'power', 'unit' => null, 'show_on_chart' => 0, 'chart_range_hours' => 1],
            'RELAY_2' => ['label' => 'Реле 2 (полив/линия 2)', 'digital_style' => 'power', 'unit' => null, 'show_on_chart' => 0, 'chart_range_hours' => 1],
            'RELAY_3' => ['label' => 'Реле 3 (вентиляция/линия 3)', 'digital_style' => 'power', 'unit' => null, 'show_on_chart' => 0, 'chart_range_hours' => 1],
            'RELAY_4' => ['label' => 'Реле 4 (освещение/линия 4)', 'digital_style' => 'power', 'unit' => null, 'show_on_chart' => 0, 'chart_range_hours' => 1],
            'SOIL_MOISTURE_RAW' => ['label' => 'Влажность почвы (сырой ADC)', 'digital_style' => 'sensor_humidity', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'LIGHT_LEVEL_RAW' => ['label' => 'Уровень освещенности (сырой ADC)', 'digital_style' => 'sensor_light', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'TANK_LEVEL_RAW' => ['label' => 'Уровень воды в баке (сырой ADC)', 'digital_style' => 'sensor_level', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'WATER_PRESSURE_RAW' => ['label' => 'Давление воды (сырой ADC)', 'digital_style' => 'sensor_pressure', 'unit' => 'adc', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'AIR_HUMIDITY' => ['label' => 'Влажность воздуха', 'digital_style' => 'sensor_humidity', 'unit' => 'percent', 'show_on_chart' => 1, 'chart_range_hours' => 24],
            'AIR_TEMPERATURE' => ['label' => 'Температура воздуха', 'digital_style' => 'sensor_temperature', 'unit' => 'celsius', 'show_on_chart' => 1, 'chart_range_hours' => 24],
        ];
    }

    private function normalizePin(string $pin): string
    {
        return strtoupper(trim($pin));
    }

    private function userPinNumber(string $pin, string $prefix): ?string
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '_([1-9][0-9]*)$/';
        if (preg_match($pattern, $pin, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function userPinConfig(string $pin): ?array
    {
        $powerNumber = $this->userPinNumber($pin, 'USER_POWER');
        if ($powerNumber !== null) {
            return [
                'label' => 'Пользовательское реле ' . $powerNumber,
                'digital_style' => 'power',
                'unit' => null,
                'show_on_chart' => 0,
                'chart_range_hours' => 1,
            ];
        }

        $sensorNumber = $this->userPinNumber($pin, 'USER_SENSOR');
        if ($sensorNumber !== null) {
            return [
                'label' => 'Пользовательский датчик ' . $sensorNumber,
                'digital_style' => 'sensor',
                'unit' => 'adc',
                'show_on_chart' => 1,
                'chart_range_hours' => 24,
            ];
        }

        return null;
    }

    private function defaultPinConfig(string $pin): ?array
    {
        $normalizedPin = strtoupper(trim($pin));
        $mapped = $this->knownPinConfigs()[$normalizedPin] ?? $this->userPinConfig($normalizedPin);

        if ($mapped === null) {
            return null;
        }

        return [
            'pin' => $normalizedPin,
            'label' => $mapped['label'],
            'unit' => $mapped['unit'],
            'digital_style' => $mapped['digital_style'],
            'desired_digital_value' => $mapped['digital_style'] === 'power' ? 0 : null,
            'show_on_chart' => (int) $mapped['show_on_chart'],
            'show_on_report' => 1,
            'is_monitored' => 0,
            'chart_range_hours' => (int) $mapped['chart_range_hours'],
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
            if ($cfg === null) {
                continue;
            }

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
