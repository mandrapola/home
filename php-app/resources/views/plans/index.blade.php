<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Plans') }}</h2>
    </x-slot>

    <style>
        .pricing-page {
            --bg: #07110d;
            --card: #10231a;
            --card-2: #13291f;
            --text: #eef8f1;
            --muted: #9db2a5;
            --accent: #42c779;
            --accent-2: #9af0b1;
            --accent-soft: rgba(66, 199, 121, 0.14);
            --border: rgba(171, 219, 185, 0.16);
            --danger-soft: rgba(255, 174, 66, 0.13);
            --shadow: 0 22px 70px rgba(0, 0, 0, 0.36);
            color: var(--text);
        }

        .pricing-page a { color: inherit; text-decoration: none; }

        .hero {
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 40px 24px 26px;
            text-align: center;
            background:
                radial-gradient(circle at 10% 0%, rgba(66, 199, 121, 0.18), transparent 34%),
                radial-gradient(circle at 92% 6%, rgba(61, 135, 255, 0.11), transparent 30%),
                linear-gradient(180deg, #07110d 0%, #091711 46%, #06100c 100%);
            box-shadow: var(--shadow);
        }

        .badge-soft {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            border: 1px solid rgba(66, 199, 121, 0.2);
            color: var(--accent-2);
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .hero h1 {
            margin: 0 auto 14px;
            max-width: 930px;
            font-size: clamp(36px, 5vw, 62px);
            line-height: 1.03;
            letter-spacing: -0.06em;
            color: var(--text);
        }

        .hero p {
            max-width: 860px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 19px;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .plan-card {
            background: linear-gradient(180deg, rgba(16, 35, 26, 0.94), rgba(12, 27, 20, 0.94));
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: 0 10px 34px rgba(0, 0, 0, 0.18);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .plan-card.is-selected {
            border-color: rgba(66, 199, 121, 0.72);
            box-shadow: 0 24px 70px rgba(66, 199, 121, 0.14), var(--shadow);
            transform: translateY(-6px);
        }

        .plan-card .card-body {
            padding: 24px;
            display:grid;
            grid-template-rows: 44px 64px 92px minmax(172px, 1fr) auto;
            height:100%;
            min-height: 466px;
        }
        .plan-title-zone,
        .plan-price-zone,
        .plan-description-zone,
        .plan-limits-zone,
        .plan-actions { min-width: 0; }
        .plan-title-zone { display:flex; align-items:flex-start; }
        .plan-price-zone { display:flex; align-items:center; }
        .plan-description-zone {
            overflow:hidden;
            color: var(--muted);
            line-height: 1.5;
        }
        .plan-card .h5 { color: var(--text); margin-bottom: 0; }
        .plan-description { color: var(--muted); margin: 0; }

        .price-main {
            margin: 0;
            font-size: 42px;
            line-height: 1;
            font-weight: 950;
            letter-spacing: -0.06em;
            color: var(--text);
        }

        .price-sub { margin: 0 0 18px; color: var(--muted); font-size: 14px; }

        .plan-card ul { list-style:none; margin:0; padding:0; }
        .plan-card li { color: var(--muted); margin-bottom: 8px; padding-left: 16px; position: relative; }
        .plan-card li::before { content: "✓"; color: var(--accent); position:absolute; left:0; }

        .plan-limits-zone { align-content:flex-start; }
        .plan-actions { align-self:end; }
        .plan-card .btn { min-height: 44px; border-radius: 999px; font-weight: 800; }
        .plan-card .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #07110d;
            border: 0;
        }
        .plan-card .btn-success {
            background: rgba(66, 199, 121, 0.16);
            border-color: rgba(66, 199, 121, 0.26);
            color: var(--text);
        }
        .plan-card .btn-outline-primary {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--border);
            color: var(--text);
        }
        .btn-placeholder { visibility:hidden; pointer-events:none; }

        .section-title {
            margin: 0 0 14px;
            font-size: clamp(28px, 3vw, 44px);
            line-height: 1.12;
            letter-spacing: -0.045em;
            color: var(--text);
        }
        .section-kicker { color: var(--accent); font-weight: 900; margin-bottom: 10px; }
        .section-desc { color: var(--muted); margin:0; font-size:18px; }

        .compare-wrap {
            overflow-x:auto;
            border:1px solid var(--border);
            border-radius:26px;
            background: rgba(16, 35, 26, 0.72);
            box-shadow: 0 10px 34px rgba(0, 0, 0, 0.18);
        }
        .compare-wrap table { width:100%; border-collapse:collapse; min-width:860px; }
        .compare-wrap th, .compare-wrap td { padding: 16px 18px; border-bottom:1px solid var(--border); text-align:left; }
        .compare-wrap th { background: rgba(255,255,255,0.035); color: var(--text); }
        .compare-wrap td { color: var(--muted); }

        .integrator {
            display:grid;
            grid-template-columns: 1.1fr .9fr;
            gap:24px;
            align-items: stretch;
            background: radial-gradient(circle at 88% 0%, rgba(154, 240, 177, 0.18), transparent 34%), linear-gradient(135deg, #0f2119, #1b5c37);
            border:1px solid rgba(154,240,177,.2);
            border-radius:32px;
            padding:36px;
            box-shadow: var(--shadow);
        }
        .integrator p { color: rgba(255,255,255,.78); font-size: 18px; }
        .integrator-list { display:grid; gap:10px; align-content:center; }
        .integrator-list div { background: rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18); border-radius:16px; padding:13px 16px; font-weight:800; }

        .faq-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
        .faq-item {
            background: linear-gradient(180deg, rgba(16, 35, 26, 0.92), rgba(12, 27, 20, 0.92));
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 22px;
        }
        .faq-item h3 { margin: 0 0 10px; color: var(--text); }
        .faq-item p { margin: 0; color: var(--muted); }

        .cta {
            text-align:center;
            background: linear-gradient(180deg, rgba(16, 35, 26, 0.94), rgba(12, 27, 20, 0.94));
            border:1px solid var(--border);
            border-radius:34px;
            padding:54px 24px;
            box-shadow: var(--shadow);
        }
        .cta h2 { max-width:780px; margin:0 auto 16px; color: var(--text); }
        .cta p { max-width:680px; margin:0 auto 28px; color: var(--muted); font-size:18px; }

        @media (max-width: 1080px) {
            .pricing-grid { grid-template-columns: 1fr 1fr; }
            .integrator { grid-template-columns: 1fr; }
        }
        @media (max-width: 820px) {
            .faq-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .pricing-grid { grid-template-columns: 1fr; }
            .plan-card.is-selected { transform:none; }
            .integrator, .cta { border-radius: 24px; padding: 28px 18px; }
        }
    </style>

    <div class="pricing-page">
        <div class="row g-3">
            <div class="col-12">
                <section class="hero">
                    <div class="badge-soft">{{ __('Pricing badge') }}</div>
                    <h1>{{ __('Pricing hero title') }}</h1>
                    <p>{{ __('Pricing hero description') }}</p>
                </section>
            </div>

            @if (session('status'))
                <div class="col-12 plans-note">
                    <div class="alert alert-success mb-0">{{ session('status') }}</div>
                </div>
            @endif

            @if ($usageSummary)
                <div class="col-12 plans-note">
                    <div class="alert alert-secondary mb-0">
                        <strong>{{ __('Effective plan') }}:</strong> {{ $usageSummary['effective_plan']?->name ?? '—' }}
                        · {{ __('pin_data limit') }}: {{ $usageSummary['pin_data_used'] }}/{{ $usageSummary['pin_data_max'] ?? '∞' }}
                        · {{ __('Scenarios') }}: {{ $usageSummary['scenarios_used'] }}/{{ $usageSummary['scenarios_max'] ?? '∞' }}
                        · {{ __('Scenario Conditions') }}: {{ $usageSummary['scenario_conditions_used'] }}/{{ $usageSummary['scenario_conditions_max'] ?? '∞' }}
                    </div>
                </div>
            @endif

            <div class="col-12">
                <section id="plans" class="pricing-grid">
                    @forelse ($plans as $plan)
                        @php($monthlyPriceUnits = (int) ($plan->daily_price_units ?? 0) * 31)
                        <article class="plan-card {{ (int) $selectedPlanId === (int) $plan->id ? 'is-selected' : '' }}">
                            <div class="card-body">
                                <div class="plan-title-zone">
                                    <h3 class="h5">{{ $plan->name }}</h3>
                                </div>
                                <div class="plan-price-zone">
                                    <div class="price-main">{{ number_format($monthlyPriceUnits, 0, '.', ' ') }} {{ __('units/month') }}</div>
                                </div>
                                <div class="plan-description-zone">
                                    <p class="plan-description">
                                        @if ($plan->description)
                                            {{ $plan->description }}
                                        @else
                                            &nbsp;
                                        @endif
                                    </p>
                                </div>
                                <ul class="plan-limits-zone">
                                    <li>{{ __('Minimum report interval') }}: {{ max(\App\Models\IoTController::MIN_INTERVAL_SECONDS, (int) ($plan->min_report_interval_seconds ?? 0)) }} {{ __('sec') }}</li>
                                    <li>{{ __('pin_data limit') }}: {{ (int) ($plan->max_pin_data_rows ?? 0) > 0 ? number_format((int) $plan->max_pin_data_rows, 0, '.', ' ') : __('No limit') }}</li>
                                    <li>{{ __('Scenarios') }}: {{ (int) ($plan->max_scenarios ?? 0) > 0 ? number_format((int) $plan->max_scenarios, 0, '.', ' ') : __('No limit') }}</li>
                                    <li>{{ __('Scenario Conditions') }}: {{ (int) ($plan->max_scenario_conditions ?? 0) > 0 ? number_format((int) $plan->max_scenario_conditions, 0, '.', ' ') : __('No limit') }}</li>
                                </ul>
                                <div class="plan-actions d-grid gap-2">
                                    <form method="POST" action="{{ route('user.plans.select', $plan) }}">
                                        @csrf
                                        <button type="submit" class="btn {{ (int) $selectedPlanId === (int) $plan->id ? 'btn-outline-primary' : 'btn-primary' }} w-100" @if ((int) $selectedPlanId === (int) $plan->id) disabled @endif>
                                            {{ (int) $selectedPlanId === (int) $plan->id ? __('Selected') : __('Choose Plan') }}
                                        </button>
                                    </form>
                                    @if ($monthlyPriceUnits > 0)
                                        <form method="POST" action="{{ route('user.plans.pay', $plan) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-success w-100">{{ __('Pay') }}</button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-success w-100 btn-placeholder" disabled aria-hidden="true">{{ __('Pay') }}</button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="alert alert-warning mb-0">{{ __('No active plans available.') }}</div>
                    @endforelse
                </section>
            </div>

            <div class="col-12 mt-2">
                <div class="section-kicker">{{ __('Pricing compare kicker') }}</div>
                <h2 class="section-title">{{ __('Pricing compare title') }}</h2>
                <p class="section-desc mb-3">{{ __('Pricing compare desc') }}</p>
                <div class="compare-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>{{ __('Feature') }}</th>
                            @foreach ($plans as $plan)
                                <th>{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>{{ __('Price') }}</td>
                            @foreach ($plans as $plan)
                                <td>{{ number_format((int) ($plan->daily_price_units ?? 0) * 31, 0, '.', ' ') }} {{ __('units/month') }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>{{ __('Minimum report interval') }}</td>
                            @foreach ($plans as $plan)
                                <td>{{ max(\App\Models\IoTController::MIN_INTERVAL_SECONDS, (int) ($plan->min_report_interval_seconds ?? 0)) }} {{ __('sec') }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>{{ __('pin_data limit') }}</td>
                            @foreach ($plans as $plan)
                                <td>{{ (int) ($plan->max_pin_data_rows ?? 0) > 0 ? number_format((int) $plan->max_pin_data_rows, 0, '.', ' ') : __('No limit') }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>{{ __('Scenarios') }}</td>
                            @foreach ($plans as $plan)
                                <td>{{ (int) ($plan->max_scenarios ?? 0) > 0 ? number_format((int) $plan->max_scenarios, 0, '.', ' ') : __('No limit') }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>{{ __('Scenario Conditions') }}</td>
                            @foreach ($plans as $plan)
                                <td>{{ (int) ($plan->max_scenario_conditions ?? 0) > 0 ? number_format((int) $plan->max_scenario_conditions, 0, '.', ' ') : __('No limit') }}</td>
                            @endforeach
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12 mt-2">
                <section class="integrator" id="integrator">
                    <div>
                        <div class="section-kicker">{{ __('For integrators') }}</div>
                        <h2 class="section-title">{{ __('Integrator title') }}</h2>
                        <p>{{ __('Integrator description') }}</p>
                    </div>
                    <div class="integrator-list">
                        <div>{{ __('White-label dashboards') }}</div>
                        <div>{{ __('Role-based access') }}</div>
                        <div>{{ __('Alice and API integration') }}</div>
                        <div>{{ __('Priority support') }}</div>
                    </div>
                </section>
            </div>

            <div class="col-12 mt-2" id="faq">
                <div class="section-kicker">FAQ</div>
                <h2 class="section-title">{{ __('FAQ title') }}</h2>
                <div class="faq-grid">
                    <div class="faq-item">
                        <h3>{{ __('FAQ q1') }}</h3>
                        <p>{{ __('FAQ a1') }}</p>
                    </div>
                    <div class="faq-item">
                        <h3>{{ __('FAQ q2') }}</h3>
                        <p>{{ __('FAQ a2') }}</p>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-2">
                <section class="cta">
                    <h2>{{ __('Pricing cta title') }}</h2>
                    <p>{{ __('Pricing cta desc') }}</p>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a class="btn btn-primary" href="{{ route('register') }}">{{ __('Start Free') }}</a>
                        <a class="btn btn-outline-light" href="{{ route('home-arduino') }}">{{ __('Documentation') }}</a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
