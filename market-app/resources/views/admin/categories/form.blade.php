<x-layouts.admin :title="($category->exists ? 'Категория' : 'Новая категория') . ' | Market Admin'">
    <h1 class="mb-6 text-2xl font-semibold">{{ $category->exists ? 'Изменить категорию' : 'Новая категория' }}</h1>

    <form method="post" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="max-w-2xl space-y-5 rounded border border-slate-200 bg-white p-6">
        @csrf
        @if ($category->exists)
            @method('put')
        @endif

        <div>
            <label class="text-sm font-medium" for="name">Название</label>
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="name" name="name" value="{{ old('name', $category->name) }}" required>
            @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="text-sm font-medium" for="slug">Slug</label>
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="slug" name="slug" value="{{ old('slug', $category->slug) }}">
            @error('slug') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="text-sm font-medium" for="description">Описание</label>
            <textarea class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="description" name="description" rows="4">{{ old('description', $category->description) }}</textarea>
        </div>
        <div>
            <label class="text-sm font-medium" for="sort_order">Сортировка</label>
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
            Активна
        </label>
        <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white" type="submit">Сохранить</button>
    </form>
</x-layouts.admin>
