<x-layouts.market :title="$item->name . ' | AiDvor Market'">
    <section class="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-[1fr_420px]">
        <div>
            <a href="{{ route('market.index') }}" class="text-sm text-slate-600 hover:text-slate-950">Назад в каталог</a>
            <div class="mt-6 overflow-hidden rounded border border-slate-200 bg-white">
                <div class="aspect-[16/9] bg-slate-200">
                    @if ($item->image_url)
                        <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center text-slate-500">{{ $item->category->name }}</div>
                    @endif
                </div>
                <div class="p-6">
                    <div class="text-sm font-medium text-emerald-700">{{ $item->category->name }}</div>
                    <h1 class="mt-2 text-3xl font-semibold">{{ $item->name }}</h1>
                    <p class="mt-4 text-lg leading-8 text-slate-600">{{ $item->summary }}</p>
                    @if ($item->description)
                        <div class="mt-6 whitespace-pre-line leading-7 text-slate-700">{{ $item->description }}</div>
                    @endif
                </div>
            </div>
        </div>

        <aside class="lg:pt-14">
            <div class="rounded border border-slate-200 bg-white p-6">
                <div class="text-sm text-slate-500">Стоимость</div>
                <div class="mt-1 text-2xl font-semibold">{{ $item->price_rub ? number_format($item->price_rub, 0, ',', ' ') . ' ₽' : 'По запросу' }}</div>
                <div class="mt-3 text-sm {{ $item->stock_quantity > 0 ? 'text-slate-600' : 'font-medium text-red-700' }}">
                    {{ $item->stock_quantity > 0 ? 'Доступно: '.$item->stock_quantity.' шт.' : 'Нет в наличии. Можно оставить заявку.' }}
                </div>

                @if (session('status'))
                    <div class="mt-5 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif

                @if ($item->stock_quantity > 0)
                    <form method="post" action="{{ route('cart.items.add', $item) }}" class="mt-6 flex gap-3">
                        @csrf
                        <input class="w-24 rounded border border-slate-300 px-3 py-2" name="quantity" type="number" min="1" max="{{ $item->stock_quantity }}" value="1" aria-label="Количество">
                        <button class="flex-1 rounded bg-slate-950 px-4 py-3 text-sm font-semibold text-white" type="submit">Добавить в корзину</button>
                    </form>
                    @error('quantity') <div class="mt-2 text-sm text-red-600">{{ $message }}</div> @enderror
                @endif

                @if ($telegramLink)
                    <a href="{{ $telegramLink }}" class="mt-4 flex w-full justify-center rounded border border-emerald-700 px-4 py-3 text-sm font-semibold text-emerald-800" target="_blank" rel="noopener">Сделать заявку в Telegram</a>
                @endif

                <form method="post" action="{{ route('market.inquiries.store', $item) }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-medium" for="name">Имя</label>
                        <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="email">Email</label>
                        <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="email" name="email" value="{{ old('email') }}">
                        @error('email') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="phone">Телефон</label>
                        <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="phone" name="phone" value="{{ old('phone') }}">
                        @error('phone') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="message">Комментарий</label>
                        <textarea class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="message" name="message" rows="4">{{ old('message') }}</textarea>
                        @error('message') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>
                    <button class="w-full rounded bg-emerald-700 px-4 py-3 text-sm font-semibold text-white" type="submit">Оставить заявку</button>
                </form>
            </div>
        </aside>
    </section>
</x-layouts.market>
