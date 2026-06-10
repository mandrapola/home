<x-layouts.admin :title="($item->exists ? 'Позиция' : 'Новая позиция') . ' | Market Admin'">
    <h1 class="mb-6 text-2xl font-semibold">{{ $item->exists ? 'Изменить позицию' : 'Новая позиция' }}</h1>

    <form method="post" action="{{ $item->exists ? route('admin.items.update', $item) : route('admin.items.store') }}" class="max-w-3xl space-y-5 rounded border border-slate-200 bg-white p-6">
        @csrf
        @if ($item->exists)
            @method('put')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium" for="category_id">Категория</label>
                <select class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="category_id" name="category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $item->category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium" for="type">Тип</label>
                <select class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="type" name="type">
                    <option value="product" @selected(old('type', $item->type) === 'product')>Товар</option>
                    <option value="service" @selected(old('type', $item->type) === 'service')>Услуга</option>
                    <option value="bundle" @selected(old('type', $item->type) === 'bundle')>Комплект</option>
                </select>
            </div>
        </div>
        <div>
            <label class="text-sm font-medium" for="name">Название</label>
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="name" name="name" value="{{ old('name', $item->name) }}" required>
            @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="text-sm font-medium" for="slug">Slug</label>
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="slug" name="slug" value="{{ old('slug', $item->slug) }}">
            @error('slug') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="text-sm font-medium" for="summary">Краткое описание</label>
            <textarea class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="summary" name="summary" rows="3" required>{{ old('summary', $item->summary) }}</textarea>
        </div>
        <div>
            <label class="text-sm font-medium" for="description">Полное описание</label>
            <textarea class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="description" name="description" rows="6">{{ old('description', $item->description) }}</textarea>
        </div>
        <div class="grid gap-5 md:grid-cols-4">
            <div>
                <label class="text-sm font-medium" for="price_rub">Цена, ₽</label>
                <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="price_rub" name="price_rub" type="number" min="0" value="{{ old('price_rub', $item->price_rub) }}">
            </div>
            <div>
                <label class="text-sm font-medium" for="stock_quantity">Доступно, шт.</label>
                <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="stock_quantity" name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', $item->stock_quantity ?? 0) }}" required>
                @error('stock_quantity') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm font-medium" for="sort_order">Сортировка</label>
                <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
            </div>
            <label class="flex items-end gap-2 pb-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
                Активна
            </label>
        </div>
        <div>
            <label class="text-sm font-medium" for="image_url">URL изображения</label>
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="image_url" name="image_url" value="{{ old('image_url', $item->image_url) }}">
        </div>
        <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white" type="submit">Сохранить</button>
    </form>
</x-layouts.admin>
