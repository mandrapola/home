<x-layouts.market title="Корзина | AiDvor Market">
    <section class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="text-3xl font-semibold">Корзина</h1>

        @if (session('status'))
            <div class="mt-5 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @if ($lines->isEmpty())
            <div class="mt-8 rounded border border-slate-200 bg-white p-6 text-slate-600">Корзина пуста.</div>
            <a class="mt-5 inline-flex rounded bg-slate-950 px-4 py-2 text-sm font-medium text-white" href="{{ route('market.index') }}">Перейти в каталог</a>
        @else
            <div class="mt-8 overflow-hidden rounded border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Позиция</th>
                            <th class="px-4 py-3">Цена</th>
                            <th class="px-4 py-3">Количество</th>
                            <th class="px-4 py-3">Сумма</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($lines as $line)
                            <tr>
                                <td class="px-4 py-3">
                                    <a class="font-medium hover:text-emerald-700" href="{{ route('market.items.show', $line['item']) }}">{{ $line['item']->name }}</a>
                                    <div class="mt-1 text-xs text-slate-500">{{ $line['item']->category?->name }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $line['unit_price_rub'] ? number_format($line['unit_price_rub'], 0, ',', ' ') . ' ₽' : 'По запросу' }}</td>
                                <td class="px-4 py-3">
                                    <form method="post" action="{{ route('cart.items.update', $line['item']) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('patch')
                                        <input class="w-20 rounded border border-slate-300 px-3 py-2" name="quantity" type="number" min="0" max="99" value="{{ $line['quantity'] }}">
                                        <button class="rounded border border-slate-300 px-3 py-2 text-xs" type="submit">Обновить</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $line['total_rub'] ? number_format($line['total_rub'], 0, ',', ' ') . ' ₽' : 'По запросу' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <form method="post" action="{{ route('cart.items.remove', $line['item']) }}">
                                        @csrf
                                        @method('delete')
                                        <button class="text-red-700" type="submit">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                <form method="post" action="{{ route('cart.clear') }}">
                    @csrf
                    @method('delete')
                    <button class="rounded border border-slate-300 px-4 py-2 text-sm" type="submit">Очистить корзину</button>
                </form>
                <div class="flex items-center gap-5">
                    <div class="text-xl font-semibold">Итого: {{ number_format($totalRub, 0, ',', ' ') }} ₽</div>
                    @if ($telegramLink)
                        <a class="rounded border border-emerald-700 px-5 py-3 text-sm font-semibold text-emerald-800" href="{{ $telegramLink }}" target="_blank" rel="noopener">Обсудить корзину в Telegram</a>
                    @endif
                    <a class="rounded bg-emerald-700 px-5 py-3 text-sm font-semibold text-white" href="{{ route('orders.checkout') }}">Оформить заказ</a>
                </div>
            </div>
        @endif
    </section>
</x-layouts.market>
