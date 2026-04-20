<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Smart Home') }}</title>
    <style>
        :root {
            --bg: #0f1724;
            --bg-soft: #182638;
            --card: #1f3148;
            --text: #e7edf6;
            --muted: #a7b6c9;
            --accent: #2ec5ff;
            --accent-2: #58d68d;
            --line: #2b415c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background:
                linear-gradient(rgba(9, 17, 28, 0.78), rgba(12, 22, 34, 0.82)),
                url('{{ asset('assets/bg.png') }}') center / cover no-repeat fixed,
                linear-gradient(160deg, var(--bg) 0%, var(--bg-soft) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .shell {
            width: min(980px, 100%);
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(21, 33, 49, 0.84);
            backdrop-filter: blur(4px);
            box-shadow: 0 20px 40px rgba(4, 10, 19, 0.45);
            overflow: hidden;
        }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
        }
        .brand { font-weight: 700; letter-spacing: 0.4px; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            display: inline-block;
            text-decoration: none;
            border: 1px solid var(--line);
            color: var(--text);
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 14px;
        }
        .btn:hover { border-color: var(--accent); }
        .btn-primary {
            border-color: transparent;
            background: linear-gradient(135deg, var(--accent), #35a6ff);
            color: #072235;
            font-weight: 600;
        }
        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 18px;
            padding: 24px;
        }
        .panel {
            border: 1px solid var(--line);
            background: rgba(31, 49, 72, 0.66);
            border-radius: 14px;
            padding: 18px;
        }
        h1 {
            margin: 0 0 12px;
            font-size: clamp(26px, 4vw, 40px);
            line-height: 1.1;
        }
        p { margin: 0 0 12px; color: var(--muted); }
        .kpis {
            display: grid;
            grid-template-columns: repeat(2, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .kpi {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
            background: rgba(12, 22, 34, 0.55);
        }
        .kpi b { display: block; font-size: 18px; margin-bottom: 3px; }
        .kpi span { color: var(--muted); font-size: 13px; }
        .list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.7; }
        .foot {
            border-top: 1px solid var(--line);
            padding: 12px 20px;
            color: var(--muted);
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        @media (max-width: 860px) {
            .hero { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="top">
        <div class="brand">Aidvor Control</div>
        <div class="nav">
            <a href="{{ route('home-arduino') }}" class="btn">Гид Aidvor</a>
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary">Открыть дашборд</a>
                @else
                    <a href="{{ route('login') }}" class="btn">Вход</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary">Регистрация</a>
                    @endif
                @endauth
            @endif
        </div>
    </div>

    <div class="hero">
        <section class="panel">
            <h1>Платформа управления<br>умным двором</h1>
            <p>Контроллеры отправляют телеметрию на сервер, сценарии принимают решения, а вы управляете всем из единого интерфейса.</p>
            <p>Система поддерживает автоматизацию реле, мониторинг датчиков и режим привязки контроллеров к пользователю.</p>

            <div class="kpis">
                <div class="kpi">
                    <b>24/7</b>
                    <span>Мониторинг состояния</span>
                </div>
                <div class="kpi">
                    <b>API</b>
                    <span>Контракт для контроллеров</span>
                </div>
                <div class="kpi">
                    <b>Сценарии</b>
                    <span>Условия и автоматизация</span>
                </div>
                <div class="kpi">
                    <b>Профили</b>
                    <span>Пользователи и таймзоны</span>
                </div>
            </div>
        </section>

        <aside class="panel">
            <h3 style="margin-top:0">Что доступно</h3>
            <ul class="list">
                <li>Дашборд контроллеров и пинов</li>
                <li>Редактирование настроек пинов</li>
                <li>Сценарии управления реле</li>
                <li>Привязка контроллера по коду</li>
                <li>Локальная работа контроллера по HTTP API</li>
            </ul>
            <div style="margin-top:16px; padding-top:12px; border-top:1px solid var(--line);">
                <a href="{{ url('/dashboard') }}" class="btn btn-primary">Перейти к управлению</a>
            </div>
        </aside>
    </div>

    <div class="foot">
        <span>Тема: Smart Home / Greenhouse</span>
        <span>{{ config('app.name', 'Smart Home') }}</span>
    </div>
</div>
</body>
</html>
