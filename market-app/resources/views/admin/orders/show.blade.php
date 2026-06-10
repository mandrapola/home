<x-layouts.admin :title="$order->number . ' | Market Admin'">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-slate-600 hover:text-slate-950">Назад к заказам</a>
            <h1 class="mt-2 text-2xl font-semibold">Заказ {{ $order->number }}</h1>
        </div>
        <form method="post" action="{{ route('admin.orders.update', $order) }}" class="flex items-center gap-2">
            @csrf
            @method('patch')
            <select class="rounded border border-slate-300 px-3 py-2 text-sm" name="status">
                <option value="new" @selected($order->status === 'new')>Новый</option>
                <option value="contacted" @selected($order->status === 'contacted')>Связались</option>
                <option value="processing" @selected($order->status === 'processing')>В работе</option>
                <option value="done" @selected($order->status === 'done')>Готово</option>
                <option value="canceled" @selected($order->status === 'canceled')>Отменен</option>
            </select>
            <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white" type="submit">Сохранить</button>
        </form>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="overflow-hidden rounded border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Позиция</th>
                        <th class="px-4 py-3">Цена</th>
                        <th class="px-4 py-3">Количество</th>
                        <th class="px-4 py-3">Сумма</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $item->name }}</td>
                            <td class="px-4 py-3">{{ $item->unit_price_rub ? number_format($item->unit_price_rub, 0, ',', ' ') . ' ₽' : 'По запросу' }}</td>
                            <td class="px-4 py-3">{{ $item->quantity }}</td>
                            <td class="px-4 py-3">{{ number_format($item->total_rub, 0, ',', ' ') }} ₽</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <aside class="space-y-6">
            <section class="rounded border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Клиент</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-slate-500">Имя</dt><dd>{{ $order->customer_name }}</dd></div>
                    <div><dt class="text-slate-500">Email</dt><dd>{{ $order->customer_email ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Телефон</dt><dd>{{ $order->customer_phone ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Сумма</dt><dd class="font-semibold">{{ number_format($order->total_rub, 0, ',', ' ') }} ₽</dd></div>
                </dl>
            </section>
            @if ($order->comment)
                <section class="rounded border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold">Комментарий</h2>
                    <div class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $order->comment }}</div>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.admin>
