<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <h2 class="h4 mb-0">{{ __('Add New Controller') }}</h2>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back to Dashboard') }}</a>
        </div>
    </x-slot>

    <div class="row g-3 justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h3 class="h6 mb-3">{{ __('Confirm Controller Code') }}</h3>
                    <p class="text-muted small mb-3">
                        {{ __('Turn on the controller and wait until the code appears on its display. Enter the code here. If a second confirmation code appears, enter it here as well.') }}
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
            const confirmBtn = document.getElementById('confirm-pairing-btn');
            const statusEl = document.getElementById('pairing-status');
            let registrationToken = '';

            function showStatus(type, text) {
                statusEl.className = 'alert mt-3';
                statusEl.classList.add(type === 'ok' ? 'alert-success' : 'alert-danger');
                statusEl.textContent = text;
                statusEl.classList.remove('d-none');
            }

            confirmBtn.addEventListener('click', async () => {
                const code = codeInput.value.trim();
                if (code.length !== 4) {
                    showStatus('error', '{{ __('Enter 4-digit code') }}');
                    return;
                }

                const payload = {code: code};
                if (registrationToken) {
                    payload.registration_token = registrationToken;
                }

                const response = await fetch('/api/pairing/confirm-by-code', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!response.ok) {
                    if (data.new_code_required) {
                        registrationToken = '';
                        codeInput.value = '';
                        showStatus('error', '{{ __('Several controllers use this code. New codes were sent to displays. Enter the new code from your controller.') }}');
                        return;
                    }
                    showStatus('error', data.message || '{{ __('Code is not confirmed') }}');
                    return;
                }

                if (data.challenge_required) {
                    registrationToken = data.registration_token || '';
                    codeInput.value = '';
                    showStatus('ok', '{{ __('Enter the new code from controller display.') }}');
                    return;
                }

                showStatus('ok', '{{ __('Controller successfully linked to your account.') }}');
                codeInput.value = '';
                registrationToken = '';
            });
        })();
    </script>
</x-app-layout>
