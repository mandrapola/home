<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'AiDvor Market Admin' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('admin.dashboard') }}" class="text-lg font-semibold">Market Admin</a>
            <nav class="flex items-center gap-4 text-sm text-slate-600">
                <a class="hover:text-slate-950" href="{{ route('admin.items.index') }}">Позиции</a>
                <a class="hover:text-slate-950" href="{{ route('admin.categories.index') }}">Категории</a>
                <a class="hover:text-slate-950" href="{{ route('admin.orders.index') }}">Заказы</a>
                <a class="hover:text-slate-950" href="{{ route('admin.inquiries.index') }}">Заявки</a>
                <a class="hover:text-slate-950" href="{{ route('admin.telegram.index') }}">Telegram</a>
                <a class="hover:text-slate-950" href="{{ route('market.index') }}">Витрина</a>
                <form method="post" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="text-slate-600 hover:text-slate-950" type="submit">Выйти</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
