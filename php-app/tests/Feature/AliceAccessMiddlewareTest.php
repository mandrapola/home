<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureAliceAccessEnabled;
use App\Models\User;
use App\Services\Billing\PlanLimitService;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AliceAccessMiddlewareTest extends TestCase
{
    private EnsureAliceAccessEnabled $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $planLimitService = Mockery::mock(PlanLimitService::class);
        $planLimitService->shouldReceive('isAliceAllowedForUser')->andReturn(true);
        $this->middleware = new EnsureAliceAccessEnabled($planLimitService);
    }

    public function test_returns_503_when_alice_globally_disabled(): void
    {
        config()->set('services.alice.enabled', false);
        $request = Request::create('/alice/test', 'GET');
        $request->attributes->set('alice_user', $this->makeAliceUser(true));
        $response = $this->middleware->handle($request, $this->nextOk());

        $this->assertSame(503, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('alice_integration_disabled', $payload['error'] ?? null);
    }

    public function test_returns_403_when_user_has_no_alice_access(): void
    {
        config()->set('services.alice.enabled', true);
        $request = Request::create('/alice/test', 'GET');
        $request->attributes->set('alice_user', $this->makeAliceUser(false));
        $response = $this->middleware->handle($request, $this->nextOk());

        $this->assertSame(403, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('alice_access_denied', $payload['error'] ?? null);
    }

    public function test_allows_request_for_enabled_user(): void
    {
        config()->set('services.alice.enabled', true);
        $request = Request::create('/alice/test', 'GET');
        $request->attributes->set('alice_user', $this->makeAliceUser(true));
        $response = $this->middleware->handle($request, $this->nextOk());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    private function makeAliceUser(bool $enabled): User
    {
        return new User([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'secret',
            'alice_enabled' => $enabled,
        ]);
    }

    private function nextOk(): \Closure
    {
        return static fn (): Response => new Response('ok', 200);
    }
}
