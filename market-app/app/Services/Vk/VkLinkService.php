<?php

namespace App\Services\Vk;

use App\Models\MarketItem;
use App\Models\Order;
use App\Models\VkRequestContext;
use App\Services\Cart;
use Illuminate\Support\Str;

class VkLinkService
{
    public function enabled(): bool
    {
        return config('vk.enabled') === true && $this->screenName() !== null;
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

        $context = VkRequestContext::query()->create([
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

        return 'https://vk.me/'.$this->screenName().'?ref='.rawurlencode($payload);
    }

    private function screenName(): ?string
    {
        $screenName = trim((string) config('vk.group_screen_name'));

        return $screenName === '' ? null : ltrim($screenName, '@');
    }
}
