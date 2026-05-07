<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'AiDvor SmartHome') }}</title>
    <style>
        :root {
            --bg: #f7f8fa;
            --text: #0a0a0a;
            --muted: #6c6c6c;
            --line: #e7e7e7;
            --card: #ffffff;
            --brand: #1f7aff;
            --brand-dark: #155dc3;
        }
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
        }
        .wrap {
            width: min(1160px, 100% - 32px);
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 0;
        }

        .brand {
            font-weight: 700;
            font-size: 18px;
            text-decoration: none;
            color: var(--text);
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .nav-link {
            color: var(--text);
            display: inline-block;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--brand);
            border-radius: 999px;
            background: var(--brand);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 18px;
            font-size: 14px;
            transition: .2s ease;
        }

        .cta:hover {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
        }

        .cta.ghost {
            background: #fff;
            color: var(--text);
            border-color: var(--line);
        }

        .hero {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 24px;
            align-items: center;
            padding: 48px 0 32px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(34px, 6vw, 64px);
            line-height: 1.03;
            letter-spacing: -0.04em;
        }

        .hero p {
            margin: 16px 0 26px;
            color: var(--muted);
            font-size: 18px;
            max-width: 560px;
        }

        .hero-card {
            background: linear-gradient(165deg, #ffffff, #f1f5ff);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 14px 40px rgba(17, 34, 68, .08);
        }

        .hero-card h3 {
            margin: 0 0 12px;
            font-size: 20px;
        }

        .hero-card ul {
            margin: 0;
            padding-left: 20px;
            color: var(--muted);
            line-height: 1.8;
        }

        .section {
            padding: 22px 0 14px;
        }

        .section-title {
            margin: 0 0 16px;
            font-size: 28px;
            letter-spacing: -0.02em;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            border: 1px solid var(--line);
            background: var(--card);
            border-radius: 16px;
            padding: 18px;
        }

        .card h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .price-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .price {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            padding: 18px;
        }

        .price b {
            display: block;
            font-size: 30px;
            margin: 10px 0 4px;
        }

        .price ul {
            margin: 0 0 16px;
            padding-left: 18px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.7;
        }

        .download {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 16px;
            padding: 20px;
        }

        .footer {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            padding: 18px 0 36px;
        }

        .footer h4 {
            margin: 0 0 8px;
            font-size: 14px;
        }

        .footer a,
        .footer p {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: 14px;
            text-decoration: none;
        }

        .footer a:hover {
            color: var(--text);
        }

        .copyright {
            border-top: 1px solid var(--line);
            padding: 14px 0 32px;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .hero,
            .grid-3,
            .price-grid,
            .footer {
                grid-template-columns: 1fr;
            }

            .download {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <a class="brand" href="{{ url('/') }}">AiDvor®</a>
        <nav class="nav">
            <a class="nav-link" href="#features">{{ __('Features') }}</a>
            <a class="nav-link" href="#pricing">{{ __('Pricing') }}</a>
            <a class="nav-link" href="#download">{{ __('Download') }}</a>
            <a class="nav-link" href="#support">{{ __('Support') }}</a>
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="cta">{{ __('Get Started') }}</a>
                @else
                    <a href="{{ route('login') }}" class="cta ghost">{{ __('Sign in') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="cta">{{ __('Get Started') }}</a>
                    @endif
                @endauth
            @endif
        </nav>
    </header>

    <section class="hero">
        <div>
            <h1>Умный двор,<br>сделать легко.</h1>
            <p>{{ __('Open, reliable control for every device.') }}</p>
            <a href="{{ url('/dashboard') }}" class="cta">{{ __('Get Started') }}</a>
        </div>
        <aside class="hero-card">
            <h3>{{ __('Powerful controllers.') }}</h3>
            <ul>
                <li>{{ __('Manage all your smart devices from a central, fast and reliable dashboard.') }}</li>
                <li>{{ __('Monitor sensors, energy, and more with near-instant updates.') }}</li>
                <li>{{ __('Automate routines and integrate with Alice for hands-free control.') }}</li>
            </ul>
        </aside>
    </section>

    <section class="section" id="features">
        <h2 class="section-title">{{ __('Features') }}</h2>
        <div class="grid-3">
            <article class="card">
                <h3>{{ __('Controllers') }}</h3>
                <p>{{ __('Provision, monitor and manage all controllers from one place.') }}</p>
            </article>
            <article class="card">
                <h3>{{ __('Telemetry') }}</h3>
                <p>{{ __('See real-time data, chart history and transformed sensor values.') }}</p>
            </article>
            <article class="card">
                <h3>{{ __('Scenes + Alice API') }}</h3>
                <p>{{ __('Build automation logic and sync with Yandex Alice integration.') }}</p>
            </article>
        </div>
    </section>

    <section class="section" id="download">
        <h2 class="section-title">{{ __('Download OpenWrt .ipk') }}</h2>
        <div class="download">
            <p style="margin:0; max-width:760px; color:var(--muted);">
                {{ __('Get AiDvor for OpenWrt routers and start managing your SmartHome securely.') }}
            </p>
            <a class="cta" href="{{ route('home-arduino.openwrt-proxy') }}">{{ __('Open Downloads') }}</a>
        </div>
    </section>

    <section class="section" id="pricing">
        <h2 class="section-title">{{ __('Simple, honest, open.') }}</h2>
        <div class="price-grid">
            <article class="price">
                <h3>{{ __('Free') }}</h3>
                <b>$0</b>
                <ul>
                    <li>{{ __('Basic support') }}</li>
                    <li>{{ __('Full telemetry') }}</li>
                    <li>{{ __('Limited plan limits') }}</li>
                </ul>
                <a class="cta ghost" href="{{ route('register') }}">{{ __('Start Free') }}</a>
            </article>
            <article class="price">
                <h3>{{ __('Pro') }}</h3>
                <b>$9</b>
                <ul>
                    <li>{{ __('Premium support') }}</li>
                    <li>{{ __('Advanced automations') }}</li>
                    <li>{{ __('Early access updates') }}</li>
                </ul>
                <a class="cta" href="{{ route('login') }}">{{ __('Buy Now') }}</a>
            </article>
        </div>
    </section>

    <section class="section footer" id="support">
        <div>
            <h4>{{ __('Support') }}</h4>
            <a href="{{ route('home-arduino.site-faq') }}">{{ __('Help Center') }}</a>
            <a href="{{ route('brand-verification') }}">{{ __('Verification') }}</a>
            <a href="{{ route('home-arduino') }}">{{ __('Documentation') }}</a>
        </div>
        <div>
            <h4>{{ __('Product') }}</h4>
            <a href="{{ route('home-arduino.controller-build') }}">{{ __('Controllers') }}</a>
            <a href="{{ route('home-arduino.server-contract') }}">{{ __('API Contract') }}</a>
            <a href="{{ route('home-arduino.openwrt-proxy') }}">{{ __('OpenWrt Package') }}</a>
        </div>
        <div>
            <h4>{{ __('Company') }}</h4>
            <p>{{ __('About') }}</p>
            <p>{{ __('Careers') }}</p>
            <p>{{ __('Blog') }}</p>
        </div>
        <div>
            <h4>{{ __('Verification') }}</h4>
            <p>{{ __('Status') }}</p>
            <p>{{ __('Brand Assets') }}</p>
            <a href="{{ route('brand-verification') }}">{{ __('Open-Source & Brand Page') }}</a>
        </div>
    </section>

    <div class="copyright">
        © {{ date('Y') }} AiDvor SmartHome
    </div>
</div>
</body>
</html>
