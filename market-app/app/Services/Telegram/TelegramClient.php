<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;

class TelegramClient
{
    public function sendMessage(int|string $chatId, string $text, array $replyMarkup = [], ?int $replyToMessageId = null): ?array
    {
        $token = trim((string) config('telegram.bot_token'));

        if ($token === '') {
            return null;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup !== []) {
            $payload['reply_markup'] = $replyMarkup;
        }

        if ($replyToMessageId !== null) {
            $payload['reply_to_message_id'] = $replyToMessageId;
        }

        return Http::asJson()
            ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload)
            ->json();
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        $token = trim((string) config('telegram.bot_token'));

        if ($token === '') {
            return;
        }

        Http::asJson()->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
        ]);
    }
}
