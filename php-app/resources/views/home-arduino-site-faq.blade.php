<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FAQ — home.aidvor.ru</title>
    <link rel="stylesheet" href="{{ asset('assets/docs.css') }}">
</head>
<body>
<div class="wrap">
    <p><a class="back" href="{{ route('home-arduino') }}">← К разделам</a></p>
    <section class="block">
        <h1>FAQ по возможностям home.aidvor.ru</h1>
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
            <h3>Что при ошибке HMAC/недоступности сервера?</h3>
            <p>Gateway уходит в offline и сохраняет локальное управление. После восстановления связи доступна повторная авторизация шлюза.</p>
        </div>
    </section>
</div>
</body>
</html>
