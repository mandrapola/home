<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DailyBalanceChargeService
{
    public function __construct(
        private readonly UserBalanceService $userBalanceService,
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    public function chargeForDate(?string $date = null): int
    {
        $billingDate = $date
            ? CarbonImmutable::parse($date)->startOfDay()
            : CarbonImmutable::yesterday()->startOfDay();

        $userIds = DB::table('user_activity_days')
            ->where('activity_date', $billingDate->toDateString())
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->unique();

        $chargedUsers = 0;
        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            $plan = $this->planLimitService->resolveSelectedPlanForUser($user);
            if (! $plan instanceof Plan || (int) ($plan->daily_price_units ?? 0) <= 0) {
                continue;
            }

            $this->userBalanceService->chargeDaily($user, $plan, $billingDate);
            $chargedUsers++;
        }

        return $chargedUsers;
    }
}
