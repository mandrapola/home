<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use App\Models\Order;
use App\Models\TelegramConversation;
use App\Models\TelegramMessage;
use App\Models\TelegramRequestContext;
use App\Services\Telegram\TelegramClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramWebhookController extends Controller
{
    private const INTENTS = [
        'availability' => 'Узнать наличие и доставку',
        'preorder' => 'Сделать предзаказ',
        'consultation' => 'Получить консультацию',
        'compatibility' => 'Уточнить совместимость',
        'setup' => 'Запросить установку/настройку',
        'custom' => 'Написать свой вопрос',
        'cart_total' => 'Уточнить итоговую стоимость',
        'cart_delivery' => 'Узнать доставку',
        'cart_order' => 'Оформить заказ',
    ];

    public function __invoke(Request $request, string $secret, TelegramClient $telegram): JsonResponse
    {
        abort_unless(hash_equals((string) config('telegram.webhook_secret'), $secret), Response::HTTP_NOT_FOUND);

        $update = $request->all();

        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query'], $telegram);
        } elseif (isset($update['message'])) {
            $this->handleMessage($update['message'], $telegram);
        }

        return response()->json(['ok' => true]);
    }

    private function handleMessage(array $message, TelegramClient $telegram): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId === null || $text === '') {
            return;
        }

        if ($this->isAdminChat($chatId)) {
            $this->handleAdminReply($message, $telegram);

            return;
        }

        if (str_starts_with($text, '/start')) {
            $payload = trim(substr($text, 6));
            $this->startConversation($message, $payload, $telegram);

            return;
        }

        $conversation = TelegramConversation::query()
            ->where('telegram_user_id', $chatId)
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $conversation) {
            $telegram->sendMessage($chatId, 'Нажмите Start по ссылке с сайта, чтобы создать заявку.');

            return;
        }

        $conversation->messages()->create([
            'direction' => 'user',
            'body' => $text,
            'telegram_message_id' => $message['message_id'] ?? null,
            'payload' => $message,
        ]);

        $adminMessageId = $this->notifyAdmin($conversation, $telegram, "Сообщение пользователя:\n".$text);

        if ($adminMessageId !== null) {
            $conversation->messages()->latest()->first()?->update(['admin_message_id' => $adminMessageId]);
        }

        $telegram->sendMessage($chatId, 'Спасибо. Передали сообщение поддержке, ответ придет сюда.');
    }

    private function startConversation(array $message, string $payload, TelegramClient $telegram): void
    {
        $chat = $message['chat'];
        $from = $message['from'] ?? $chat;
        $context = $this->resolveContext($payload);

        if ($context === null) {
            $telegram->sendMessage($chat['id'], 'Не удалось найти товар или заявку. Откройте ссылку с сайта еще раз.');

            return;
        }

        $conversation = TelegramConversation::query()->create([
            'market_item_id' => $context['item']?->id,
            'order_id' => $context['order']?->id,
            'context_type' => $context['type'],
            'context_token' => $context['token'],
            'telegram_user_id' => $chat['id'],
            'telegram_username' => $from['username'] ?? null,
            'telegram_first_name' => $from['first_name'] ?? null,
            'context_payload' => $context['payload'],
        ]);

        $conversation->messages()->create([
            'direction' => 'user',
            'body' => '/start '.$payload,
            'telegram_message_id' => $message['message_id'] ?? null,
            'payload' => $message,
        ]);

        $telegram->sendMessage(
            $chat['id'],
            $this->startText($conversation),
            $this->intentKeyboard($conversation),
        );
    }

    private function handleCallback(array $callback, TelegramClient $telegram): void
    {
        $data = (string) ($callback['data'] ?? '');
        $parts = explode(':', $data);

        if (count($parts) !== 3 || $parts[0] !== 'intent') {
            return;
        }

        $conversation = TelegramConversation::query()->find((int) $parts[1]);
        $intent = $parts[2];

        if (! $conversation || ! isset(self::INTENTS[$intent])) {
            return;
        }

        $conversation->update(['intent' => $intent]);
        $telegram->answerCallbackQuery((string) $callback['id'], 'Заявка принята');

        if ($intent === 'custom') {
            $telegram->sendMessage($conversation->telegram_user_id, 'Напишите вопрос одним сообщением. Мы ответим здесь.');

            return;
        }

        $adminMessageId = $this->notifyAdmin($conversation, $telegram, 'Выбрано действие: '.self::INTENTS[$intent]);
        $conversation->update(['admin_message_id' => $adminMessageId]);

        $telegram->sendMessage($conversation->telegram_user_id, 'Спасибо. Заявка передана поддержке, ответ придет сюда.');
    }

    private function handleAdminReply(array $message, TelegramClient $telegram): void
    {
        $replyToMessageId = $message['reply_to_message']['message_id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        if ($replyToMessageId === null || $text === '') {
            return;
        }

        $conversation = TelegramConversation::query()
            ->where('admin_message_id', $replyToMessageId)
            ->orWhereHas('messages', fn ($query) => $query->where('admin_message_id', $replyToMessageId))
            ->latest()
            ->first();

        if (! $conversation) {
            return;
        }

        $telegram->sendMessage($conversation->telegram_user_id, $text);

        $conversation->messages()->create([
            'direction' => 'admin',
            'body' => $text,
            'admin_message_id' => $message['message_id'] ?? null,
            'payload' => $message,
        ]);
    }

    private function resolveContext(string $payload): ?array
    {
        if (str_starts_with($payload, 'item_')) {
            $slug = substr($payload, 5);
            $item = MarketItem::query()->where('slug', $slug)->where('is_active', true)->first();

            return $item ? [
                'type' => 'item',
                'token' => $slug,
                'item' => $item,
                'order' => null,
                'payload' => ['item_name' => $item->name, 'item_slug' => $item->slug],
            ] : null;
        }

        if (str_starts_with($payload, 'cart_')) {
            $token = substr($payload, 5);
            $context = TelegramRequestContext::query()
                ->where('token', $token)
                ->where('type', 'cart')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->first();

            return $context ? [
                'type' => 'cart',
                'token' => $token,
                'item' => null,
                'order' => null,
                'payload' => $context->payload,
            ] : null;
        }

        if (str_starts_with($payload, 'order_')) {
            $number = substr($payload, 6);
            $order = Order::query()->where('number', $number)->first();

            return $order ? [
                'type' => 'order',
                'token' => $number,
                'item' => null,
                'order' => $order,
                'payload' => ['order_number' => $order->number, 'total_rub' => $order->total_rub],
            ] : null;
        }

        return null;
    }

    private function startText(TelegramConversation $conversation): string
    {
        if ($conversation->context_type === 'item') {
            return "Заявка на товар:\n".$conversation->item?->name."\n\nЧто хотите сделать?";
        }

        if ($conversation->context_type === 'cart') {
            $items = collect($conversation->context_payload['items'] ?? [])
                ->map(fn (array $item): string => $item['name'].' — '.$item['quantity'].' шт.')
                ->implode("\n");

            return "Заявка по корзине:\n".$items."\n\nЧто хотите сделать?";
        }

        return "Заявка по заказу ".$conversation->order?->number."\n\nЧто хотите сделать?";
    }

    private function intentKeyboard(TelegramConversation $conversation): array
    {
        $keys = $conversation->context_type === 'cart'
            ? ['cart_total', 'cart_delivery', 'cart_order', 'consultation', 'custom']
            : ['availability', 'preorder', 'consultation', 'compatibility', 'setup', 'custom'];

        return [
            'inline_keyboard' => collect($keys)
                ->map(fn (string $key): array => [[
                    'text' => self::INTENTS[$key],
                    'callback_data' => 'intent:'.$conversation->id.':'.$key,
                ]])
                ->all(),
        ];
    }

    private function notifyAdmin(TelegramConversation $conversation, TelegramClient $telegram, string $line): ?int
    {
        $adminChatId = config('telegram.admin_chat_id');

        if (! $adminChatId) {
            return null;
        }

        $response = $telegram->sendMessage($adminChatId, $this->adminText($conversation, $line));

        return $response['result']['message_id'] ?? null;
    }

    private function adminText(TelegramConversation $conversation, string $line): string
    {
        $user = $conversation->telegram_username
            ? '@'.$conversation->telegram_username
            : 'Telegram ID '.$conversation->telegram_user_id;

        return implode("\n", array_filter([
            'Новая заявка из маркета',
            'Контекст: '.$this->contextTitle($conversation),
            'Пользователь: '.$user,
            $line,
            '',
            'Ответьте reply на это сообщение, чтобы написать пользователю.',
        ]));
    }

    private function contextTitle(TelegramConversation $conversation): string
    {
        return match ($conversation->context_type) {
            'item' => 'товар '.$conversation->item?->name,
            'cart' => 'корзина',
            'order' => 'заказ '.$conversation->order?->number,
            default => $conversation->context_type,
        };
    }

    private function isAdminChat(int|string $chatId): bool
    {
        return (string) $chatId === (string) config('telegram.admin_chat_id');
    }
}
