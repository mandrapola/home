<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Inquiry;
use App\Models\MarketItem;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketController extends Controller
{
    public function index(Request $request, TelegramLinkService $telegramLinks): View
    {
        $category = null;
        $categorySlug = $request->string('category')->toString();

        $items = MarketItem::query()
            ->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->when($categorySlug !== '', function ($query) use ($categorySlug, &$category): void {
                $category = Category::query()
                    ->where('slug', $categorySlug)
                    ->where('is_active', true)
                    ->firstOrFail();

                $query->whereBelongsTo($category);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('market.index', [
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'currentCategory' => $category,
            'items' => $items,
            'telegramLinks' => $items->mapWithKeys(fn (MarketItem $item): array => [$item->id => $telegramLinks->itemLink($item)]),
        ]);
    }

    public function show(MarketItem $item, TelegramLinkService $telegramLinks): View
    {
        abort_unless($item->is_active && $item->category?->is_active, 404);

        return view('market.show', [
            'item' => $item->load('category'),
            'telegramLink' => $telegramLinks->itemLink($item),
        ]);
    }

    public function storeInquiry(Request $request, MarketItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:64', 'required_without:email'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        Inquiry::query()->create([
            ...$data,
            'market_item_id' => $item->id,
            'status' => 'new',
        ]);

        return back()->with('status', 'Заявка отправлена. Мы свяжемся с вами.');
    }
}
