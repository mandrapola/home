<?php

namespace App\Services\Billing;

use App\Models\BalanceTransaction;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserBalance;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class UserBalanceService
{
    public function balanceFor(User|int $user): UserBalance
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        return UserBalance::query()->firstOrCreate(
            ['user_id' => $userId],
            ['balance_units' => 0]
        );
    }

    public function isBillingBlocked(User $user): bool
    {
        $balance = $this->balanceFor($user);

        return $balance->billing_blocked_at !== null;
    }

    public function credit(
        User $user,
        int $amountUnits,
        string $description,
        ?PaymentTransaction $paymentOrder = null
    ): UserBalance {
        if ($amountUnits <= 0) {
            return $this->balanceFor($user);
        }

        return DB::transaction(function () use ($user, $amountUnits, $description, $paymentOrder): UserBalance {
            $balance = UserBalance::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $balance) {
                $balance = UserBalance::query()->create([
                    'user_id' => $user->id,
                    'balance_units' => 0,
                ]);
                $balance = UserBalance::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            }

            $balance->balance_units += $amountUnits;
            $balance->billing_blocked_at = null;
            $balance->billing_block_reason = null;
            $balance->save();

            BalanceTransaction::query()->create([
                'user_id' => $user->id,
                'type' => 'topup',
                'amount_units' => $amountUnits,
                'balance_after_units' => $balance->balance_units,
                'description' => $description,
                'payment_order_id' => $paymentOrder?->id,
            ]);

            return $balance;
        });
    }

    public function chargeDaily(User $user, Plan $plan, CarbonInterface $billingDate, string $reason = 'daily'): void
    {
        $required = (int) ($plan->daily_price_units ?? 0);
        if ($required <= 0) {
            return;
        }

        $date = $billingDate->toDateString();
        $successType = $reason === 'plan_switch' ? 'plan_switch_charge' : 'daily_charge';
        $failedType = $reason === 'plan_switch' ? 'plan_switch_charge_failed' : 'daily_charge_failed';

        DB::transaction(function () use ($user, $plan, $required, $date, $successType, $failedType): void {
            $chargeTypes = [
                'daily_charge',
                'daily_charge_failed',
                'plan_switch_charge',
                'plan_switch_charge_failed',
            ];

            $alreadyChargedUnits = abs((int) BalanceTransaction::query()
                ->where('user_id', $user->id)
                ->where('billing_date', $date)
                ->whereIn('type', $chargeTypes)
                ->where('amount_units', '<', 0)
                ->lockForUpdate()
                ->sum('amount_units'));

            $remainingRequired = $required - $alreadyChargedUnits;
            if ($remainingRequired <= 0) {
                return;
            }

            $hasChargeAttemptToday = BalanceTransaction::query()
                ->where('user_id', $user->id)
                ->where('billing_date', $date)
                ->whereIn('type', $chargeTypes)
                ->exists();

            $balance = UserBalance::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $balance) {
                $balance = UserBalance::query()->create([
                    'user_id' => $user->id,
                    'balance_units' => 0,
                ]);
                $balance = UserBalance::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            }

            $charged = min(max(0, (int) $balance->balance_units), $remainingRequired);
            if ($charged <= 0 && $hasChargeAttemptToday) {
                return;
            }

            $balance->balance_units -= $charged;

            $isPaid = ($alreadyChargedUnits + $charged) >= $required;
            $balance->billing_blocked_at = $isPaid ? null : now();
            $balance->billing_block_reason = $isPaid ? null : 'insufficient_balance';
            $balance->save();

            BalanceTransaction::query()->create([
                'user_id' => $user->id,
                'type' => $isPaid ? $successType : $failedType,
                'amount_units' => -$charged,
                'required_amount_units' => $required,
                'balance_after_units' => $balance->balance_units,
                'billing_date' => $date,
                'description' => sprintf('Daily charge for %s plan', $plan->name),
            ]);
        });
    }
}
