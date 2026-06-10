<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход | AiDvor Market Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4 py-12">
        <form method="post" action="{{ route('admin.login.store') }}" class="w-full rounded border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <h1 class="text-2xl font-semibold">Market Admin</h1>
            <p class="mt-2 text-sm text-slate-600">Вход для пользователей с ролью administrator.</p>

            <div class="mt-6">
                <label class="text-sm font-medium" for="email">Email</label>
                <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                @error('email') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="mt-4">
                <label class="text-sm font-medium" for="password">Пароль</label>
                <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" id="password" name="password" type="password" required>
                @error('password') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remember" value="1">
                Запомнить меня
            </label>

            <button class="mt-6 w-full rounded bg-emerald-700 px-4 py-3 text-sm font-semibold text-white" type="submit">Войти</button>
        </form>
    </main>
</body>
</html>
