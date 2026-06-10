<x-layouts.market title="Оформление заказа | AiDvor Market">
    <section class="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-[1fr_380px]">
        <div>
            <h1 class="text-3xl font-semibold">Оформление заказа</h1>
            <form method="post" action="{{ route('orders.store') }}" class="mt-8 space-y-5 rounded border border-slate-200 bg-white p-6">
                @csrf
                <div>
                    <label class="text-sm font-medium" for="customer_name">Имя</label>
                    <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required>
                    @error('customer_name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium" for="customer_email">Email</label>
                        <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="customer_email" name="customer_email" value="{{ old('customer_email') }}">
                        @error('customer_email') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="customer_phone">Телефон</label>
                        <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}">
                        @error('customer_phone') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium" for="comment">Комментарий</label>
                    <textarea class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="comment" name="comment" rows="4">{{ old('comment') }}</textarea>
                    @error('comment') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <button class="rounded bg-emerald-700 px-5 py-3 text-sm font-semibold text-white" type="submit">Отправить заказ</button>
            </form>
        </div>

        <aside>
            <div class="rounded border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Состав</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($lines as $line)
                        <div class="flex justify-between gap-4 text-sm">
                            <div>
                                <div class="font-medium">{{ $line['item']->name }}</div>
                                <div class="mt-1 text-slate-500">{{ $line['quantity'] }} шт.</div>
                            </div>
                            <div class="font-medium">{{ $line['total_rub'] ? number_format($line['total_rub'], 0, ',', ' ') . ' ₽' : 'По запросу' }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 border-t border-slate-200 pt-5 text-xl font-semibold">Итого: {{ number_format($totalRub, 0, ',', ' ') }} ₽</div>
            </div>
        </aside>
    </section>
</x-layouts.market>
