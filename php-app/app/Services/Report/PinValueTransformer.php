<?php

declare(strict_types=1);

namespace App\Services\Report;

class PinValueTransformer
{
    /**
     * @param  object|array<string,mixed>  $pin
     */
    public function transform(object|array $pin, ?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $style = strtolower(trim((string) $this->read($pin, 'digital_style', '')));
        if ($style !== 'sensor_humidity') {
            return $value;
        }

        $showPercent = (int) $this->read($pin, 'moisture_show_percent', 0) === 1;
        if (! $showPercent) {
            return $value;
        }

        $dry = $this->toFloatOrNull($this->read($pin, 'moisture_raw_dry'));
        $wet = $this->toFloatOrNull($this->read($pin, 'moisture_raw_wet'));
        if ($dry === null || $wet === null || abs($dry - $wet) < 0.000001) {
            return $value;
        }

        $percent = (($dry - $value) / ($dry - $wet)) * 100.0;
        return (float) round(max(0.0, min(100.0, $percent)));
    }

    /**
     * @param  object|array<string,mixed>  $pin
     */
    public function resolveUnit(object|array $pin): ?string
    {
        $style = strtolower(trim((string) $this->read($pin, 'digital_style', '')));
        $unit = trim((string) $this->read($pin, 'unit', ''));
        $showPercent = (int) $this->read($pin, 'moisture_show_percent', 0) === 1;

        if ($style === 'sensor_humidity' && $showPercent) {
            return '%';
        }

        return $unit !== '' ? $unit : null;
    }

    /**
     * @param  object|array<string,mixed>  $pin
     */
    private function read(object|array $pin, string $key, mixed $default = null): mixed
    {
        if (is_array($pin)) {
            return $pin[$key] ?? $default;
        }

        return $pin->{$key} ?? $default;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
