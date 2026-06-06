<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BalanceTransaction;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserBalance;
use App\Services\Billing\UserBalanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingDailyChargeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_daily_charge_collects_only_missing_difference_for_the_day(): void
    {
        Carbon::setTestNow('2026-05-29 12:00:00');

        $plan = $this->createPlan(['daily_price_units' => 100]);
        $user = $this->createUser($plan);
        $this->createBalance($user, 1000);
        $this->createCharge($user, -30, 'daily_charge', '2026-05-29', 30);

        app(UserBalanceService::class)->chargeDaily($user, $plan, Carbon::parse('2026-05-29'));

        $this->assertSame(930, UserBalance::query()->where('user_id', $user->id)->value('balance_units'));
        $this->assertSame(100, $this->chargedUnitsForDate($user, '2026-05-29'));

        Carbon::setTestNow();
    }

    public function test_plan_downgrade_charges_previous_plan_price_difference(): void
    {
        Carbon::setTestNow('2026-05-29 12:00:00');

        $pro = $this->createPlan(['daily_price_units' => 100]);
        $free = $this->createPlan(['daily_price_units' => 0]);
        $user = $this->createUser($pro);
        $this->createBalance($user, 1000);
        $this->createCharge($user, -40, 'daily_charge_failed', '2026-05-29', 100);

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post(route('user.plans.select', $free), ['_token' => 'test-token'])
            ->assertRedirect(route('user.plans.index'));

        $this->assertSame(940, UserBalance::query()->where('user_id', $user->id)->value('balance_units'));
        $this->assertSame(100, $this->chargedUnitsForDate($user, '2026-05-29'));
        $this->assertSame((int) $free->id, (int) $user->fresh()->selected_plan_id);

        Carbon::setTestNow();
    }

    public function test_plan_downgrade_does_not_refund_or_charge_when_day_is_already_paid_above_target(): void
    {
        Carbon::setTestNow('2026-05-29 12:00:00');

        $middle = $this->createPlan(['daily_price_units' => 50]);
        $free = $this->createPlan(['daily_price_units' => 0]);
        $user = $this->createUser($middle);
        $this->createBalance($user, 1000);
        $this->createCharge($user, -100, 'daily_charge', '2026-05-29', 100);

        $beforeCount = BalanceTransaction::query()->where('user_id', $user->id)->count();

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post(route('user.plans.select', $free), ['_token' => 'test-token'])
            ->assertRedirect(route('user.plans.index'));

        $this->assertSame(1000, UserBalance::query()->where('user_id', $user->id)->value('balance_units'));
        $this->assertSame($beforeCount, BalanceTransaction::query()->where('user_id', $user->id)->count());
        $this->assertSame((int) $free->id, (int) $user->fresh()->selected_plan_id);

        Carbon::setTestNow();
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createPlan(array $overrides = []): Plan
    {
        return Plan::query()->create(array_merge([
            'code' => 'billing_' . Str::lower(Str::random(10)),
            'name' => 'Billing plan',
            'description' => 'Billing plan',
            'daily_price_units' => 0,
            'report_epoch_seconds' => 300,
            'report_max_requests_per_epoch' => 0,
            'price_currency' => 'RUB',
            'max_pin_data_rows' => 0,
            'max_scenarios' => 0,
            'max_scenario_conditions' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function createUser(Plan $plan): User
    {
        return User::query()->create([
            'name' => 'Billing User',
            'email' => 'billing_' . Str::lower(Str::random(10)) . '@example.test',
            'password' => 'password',
            'time_zone' => 'Europe/Moscow',
            'locale' => 'ru',
            'alice_enabled' => false,
            'selected_plan_id' => $plan->id,
        ]);
    }

    private function createBalance(User $user, int $balanceUnits): void
    {
        UserBalance::query()->create([
            'user_id' => $user->id,
            'balance_units' => $balanceUnits,
        ]);
    }

    private function createCharge(User $user, int $amountUnits, string $type, string $date, int $required): void
    {
        BalanceTransaction::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'amount_units' => $amountUnits,
            'required_amount_units' => $required,
            'balance_after_units' => 0,
            'billing_date' => $date,
            'description' => 'Existing charge',
        ]);
    }

    private function chargedUnitsForDate(User $user, string $date): int
    {
        return abs((int) BalanceTransaction::query()
            ->where('user_id', $user->id)
            ->where('billing_date', $date)
            ->whereIn('type', [
                'daily_charge',
                'daily_charge_failed',
                'plan_switch_charge',
                'plan_switch_charge_failed',
            ])
            ->where('amount_units', '<', 0)
            ->sum('amount_units'));
    }
}
