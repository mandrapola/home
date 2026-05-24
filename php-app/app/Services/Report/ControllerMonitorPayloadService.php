<?php

declare(strict_types=1);

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ControllerMonitorPayloadService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function __construct(
        private readonly PinValueTransformer $pinValueTransformer,
    ) {
    }

    public function buildMonitorValue(string $controllerId): ?string
    {
        $entries = $this->buildEntries($controllerId);
        if (count($entries) === 0) {
            return null;
        }

        $cacheKey = $this->cacheKey($controllerId);
        $index = (int) Cache::get($cacheKey, 0);
        if ($index < 0) {
            $index = 0;
        }

        $selected = $entries[$index % count($entries)] ?? null;
        Cache::put($cacheKey, ($index + 1) % count($entries), now()->addSeconds(self::CACHE_TTL_SECONDS));

        return $selected !== null ? (string) $selected : null;
    }

    /**
     * @return array<int,string>
     */
    private function buildEntries(string $controllerId): array
    {
        $timeZone = $this->resolveUserTimeZone($controllerId);

        $selectColumns = ['value', 'unit', 'digital_style'];
        if ($this->hasMoistureColumns()) {
            $selectColumns[] = 'moisture_raw_dry';
            $selectColumns[] = 'moisture_raw_wet';
            $selectColumns[] = 'moisture_show_percent';
        }

        $sensorEntries = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->where('digital_style', 'like', 'sensor%')
            ->where('is_monitored', 1)
            ->whereNotNull('value')
            ->orderBy('pin')
            ->get($selectColumns)
            ->map(function (object $row): string {
                $transformed = $this->pinValueTransformer->transform($row, is_numeric($row->value) ? (float) $row->value : null);
                if ($transformed === null) {
                    return '';
                }
                $value = $this->formatNumeric($transformed);
                $unit = trim((string) ($this->pinValueTransformer->resolveUnit($row) ?? ''));
                $unit = str_replace(';', '', $unit);

                return $unit !== '' ? ($value . $unit) : $value;
            })
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        $timeValue = Carbon::now($timeZone)->format('H:i');

        $entries = [];
        foreach ($sensorEntries as $entry) {
            $entries[] = $entry;
        }
        $entries[] = $timeValue;

        return $entries;
    }

    private function resolveUserTimeZone(string $controllerId): string
    {
        $timeZone = DB::table('controller as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.id', $controllerId)
            ->limit(1)
            ->value('u.time_zone');

        $resolved = is_string($timeZone) ? trim($timeZone) : '';
        if ($resolved === '' || ! in_array($resolved, \DateTimeZone::listIdentifiers(), true)) {
            return 'Europe/Moscow';
        }

        return $resolved;
    }

    private function cacheKey(string $controllerId): string
    {
        return 'controller:monitor:cycle:' . $controllerId;
    }

    private function formatNumeric(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return (string) (int) round($value);
        }

        $formatted = number_format($value, 1, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function hasMoistureColumns(): bool
    {
        return Schema::hasColumn('pin', 'moisture_raw_dry')
            && Schema::hasColumn('pin', 'moisture_raw_wet')
            && Schema::hasColumn('pin', 'moisture_show_percent');
    }
}
