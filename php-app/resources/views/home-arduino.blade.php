<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AiDvor Docs — Разделы</title>
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
    <style>
        .wrap { width: min(1100px, 100% - 32px); margin: 0 auto; padding: 24px 0 36px; }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; gap: 12px; flex-wrap: wrap; }
        .btn { text-decoration: none; color: #fff; background: var(--accent); padding: 9px 14px; border-radius: 10px; font-weight: 600; font-size: 14px; display: inline-block; }
        .btn:hover { background: #155dc3; color: #fff; }
        .hero, .card { border: 1px solid var(--line); background: #fff; border-radius: 16px; box-shadow: 0 8px 22px rgba(17,34,68,.06); }
        .hero { padding: 18px 20px; margin-bottom: 14px; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
        .card { padding: 14px 16px; }
        h1, h2, h3 { margin: 0 0 10px; letter-spacing: -0.01em; }
        p, li { color: var(--muted); }
        p { margin: 0 0 10px; }
        .go { color: var(--accent); text-decoration: none; font-weight: 600; display: inline-block; margin-top: 8px; }
        .go:hover { text-decoration: underline; }
    </style>
</head>
<body class="theme-body">
<div class="wrap">
    <div class="top">
        <h2>AiDvor · Документация</h2>
        <a class="btn" href="{{ url('/') }}">На главную</a>
    </div>

    <section class="hero">
        <h1>Разделы документации</h1>
        <p>Каждая тема вынесена на отдельную страницу для расширенного описания.</p>
    </section>

    <section class="grid">
        <article class="card">
            <h2>1. Сборка контроллера (Arduino UNO)</h2>
            <p>Комплектующие, схема подключения, распиновка и базовый запуск.</p>
            <a class="go" href="{{ route('home-arduino.controller-build') }}">Открыть раздел →</a>
        </article>
        <article class="card">
            <h2>2. OpenWrt Proxy и AiDvor</h2>
            <p>Установка, настройка gateway и рабочий минимальный набор параметров.</p>
            <a class="go" href="{{ route('home-arduino.openwrt-proxy') }}">Открыть раздел →</a>
        </article>
        <article class="card">
            <h2>3. Возможности home.aidvor.ru (FAQ)</h2>
            <p>Привязка, сценарии, дашборд, графики и типовые ответы на вопросы.</p>
            <a class="go" href="{{ route('home-arduino.site-faq') }}">Открыть раздел →</a>
        </article>
        <article class="card">
            <h2>4. Контракт API controller/report</h2>
            <p>Формат запроса к серверу, описание полей и формат ответа для контроллера.</p>
            <a class="go" href="{{ route('home-arduino.server-contract') }}">Открыть раздел →</a>
        </article>
    </section>
</div>
</body>
</html>
