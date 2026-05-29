<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ControllerRegistrationAttempt;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ControllerLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_delete_own_controller_with_history_and_scenarios(): void
    {
        $user = $this->createUser();
        $controllerId = $this->createController($user, ['device_uid' => 'esp_delete_test']);
        $pinId = $this->createPin($controllerId);
        $virtualControllerId = $this->createController($user, [
            'status' => 'virtual',
            'is_service' => 1,
            'device_uid' => null,
            'name' => 'Алиса',
        ]);
        $virtualPinId = $this->createPin($virtualControllerId, [
            'pin' => 'ALICE_RELAY_1',
            'external_target_pin_id' => $pinId,
        ]);
        $scenarioId = $this->createScenario($pinId);
        $virtualScenarioId = $this->createScenario($virtualPinId);

        DB::table('pin_data')->insert([
            'id' => (string) Uuid::uuid7(),
            'pin_id' => $pinId,
            'value' => 1,
            'created_at' => now(),
        ]);
        DB::table('scenario_condition')->insert([
            [
                'id' => (string) Uuid::uuid7(),
                'scenario_id' => $scenarioId,
                'pin_id' => $pinId,
                'operator' => 'gt',
                'threshold' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Uuid::uuid7(),
                'scenario_id' => $virtualScenarioId,
                'pin_id' => $pinId,
                'operator' => 'gt',
                'threshold' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->deleteJson('/api/pairing/my-controllers/' . $controllerId, ['_token' => 'test-token'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('controller', ['id' => $controllerId]);
        $this->assertDatabaseMissing('pin', ['id' => $pinId]);
        $this->assertDatabaseMissing('pin', ['id' => $virtualPinId]);
        $this->assertDatabaseMissing('pin_data', ['pin_id' => $pinId]);
        $this->assertDatabaseMissing('scenario', ['id' => $scenarioId]);
        $this->assertDatabaseMissing('scenario', ['id' => $virtualScenarioId]);
        $this->assertDatabaseHas('controller', ['id' => $virtualControllerId]);
    }

    public function test_user_cannot_delete_service_controller(): void
    {
        $user = $this->createUser();
        $controllerId = $this->createController($user, [
            'status' => 'virtual',
            'is_service' => 1,
            'device_uid' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->deleteJson('/api/pairing/my-controllers/' . $controllerId, ['_token' => 'test-token'])
            ->assertForbidden();

        $this->assertDatabaseHas('controller', ['id' => $controllerId]);
    }

    public function test_pairing_reuses_existing_controller_with_same_device_uid_for_user(): void
    {
        $user = $this->createUser();
        $controllerId = $this->createController($user, ['device_uid' => 'esp_reuse_test']);
        $attempt = ControllerRegistrationAttempt::query()->create([
            'id' => (string) Uuid::uuid7(),
            'device_uid' => 'esp_reuse_test',
            'provisioning_token_hash' => hash('sha256', 'provisioning-token'),
            'code' => '1234',
            'status' => 'pending',
            'last_seen_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $first = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->postJson('/api/pairing/confirm-by-code', [
                '_token' => 'test-token',
                'code' => '1234',
            ]);

        $first->assertStatus(202);
        $token = (string) $first->json('registration_token');
        $challenge = (string) $attempt->fresh()->challenge_code;

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->postJson('/api/pairing/confirm-by-code', [
                '_token' => 'test-token',
                'code' => $challenge,
                'registration_token' => $token,
            ])
            ->assertOk()
            ->assertJson(['controller_id' => $controllerId]);

        $this->assertSame(1, DB::table('controller')->where('device_uid', 'esp_reuse_test')->count());
        $this->assertNotNull(DB::table('controller')->where('id', $controllerId)->value('pending_api_token'));
    }

    private function createUser(): User
    {
        $plan = Plan::query()->create([
            'code' => 'controller_lifecycle_' . Str::lower(Str::random(8)),
            'name' => 'Controller lifecycle',
            'description' => 'Controller lifecycle',
            'daily_price_units' => 0,
            'min_report_interval_seconds' => 5,
            'price_currency' => 'RUB',
            'max_pin_data_rows' => 0,
            'max_scenarios' => 0,
            'max_scenario_conditions' => 0,
            'is_active' => true,
        ]);

        return User::query()->create([
            'name' => 'Lifecycle User',
            'email' => 'lifecycle_' . Str::lower(Str::random(10)) . '@example.test',
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
    private function createController(User $user, array $overrides = []): string
    {
        $controllerId = (string) Uuid::uuid7();
        DB::table('controller')->insert(array_merge([
            'id' => $controllerId,
            'user_id' => $user->id,
            'device_uid' => 'esp_' . Str::lower(Str::random(10)),
            'name' => 'Controller',
            'discription' => null,
            'send_interval_seconds' => 5,
            'status' => 'active',
            'is_service' => 0,
            'last_seen_at' => now(),
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
            'pin' => 'RELAY_' . Str::upper(Str::random(6)),
            'label' => 'Relay',
            'unit' => null,
            'digital_style' => 'power',
            'value' => 0,
            'value_updated_at' => now(),
            'desired_digital_value' => 0,
            'desired_digital_updated_at' => null,
            'last_on_command_sent_at' => null,
            'show_on_chart' => 0,
            'show_on_report' => 1,
            'is_monitored' => 0,
            'external_enabled' => 1,
            'external_source' => null,
            'external_target_pin_id' => null,
            'chart_range_hours' => 1,
            'enable_scenario' => 1,
        ], $overrides));

        return $pinId;
    }

    private function createScenario(string $pinId): string
    {
        $scenarioId = (string) Uuid::uuid7();
        DB::table('scenario')->insert([
            'id' => $scenarioId,
            'pin_id' => $pinId,
            'name' => 'Scenario ' . Str::lower(Str::random(6)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $scenarioId;
    }
}
