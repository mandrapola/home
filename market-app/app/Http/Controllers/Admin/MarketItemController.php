<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MarketItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MarketItemController extends Controller
{
    public function index(): View
    {
        return view('admin.items.index', [
            'items' => MarketItem::query()->with('category')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.items.form', [
            'item' => new MarketItem(['type' => 'product']),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MarketItem::query()->create($this->validatedData($request));

        return redirect()->route('admin.items.index')->with('status', 'Позиция создана.');
    }

    public function edit(MarketItem $item): View
    {
        return view('admin.items.form', [
            'item' => $item,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, MarketItem $item): RedirectResponse
    {
        $item->update($this->validatedData($request, $item));

        return redirect()->route('admin.items.index')->with('status', 'Позиция обновлена.');
    }

    private function validatedData(Request $request, ?MarketItem $item = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'type' => ['required', 'in:product,service,bundle'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:market_items,slug,'.($item?->id ?? 'NULL')],
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_rub' => ['nullable', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function categories()
    {
        return Category::query()->orderBy('sort_order')->orderBy('name')->get();
    }
}
