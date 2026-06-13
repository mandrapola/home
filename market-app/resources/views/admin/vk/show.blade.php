<x-layouts.admin title="VK-заявка | Market Admin">
    <div class="mb-6">
        <a href="{{ route('admin.vk.index') }}" class="text-sm text-slate-600 hover:text-slate-950">Назад к VK-заявкам</a>
        <h1 class="mt-2 text-2xl font-semibold">VK-заявка #{{ $conversation->id }}</h1>
    </div>

    <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
        <aside class="rounded border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Данные</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-slate-500">Пользователь</dt><dd>VK ID {{ $conversation->vk_user_id }}</dd></div>
                <div><dt class="text-slate-500">Контекст</dt><dd>{{ $context['title'] }}</dd></div>
                <div><dt class="text-slate-500">Объект</dt><dd>{{ $context['name'] }}</dd></div>
                <div><dt class="text-slate-500">Действие</dt><dd>{{ $intentLabel ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Статус</dt><dd>{{ $conversation->status }}</dd></div>
            </dl>
        </aside>

        <section class="rounded border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Сообщения</h2>

            @if (session('status'))
                <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-4 rounded border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-medium uppercase text-slate-500">{{ $context['title'] }}</div>
                        <div class="mt-1 text-base font-semibold text-slate-950">{{ $context['name'] }}</div>
                    </div>
                    @if ($context['url'])
                        <a class="text-sm font-medium text-slate-700 hover:text-slate-950" href="{{ $context['url'] }}">
                            Открыть
                        </a>
                    @endif
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    @foreach ($context['rows'] as $label => $value)
                        <div>
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div>
                        <dt class="text-slate-500">Выбранное действие</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ $intentLabel ?: 'Пока не выбрано' }}</dd>
                    </div>
                </dl>

                @if ($context['items'])
                    <div class="mt-4 overflow-x-auto rounded border border-slate-200 bg-white">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="bg-white text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Позиция</th>
                                    <th class="px-3 py-2">Кол-во</th>
                                    <th class="px-3 py-2">Цена</th>
                                    <th class="px-3 py-2">Сумма</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($context['items'] as $item)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-slate-900">{{ $item['name'] }}</td>
                                        <td class="px-3 py-2">{{ $item['quantity'] }}</td>
                                        <td class="px-3 py-2">{{ $item['unit_price'] }}</td>
                                        <td class="px-3 py-2">{{ $item['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

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

            <form method="POST" action="{{ route('admin.vk.reply', $conversation) }}" class="mt-6 border-t border-slate-200 pt-5">
                @csrf
                <label class="text-sm font-medium" for="message">Ответ пользователю</label>
                <textarea
                    class="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                    id="message"
                    name="message"
                    rows="4"
                    required
                >{{ old('message') }}</textarea>
                @error('message') <div class="mt-2 text-sm text-red-600">{{ $message }}</div> @enderror

                <div class="mt-3 flex justify-end">
                    <button class="rounded bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">
                        Отправить во VK
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.admin>
