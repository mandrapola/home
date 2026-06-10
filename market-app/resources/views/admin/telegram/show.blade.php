<x-layouts.admin title="Telegram-заявка | Market Admin">
    <div class="mb-6">
        <a href="{{ route('admin.telegram.index') }}" class="text-sm text-slate-600 hover:text-slate-950">Назад к Telegram-заявкам</a>
        <h1 class="mt-2 text-2xl font-semibold">Telegram-заявка #{{ $conversation->id }}</h1>
    </div>

    <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
        <aside class="rounded border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Данные</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-slate-500">Пользователь</dt><dd>{{ $conversation->telegram_username ? '@'.$conversation->telegram_username : $conversation->telegram_user_id }}</dd></div>
                <div><dt class="text-slate-500">Контекст</dt><dd>{{ $conversation->context_type }}</dd></div>
                <div><dt class="text-slate-500">Действие</dt><dd>{{ $conversation->intent ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Статус</dt><dd>{{ $conversation->status }}</dd></div>
            </dl>
        </aside>

        <section class="rounded border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Сообщения</h2>
            <div class="mt-4 space-y-4">
                @forelse ($conversation->messages as $message)
                    <article class="rounded border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-4 text-xs text-slate-500">
                            <span>{{ $message->direction }}</span>
                            <span>{{ $message->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $message->body ?: '—' }}</div>
                    </article>
                @empty
                    <div class="text-sm text-slate-600">Сообщений пока нет.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.admin>
