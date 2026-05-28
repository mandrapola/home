<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Alice\AliceSmartHomeService;
use App\Services\Alice\AliceVirtualControllerService;
use App\Services\Report\ScenarioDesiredValueService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AliceVirtualControllerScenarioTest extends TestCase
{
    use DatabaseTransactions;

    public function test_alice_action_updates_virtual_pin_instead_of_power_pin(): void
    {
        $user = $this->createUser();
        $controllerId = $this->createController($user);
        $relayPinId = $this->createRelayPin($controllerId, [
            'desired_digital_value' => 0,
            'enable_scenario' => 1,
        ]);

        $payload = app(AliceSmartHomeService::class)->buildActionPayload($user, [[
            'id' => $relayPinId,
            'capabilities' => [[
                'type' => 'devices.capabilities.on_off',
                'state' => ['instance' => 'on', 'value' => true],
            ]],
        ]]);

        $this->assertSame('DONE', $payload['payload']['devices'][0]['action_result']['status']);

        $relayPin = DB::table('pin')->where('id', $relayPinId)->first();
        $this->assertSame(0, (int) $relayPin->desired_digital_value);
        $this->assertSame(1, (int) $relayPin->enable_scenario);

        $virtualPin = DB::table('pin')
            ->where('external_source', AliceVirtualControllerService::SOURCE)
            ->where('external_target_pin_id', $relayPinId)
            ->first();

        $this->assertNotNull($virtualPin);
        $this->assertSame(AliceVirtualControllerService::DIGITAL_STYLE, (string) $virtualPin->digital_style);
        $this->assertSame(1.0, (float) $virtualPin->value);
        $this->assertSame('Алиса: Полив', (string) $virtualPin->label);
    }

    public function test_scenario_can_use_alice_virtual_pin_as_condition_source(): void
    {
        $user = $this->createUser();
        $controllerId = $this->createController($user);
        $relayPinId = $this->createRelayPin($controllerId);

        app(AliceSmartHomeService::class)->buildActionPayload($user, [[
            'id' => $relayPinId,
            'capabilities' => [[
                'type' => 'devices.capabilities.on_off',
                'state' => ['instance' => 'on', 'value' => true],
            ]],
        ]]);

        $virtualPinId = (string) DB::table('pin')
            ->where('external_source', AliceVirtualControllerService::SOURCE)
            ->where('external_target_pin_id', $relayPinId)
            ->value('id');

        $scenarioId = (string) Uuid::uuid7();
        DB::table('scenario')->insert([
            'id' => $scenarioId,
            'pin_id' => $relayPinId,
            'name' => 'Alice controls relay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('scenario_condition')->insert([
            'id' => (string) Uuid::uuid7(),
            'scenario_id' => $scenarioId,
            'pin_id' => $virtualPinId,
            'operator' => 'eq',
            'threshold' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ScenarioDesiredValueService::class)->applyDesiredValue($controllerId);

        $this->assertSame(1, (int) DB::table('pin')->where('id', $relayPinId)->value('desired_digital_value'));
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Alice scenario user',
            'email' => 'alice_scenario_' . strtolower(str_replace('-', '', (string) Uuid::uuid4())) . '@example.test',
            'password' => 'password',
            'time_zone' => 'Europe/Moscow',
            'locale' => 'ru',
            'alice_enabled' => true,
        ]);
    }

    private function createController(User $user): string
    {
        $controllerId = (string) Uuid::uuid7();
        DB::table('controller')->insert([
            'id' => $controllerId,
            'user_id' => (int) $user->id,
            'name' => 'Greenhouse',
            'send_interval_seconds' => 30,
            'status' => 'active',
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $controllerId;
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createRelayPin(string $controllerId, array $overrides = []): string
    {
        $pinId = (string) Uuid::uuid7();
        DB::table('pin')->insert(array_merge([
            'id' => $pinId,
            'controller_id' => $controllerId,
            'pin' => 'RELAY_1',
            'label' => 'Полив',
            'digital_style' => 'power',
            'desired_digital_value' => 0,
            'enable_scenario' => 1,
            'external_enabled' => 1,
        ], $overrides));

        return $pinId;
    }
}
