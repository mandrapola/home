<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkConversation;
use App\Services\Vk\VkClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VkConversationController extends Controller
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

    public function index(): View
    {
        return view('admin.vk.index', [
            'conversations' => VkConversation::query()
                ->with(['item', 'order'])
                ->withCount('messages')
                ->latest()
                ->get(),
        ]);
    }

    public function show(VkConversation $conversation): View
    {
        $conversation->load([
            'item',
            'order.items',
            'messages' => fn ($query) => $query->oldest(),
        ]);

        return view('admin.vk.show', [
            'conversation' => $conversation,
            'context' => $this->context($conversation),
            'intentLabel' => $conversation->intent ? (self::INTENTS[$conversation->intent] ?? $conversation->intent) : null,
        ]);
    }

    public function reply(Request $request, VkConversation $conversation, VkClient $vk): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $message = trim($validated['message']);

        if ($message === '') {
            return back()
                ->withInput()
                ->withErrors(['message' => 'Введите текст ответа.']);
        }

        $response = $vk->sendMessage($conversation->vk_user_id, $message);

        if (! isset($response['response'])) {
            return back()
                ->withInput()
                ->withErrors(['message' => 'VK не принял сообщение. Проверьте токен сообщества и права messages.']);
        }

        $conversation->messages()->create([
            'direction' => 'admin',
            'body' => $message,
            'vk_message_id' => $response['response'],
            'payload' => $response,
        ]);

        return redirect()
            ->route('admin.vk.show', $conversation)
            ->with('status', 'Ответ отправлен во VK.');
    }

    private function context(VkConversation $conversation): array
    {
        if ($conversation->context_type === 'item') {
            return [
                'title' => 'Заявка на товар',
                'name' => $conversation->item?->name ?? ($conversation->context_payload['item_name'] ?? 'Товар не найден'),
                'url' => $conversation->item ? route('market.items.show', $conversation->item) : null,
                'rows' => [
                    'Тип' => $conversation->item ? ($conversation->item->type === 'service' ? 'Услуга' : 'Товар') : '—',
                    'Цена' => $this->money($conversation->item?->price_rub),
                    'Остаток' => $conversation->item?->stock_quantity !== null ? (string) $conversation->item->stock_quantity : '—',
                    'Артикул/slug' => $conversation->context_token ?: '—',
                ],
                'items' => [],
            ];
        }

        if ($conversation->context_type === 'cart') {
            $items = collect($conversation->context_payload['items'] ?? [])
                ->map(fn (array $item): array => [
                    'name' => (string) ($item['name'] ?? 'Позиция'),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'unit_price' => $this->money($item['unit_price_rub'] ?? null),
                    'total' => $this->money($item['total_rub'] ?? null),
                ])
                ->all();

            return [
                'title' => 'Заявка по корзине',
                'name' => count($items).' поз.',
                'url' => null,
                'rows' => [
                    'Итого' => $this->money($conversation->context_payload['total_rub'] ?? null),
                    'Токен' => $conversation->context_token ?: '—',
                ],
                'items' => $items,
            ];
        }

        return [
            'title' => 'Заявка по заказу',
            'name' => $conversation->order?->number ?? ($conversation->context_payload['order_number'] ?? 'Заказ не найден'),
            'url' => $conversation->order ? route('admin.orders.show', $conversation->order) : null,
            'rows' => [
                'Статус заказа' => $conversation->order?->status ?? '—',
                'Итого' => $this->money($conversation->order?->total_rub ?? ($conversation->context_payload['total_rub'] ?? null)),
                'Клиент' => $conversation->order?->customer_name ?? '—',
                'Email' => $conversation->order?->customer_email ?: '—',
            ],
            'items' => $conversation->order?->items
                ->map(fn ($item): array => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $this->money($item->unit_price_rub),
                    'total' => $this->money($item->total_rub),
                ])
                ->all() ?? [],
        ];
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'По запросу';
        }

        return number_format((int) $value, 0, ',', ' ').' ₽';
    }
}
