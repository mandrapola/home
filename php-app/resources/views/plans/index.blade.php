<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Plans') }}</h2>
    </x-slot>

    <div class="row g-3">
        @if (session('status'))
            <div class="col-12">
                <div class="alert alert-success mb-0">{{ session('status') }}</div>
            </div>
        @endif

        @if ($selectedSubscription)
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    {{ __('Selected plan') }}: <strong>{{ $selectedSubscription->plan?->name ?? '—' }}</strong>.
                    {{ __('Status') }}: <strong>{{ $selectedSubscription->status }}</strong>.
                    {{ __('If status is pending or expired, FREE limits are applied.') }}
                </div>
            </div>
        @endif
        @if ($usageSummary)
            <div class="col-12">
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
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h3 class="h5">{{ $plan->name }}</h3>
                        @if ($plan->description)
                            <p class="text-muted">{{ $plan->description }}</p>
                        @endif
                        <p class="mb-1">
                            <strong>{{ number_format((float) $plan->price_amount, 2, '.', ' ') }} {{ $plan->price_currency }}</strong>
                        </p>
                        <p class="small text-muted mb-3">
                            {{ number_format((float) $plan->price_amount, 2, '.', ' ') }} {{ $plan->price_currency === 'RUB' ? ' / ' . rtrim(rtrim(number_format((float) $plan->price_amount, 2, '.', ''), '0'), '.') . ' ₽' : '' }}
                        </p>
                        <ul class="small mb-3">
                            <li>{{ __('Controllers limit') }}: {{ $plan->max_controllers ?? __('Unlimited') }}</li>
                            <li>{{ __('pin_data limit') }}: {{ $plan->max_pin_data_rows ?? __('Unlimited') }}</li>
                            <li>{{ __('Alice integration') }}: {{ $plan->alice_enabled ? __('Enabled') : __('Disabled') }}</li>
                        </ul>
                        <form method="POST" action="{{ route('user.plans.select', $plan) }}" class="mt-auto">
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
                            <form method="POST" action="{{ route('user.plans.pay', $plan) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-success w-100">{{ __('Pay') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-warning mb-0">{{ __('No active plans available.') }}</div>
            </div>
        @endforelse

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="h6 mb-3">{{ __('Recent payments') }}</h3>
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
                                        <td><span class="badge bg-secondary">{{ $order->status }}</span></td>
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
</x-app-layout>
