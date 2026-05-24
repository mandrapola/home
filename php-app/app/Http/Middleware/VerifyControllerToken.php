<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IoTController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyControllerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->bearerToken());
        if ($token === '') {
            return response()->json([
                'error' => 'controller_auth_failed',
                'message' => 'Bearer token is required.',
            ], 401);
        }

        $hash = hash('sha256', $token);
        $controller = IoTController::query()
            ->where('api_token_hash', $hash)
            ->first(['id', 'api_token_hash']);

        if (! $controller || ! hash_equals((string) $controller->api_token_hash, $hash)) {
            return response()->json([
                'error' => 'controller_auth_failed',
                'message' => 'Invalid bearer token.',
            ], 401);
        }

        $request->attributes->set('authenticated_controller_id', (string) $controller->id);

        return $next($request);
    }
}
