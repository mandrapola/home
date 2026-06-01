<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Alice\AliceSmartHomeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AliceSensorIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_soil_moisture_percent_sensor_is_exposed_to_alice_as_humidity(): void
    {
        $user = $this->createUser();
        $controllerId = $this->createController($user);
        $pinId = $this->createSoilMoisturePin($controllerId);

        $payload = app(AliceSmartHomeService::class)->buildDevicesPayload($user);

        $device = collect($payload['payload']['devices'])->firstWhere('id', $pinId);
        $this->assertNotNull($device);
        $this->assertSame('devices.types.sensor', $device['type']);
        $this->assertSame('devices.properties.float', $device['properties'][0]['type']);
        $this->assertSame('humidity', $device['properties'][0]['parameters']['instance']);
        $this->assertSame('unit.percent', $device['properties'][0]['parameters']['unit']);
    }

    public function test_soil_moisture_query_returns_calibrated_percent_value(): void
    {
        $user = $this->createUser();
        $controllerId = $this->createController($user);
        $pinId = $this->createSoilMoisturePin($controllerId);

        DB::table('pin_data')->insert([
            [
                'id' => (string) Uuid::uuid7(),
                'pin_id' => $pinId,
                'value' => 250,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Uuid::uuid7(),
                'pin_id' => $pinId,
                'value' => 350,
                'created_at' => now()->subSecond(),
                'updated_at' => now()->subSecond(),
            ],
        ]);

        $payload = app(AliceSmartHomeService::class)->buildQueryPayload($user, [$pinId]);

        $state = $payload['payload']['devices'][0]['properties'][0]['state'];
        $this->assertSame('humidity', $state['instance']);
        $this->assertSame(70.0, $state['value']);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Alice sensor user',
            'email' => 'alice_sensor_' . strtolower(str_replace('-', '', (string) Uuid::uuid4())) . '@example.test',
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

    private function createSoilMoisturePin(string $controllerId): string
    {
        $pinId = (string) Uuid::uuid7();
        DB::table('pin')->insert([
            'id' => $pinId,
            'controller_id' => $controllerId,
            'pin' => 'SOIL_MOISTURE_RAW',
            'label' => 'Влажность почвы',
            'unit' => 'adc',
            'digital_style' => 'sensor_humidity',
            'value' => 250,
            'show_on_chart' => 1,
            'show_on_report' => 1,
            'is_monitored' => 0,
            'external_enabled' => 1,
            'moisture_raw_dry' => 1000,
            'moisture_raw_wet' => 0,
            'moisture_show_percent' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $pinId;
    }
}
