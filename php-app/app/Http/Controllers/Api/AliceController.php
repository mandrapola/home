<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Alice\AliceSmartHomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AliceController extends Controller
{
    public function __construct(private readonly AliceSmartHomeService $service)
    {
    }

    public function devices(Request $request): JsonResponse
    {
        $user = $this->resolveAliceUser($request);
        return response()->json(
            $this->service->buildDevicesPayload($user, $this->extractRequestId($request))
        );
    }

    public function query(Request $request): JsonResponse
    {
        $user = $this->resolveAliceUser($request);
        $devicesInput = $request->input('payload.devices', $request->input('devices', []));
        if (! is_array($devicesInput)) {
            return response()->json([
                'request_id' => $this->extractRequestId($request),
                'error' => 'invalid_payload',
                'message' => 'payload.devices must be array',
            ], 400);
        }

        $deviceIds = collect($devicesInput)
            ->pluck('id')
            ->filter(fn ($id) => is_string($id) || is_numeric($id))
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        return response()->json(
            $this->service->buildQueryPayload($user, $deviceIds, $this->extractRequestId($request))
        );
    }

    public function action(Request $request): JsonResponse
    {
        $user = $this->resolveAliceUser($request);
        $devices = $request->input('payload.devices', $request->input('devices', []));
        if (! is_array($devices)) {
            return response()->json([
                'request_id' => $this->extractRequestId($request),
                'error' => 'invalid_payload',
                'message' => 'payload.devices must be array',
            ], 400);
        }

        return response()->json(
            $this->service->buildActionPayload($user, $devices, $this->extractRequestId($request))
        );
    }

    public function unlink(Request $request): JsonResponse
    {
        $user = $this->resolveAliceUser($request);
        $accessTokenId = $request->attributes->get('alice_access_token_id');
        if (is_numeric($accessTokenId)) {
            DB::table('alice_oauth_access_tokens')
                ->where('id', (int) $accessTokenId)
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $yandexUserId = (string) $request->attributes->get('alice_yandex_user_id', '');
        if ($yandexUserId !== '') {
            $this->service->unlink($user, $yandexUserId);
        }

        return response()->json([
            'request_id' => $this->extractRequestId($request),
            'payload' => new \stdClass(),
        ]);
    }

    private function resolveAliceUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->attributes->get('alice_user');
        return $user;
    }

    private function extractRequestId(Request $request): ?string
    {
        $value = $request->input('request_id');
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $header = $request->header('X-Request-Id');
        return is_string($header) && trim($header) !== '' ? trim($header) : null;
    }
}
