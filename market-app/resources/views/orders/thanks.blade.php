<x-layouts.market title="Заказ отправлен | AiDvor Market">
    <section class="mx-auto max-w-3xl px-4 py-16">
        <div class="rounded border border-slate-200 bg-white p-8">
            <div class="text-sm font-medium text-emerald-700">Заказ отправлен</div>
            <h1 class="mt-2 text-3xl font-semibold">Спасибо</h1>
            <p class="mt-4 leading-7 text-slate-600">Номер заказа: <span class="font-semibold text-slate-950">{{ $order->number }}</span>. Мы свяжемся с вами для подтверждения состава, оплаты и доставки.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                @if ($telegramLink)
                    <a class="inline-flex rounded border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-800" href="{{ $telegramLink }}" target="_blank" rel="noopener">Продолжить в Telegram</a>
                @endif
                @if ($vkLink)
                    <a class="inline-flex rounded border border-blue-700 px-4 py-2 text-sm font-medium text-blue-800" href="{{ $vkLink }}" target="_blank" rel="noopener">Продолжить во VK</a>
                @endif
                <a class="inline-flex rounded bg-slate-950 px-4 py-2 text-sm font-medium text-white" href="{{ route('market.index') }}">Вернуться в каталог</a>
            </div>
        </div>
    </section>
</x-layouts.market>
