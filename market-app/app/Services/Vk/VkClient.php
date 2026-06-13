<?php

namespace App\Services\Vk;

use Illuminate\Support\Facades\Http;

class VkClient
{
    public function sendMessage(int|string $peerId, string $message, array $keyboard = []): ?array
    {
        $token = trim((string) config('vk.access_token'));

        if ($token === '') {
            return null;
        }

        $payload = [
            'access_token' => $token,
            'v' => config('vk.api_version', '5.199'),
            'peer_id' => $peerId,
            'random_id' => random_int(1, PHP_INT_MAX),
            'message' => $message,
        ];

        if ($keyboard !== []) {
            $payload['keyboard'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        }

        return Http::asForm()
            ->post('https://api.vk.com/method/messages.send', $payload)
            ->json();
    }
}
