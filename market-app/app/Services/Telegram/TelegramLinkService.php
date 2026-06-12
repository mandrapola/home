<?php

namespace App\Services\Telegram;

use App\Models\MarketItem;
use App\Models\Order;
use App\Models\TelegramRequestContext;
use App\Services\Cart;
use Illuminate\Support\Str;

class TelegramLinkService
{
    public function enabled(): bool
    {
        return config('telegram.enabled') === true && $this->botUsername() !== null;
    }

    public function itemLink(MarketItem $item): ?string
    {
        return $this->startLink('item_'.$item->slug);
    }

    public function cartLink(Cart $cart): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return null;
        }

        $context = TelegramRequestContext::query()->create([
            'token' => Str::random(24),
            'type' => 'cart',
            'payload' => [
                'items' => $lines->map(fn (array $line): array => [
                    'id' => $line['item']->id,
                    'name' => $line['item']->name,
                    'slug' => $line['item']->slug,
                    'quantity' => $line['quantity'],
                    'unit_price_rub' => $line['unit_price_rub'],
                    'total_rub' => $line['total_rub'],
                ])->values()->all(),
                'total_rub' => $cart->totalRub(),
            ],
            'expires_at' => now()->addDays(7),
        ]);

        return $this->startLink('cart_'.$context->token);
    }

    public function orderLink(Order $order): ?string
    {
        return $this->startLink('order_'.$order->number);
    }

    public function startLink(string $payload): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $username = $this->botUsername();

        if ($username === null) {
            return null;
        }

        return 'https://t.me/'.$username.'?start='.rawurlencode($payload);
    }

    private function botUsername(): ?string
    {
        $username = trim((string) config('telegram.bot_username'));

        return $username === '' ? null : ltrim($username, '@');
    }
}
