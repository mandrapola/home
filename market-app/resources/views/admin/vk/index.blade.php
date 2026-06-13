<x-layouts.admin title="VK-заявки | Market Admin">
    <h1 class="mb-6 text-2xl font-semibold">VK-заявки</h1>

    <div class="overflow-hidden rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Пользователь</th>
                    <th class="px-4 py-3">Контекст</th>
                    <th class="px-4 py-3">Действие</th>
                    <th class="px-4 py-3">Сообщений</th>
                    <th class="px-4 py-3">Статус</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($conversations as $conversation)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium">VK ID {{ $conversation->vk_user_id }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $conversation->created_at->format('d.m.Y H:i') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($conversation->context_type === 'item')
                                {{ $conversation->item?->name ?? 'Товар удален' }}
                            @elseif ($conversation->context_type === 'order')
                                Заказ {{ $conversation->order?->number }}
                            @else
                                Корзина
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $conversation->intent ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $conversation->messages_count }}</td>
                        <td class="px-4 py-3">{{ $conversation->status }}</td>
                        <td class="px-4 py-3 text-right"><a class="text-emerald-700" href="{{ route('admin.vk.show', $conversation) }}">Открыть</a></td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-slate-600" colspan="6">VK-заявок пока нет.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
