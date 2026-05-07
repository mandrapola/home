<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Profile') }}</h2>
    </x-slot>
    @php
        $statusLabel = static fn (?string $status): string => $status ? __('status.' . strtolower($status)) : '—';
    @endphp
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        .profile-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card);
            box-shadow: 0 8px 22px rgba(17,34,68,.06);
        }
        .profile-card .card-body {
            padding: 20px;
        }
        .profile-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            letter-spacing: -0.01em;
        }
        .profile-kv {
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
        }
        .profile-kv:last-child {
            border-bottom: none;
        }
        .profile-kv .k {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 2px;
        }
        .profile-kv .v {
            color: var(--text);
            font-weight: 600;
        }
        .profile-chip {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            color: var(--muted);
            background: var(--chip-bg);
        }
        .profile-chip.ok {
            border-color: #d4efdf;
            background: #f2fbf6;
            color: #1a7f4b;
        }
        .profile-chip.off {
            border-color: #f1d0d5;
            background: #fff5f6;
            color: #9d2d3f;
        }
        .profile-section-divider {
            border-top: 1px solid var(--line);
            margin: 14px 0;
            padding-top: 14px;
        }
        .theme-form-panel {
            background: var(--chip-bg) !important;
            border-color: var(--line) !important;
            border-radius: 12px;
        }
        .payments-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card);
            box-shadow: 0 8px 22px rgba(17,34,68,.06);
        }
        .payments-card .table {
            --bs-table-bg: transparent;
        }
        .status-badge {
            display: inline-flex;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 12px;
            background: var(--chip-bg);
            color: var(--muted);
        }
    </style>

    <div class="profile-grid">
        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="profile-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="profile-title">{{ __('User Data') }}</h3>
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#profileEditForm">
                            {{ __('Edit') }}
                        </button>
                    </div>

                    <div class="profile-kv">
                        <div class="k">{{ __('Name') }}</div>
                        <div class="v">{{ $user->name }}</div>
                    </div>

                    <div class="profile-kv">
                        <div class="k">E-mail</div>
                        <div class="v">{{ $user->email }}</div>
                    </div>

                    <div class="profile-kv">
                        <div class="k">{{ __('Current Time Zone') }}</div>
                        <div class="v">{{ $user->time_zone ?? 'Europe/Moscow' }}</div>
                    </div>

                    <div class="profile-kv">
                        <div class="k">{{ __('Current Language') }}</div>
                        <div class="v">{{ strtoupper((string) ($user->locale ?? 'ru')) }}</div>
                    </div>
                    <div class="profile-kv">
                        <div class="k">{{ __('Alice Access') }}</div>
                        <div class="v">
                            @if ($user->alice_enabled)
                                <span class="profile-chip ok">{{ __('Enabled') }}</span>
                            @else
                                <span class="profile-chip off">{{ __('Disabled') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="profile-section-divider"></div>

                    <div class="mb-2">
                        <div class="text-muted small">Yandex Alice</div>
                        <div class="v">
                            @if ($aliceLinkedAccount)
                                {{ __('Connected') }} (ID: {{ $aliceLinkedAccount->yandex_user_id }})
                            @else
                                {{ __('Not connected') }}
                            @endif
                        </div>
                    </div>

                    @if (session('alice-status'))
                        <div class="alert alert-success py-2 mb-3">{{ session('alice-status') }}</div>
                    @endif
                    @if (session('alice-error'))
                        <div class="alert alert-danger py-2 mb-3">{{ session('alice-error') }}</div>
                    @endif

                    <div class="d-flex gap-2 mb-3">
                        <a href="{{ route('profile.alice.connect') }}"
                           class="btn btn-outline-primary btn-sm @if (!config('services.alice.enabled') || !($user->alice_enabled ?? false)) disabled @endif">
                            {{ __('Connect Alice') }}
                        </a>

                        @if ($aliceLinkedAccount)
                            <form method="post" action="{{ route('profile.alice.disconnect') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    {{ __('Disconnect Alice') }}
                                </button>
                            </form>
                        @endif
                    </div>

                    @if (session('status') === 'profile-updated')
                        <div class="alert alert-success py-2 mb-3">{{ __('Profile updated.') }}</div>
                    @endif

                    <div id="profileEditForm" class="collapse">
                        <form method="post" action="{{ route('profile.update') }}" class="border rounded p-3 theme-form-panel">
                            @csrf
                            @method('patch')

                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required maxlength="255">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('Time Zone') }}</label>
                                <select name="time_zone" class="form-select @error('time_zone') is-invalid @enderror" required>
                                    @php($selectedTimeZone = old('time_zone', $user->time_zone ?? 'Europe/Moscow'))
                                    @foreach ($timeZones as $timeZone)
                                        <option value="{{ $timeZone }}" @selected($selectedTimeZone === $timeZone)>{{ $timeZone }}</option>
                                    @endforeach
                                </select>
                                @error('time_zone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('Interface Language') }}</label>
                                <select name="locale" class="form-select @error('locale') is-invalid @enderror" required>
                                    @php($selectedLocale = old('locale', $user->locale ?? 'ru'))
                                    @foreach ($locales as $locale)
                                        <option value="{{ $locale }}" @selected($selectedLocale === $locale)>{{ strtoupper($locale) }}</option>
                                    @endforeach
                                </select>
                                @error('locale')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-success btn-sm">{{ __('Save') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <div class="payments-card">
                    <div class="card-body">
                        <h3 class="profile-title mb-3">{{ __('Recent payments') }}</h3>
                        @if ($paymentOrders->isEmpty())
                            <p class="text-muted small mb-0">{{ __('No payments yet.') }}</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>{{ __('ID') }}</th>
                                        <th>{{ __('Plan') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Created') }}</th>
                                        <th>{{ __('Paid at') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($paymentOrders as $order)
                                        <tr>
                                            <td>#{{ $order->id }}</td>
                                            <td>{{ $order->plan?->name ?? '—' }}</td>
                                            <td>{{ number_format((float) $order->amount, 2, '.', ' ') }} {{ $order->currency }}</td>
                                            <td><span class="status-badge">{{ $statusLabel($order->status) }}</span></td>
                                            <td>{{ optional($order->created_at)->format('Y-m-d H:i') }}</td>
                                            <td>{{ optional($order->paid_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
