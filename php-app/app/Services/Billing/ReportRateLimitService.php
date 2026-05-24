<?php

namespace App\Services\Billing;

use App\Models\IoTController;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportRateLimitService
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    /**
     * @return array{allowed: bool, retry_after_seconds: int, interval_seconds: int, user_id: int|null}
     */
    public function checkAndRecordAccepted(string $controllerId): array
    {
        return DB::transaction(function () use ($controllerId): array {
            $ownerId = (int) (DB::table('controller')->where('id', $controllerId)->value('user_id') ?? 0);

            if ($ownerId <= 0) {
                return ['allowed' => true, 'retry_after_seconds' => 0, 'interval_seconds' => 0, 'user_id' => null];
            }

            $now = now();
            $user = User::query()->find($ownerId);
            if (! $user) {
                return ['allowed' => true, 'retry_after_seconds' => 0, 'interval_seconds' => 0, 'user_id' => null];
            }

            $plan = $this->planLimitService->resolveEffectivePlanForUser($user);
            $interval = max(
                IoTController::MIN_INTERVAL_SECONDS,
                (int) ($plan?->min_report_interval_seconds ?? IoTController::MIN_INTERVAL_SECONDS)
            );

            $state = DB::table('user_report_limits')
                ->where('user_id', $ownerId)
                ->lockForUpdate()
                ->first(['last_report_accepted_at']);

            if ($state?->last_report_accepted_at) {
                $elapsed = \Illuminate\Support\Carbon::parse((string) $state->last_report_accepted_at)->diffInSeconds($now);
                if ($elapsed < $interval) {
                    return [
                        'allowed' => false,
                        'retry_after_seconds' => $interval - $elapsed,
                        'interval_seconds' => $interval,
                        'user_id' => $ownerId,
                    ];
                }
            }

            DB::table('user_report_limits')->updateOrInsert(
                ['user_id' => $ownerId],
                [
                    'last_report_accepted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            return ['allowed' => true, 'retry_after_seconds' => 0, 'interval_seconds' => 0, 'user_id' => null];
        });
    }
}
