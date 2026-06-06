<?php

namespace App\Services\Billing;

use App\Models\IoTController;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportRateLimitService
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    /**
     * @return array{allowed: bool, retry_after_seconds: int, epoch_seconds: int, max_requests: int, requests_count: int, user_id: int|null}
     */
    public function checkAndRecordAccepted(string $controllerId): array
    {
        return DB::transaction(function () use ($controllerId): array {
            $ownerId = (int) (DB::table('controller')->where('id', $controllerId)->value('user_id') ?? 0);

            if ($ownerId <= 0) {
                return $this->allowedWithoutOwner();
            }

            $now = now();
            $user = User::query()->find($ownerId);
            if (! $user) {
                return $this->allowedWithoutOwner();
            }

            $plan = $this->planLimitService->resolveEffectivePlanForUser($user);
            $epochSeconds = max(60, (int) ($plan?->report_epoch_seconds ?? 300));
            $maxRequests = (int) ($plan?->report_max_requests_per_epoch ?? 0);
            if ($maxRequests <= 0) {
                $maxRequests = $this->automaticRequestLimit($ownerId, $epochSeconds);
            }

            $state = DB::table('user_report_rate_counters')
                ->where('user_id', $ownerId)
                ->lockForUpdate()
                ->first(['epoch_ends_at', 'requests_count']);

            if (! $state) {
                DB::table('user_report_rate_counters')->insertOrIgnore([
                    'user_id' => $ownerId,
                    'epoch_ends_at' => $now->copy()->addSeconds($epochSeconds),
                    'requests_count' => 0,
                ]);

                $state = DB::table('user_report_rate_counters')
                    ->where('user_id', $ownerId)
                    ->lockForUpdate()
                    ->first(['epoch_ends_at', 'requests_count']);
            }

            if (! $state) {
                return $this->allowedWithoutOwner();
            }

            $epochEndsAt = Carbon::parse((string) $state->epoch_ends_at);
            $requestsCount = (int) $state->requests_count;

            if ($now->greaterThanOrEqualTo($epochEndsAt)) {
                $epochEndsAt = $now->copy()->addSeconds($epochSeconds);
                $requestsCount = 0;

                DB::table('user_report_rate_counters')
                    ->where('user_id', $ownerId)
                    ->update([
                        'epoch_ends_at' => $epochEndsAt,
                        'requests_count' => 0,
                    ]);
            }

            if ($requestsCount >= $maxRequests) {
                return [
                    'allowed' => false,
                    'retry_after_seconds' => max(1, (int) $now->diffInSeconds($epochEndsAt)),
                    'epoch_seconds' => $epochSeconds,
                    'max_requests' => $maxRequests,
                    'requests_count' => $requestsCount,
                    'user_id' => $ownerId,
                ];
            }

            DB::table('user_report_rate_counters')
                ->where('user_id', $ownerId)
                ->update(['requests_count' => $requestsCount + 1]);

            return [
                'allowed' => true,
                'retry_after_seconds' => 0,
                'epoch_seconds' => $epochSeconds,
                'max_requests' => $maxRequests,
                'requests_count' => $requestsCount + 1,
                'user_id' => $ownerId,
            ];
        });
    }

    private function automaticRequestLimit(int $ownerId, int $epochSeconds): int
    {
        $controllersCount = (int) DB::table('controller')
            ->where('user_id', $ownerId)
            ->where('is_service', 0)
            ->count('id');

        return max(1, $controllersCount) * max(1, (int) floor($epochSeconds / IoTController::MIN_INTERVAL_SECONDS));
    }

    /**
     * @return array{allowed: true, retry_after_seconds: 0, epoch_seconds: 0, max_requests: 0, requests_count: 0, user_id: null}
     */
    private function allowedWithoutOwner(): array
    {
        return [
            'allowed' => true,
            'retry_after_seconds' => 0,
            'epoch_seconds' => 0,
            'max_requests' => 0,
            'requests_count' => 0,
            'user_id' => null,
        ];
    }
}
