<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>home.arduino.ru — Разделы</title>
    <link rel="stylesheet" href="{{ asset('assets/docs.css') }}">
</head>
<body>
<div class="wrap">
    <div class="top">
        <h2>home.arduino.ru · Документация</h2>
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
            <h2>2. OpenWrt Proxy и Home Aidvor</h2>
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
