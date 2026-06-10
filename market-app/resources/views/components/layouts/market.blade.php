<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'AiDvor Market' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('market.index') }}" class="text-lg font-semibold tracking-normal">AiDvor Market</a>
            <nav class="flex items-center gap-4 text-sm text-slate-600">
                <a class="hover:text-slate-950" href="{{ route('market.index') }}">Каталог</a>
                <a class="hover:text-slate-950" href="{{ route('cart.show') }}">Корзина</a>
                <a class="hover:text-slate-950" href="{{ route('admin.items.index') }}">Админка</a>
            </nav>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>
</body>
</html>
