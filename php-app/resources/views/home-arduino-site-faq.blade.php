<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FAQ — AiDvor</title>
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
    @include('layouts.theme-init')
    <style>
        .wrap { width: min(1100px, 100% - 32px); margin: 0 auto; padding: 24px 0 36px; }
        .back { color: var(--accent); text-decoration: none; font-weight: 600; }
        .back:hover { text-decoration: underline; }
        .block { border: 1px solid var(--line); background: var(--card); border-radius: 16px; box-shadow: 0 8px 22px rgba(17,34,68,.06); padding: 16px 18px; margin-bottom: 12px; }
        h1, h2, h3 { margin: 0 0 10px; letter-spacing: -0.01em; }
        p { color: var(--muted); line-height: 1.6; margin: 0; }
        .item { border-top: 1px solid var(--line); padding-top: 10px; margin-top: 10px; }
    </style>
</head>
<body class="theme-body">
<div class="wrap">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">
        <p class="mb-0"><a class="back" href="{{ route('home-arduino') }}">← К разделам</a></p>
        @include('layouts.theme-switcher', ['compact' => true, 'id' => 'faq_theme_switcher'])
    </div>
    <section class="block">
        <h1>FAQ по возможностям AiDvor</h1>
        <p>Короткие ответы по ключевым функциям платформы.</p>
        <div class="item">
            <h3>Как привязать контроллер?</h3>
            <p>Откройте страницу добавления контроллера, запросите код привязки и подтвердите его в профиле.</p>
        </div>
        <div class="item">
            <h3>Что доступно после привязки?</h3>
            <p>Дашборд контроллеров, карточки пинов, сценарии, графики датчиков и профиль пользователя.</p>
        </div>
        <div class="item">
            <h3>Как работают сценарии?</h3>
            <p>Условия внутри одного сценария объединяются по И, несколько сценариев для цели работают по ИЛИ.</p>
        </div>
        <div class="item">
            <h3>Что при ошибке авторизации/недоступности сервера?</h3>
            <p>ESP8266 показывает локальный интерфейс и применяет настроенную защиту реле. При утрате токена контроллер проходит привязку заново.</p>
        </div>
    </section>
</div>
@include('layouts.theme-runtime')
</body>
</html>
