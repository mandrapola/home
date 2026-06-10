<x-layouts.admin title="Заказы | Market Admin">
    <h1 class="mb-6 text-2xl font-semibold">Заказы</h1>

    <div class="overflow-hidden rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Номер</th>
                    <th class="px-4 py-3">Клиент</th>
                    <th class="px-4 py-3">Позиций</th>
                    <th class="px-4 py-3">Сумма</th>
                    <th class="px-4 py-3">Статус</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $order->number }}</td>
                        <td class="px-4 py-3">
                            <div>{{ $order->customer_name }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $order->created_at->format('d.m.Y H:i') }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $order->items_count }}</td>
                        <td class="px-4 py-3">{{ number_format($order->total_rub, 0, ',', ' ') }} ₽</td>
                        <td class="px-4 py-3">{{ $order->status }}</td>
                        <td class="px-4 py-3 text-right"><a class="text-emerald-700" href="{{ route('admin.orders.show', $order) }}">Открыть</a></td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-slate-600" colspan="6">Заказов пока нет.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
