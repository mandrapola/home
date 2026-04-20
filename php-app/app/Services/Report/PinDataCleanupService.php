<?php

declare(strict_types=1);

namespace App\Services\Report;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PinDataCleanupService
{
    private const LAST_RUN_CACHE_KEY = 'pin_data_cleanup:last_run_bucket';
    private const LOCK_CACHE_KEY = 'pin_data_cleanup:lock';

    public function cleanupIfDue(): void
    {
        $periodMinutes = max(1, (int) config('smarthome.pin_data_cleanup_period_minutes', 60));
        $currentBucket = now()->utc()->format('YmdH');

        if ((string) Cache::get(self::LAST_RUN_CACHE_KEY, '') === $currentBucket) {
            return;
        }

        $lock = Cache::lock(self::LOCK_CACHE_KEY, 55);
        if (! $lock->get()) {
            return;
        }

        try {
            if ((string) Cache::get(self::LAST_RUN_CACHE_KEY, '') === $currentBucket) {
                return;
            }

            $retentionHours = max(1, (int) config('smarthome.pin_data_retention_hours', 24));
            $cutoff = now()->subHours($retentionHours);

            DB::table('pin_data')
                ->where('created_at', '<', $cutoff)
                ->delete();

            Cache::put(
                self::LAST_RUN_CACHE_KEY,
                $currentBucket,
                now()->addMinutes($periodMinutes + 5)
            );
        } finally {
            optional($lock)->release();
        }
    }
}

