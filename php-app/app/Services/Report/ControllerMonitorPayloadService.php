<?php

declare(strict_types=1);

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ControllerMonitorPayloadService
{
    private const CACHE_TTL_SECONDS = 86400;

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

        $sensorEntries = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->where('digital_style', 'like', 'sensor%')
            ->where('is_monitored', 1)
            ->whereNotNull('value')
            ->orderBy('pin')
            ->get(['value', 'unit'])
            ->map(function (object $row): string {
                $value = $this->formatNumeric((float) $row->value);
                $unit = trim((string) ($row->unit ?? ''));
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
        $timeZone = DB::table('controller_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.controller_id', $controllerId)
            ->orderByRaw("CASE WHEN cu.role = 'owner' THEN 0 ELSE 1 END")
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
}
