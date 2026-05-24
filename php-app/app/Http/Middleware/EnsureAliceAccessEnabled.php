<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAliceAccessEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('services.alice.enabled', false)) {
            return $this->jsonError('alice_integration_disabled', 'Alice integration is disabled.', 503);
        }

        $user = $request->attributes->get('alice_user') ?: $request->user();
        if (! $user) {
            return $this->jsonError('alice_user_not_authenticated', 'Alice user is not authenticated.', 401);
        }

        if (! (bool) ($user->alice_enabled ?? false)) {
            return $this->jsonError('alice_access_denied', 'Alice integration is not enabled for this user.', 403);
        }

        return $next($request);
    }

    private function jsonError(string $error, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'message' => $message,
        ], $status);
    }
}
