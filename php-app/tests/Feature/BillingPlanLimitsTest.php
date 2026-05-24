<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ControllerReadingsReceived;
use App\Events\ControllerReportReceived;
use App\Http\Middleware\VerifyControllerToken;
use App\Models\Plan;
use App\Models\User;
use App\Services\Report\PinDataHistoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class BillingPlanLimitsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_endpoint_returns_429_when_requests_are_sent_before_plan_interval(): void
    {
        Event::fake([
            ControllerReportReceived::class,
            ControllerReadingsReceived::class,
        ]);
        Carbon::setTestNow('2026-05-24 12:00:00');

        $plan = $this->createPlan([
            'min_report_interval_seconds' => 10,
        ]);
        $user = $this->createUser($plan);
        $controllerId = $this->createControllerForUser($user, [
            'send_interval_seconds' => 10,
        ]);

        $this->withoutMiddleware(VerifyControllerToken::class);

        $payload = [
            'controller_id' => $controllerId,
            'readings' => [
                ['pin' => 'soil_moisture_raw', 'value' => 400],
            ],
        ];

        $this->postJson('/api/controller/report', $payload)->assertOk();

        Carbon::setTestNow('2026-05-24 12:00:05');

        $this->postJson('/api/controller/report', $payload)
            ->assertStatus(429)
            ->assertJson([
                'error' => 'rate_limit',
                'retry_after_seconds' => 5,
            ]);

        Carbon::setTestNow();
    }

    public function test_pin_data_rows_are_not_inserted_after_plan_limit_is_reached(): void
    {
        $plan = $this->createPlan([
            'max_pin_data_rows' => 1,
        ]);
        $user = $this->createUser($plan);
        $controllerId = $this->createControllerForUser($user);
        $pinId = $this->createPin($controllerId, [
            'pin' => 'RELAY_1',
            'digital_style' => 'power',
        ]);

        DB::table('pin_data')->insert([
            'id' => (string) Uuid::uuid7(),
            'pin_id' => $pinId,
            'value' => 0,
            'created_at' => now(),
        ]);

        app(PinDataHistoryService::class)->storeReadings(
            [['pin' => 'RELAY_1', 'value' => 1]],
            ['RELAY_1' => $pinId],
            ['RELAY_1' => 'power'],
            $controllerId
        );

        $this->assertSame(1, DB::table('pin_data')->where('pin_id', $pinId)->count());
    }

    public function test_scenario_creation_is_rejected_after_plan_limit_is_reached(): void
    {
        $plan = $this->createPlan([
            'max_scenarios' => 1,
        ]);
        $user = $this->createUser($plan);
        $controllerId = $this->createControllerForUser($user);
        $targetPinId = $this->createPin($controllerId, [
            'pin' => 'RELAY_1',
            'digital_style' => 'power',
        ]);

        DB::table('scenario')->insert([
            'id' => (string) Uuid::uuid7(),
            'pin_id' => $targetPinId,
            'name' => 'existing scenario',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/scenes/scenario-definitions', [
                'pin_id' => $targetPinId,
                'name' => 'new scenario',
            ])
            ->assertStatus(409)
            ->assertJson([
                'error' => 'plan_limit_exceeded',
                'used' => 1,
                'max' => 1,
            ]);

        $this->assertSame(1, DB::table('scenario')->where('pin_id', $targetPinId)->count());
    }

    public function test_scenario_condition_creation_is_rejected_after_plan_limit_is_reached(): void
    {
        $plan = $this->createPlan([
            'max_scenario_conditions' => 1,
        ]);
        $user = $this->createUser($plan);
        $controllerId = $this->createControllerForUser($user);
        $targetPinId = $this->createPin($controllerId, [
            'pin' => 'RELAY_1',
            'digital_style' => 'power',
        ]);
        $sourcePinId = $this->createPin($controllerId, [
            'pin' => 'SOIL_MOISTURE_RAW',
            'digital_style' => 'sensor',
        ]);
        $scenarioId = (string) Uuid::uuid7();

        DB::table('scenario')->insert([
            'id' => $scenarioId,
            'pin_id' => $targetPinId,
            'name' => 'watering',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('scenario_condition')->insert([
            'id' => (string) Uuid::uuid7(),
            'scenario_id' => $scenarioId,
            'pin_id' => $sourcePinId,
            'operator' => 'gt',
            'threshold' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/scenes/conditions', [
                'scenario_id' => $scenarioId,
                'pin_id' => $sourcePinId,
                'operator' => 'lt',
                'threshold' => 900,
            ])
            ->assertStatus(409)
            ->assertJson([
                'error' => 'plan_limit_exceeded',
                'used' => 1,
                'max' => 1,
            ]);

        $this->assertSame(1, DB::table('scenario_condition')->where('scenario_id', $scenarioId)->count());
    }

    public function test_scenarios_do_not_execute_when_scenario_limit_is_exceeded(): void
    {
        Event::fake([
            ControllerReportReceived::class,
            ControllerReadingsReceived::class,
        ]);

        $plan = $this->createPlan([
            'max_scenarios' => 1,
        ]);
        $user = $this->createUser($plan);
        $controllerId = $this->createControllerForUser($user);
        $targetPinId = $this->createPin($controllerId, [
            'pin' => 'RELAY_1',
            'digital_style' => 'power',
            'desired_digital_value' => 1,
        ]);
        $this->createPin($controllerId, [
            'pin' => 'RELAY_2',
            'digital_style' => 'power',
            'desired_digital_value' => 1,
        ]);

        DB::table('scenario')->insert([
            [
                'id' => (string) Uuid::uuid7(),
                'pin_id' => $targetPinId,
                'name' => 'scenario one',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Uuid::uuid7(),
                'pin_id' => $targetPinId,
                'name' => 'scenario two',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->withoutMiddleware(VerifyControllerToken::class);

        $response = $this->postJson('/api/controller/report', [
            'controller_id' => $controllerId,
            'readings' => [
                ['pin' => 'soil_moisture_raw', 'value' => 400],
            ],
        ]);

        $response->assertOk();
        $this->assertSame([], $response->json('digital_outputs'));
    }

    public function test_scenarios_do_not_execute_when_scenario_condition_limit_is_exceeded(): void
    {
        Event::fake([
            ControllerReportReceived::class,
            ControllerReadingsReceived::class,
        ]);

        $plan = $this->createPlan([
            'max_scenario_conditions' => 1,
        ]);
        $user = $this->createUser($plan);
        $controllerId = $this->createControllerForUser($user);
        $targetPinId = $this->createPin($controllerId, [
            'pin' => 'RELAY_1',
            'digital_style' => 'power',
            'desired_digital_value' => 1,
        ]);
        $sourcePinId = $this->createPin($controllerId, [
            'pin' => 'SOIL_MOISTURE_RAW',
            'digital_style' => 'sensor',
        ]);
        $scenarioId = (string) Uuid::uuid7();

        DB::table('scenario')->insert([
            'id' => $scenarioId,
            'pin_id' => $targetPinId,
            'name' => 'watering',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('scenario_condition')->insert([
            [
                'id' => (string) Uuid::uuid7(),
                'scenario_id' => $scenarioId,
                'pin_id' => $sourcePinId,
                'operator' => 'gt',
                'threshold' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Uuid::uuid7(),
                'scenario_id' => $scenarioId,
                'pin_id' => $sourcePinId,
                'operator' => 'lt',
                'threshold' => 900,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->withoutMiddleware(VerifyControllerToken::class);

        $response = $this->postJson('/api/controller/report', [
            'controller_id' => $controllerId,
            'readings' => [
                ['pin' => 'soil_moisture_raw', 'value' => 400],
            ],
        ]);

        $response->assertOk();
        $this->assertSame([], $response->json('digital_outputs'));
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createPlan(array $overrides = []): Plan
    {
        return Plan::query()->create(array_merge([
            'code' => 'test_' . Str::lower(Str::random(10)),
            'name' => 'Test plan',
            'description' => 'Test plan',
            'daily_price_units' => 0,
            'min_report_interval_seconds' => 5,
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
            'name' => 'Test user',
            'email' => 'test_' . Str::lower(Str::random(10)) . '@example.test',
            'password' => 'password',
            'time_zone' => 'Europe/Moscow',
            'locale' => 'ru',
            'alice_enabled' => false,
            'selected_plan_id' => $plan->id,
        ]);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createControllerForUser(User $user, array $overrides = []): string
    {
        $controllerId = (string) Uuid::uuid7();

        DB::table('controller')->insert(array_merge([
            'id' => $controllerId,
            'user_id' => $user->id,
            'name' => 'Test controller',
            'discription' => null,
            'send_interval_seconds' => 5,
            'status' => 'claimed',
            'last_seen_at' => null,
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $controllerId;
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createPin(string $controllerId, array $overrides = []): string
    {
        $pinId = (string) Uuid::uuid7();

        DB::table('pin')->insert(array_merge([
            'id' => $pinId,
            'controller_id' => $controllerId,
            'pin' => 'RELAY_1',
            'label' => 'Relay 1',
            'unit' => null,
            'digital_style' => 'power',
            'value' => null,
            'value_updated_at' => null,
            'desired_digital_value' => null,
            'desired_digital_updated_at' => null,
            'show_on_chart' => false,
            'show_on_report' => true,
            'is_monitored' => false,
            'external_enabled' => true,
            'chart_range_hours' => 1,
            'enable_scenario' => true,
        ], $overrides));

        return $pinId;
    }
}
