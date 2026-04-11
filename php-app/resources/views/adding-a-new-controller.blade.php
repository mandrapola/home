<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <h2 class="h4 mb-0">{{ __('Добавление нового контроллера') }}</h2>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Назад в дашбоард</a>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h3 class="h6 mb-3">1. Запросите код привязки</h3>
                    <p class="text-muted small mb-3">
                        Код будет отправлен на все свободные контроллеры. У каждого контроллера код уникальный.
                    </p>
                    <div class="d-grid mt-3">
                        <button id="start-pairing-btn" class="btn btn-primary">Запросить 4-значный код</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h3 class="h6 mb-3">2. Подтвердите код с TM1637</h3>
                    <p class="text-muted small mb-3">
                        После запроса кода контроллер получит его в ответе API и покажет на индикаторе TM1637.
                    </p>
                    <div class="mb-3">
                        <label for="pairing-code" class="form-label">Код с индикатора</label>
                        <input id="pairing-code" type="text" class="form-control" maxlength="4" placeholder="0000">
                    </div>
                    <div class="d-grid d-sm-flex gap-2">
                        <button id="confirm-pairing-btn" class="btn btn-success">Привязать контроллер</button>
                    </div>
                    <div id="pairing-status" class="alert mt-3 d-none" role="alert"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const codeInput = document.getElementById('pairing-code');
            const startBtn = document.getElementById('start-pairing-btn');
            const confirmBtn = document.getElementById('confirm-pairing-btn');
            const statusEl = document.getElementById('pairing-status');
            let activeSession = false;

            function showStatus(type, text) {
                statusEl.className = 'alert mt-3';
                statusEl.classList.add(type === 'ok' ? 'alert-success' : 'alert-danger');
                statusEl.textContent = text;
                statusEl.classList.remove('d-none');
            }

            startBtn.addEventListener('click', async () => {
                const response = await fetch('/api/pairing/start-all', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({})
                });
                const data = await response.json();
                if (!response.ok) {
                    showStatus('error', data.message || 'Не удалось запустить привязку');
                    return;
                }

                activeSession = true;
                showStatus('ok', 'Коды отправлены на свободные контроллеры: ' + data.created_count + '. Введите код с нужного контроллера.');
            });

            confirmBtn.addEventListener('click', async () => {
                const code = codeInput.value.trim();
                if (!activeSession) {
                    showStatus('error', 'Сначала запросите код привязки');
                    return;
                }
                if (code.length !== 4) {
                    showStatus('error', 'Введите 4-значный код');
                    return;
                }

                const response = await fetch('/api/pairing/confirm-by-code', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({code: code})
                });
                const data = await response.json();
                if (!response.ok) {
                    showStatus('error', data.message || 'Код не подтверждён');
                    return;
                }

                showStatus('ok', 'Контроллер успешно привязан к вашему аккаунту.');
                codeInput.value = '';
                activeSession = false;
            });
        })();
    </script>
</x-app-layout>
