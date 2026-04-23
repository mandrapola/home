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
        $yandexUserId = trim((string) $request->header('X-Alice-User-Id', ''));
        if ($yandexUserId === '') {
            return $this->jsonError('alice_user_not_authenticated', 'Missing X-Alice-User-Id header.', 401);
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

