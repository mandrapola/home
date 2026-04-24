<?php

declare(strict_types=1);

namespace App\Services\Alice;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AliceStateNotificationService
{
    /**
     * @param array<string,bool> $changedPowerStatesByPinId
     */
    public function notifyPowerStateChanges(string $controllerId, array $changedPowerStatesByPinId): void
    {
        if ($changedPowerStatesByPinId === []) {
            return;
        }

        if (! $this->alertsEnabled()) {
            return;
        }

        $skillId = trim((string) config('services.alice.skill_id', ''));
        $dialogsToken = trim((string) config('services.alice.dialogs_oauth_token', ''));
        if ($skillId === '' || $dialogsToken === '') {
            return;
        }

        $pinIds = array_map(static fn (string $id): string => (string) $id, array_keys($changedPowerStatesByPinId));
        $devices = [];
        foreach ($pinIds as $pinId) {
            $devices[] = [
                'id' => $pinId,
                'status' => 'online',
                'capabilities' => [[
                    'type' => 'devices.capabilities.on_off',
                    'state' => [
                        'instance' => 'on',
                        'value' => (bool) ($changedPowerStatesByPinId[$pinId] ?? false),
                    ],
                ]],
            ];
        }

        if ($devices === []) {
            return;
        }

        $userIds = DB::table('controller_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->join('alice_accounts as aa', function ($join): void {
                $join->on('aa.user_id', '=', 'cu.user_id')
                    ->whereNull('aa.unlinked_at');
            })
            ->where('cu.controller_id', $controllerId)
            ->where('u.alice_enabled', 1)
            ->distinct()
            ->pluck('cu.user_id')
            ->all();

        if ($userIds === []) {
            return;
        }

        $endpoint = sprintf(
            'https://dialogs.yandex.net/api/v1/skills/%s/callback/state',
            rawurlencode($skillId)
        );

        foreach ($userIds as $userId) {
            try {
                $response = Http::asJson()
                    ->timeout(5)
                    ->withHeaders([
                        'Authorization' => 'OAuth ' . $dialogsToken,
                    ])
                    ->post($endpoint, [
                        'ts' => microtime(true),
                        'payload' => [
                            'user_id' => (string) $userId,
                            'devices' => $devices,
                        ],
                    ]);

                if (! $response->successful()) {
                    Log::warning('alice_state_notify_failed', [
                        'controller_id' => $controllerId,
                        'user_id' => (string) $userId,
                        'status' => $response->status(),
                        'response' => mb_substr($response->body(), 0, 500),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('alice_state_notify_exception', [
                    'controller_id' => $controllerId,
                    'user_id' => (string) $userId,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function alertsEnabled(): bool
    {
        return (bool) config('services.alice.enabled', false)
            && (bool) config('services.alice.alerts_enabled', false);
    }
}

