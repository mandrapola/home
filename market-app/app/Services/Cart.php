<?php

namespace App\Services;

use App\Models\MarketItem;
use Illuminate\Support\Collection;

class Cart
{
    private const SESSION_KEY = 'cart.items';

    public function add(MarketItem $item, int $quantity = 1): void
    {
        $quantity = max(1, min($quantity, 99));
        $items = $this->rawItems();
        $id = (string) $item->id;

        $items[$id] = min(($items[$id] ?? 0) + $quantity, $item->stock_quantity, 99);

        session()->put(self::SESSION_KEY, $items);
    }

    public function update(MarketItem $item, int $quantity): void
    {
        $items = $this->rawItems();
        $id = (string) $item->id;

        if ($quantity < 1) {
            unset($items[$id]);
        } else {
            $items[$id] = min($quantity, 99);
        }

        session()->put(self::SESSION_KEY, $items);
    }

    public function remove(MarketItem $item): void
    {
        $items = $this->rawItems();
        unset($items[(string) $item->id]);

        session()->put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function lines(): Collection
    {
        $items = $this->rawItems();

        if ($items === []) {
            return collect();
        }

        return MarketItem::query()
            ->with('category')
            ->whereIn('id', array_keys($items))
            ->get()
            ->map(function (MarketItem $item) use ($items): array {
                $quantity = (int) $items[(string) $item->id];
                $unitPrice = $item->price_rub;

                return [
                    'item' => $item,
                    'quantity' => $quantity,
                    'unit_price_rub' => $unitPrice,
                    'total_rub' => $unitPrice ? $unitPrice * $quantity : 0,
                ];
            })
            ->sortBy(fn (array $line): string => $line['item']->name)
            ->values();
    }

    public function count(): int
    {
        return array_sum($this->rawItems());
    }

    public function totalRub(): int
    {
        return (int) $this->lines()->sum('total_rub');
    }

    private function rawItems(): array
    {
        return session()->get(self::SESSION_KEY, []);
    }
}
