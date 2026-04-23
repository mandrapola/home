<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveAliceUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = trim((string) ($request->bearerToken() ?? ''));
        if ($bearer !== '') {
            $token = DB::table('alice_oauth_access_tokens')
                ->where('token_hash', hash('sha256', $bearer))
                ->whereNull('revoked_at')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first(['id', 'user_id', 'client_id']);

            if (! $token || ! is_numeric($token->user_id)) {
                return $this->jsonError('alice_user_not_authenticated', 'Invalid bearer token.', 401);
            }

            $user = User::query()->find((int) $token->user_id);
            if (! $user) {
                return $this->jsonError('alice_user_not_found', 'Linked user account was not found.', 401);
            }

            DB::table('alice_oauth_access_tokens')
                ->where('id', (int) $token->id)
                ->update([
                    'last_used_at' => now(),
                    'updated_at' => now(),
                ]);

            $request->attributes->set('alice_user', $user);
            $request->attributes->set('alice_access_token_id', (int) $token->id);
            $request->attributes->set('alice_oauth_client_id', (string) ($token->client_id ?? ''));

            return $next($request);
        }

        $yandexUserId = trim((string) $request->header('X-Alice-User-Id', ''));
        if ($yandexUserId === '') {
            return $this->jsonError('alice_user_not_authenticated', 'Missing bearer token or X-Alice-User-Id header.', 401);
        }

        $userId = DB::table('alice_accounts')
            ->where('yandex_user_id', $yandexUserId)
            ->whereNull('unlinked_at')
            ->value('user_id');

        if (! is_numeric($userId)) {
            return $this->jsonError('alice_user_not_linked', 'Alice account is not linked to any user.', 401);
        }

        $user = User::query()->find((int) $userId);
        if (! $user) {
            return $this->jsonError('alice_user_not_found', 'Linked user account was not found.', 401);
        }

        $request->attributes->set('alice_user', $user);
        $request->attributes->set('alice_yandex_user_id', $yandexUserId);

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
