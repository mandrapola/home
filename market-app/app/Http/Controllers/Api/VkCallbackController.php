<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use App\Models\Order;
use App\Models\VkConversation;
use App\Models\VkRequestContext;
use App\Services\Vk\VkClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VkCallbackController extends Controller
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

    public function __invoke(Request $request, VkClient $vk): Response
    {
        $payload = $request->all();

        if (($payload['type'] ?? null) === 'confirmation') {
            return response((string) config('vk.confirmation_code'));
        }

        $secret = (string) config('vk.secret');

        if ($secret !== '' && ! hash_equals($secret, (string) ($payload['secret'] ?? ''))) {
            abort(404);
        }

        if (($payload['type'] ?? null) === 'message_new') {
            $this->handleMessage($payload['object']['message'] ?? [], $vk);
        }

        return response('ok');
    }

    private function handleMessage(array $message, VkClient $vk): void
    {
        $peerId = $message['peer_id'] ?? null;
        $userId = $message['from_id'] ?? null;

        if ($peerId === null || $userId === null) {
            return;
        }

        $buttonPayload = $this->decodePayload($message['payload'] ?? null);

        if (($buttonPayload['type'] ?? null) === 'intent') {
            $this->handleIntent($message, $buttonPayload, $vk);

            return;
        }

        $contextPayload = $message['ref'] ?? $buttonPayload['start'] ?? null;

        if (is_string($contextPayload) && $contextPayload !== '') {
            $this->startConversation($message, $contextPayload, $vk);

            return;
        }

        $conversation = VkConversation::query()
            ->where('vk_user_id', $userId)
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $conversation) {
            $vk->sendMessage($peerId, 'Откройте заявку по кнопке с сайта, чтобы мы поняли, какой товар вас интересует.');

            return;
        }

        $conversation->messages()->create([
            'direction' => 'user',
            'body' => trim((string) ($message['text'] ?? '')),
            'vk_message_id' => $message['id'] ?? null,
            'payload' => $message,
        ]);

        $vk->sendMessage($peerId, 'Спасибо. Сообщение сохранено, поддержка ответит здесь в диалоге VK.');
    }

    private function startConversation(array $message, string $payload, VkClient $vk): void
    {
        $context = $this->resolveContext($payload);
        $peerId = $message['peer_id'];
        $userId = $message['from_id'];

        if ($context === null) {
            $vk->sendMessage($peerId, 'Не удалось найти товар или заявку. Откройте ссылку с сайта еще раз.');

            return;
        }

        $conversation = VkConversation::query()->create([
            'market_item_id' => $context['item']?->id,
            'order_id' => $context['order']?->id,
            'context_type' => $context['type'],
            'context_token' => $context['token'],
            'vk_user_id' => $userId,
            'context_payload' => $context['payload'],
        ]);

        $conversation->messages()->create([
            'direction' => 'user',
            'body' => trim((string) ($message['text'] ?? '')),
            'vk_message_id' => $message['id'] ?? null,
            'payload' => $message,
        ]);

        $vk->sendMessage($peerId, $this->startText($conversation), $this->intentKeyboard($conversation));
    }

    private function handleIntent(array $message, array $payload, VkClient $vk): void
    {
        $conversation = VkConversation::query()->find((int) ($payload['conversation_id'] ?? 0));
        $intent = (string) ($payload['intent'] ?? '');

        if (! $conversation || ! isset(self::INTENTS[$intent])) {
            return;
        }

        $conversation->update(['intent' => $intent]);
        $conversation->messages()->create([
            'direction' => 'user',
            'body' => self::INTENTS[$intent],
            'vk_message_id' => $message['id'] ?? null,
            'payload' => $message,
        ]);

        $vk->sendMessage(
            $message['peer_id'],
            $intent === 'custom'
                ? 'Напишите вопрос одним сообщением. Поддержка ответит здесь в диалоге VK.'
                : 'Спасибо. Заявка сохранена, поддержка ответит здесь в диалоге VK.',
        );
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
            $context = VkRequestContext::query()
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

    private function startText(VkConversation $conversation): string
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

    private function intentKeyboard(VkConversation $conversation): array
    {
        $keys = $conversation->context_type === 'cart'
            ? ['cart_total', 'cart_delivery', 'cart_order', 'consultation', 'custom']
            : ['availability', 'preorder', 'consultation', 'compatibility', 'setup', 'custom'];

        return [
            'one_time' => false,
            'inline' => true,
            'buttons' => collect($keys)
                ->map(fn (string $key): array => [[
                    'action' => [
                        'type' => 'text',
                        'label' => self::INTENTS[$key],
                        'payload' => json_encode([
                            'type' => 'intent',
                            'conversation_id' => $conversation->id,
                            'intent' => $key,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                    'color' => 'primary',
                ]])
                ->all(),
        ];
    }

    private function decodePayload(mixed $payload): array
    {
        if (! is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
