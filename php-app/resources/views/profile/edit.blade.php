<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Profile') }}</h2>
    </x-slot>

    @php
        $statusLabel = static fn (?string $status): string => $status ? __('status.' . strtolower($status)) : '—';
        $statusClass = static function (?string $status): string {
            $s = strtolower((string) $status);
            if (in_array($s, ['succeeded', 'paid', 'active'], true)) {
                return 'success';
            }
            if (in_array($s, ['failed', 'canceled', 'cancelled', 'error'], true)) {
                return 'error';
            }
            return '';
        };
        $initial = mb_strtoupper(mb_substr((string) $user->name, 0, 1));
        $selectedPlanName = $user->selectedPlan?->name ?? __('Not selected');
        $paymentsCount = $paymentOrders->count();
        $aliceConnected = (bool) $aliceLinkedAccount;
    @endphp

    <style>
        .profile-page {
            --profile-bg: #07110d;
            --profile-card: rgba(16, 35, 26, .94);
            --profile-card-2: rgba(19, 41, 31, .96);
            --profile-border: rgba(171, 219, 185, .16);
            --profile-text: #eef8f1;
            --profile-muted: #9db2a5;
            --profile-accent: #42c779;
            --profile-accent-2: #9af0b1;
            --profile-danger: #ff768a;
            --profile-shadow: 0 22px 70px rgba(0, 0, 0, .34);
            --profile-radius: 24px;
            display: grid;
            gap: 20px;
        }

        html[data-theme='light'] .profile-page {
            --profile-bg: #f4f8f5;
            --profile-card: #ffffff;
            --profile-card-2: #f7fbf8;
            --profile-border: rgba(31, 71, 44, .16);
            --profile-text: #1f2a24;
            --profile-muted: #5f7267;
            --profile-accent: #2f7d4f;
            --profile-accent-2: #8fd8a6;
            --profile-danger: #c93f5b;
            --profile-shadow: 0 14px 36px rgba(17, 34, 22, .10);
        }

        .profile-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--profile-border);
            border-radius: 32px;
            padding: 26px;
            background:
                radial-gradient(circle at 90% 0%, rgba(154, 240, 177, .18), transparent 36%),
                linear-gradient(135deg, rgba(16, 35, 26, .98), rgba(21, 80, 48, .86));
            box-shadow: var(--profile-shadow);
        }

        html[data-theme='light'] .profile-hero {
            background:
                radial-gradient(circle at 90% 0%, rgba(154, 240, 177, .24), transparent 40%),
                linear-gradient(135deg, #eef8f1, #d9f1e2);
        }

        .profile-hero-main {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 18px;
            align-items: center;
        }

        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 24px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--profile-accent), var(--profile-accent-2));
            color: #07110d;
            font-size: 28px;
            font-weight: 950;
            box-shadow: 0 0 34px rgba(66, 199, 121, .26);
        }

        .profile-eyebrow {
            color: var(--profile-accent-2);
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        html[data-theme='light'] .profile-eyebrow { color: var(--profile-accent); }

        .profile-hero h1 {
            margin: 0;
            color: var(--profile-text);
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.05;
            letter-spacing: -.045em;
        }

        .profile-hero p {
            margin: 8px 0 0;
            color: color-mix(in srgb, var(--profile-text) 74%, transparent);
        }

        .profile-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn-profile-primary {
            border: 0;
            background: linear-gradient(135deg, var(--profile-accent), var(--profile-accent-2));
            color: #07110d;
            font-weight: 850;
            border-radius: 999px;
            padding-inline: 16px;
            box-shadow: 0 12px 30px rgba(66, 199, 121, .22);
        }

        .btn-profile-ghost {
            border: 1px solid var(--profile-border);
            background: rgba(255, 255, 255, .06);
            color: var(--profile-text);
            font-weight: 750;
            border-radius: 999px;
            padding-inline: 16px;
        }

        html[data-theme='light'] .btn-profile-ghost {
            background: rgba(255, 255, 255, .75);
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-top: 22px;
        }

        .profile-stat {
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 20px;
            padding: 16px;
            background: rgba(255, 255, 255, .055);
        }

        html[data-theme='light'] .profile-stat {
            border-color: var(--profile-border);
            background: rgba(255, 255, 255, .68);
        }

        .profile-stat .label {
            color: color-mix(in srgb, var(--profile-text) 65%, transparent);
            font-size: 12px;
            margin-bottom: 6px;
        }

        .profile-stat .value {
            color: var(--profile-text);
            font-size: 18px;
            font-weight: 900;
            letter-spacing: -.02em;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 20px;
            align-items: start;
        }

        .profile-stack { display: grid; gap: 20px; }

        .profile-panel {
            border: 1px solid var(--profile-border);
            border-radius: var(--profile-radius);
            background: linear-gradient(180deg, var(--profile-card), var(--profile-card-2));
            box-shadow: 0 12px 36px rgba(0, 0, 0, .20);
            overflow: hidden;
        }

        .profile-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 14px;
            padding: 20px 22px;
            border-bottom: 1px solid var(--profile-border);
        }

        .profile-panel-title {
            margin: 0;
            color: var(--profile-text);
            font-size: 18px;
            font-weight: 850;
            letter-spacing: -.025em;
        }

        .profile-panel-subtitle {
            margin: 5px 0 0;
            color: var(--profile-muted);
            font-size: 14px;
        }

        .profile-panel-body { padding: 20px 22px 22px; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .info-item {
            border: 1px solid var(--profile-border);
            border-radius: 18px;
            padding: 14px 16px;
            background: rgba(255, 255, 255, .045);
        }

        html[data-theme='light'] .info-item { background: rgba(255, 255, 255, .8); }

        .info-item .k { color: var(--profile-muted); font-size: 12px; margin-bottom: 4px; }
        .info-item .v { color: var(--profile-text); font-weight: 800; word-break: break-word; }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            padding: 6px 11px;
            border: 1px solid var(--profile-border);
            background: rgba(255, 255, 255, .055);
            color: var(--profile-muted);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-chip.ok {
            color: var(--profile-accent-2);
            border-color: rgba(66, 199, 121, .34);
            background: rgba(66, 199, 121, .11);
        }

        html[data-theme='light'] .status-chip.ok { color: #1f8f51; }

        .status-chip.danger {
            color: var(--profile-danger);
            border-color: rgba(255, 118, 138, .34);
            background: rgba(255, 118, 138, .10);
        }

        .status-chip::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
            box-shadow: 0 0 16px currentColor;
        }

        .integration-card {
            display: grid;
            grid-template-columns: 48px 1fr;
            gap: 14px;
            align-items: start;
            border: 1px solid var(--profile-border);
            border-radius: 20px;
            padding: 16px;
            background: rgba(255, 255, 255, .045);
        }

        html[data-theme='light'] .integration-card { background: rgba(255, 255, 255, .78); }

        .integration-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: rgba(66, 199, 121, .14);
            color: var(--profile-accent-2);
            font-weight: 950;
            font-size: 20px;
        }

        html[data-theme='light'] .integration-icon { color: #1f8f51; }

        .integration-card h3 {
            margin: 0 0 5px;
            color: var(--profile-text);
            font-size: 16px;
            font-weight: 850;
        }

        .integration-card p {
            margin: 0 0 12px;
            color: var(--profile-muted);
            font-size: 14px;
        }

        .integration-actions { display: flex; flex-wrap: wrap; gap: 10px; }

        .settings-form { display: grid; gap: 16px; }

        .settings-form .form-control,
        .settings-form .form-select {
            color: var(--profile-text);
            background-color: rgba(255, 255, 255, .055);
            border-color: var(--profile-border);
            border-radius: 14px;
            min-height: 42px;
        }

        html[data-theme='light'] .settings-form .form-control,
        html[data-theme='light'] .settings-form .form-select {
            background-color: rgba(255, 255, 255, .85);
        }

        .settings-form .form-select option {
            color: #1f2a24;
            background: #ffffff;
        }

        html[data-theme='dark'] .settings-form .form-select option {
            color: #eef8f1;
            background: #10231a;
        }

        .settings-form .form-control:focus,
        .settings-form .form-select:focus {
            color: var(--profile-text);
            background-color: rgba(255, 255, 255, .08);
            border-color: rgba(66, 199, 121, .55);
            box-shadow: 0 0 0 .2rem rgba(66, 199, 121, .12);
        }

        .settings-form .form-label { color: var(--profile-muted); font-weight: 700; margin-bottom: 6px; }

        .payments-table th,
        .payments-table td {
            border-color: var(--profile-border) !important;
            color: var(--profile-text);
            vertical-align: middle;
            background: transparent !important;
        }

        .payments-table thead th {
            color: var(--profile-muted);
            font-weight: 700;
            font-size: 13px;
            background: rgba(255, 255, 255, .03) !important;
        }

        .payments-table tbody tr:hover td {
            background: rgba(255, 255, 255, .04) !important;
        }

        html[data-theme='light'] .payments-table thead th {
            background: rgba(31, 71, 44, .05) !important;
        }

        html[data-theme='light'] .payments-table tbody tr:hover td {
            background: rgba(31, 71, 44, .04) !important;
        }

        .payments-shell {
            border: 1px solid var(--profile-border);
            border-radius: 18px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(255, 255, 255, .02), rgba(255, 255, 255, .00));
        }

        .payments-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px 14px;
            border-bottom: 1px solid var(--profile-border);
        }

        .payments-title {
            margin: 0;
            color: var(--profile-text);
            font-size: 31px;
            font-size: clamp(18px, 1.9vw, 31px);
            line-height: 1.1;
            font-weight: 850;
            letter-spacing: -0.02em;
        }

        .payments-subtitle {
            margin: 4px 0 0;
            color: var(--profile-muted);
            font-size: 14px;
        }

        .payments-link {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 14px;
            border: 1px solid var(--profile-border);
            border-radius: 999px;
            color: var(--profile-text);
            background: rgba(255, 255, 255, .04);
            font-size: 14px;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .payments-link:hover {
            background: rgba(255, 255, 255, .08);
            color: var(--profile-text);
        }

        .payments-body {
            padding: 10px 18px 14px;
        }

        .payments-table thead th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: color-mix(in srgb, var(--profile-text) 72%, transparent);
            font-weight: 700;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .payments-table tbody td {
            padding-top: 14px;
            padding-bottom: 14px;
            border-top: 1px solid var(--profile-border);
        }

        .payments-table .td-id,
        .payments-table .td-amount {
            font-weight: 800;
        }

        .status-chip {
            border-radius: 999px;
            padding: 6px 11px;
            border: 1px solid var(--profile-border);
            background: rgba(255, 255, 255, .055);
            color: var(--profile-muted);
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
        }

        .status-chip.success {
            color: color-mix(in srgb, var(--profile-accent) 70%, var(--profile-text));
            border-color: color-mix(in srgb, var(--profile-accent) 42%, transparent);
            background: color-mix(in srgb, var(--profile-accent) 12%, transparent);
        }

        .status-chip.error {
            color: color-mix(in srgb, var(--profile-danger) 70%, var(--profile-text));
            border-color: color-mix(in srgb, var(--profile-danger) 42%, transparent);
            background: color-mix(in srgb, var(--profile-danger) 12%, transparent);
        }

        .small-muted { color: var(--profile-muted); font-size: 13px; }

        @media (max-width: 1100px) {
            .profile-layout { grid-template-columns: 1fr; }
            .profile-stats { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 760px) {
            .profile-hero-main { grid-template-columns: auto 1fr; }
            .profile-actions { grid-column: 1 / -1; justify-content: flex-start; }
            .info-grid { grid-template-columns: 1fr; }
            .profile-stats { grid-template-columns: 1fr; }
        }
    </style>

    <div class="profile-page">
        <section class="profile-hero">
            <div class="profile-hero-main">
                <div class="profile-avatar">{{ $initial }}</div>
                <div>
                    <div class="profile-eyebrow">{{ __('User Data') }}</div>
                    <h1>{{ $user->name }}</h1>
                    <p>{{ $user->email }}</p>
                </div>
                <div class="profile-actions">
                    <button class="btn btn-profile-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#profileEditForm" aria-expanded="false">
                        {{ __('Edit') }}
                    </button>
                    <a class="btn btn-profile-ghost btn-sm" href="{{ route('user.plans.index') }}">
                        {{ __('Plans') }}
                    </a>
                </div>
            </div>

            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="label">{{ __('Selected plan') }}</div>
                    <div class="value">{{ $selectedPlanName }}</div>
                </div>
                <div class="profile-stat">
                    <div class="label">{{ __('Alice Access') }}</div>
                    <div class="value">{{ $user->alice_enabled ? __('Enabled') : __('Disabled') }}</div>
                </div>
                <div class="profile-stat">
                    <div class="label">Yandex Alice</div>
                    <div class="value">{{ $aliceConnected ? __('Connected') : __('Not connected') }}</div>
                </div>
                <div class="profile-stat">
                    <div class="label">{{ __('payments') }}</div>
                    <div class="value">{{ $paymentsCount }}</div>
                </div>
            </div>
        </section>

        <div class="profile-layout">
            <div class="profile-stack">
                <section class="profile-panel">
                    <div class="profile-panel-head">
                        <div>
                            <h2 class="profile-panel-title">{{ __('User Data') }}</h2>
                            <p class="profile-panel-subtitle">{{ __('Current Time Zone') }} / {{ __('Current Language') }}</p>
                        </div>
                    </div>
                    <div class="profile-panel-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="k">{{ __('Name') }}</div>
                                <div class="v">{{ $user->name }}</div>
                            </div>
                            <div class="info-item">
                                <div class="k">E-mail</div>
                                <div class="v">{{ $user->email }}</div>
                            </div>
                            <div class="info-item">
                                <div class="k">{{ __('Current Time Zone') }}</div>
                                <div class="v">{{ $user->time_zone ?? 'Europe/Moscow' }}</div>
                            </div>
                            <div class="info-item">
                                <div class="k">{{ __('Current Language') }}</div>
                                <div class="v">{{ strtoupper((string) ($user->locale ?? 'ru')) }}</div>
                            </div>
                        </div>

                        @if (session('status') === 'profile-updated')
                            <div class="alert alert-success py-2 mt-3 mb-0">{{ __('Profile updated.') }}</div>
                        @endif

                        <div id="profileEditForm" class="collapse mt-3">
                            <form method="post" action="{{ route('profile.update') }}" class="settings-form">
                                @csrf
                                @method('patch')

                                <div>
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required maxlength="255">
                                    @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div>
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

                                <div>
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

                                <div>
                                    <button type="submit" class="btn btn-profile-primary btn-sm">{{ __('Save') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="profile-panel">
                    <div class="payments-shell">
                        <div class="payments-head">
                            <div>
                                <h2 class="payments-title">{{ __('Recent payments') }}</h2>
                                <p class="payments-subtitle">{{ __('History of tariff and connected services payments.') }}</p>
                            </div>
                            <a class="payments-link" href="{{ route('user.plans.index') }}">{{ __('Plans') }}</a>
                        </div>
                        <div class="payments-body">
                            @if ($paymentOrders->isEmpty())
                                <p class="small-muted mb-0">{{ __('No payments yet.') }}</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 payments-table">
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
                                                <td class="td-id">#{{ $order->id }}</td>
                                                <td>{{ $order->plan?->name ?? '—' }}</td>
                                                <td class="td-amount">{{ number_format((float) $order->amount, 2, '.', ' ') }} {{ $order->currency }}</td>
                                                <td>
                                                    <span class="status-chip {{ $statusClass($order->status) }}">{{ $statusLabel($order->status) }}</span>
                                                </td>
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
                </section>
            </div>

            <aside class="profile-stack">
                <section class="profile-panel">
                    <div class="profile-panel-head">
                        <div>
                            <h2 class="profile-panel-title">Yandex Alice</h2>
                            <p class="profile-panel-subtitle">{{ __('Alice Access') }}</p>
                        </div>
                    </div>
                    <div class="profile-panel-body">
                        <div class="integration-card mb-3">
                            <div class="integration-icon">A</div>
                            <div>
                                <h3>Yandex Alice</h3>
                                <p>{{ __('Available for Alice') }}</p>
                                <div class="d-flex flex-wrap gap-2">
                                    @if ($user->alice_enabled)
                                        <span class="status-chip ok">{{ __('Enabled') }}</span>
                                    @else
                                        <span class="status-chip danger">{{ __('Disabled') }}</span>
                                    @endif

                                    @if ($aliceConnected)
                                        <span class="status-chip ok">{{ __('Connected') }}</span>
                                    @else
                                        <span class="status-chip">{{ __('Not connected') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if (session('alice-status'))
                            <div class="alert alert-success py-2 mb-3">{{ session('alice-status') }}</div>
                        @endif
                        @if (session('alice-error'))
                            <div class="alert alert-danger py-2 mb-3">{{ session('alice-error') }}</div>
                        @endif

                        <div class="integration-actions">
                            <a href="{{ route('profile.alice.connect') }}" class="btn btn-profile-primary btn-sm @if (!config('services.alice.enabled') || !($user->alice_enabled ?? false)) disabled @endif">
                                {{ __('Connect Alice') }}
                            </a>

                            @if ($aliceLinkedAccount)
                                <form method="post" action="{{ route('profile.alice.disconnect') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-profile-ghost btn-sm">
                                        {{ __('Disconnect Alice') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
