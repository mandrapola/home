<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <h2 class="h4 mb-0">{{ __('Add New Controller') }}</h2>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back to Dashboard') }}</a>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h3 class="h6 mb-3">{{ __('1. Request Pairing Code') }}</h3>
                    <p class="text-muted small mb-3">
                        {{ __('Code will be sent to all unpaired controllers. Each controller gets a unique code.') }}
                    </p>
                    <div class="d-grid mt-3">
                        <button id="start-pairing-btn" class="btn btn-primary">{{ __('Request 4-Digit Code') }}</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h3 class="h6 mb-3">{{ __('2. Confirm Code from TM1637') }}</h3>
                    <p class="text-muted small mb-3">
                        {{ __('After requesting code, the controller receives it in API response and shows it on TM1637.') }}
                    </p>
                    <div class="mb-3">
                        <label for="pairing-code" class="form-label">{{ __('Code from Display') }}</label>
                        <input id="pairing-code" type="text" class="form-control" maxlength="4" placeholder="{{ __('0000') }}">
                    </div>
                    <div class="d-grid d-sm-flex gap-2">
                        <button id="confirm-pairing-btn" class="btn btn-success">{{ __('Pair Controller') }}</button>
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
                    showStatus('error', data.message || '{{ __('Failed to start pairing') }}');
                    return;
                }

                activeSession = true;
                showStatus('ok', '{{ __('Codes sent to unpaired controllers') }}: ' + data.created_count + '. {{ __('Enter code from desired controller.') }}');
            });

            confirmBtn.addEventListener('click', async () => {
                const code = codeInput.value.trim();
                if (!activeSession) {
                    showStatus('error', '{{ __('Request pairing code first') }}');
                    return;
                }
                if (code.length !== 4) {
                    showStatus('error', '{{ __('Enter 4-digit code') }}');
                    return;
                }

                const response = await fetch('/api/pairing/confirm-by-code', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({code: code})
                });
                const data = await response.json();
                if (!response.ok) {
                    showStatus('error', data.message || '{{ __('Code is not confirmed') }}');
                    return;
                }

                showStatus('ok', '{{ __('Controller successfully linked to your account.') }}');
                codeInput.value = '';
                activeSession = false;
            });
        })();
    </script>
</x-app-layout>
