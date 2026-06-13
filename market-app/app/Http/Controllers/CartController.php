<?php

namespace App\Http\Controllers;

use App\Models\MarketItem;
use App\Services\Cart;
use App\Services\Telegram\TelegramLinkService;
use App\Services\Vk\VkLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Cart $cart, TelegramLinkService $telegramLinks, VkLinkService $vkLinks): View
    {
        return view('cart.show', [
            'lines' => $cart->lines(),
            'totalRub' => $cart->totalRub(),
            'telegramLink' => $telegramLinks->cartLink($cart),
            'vkLink' => $vkLinks->cartLink($cart),
        ]);
    }

    public function add(Request $request, MarketItem $item, Cart $cart): RedirectResponse
    {
        abort_unless($item->is_active, 404);
        abort_if($item->stock_quantity < 1, 422, 'Позиции нет в наличии.');

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $quantity = (int) ($data['quantity'] ?? 1);

        if ($quantity > $item->stock_quantity) {
            return back()->withErrors([
                'quantity' => 'Доступно только '.$item->stock_quantity.' шт.',
            ]);
        }

        $cart->add($item, $quantity);

        return back()->with('status', 'Позиция добавлена в корзину.');
    }

    public function update(Request $request, MarketItem $item, Cart $cart): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        if ((int) $data['quantity'] > $item->stock_quantity) {
            return back()->withErrors([
                'quantity' => 'Доступно только '.$item->stock_quantity.' шт.',
            ]);
        }

        $cart->update($item, (int) $data['quantity']);

        return back()->with('status', 'Корзина обновлена.');
    }

    public function remove(MarketItem $item, Cart $cart): RedirectResponse
    {
        $cart->remove($item);

        return back()->with('status', 'Позиция удалена из корзины.');
    }

    public function clear(Cart $cart): RedirectResponse
    {
        $cart->clear();

        return redirect()->route('cart.show')->with('status', 'Корзина очищена.');
    }
}
