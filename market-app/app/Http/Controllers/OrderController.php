<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Cart;
use App\Services\Telegram\TelegramLinkService;
use App\Services\Vk\VkLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function checkout(Cart $cart): View|RedirectResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.show')->with('status', 'Корзина пуста.');
        }

        return view('orders.checkout', [
            'lines' => $lines,
            'totalRub' => $cart->totalRub(),
        ]);
    }

    public function store(Request $request, Cart $cart): RedirectResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.show')->with('status', 'Корзина пуста.');
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255', 'required_without:customer_phone'],
            'customer_phone' => ['nullable', 'string', 'max:64', 'required_without:customer_email'],
            'comment' => ['nullable', 'string', 'max:3000'],
        ]);

        $order = DB::transaction(function () use ($data, $lines, $cart): Order {
            $order = Order::query()->create([
                ...$data,
                'number' => $this->nextNumber(),
                'status' => 'new',
                'total_rub' => $cart->totalRub(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'market_item_id' => $line['item']->id,
                    'name' => $line['item']->name,
                    'quantity' => $line['quantity'],
                    'unit_price_rub' => $line['unit_price_rub'],
                    'total_rub' => $line['total_rub'],
                ]);
            }

            return $order;
        });

        $cart->clear();

        return redirect()->route('orders.thanks', $order)->with('status', 'Заказ отправлен.');
    }

    public function thanks(Order $order, TelegramLinkService $telegramLinks, VkLinkService $vkLinks): View
    {
        return view('orders.thanks', [
            'order' => $order,
            'telegramLink' => $telegramLinks->orderLink($order),
            'vkLink' => $vkLinks->orderLink($order),
        ]);
    }

    private function nextNumber(): string
    {
        return 'M'.now()->format('Ymd').'-'.str_pad((string) (Order::query()->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
