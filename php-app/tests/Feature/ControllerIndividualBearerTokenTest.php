<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ControllerReadingsReceived;
use App\Events\ControllerReportReceived;
use App\Models\ControllerRegistrationAttempt;
use App\Models\IoTController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ControllerIndividualBearerTokenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pairing_registration_creates_hashed_individual_api_token(): void
    {
        $user = User::query()->create([
            'name' => 'Pairing user',
            'email' => 'pairing_' . Str::lower(Str::random(8)) . '@example.test',
            'password' => 'password',
            'time_zone' => 'Europe/Moscow',
            'locale' => 'ru',
        ]);

        $attempt = ControllerRegistrationAttempt::query()->create([
            'id' => (string) Uuid::uuid7(),
            'device_uid' => 'esp_1eaf90',
            'provisioning_token_hash' => hash('sha256', 'provisioning-token-for-test-000001'),
            'code' => '7542',
            'status' => 'pending',
            'last_seen_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $firstConfirmation = $this->actingAs($user)->postJson('/api/pairing/confirm-by-code', [
            'code' => '7542',
        ]);

        $firstConfirmation->assertStatus(202)->assertJson(['challenge_required' => true]);

        $attempt->refresh();
        $secondConfirmation = $this->actingAs($user)->postJson('/api/pairing/confirm-by-code', [
            'code' => (string) $attempt->challenge_code,
            'registration_token' => (string) $firstConfirmation->json('registration_token'),
        ]);

        $secondConfirmation->assertOk();

        $controller = IoTController::query()->findOrFail((string) $secondConfirmation->json('controller_id'));
        $this->assertNotSame('', (string) $controller->api_token_hash);
        $this->assertNotSame('', (string) $controller->pending_api_token);
        $this->assertSame(hash('sha256', (string) $controller->pending_api_token), (string) $controller->api_token_hash);
        $this->assertNotSame(
            (string) $controller->pending_api_token,
            (string) DB::table('controller')->where('id', $controller->id)->value('pending_api_token')
        );
    }

    public function test_provisioning_creates_registration_attempt_bound_to_temporary_token(): void
    {
        $payload = [
            'controller_id' => '',
            'device_uid' => 'esp_new_device',
            'readings' => [['pin' => 'soil_moisture_raw', 'value' => 400]],
        ];
        $token = 'provisioning-token-for-test-000000';

        $this->postProvisionRequest($payload, $token)
            ->assertOk()
            ->assertJson([
                'registration_required' => true,
                'controller_id' => '',
            ]);

        $attempt = ControllerRegistrationAttempt::query()
            ->where('device_uid', 'esp_new_device')
            ->firstOrFail();
        $this->assertSame(hash('sha256', $token), $attempt->provisioning_token_hash);

        $this->postProvisionRequest($payload, 'different-provisioning-token-000')
            ->assertStatus(401)
            ->assertJson(['error' => 'provision_auth_failed']);
    }

    public function test_individual_bearer_token_authenticates_its_controller_and_clears_pending_copy(): void
    {
        Event::fake([ControllerReportReceived::class, ControllerReadingsReceived::class]);

        $controller = $this->createController('individual-token');

        $this->assertNotSame(
            'individual-token',
            DB::table('controller')->where('id', $controller->id)->value('pending_api_token')
        );
        $this->assertSame('individual-token', IoTController::query()->findOrFail($controller->id)->pending_api_token);

        $payload = [
            'controller_id' => $controller->id,
            'readings' => [['pin' => 'soil_moisture_raw', 'value' => 400]],
        ];

        $this->postBearerReport($payload, 'individual-token')->assertOk();
        $this->assertNull(IoTController::query()->findOrFail($controller->id)->pending_api_token);
    }

    public function test_report_rejects_request_without_bearer_token(): void
    {
        Event::fake([ControllerReportReceived::class, ControllerReadingsReceived::class]);
        $controller = $this->createController('individual-token');
        $payload = [
            'controller_id' => $controller->id,
            'readings' => [['pin' => 'soil_moisture_raw', 'value' => 400]],
        ];

        $this->postJson('/api/controller/report', $payload)
            ->assertStatus(401)
            ->assertJson(['error' => 'controller_auth_failed']);
    }

    public function test_individual_bearer_token_cannot_submit_report_for_another_controller(): void
    {
        Event::fake([ControllerReportReceived::class, ControllerReadingsReceived::class]);

        $authenticatedController = $this->createController('first-token');
        $otherController = $this->createController('second-token');

        $payload = [
            'controller_id' => $otherController->id,
            'readings' => [['pin' => 'soil_moisture_raw', 'value' => 400]],
        ];

        $this->postBearerReport($payload, 'first-token')
            ->assertStatus(401)
            ->assertJson(['error' => 'controller_auth_failed']);
    }

    public function test_provisioning_request_receives_individual_token_after_pairing(): void
    {
        Event::fake([ControllerReportReceived::class, ControllerReadingsReceived::class]);
        $controller = $this->createController('individual-token');
        $provisioningToken = 'provisioning-token-for-test-000002';
        ControllerRegistrationAttempt::query()->create([
            'id' => (string) Uuid::uuid7(),
            'device_uid' => 'esp_1eaf90',
            'provisioning_token_hash' => hash('sha256', $provisioningToken),
            'code' => '1111',
            'status' => 'claimed',
            'registered_controller_id' => $controller->id,
            'last_seen_at' => now(),
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $payload = [
            'controller_id' => $controller->id,
            'device_uid' => 'esp_1eaf90',
            'readings' => [['pin' => 'soil_moisture_raw', 'value' => 400]],
        ];

        $this->postProvisionRequest($payload, $provisioningToken)
            ->assertOk()
            ->assertJson([
                'controller_id' => $controller->id,
                'api_token' => 'individual-token',
            ]);
    }

    public function test_provisioning_request_issues_token_for_confirmed_controller_without_token(): void
    {
        Event::fake([ControllerReportReceived::class, ControllerReadingsReceived::class]);
        $provisioningToken = 'provisioning-token-for-test-000003';
        $controller = IoTController::query()->create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Migrated controller',
            'send_interval_seconds' => 5,
            'status' => 'active',
            'claimed_at' => now(),
        ]);
        ControllerRegistrationAttempt::query()->create([
            'id' => (string) Uuid::uuid7(),
            'device_uid' => 'esp_legacy',
            'provisioning_token_hash' => hash('sha256', $provisioningToken),
            'code' => '1112',
            'status' => 'claimed',
            'registered_controller_id' => $controller->id,
            'last_seen_at' => now(),
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postProvisionRequest([
            'controller_id' => $controller->id,
            'device_uid' => 'esp_legacy',
            'readings' => [['pin' => 'soil_moisture_raw', 'value' => 400]],
        ], $provisioningToken);

        $response->assertOk()->assertJsonStructure(['api_token']);
        $controller->refresh();
        $this->assertSame(hash('sha256', (string) $response->json('api_token')), $controller->api_token_hash);
    }

    private function createController(string $apiToken): IoTController
    {
        return IoTController::query()->create([
            'id' => (string) Uuid::uuid7(),
            'api_token_hash' => hash('sha256', $apiToken),
            'pending_api_token' => $apiToken,
            'api_token_generated_at' => now(),
            'name' => 'Test controller',
            'send_interval_seconds' => 5,
            'status' => 'active',
            'claimed_at' => now(),
        ]);
    }

    private function postBearerReport(array $payload, string $apiToken)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $apiToken)
            ->postJson('/api/controller/report', $payload);
    }

    private function postProvisionRequest(array $payload, string $provisioningToken)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $provisioningToken)
            ->postJson('/api/controller/provision', $payload);
    }
}
