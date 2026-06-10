<x-layouts.admin title="Категории | Market Admin">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Категории</h1>
        <a class="rounded bg-slate-950 px-4 py-2 text-sm font-medium text-white" href="{{ route('admin.categories.create') }}">Добавить</a>
    </div>

    <div class="overflow-hidden rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Название</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Статус</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach ($categories as $category)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $category->slug }}</td>
                        <td class="px-4 py-3">{{ $category->is_active ? 'Активна' : 'Скрыта' }}</td>
                        <td class="px-4 py-3 text-right"><a class="text-emerald-700" href="{{ route('admin.categories.edit', $category) }}">Изменить</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
