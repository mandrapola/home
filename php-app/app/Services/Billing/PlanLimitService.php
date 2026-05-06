<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanLimitService
{
    public function getDefaultPlan(): ?Plan
    {
        $code = (string) config('smarthome.default_plan', 'free');

        return Plan::query()->where('code', $code)->first();
    }

    public function resolveEffectivePlanForUser(User $user): ?Plan
    {
        $now = Carbon::now();

        $activeSubscription = DB::table('user_subscriptions as us')
            ->join('plans as p', 'p.id', '=', 'us.plan_id')
            ->where('us.user_id', $user->id)
            ->where('us.status', 'active')
            ->where('us.starts_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('us.ends_at')->orWhere('us.ends_at', '>', $now);
            })
            ->orderByDesc('us.id')
            ->select('p.id')
            ->first();

        if ($activeSubscription?->id) {
            return Plan::query()->find((int) $activeSubscription->id);
        }

        return $this->getDefaultPlan();
    }

    public function resolveSelectedPlanForUser(User $user): ?Plan
    {
        if (! $user->selected_plan_id) {
            return null;
        }

        return Plan::query()->find((int) $user->selected_plan_id);
    }

    /**
     * @return array{allowed: bool, used: int, max: int|null, effective_plan: ?Plan}
     */
    public function controllerAttachAllowance(User $user): array
    {
        $plan = $this->resolveEffectivePlanForUser($user);
        $used = (int) DB::table('controller_user')
            ->where('user_id', (string) $user->id)
            ->distinct('controller_id')
            ->count('controller_id');
        $max = $plan?->max_controllers;

        return [
            'allowed' => $max === null || $used < $max,
            'used' => $used,
            'max' => $max,
            'effective_plan' => $plan,
        ];
    }

    public function isAliceAllowedForUser(User $user): bool
    {
        $plan = $this->resolveEffectivePlanForUser($user);
        if (! $plan) {
            return false;
        }

        return (bool) $plan->alice_enabled;
    }

    public function canInsertPinDataForController(string $controllerId): bool
    {
        $ownerIds = DB::table('controller_user')
            ->where('controller_id', $controllerId)
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if (count($ownerIds) === 0) {
            return true;
        }

        foreach ($ownerIds as $ownerId) {
            $user = User::query()->find($ownerId);
            if (! $user) {
                continue;
            }

            $plan = $this->resolveEffectivePlanForUser($user);
            $maxRows = $plan?->max_pin_data_rows;
            if ($maxRows === null) {
                continue;
            }

            $usedRows = (int) DB::table('pin_data as pd')
                ->join('pin as p', 'p.id', '=', 'pd.pin_id')
                ->join('controller_user as cu', 'cu.controller_id', '=', 'p.controller_id')
                ->where('cu.user_id', (string) $ownerId)
                ->count('pd.id');

            if ($usedRows >= $maxRows) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *   selected_plan: ?Plan,
     *   effective_plan: ?Plan,
     *   controllers_used: int,
     *   controllers_max: int|null,
     *   pin_data_used: int,
     *   pin_data_max: int|null,
     *   controller_slots_left: int|null
     * }
     */
    public function usageSummaryForUser(User $user): array
    {
        $selectedPlan = $this->resolveSelectedPlanForUser($user);
        $effectivePlan = $this->resolveEffectivePlanForUser($user);

        $controllersUsed = (int) DB::table('controller_user')
            ->where('user_id', (string) $user->id)
            ->distinct('controller_id')
            ->count('controller_id');

        $pinDataUsed = (int) DB::table('pin_data as pd')
            ->join('pin as p', 'p.id', '=', 'pd.pin_id')
            ->join('controller_user as cu', 'cu.controller_id', '=', 'p.controller_id')
            ->where('cu.user_id', (string) $user->id)
            ->count('pd.id');

        $controllersMax = $effectivePlan?->max_controllers;
        $pinDataMax = $effectivePlan?->max_pin_data_rows;

        return [
            'selected_plan' => $selectedPlan,
            'effective_plan' => $effectivePlan,
            'controllers_used' => $controllersUsed,
            'controllers_max' => $controllersMax,
            'pin_data_used' => $pinDataUsed,
            'pin_data_max' => $pinDataMax,
            'controller_slots_left' => $controllersMax === null ? null : max(0, $controllersMax - $controllersUsed),
        ];
    }
}
