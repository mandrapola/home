<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Plans') }}</h2>
    </x-slot>
    <style>
        .plans-note .alert {
            border-radius: 12px;
        }
        .plan-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 22px rgba(17,34,68,.06);
            transition: box-shadow .2s ease, transform .2s ease;
        }
        .plan-card .card-body {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .plan-card .plan-header {
            min-height: 132px;
        }
        .plan-card .plan-description {
            margin-bottom: 0;
            color: var(--muted);
            line-height: 1.35;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
            min-height: calc(1em * 1.35 * 3);
        }
        .plan-card:hover {
            box-shadow: 0 12px 28px rgba(17,34,68,.09);
            transform: translateY(-2px);
        }
        .plan-card .price-main {
            font-size: 30px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 2px;
        }
        .plan-card .price-sub {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 14px;
        }
        .plan-card ul {
            padding-left: 18px;
        }
        .plan-card li {
            margin-bottom: 6px;
            color: var(--muted);
        }
        .plan-card .plan-actions {
            margin-top: auto;
        }
        .plan-card .btn {
            min-height: 44px;
        }
        .plan-card .btn-placeholder {
            visibility: hidden;
            pointer-events: none;
        }
    </style>

    <div class="row g-3">
        @if (session('status'))
            <div class="col-12 plans-note">
                <div class="alert alert-success mb-0">{{ session('status') }}</div>
            </div>
        @endif
        @if ($usageSummary)
            <div class="col-12 plans-note">
                <div class="alert alert-secondary mb-0">
                    <strong>{{ __('Effective plan') }}:</strong> {{ $usageSummary['effective_plan']?->name ?? '—' }}
                    · {{ __('Controllers') }}: {{ $usageSummary['controllers_used'] }}/{{ $usageSummary['controllers_max'] ?? '∞' }}
                    · {{ __('pin_data limit') }}: {{ $usageSummary['pin_data_used'] }}/{{ $usageSummary['pin_data_max'] ?? '∞' }}
                    · {{ __('Slots left') }}: {{ $usageSummary['controller_slots_left'] ?? '∞' }}
                </div>
            </div>
        @endif

        @forelse ($plans as $plan)
            <div class="col-12 col-lg-4">
                <div class="plan-card h-100">
                    <div class="card-body">
                        <div class="plan-header">
                            <h3 class="h5">{{ $plan->name }}</h3>
                            @if ($plan->description)
                                <p class="plan-description">{{ $plan->description }}</p>
                            @endif
                        </div>
                        <div class="price-main">{{ number_format((float) $plan->price_amount, 2, '.', ' ') }} {{ $plan->price_currency }}</div>
                        <div class="price-sub">
                            {{ number_format((float) $plan->price_amount, 2, '.', ' ') }} {{ $plan->price_currency === 'RUB' ? ' / ' . rtrim(rtrim(number_format((float) $plan->price_amount, 2, '.', ''), '0'), '.') . ' ₽' : '' }}
                        </div>
                        <ul class="small mb-3">
                            <li>{{ __('Controllers limit') }}: {{ $plan->max_controllers ?? __('Unlimited') }}</li>
                            <li>{{ __('pin_data limit') }}: {{ $plan->max_pin_data_rows ?? __('Unlimited') }}</li>
                            <li>{{ __('Alice integration') }}: {{ $plan->alice_enabled ? __('Enabled') : __('Disabled') }}</li>
                        </ul>
                        <div class="plan-actions d-grid gap-2">
                            <form method="POST" action="{{ route('user.plans.select', $plan) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="btn {{ (int) $selectedPlanId === (int) $plan->id ? 'btn-outline-primary' : 'btn-primary' }} w-100"
                                    @if ((int) $selectedPlanId === (int) $plan->id) disabled @endif
                                >
                                    {{ (int) $selectedPlanId === (int) $plan->id ? __('Selected') : __('Choose Plan') }}
                                </button>
                            </form>
                            @if ((float) $plan->price_amount > 0)
                                <form method="POST" action="{{ route('user.plans.pay', $plan) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">{{ __('Pay') }}</button>
                                </form>
                            @else
                                <button type="button" class="btn btn-success w-100 btn-placeholder" disabled aria-hidden="true">{{ __('Pay') }}</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-warning mb-0">{{ __('No active plans available.') }}</div>
            </div>
        @endforelse

    </div>
</x-app-layout>
