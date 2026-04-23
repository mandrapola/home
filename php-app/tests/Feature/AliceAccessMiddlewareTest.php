<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureAliceAccessEnabled;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AliceAccessMiddlewareTest extends TestCase
{
    private EnsureAliceAccessEnabled $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureAliceAccessEnabled();
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

    private function makeAliceUser(bool $enabled): object
    {
        return new class($enabled) {
            public bool $alice_enabled;

            public function __construct(bool $enabled)
            {
                $this->alice_enabled = $enabled;
            }
        };
    }

    private function nextOk(): \Closure
    {
        return static fn (): Response => new Response('ok', 200);
    }
}
