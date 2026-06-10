<x-layouts.admin title="Заявки | Market Admin">
    <h1 class="mb-6 text-2xl font-semibold">Заявки</h1>

    <div class="space-y-4">
        @forelse ($inquiries as $inquiry)
            <article class="rounded border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">{{ $inquiry->created_at->format('d.m.Y H:i') }}</div>
                        <h2 class="mt-1 text-lg font-semibold">{{ $inquiry->name }}</h2>
                        <div class="mt-2 text-sm text-slate-600">{{ $inquiry->item?->name ?? 'Позиция удалена' }}</div>
                    </div>
                    <form method="post" action="{{ route('admin.inquiries.update', $inquiry) }}">
                        @csrf
                        @method('patch')
                        <select class="rounded border border-slate-300 px-3 py-2 text-sm" name="status" onchange="this.form.submit()">
                            <option value="new" @selected($inquiry->status === 'new')>Новая</option>
                            <option value="contacted" @selected($inquiry->status === 'contacted')>Связались</option>
                            <option value="done" @selected($inquiry->status === 'done')>Готово</option>
                            <option value="canceled" @selected($inquiry->status === 'canceled')>Отменена</option>
                        </select>
                    </form>
                </div>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                    <div><dt class="text-slate-500">Email</dt><dd>{{ $inquiry->email ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Телефон</dt><dd>{{ $inquiry->phone ?: '—' }}</dd></div>
                </dl>
                @if ($inquiry->message)
                    <div class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $inquiry->message }}</div>
                @endif
            </article>
        @empty
            <div class="rounded border border-slate-200 bg-white p-6 text-slate-600">Заявок пока нет.</div>
        @endforelse
    </div>
</x-layouts.admin>
