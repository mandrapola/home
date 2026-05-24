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
        $defaultPlan = $this->getDefaultPlan();
        $selectedPlan = $this->resolveSelectedPlanForUser($user);
        if ($selectedPlan) {
            $dailyPrice = (int) ($selectedPlan->daily_price_units ?? 0);
            if ($dailyPrice <= 0) {
                return $selectedPlan;
            }

            $balance = DB::table('user_balances')->where('user_id', $user->id)->first([
                'balance_units',
                'billing_blocked_at',
            ]);

            if ($balance) {
                if ((int) $balance->balance_units > 0 && $balance->billing_blocked_at === null) {
                    return $selectedPlan;
                }

                return $defaultPlan;
            }
        }

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

        return $defaultPlan;
    }

    public function resolveSelectedPlanForUser(User $user): ?Plan
    {
        if (! $user->selected_plan_id) {
            return null;
        }

        return Plan::query()->find((int) $user->selected_plan_id);
    }

    public function isAliceAllowedForUser(User $user): bool
    {
        return true;
    }

    /**
     * Kept for old pairing endpoints. Controller count is no longer tariff-limited.
     *
     * @return array{allowed: bool, used: int, max: int|null, effective_plan: ?Plan}
     */
    public function controllerAttachAllowance(User $user): array
    {
        $used = (int) DB::table('controller')
            ->where('user_id', (string) $user->id)
            ->count('id');

        return [
            'allowed' => true,
            'used' => $used,
            'max' => null,
            'effective_plan' => $this->resolveEffectivePlanForUser($user),
        ];
    }

    public function canInsertPinDataForController(string $controllerId): bool
    {
        $ownerId = (int) (DB::table('controller')->where('id', $controllerId)->value('user_id') ?? 0);

        if ($ownerId <= 0) {
            return true;
        }

        $user = User::query()->find($ownerId);
        if (! $user) {
            return true;
        }

        $plan = $this->resolveEffectivePlanForUser($user);
        $maxRows = $this->normalizeLimit($plan?->max_pin_data_rows);
        if ($maxRows === null) {
            return true;
        }

        $usedRows = (int) DB::table('pin_data as pd')
            ->join('pin as p', 'p.id', '=', 'pd.pin_id')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', $ownerId)
            ->count('pd.id');

        return $usedRows < $maxRows;
    }

    /**
     * @return array{allowed: bool, used: int, max: int|null, effective_plan: ?Plan}
     */
    public function scenarioCreateAllowance(User $user): array
    {
        $plan = $this->resolveEffectivePlanForUser($user);
        $used = $this->countUserScenarios($user);
        $max = $this->normalizeLimit($plan?->max_scenarios);

        return [
            'allowed' => $max === null || $used < $max,
            'used' => $used,
            'max' => $max,
            'effective_plan' => $plan,
        ];
    }

    /**
     * @return array{allowed: bool, used: int, max: int|null, effective_plan: ?Plan}
     */
    public function scenarioConditionCreateAllowance(User $user): array
    {
        $plan = $this->resolveEffectivePlanForUser($user);
        $used = $this->countUserScenarioConditions($user);
        $max = $this->normalizeLimit($plan?->max_scenario_conditions);

        return [
            'allowed' => $max === null || $used < $max,
            'used' => $used,
            'max' => $max,
            'effective_plan' => $plan,
        ];
    }

    public function scenarioExecutionAllowedForController(string $controllerId): bool
    {
        $ownerId = (int) (DB::table('controller')->where('id', $controllerId)->value('user_id') ?? 0);

        if ($ownerId <= 0) {
            return true;
        }

        $user = User::query()->find($ownerId);
        if (! $user) {
            return true;
        }

        $plan = $this->resolveEffectivePlanForUser($user);
        $scenarioMax = $this->normalizeLimit($plan?->max_scenarios);
        $conditionMax = $this->normalizeLimit($plan?->max_scenario_conditions);

        if ($scenarioMax !== null && $this->countUserScenarios($user) > $scenarioMax) {
            return false;
        }

        if ($conditionMax !== null && $this->countUserScenarioConditions($user) > $conditionMax) {
            return false;
        }

        return true;
    }

    private function countUserScenarios(User $user): int
    {
        return (int) DB::table('scenario as s')
            ->join('pin as p', 'p.id', '=', 's.pin_id')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', (string) $user->id)
            ->count('s.id');
    }

    private function countUserScenarioConditions(User $user): int
    {
        return (int) DB::table('scenario_condition as sc')
            ->join('scenario as s', 's.id', '=', 'sc.scenario_id')
            ->join('pin as p', 'p.id', '=', 's.pin_id')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', (string) $user->id)
            ->count('sc.id');
    }

    private function normalizeLimit(null|int|string $value): ?int
    {
        $limit = (int) ($value ?? 0);

        return $limit > 0 ? $limit : null;
    }

    /**
     * @return array{
     *   selected_plan: ?Plan,
     *   effective_plan: ?Plan,
     *   pin_data_used: int,
     *   pin_data_max: int|null,
     *   scenarios_used: int,
     *   scenarios_max: int|null,
     *   scenario_conditions_used: int,
     *   scenario_conditions_max: int|null,
     *   min_report_interval_seconds: int,
     *   balance_units: int,
     *   billing_blocked: bool
     * }
     */
    public function usageSummaryForUser(User $user): array
    {
        $selectedPlan = $this->resolveSelectedPlanForUser($user);
        $effectivePlan = $this->resolveEffectivePlanForUser($user);

        $pinDataUsed = (int) DB::table('pin_data as pd')
            ->join('pin as p', 'p.id', '=', 'pd.pin_id')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', (string) $user->id)
            ->count('pd.id');

        $pinDataMax = $this->normalizeLimit($effectivePlan?->max_pin_data_rows);
        $scenariosUsed = $this->countUserScenarios($user);
        $scenarioConditionsUsed = $this->countUserScenarioConditions($user);
        $balance = DB::table('user_balances')->where('user_id', $user->id)->first([
            'balance_units',
            'billing_blocked_at',
        ]);

        return [
            'selected_plan' => $selectedPlan,
            'effective_plan' => $effectivePlan,
            'pin_data_used' => $pinDataUsed,
            'pin_data_max' => $pinDataMax,
            'scenarios_used' => $scenariosUsed,
            'scenarios_max' => $this->normalizeLimit($effectivePlan?->max_scenarios),
            'scenario_conditions_used' => $scenarioConditionsUsed,
            'scenario_conditions_max' => $this->normalizeLimit($effectivePlan?->max_scenario_conditions),
            'min_report_interval_seconds' => (int) ($effectivePlan?->min_report_interval_seconds ?? 0),
            'balance_units' => (int) ($balance->balance_units ?? 0),
            'billing_blocked' => $balance !== null && $balance->billing_blocked_at !== null,
        ];
    }
}
