<x-layouts.market title="AiDvor Market">
    <section class="bg-white">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 md:grid-cols-[1.1fr_0.9fr] md:items-center">
            <div>
                <p class="text-sm font-medium uppercase tracking-wide text-emerald-700">market.aidvor.ru</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-normal text-slate-950 md:text-5xl">Товары и услуги для AiDvor</h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-600">
                    Готовые комплекты контроллеров, датчики, корпуса, прошивка, настройка и консультации для DIY IoT-проектов.
                </p>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 p-6">
                <div class="text-sm font-medium text-slate-500">Формат MVP</div>
                <div class="mt-3 text-2xl font-semibold">Каталог + заявки</div>
                <p class="mt-3 text-sm leading-6 text-slate-600">Онлайн-оплата не подключена. Заявки обрабатываются вручную, чтобы не смешивать маркет с подписками home.aidvor.ru.</p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-8">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('market.index') }}" class="rounded border px-3 py-2 text-sm {{ $currentCategory ? 'border-slate-200 bg-white text-slate-700' : 'border-emerald-700 bg-emerald-700 text-white' }}">Все</a>
            @foreach ($categories as $category)
                <a href="{{ route('market.index', ['category' => $category->slug]) }}" class="rounded border px-3 py-2 text-sm {{ $currentCategory?->is($category) ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-700' }}">
                    {{ $category->name }}
                </a>
            @endforeach
        </div>

        <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($items as $item)
                <article class="flex min-h-80 flex-col overflow-hidden rounded border border-slate-200 bg-white">
                    <div class="aspect-[4/3] bg-slate-200">
                        @if ($item->image_url)
                            <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center px-8 text-center text-sm font-medium text-slate-500">{{ $item->category->name }}</div>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <div class="text-xs font-medium uppercase tracking-wide text-emerald-700">{{ $item->type === 'service' ? 'Услуга' : ($item->type === 'bundle' ? 'Комплект' : 'Товар') }}</div>
                        <h2 class="mt-2 text-xl font-semibold">{{ $item->name }}</h2>
                        <p class="mt-3 flex-1 text-sm leading-6 text-slate-600">{{ $item->summary }}</p>
                        <div class="mt-4 text-sm {{ $item->stock_quantity > 0 ? 'text-slate-600' : 'font-medium text-red-700' }}">
                            {{ $item->stock_quantity > 0 ? 'Доступно: '.$item->stock_quantity.' шт.' : 'Нет в наличии' }}
                        </div>
                        <div class="mt-5 flex items-center justify-between gap-4">
                            <div class="font-semibold">{{ $item->price_rub ? number_format($item->price_rub, 0, ',', ' ') . ' ₽' : 'По запросу' }}</div>
                            <div class="flex gap-2">
                                @if ($item->stock_quantity > 0)
                                    <form method="post" action="{{ route('cart.items.add', $item) }}">
                                        @csrf
                                        <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white" type="submit">В корзину</button>
                                    </form>
                                @endif
                                @if ($telegramLinks[$item->id] ?? null)
                                    <a href="{{ $telegramLinks[$item->id] }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700" target="_blank" rel="noopener">Заявка в Telegram</a>
                                @endif
                                @if ($vkLinks[$item->id] ?? null)
                                    <a href="{{ $vkLinks[$item->id] }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700" target="_blank" rel="noopener">Заявка во VK</a>
                                @endif
                                <a href="{{ route('market.items.show', $item) }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-medium text-white">Подробнее</a>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded border border-slate-200 bg-white p-6 text-slate-600">Позиции пока не добавлены.</div>
            @endforelse
        </div>
    </section>
</x-layouts.market>
