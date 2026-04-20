<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OpenWrt Proxy — home.arduino.ru</title>
    <link rel="stylesheet" href="{{ asset('assets/docs.css') }}">
</head>
<body>
<div class="wrap">
    <p><a class="back" href="{{ route('home-arduino') }}">← К разделам</a></p>
    <section class="block">
        <h1>Прокси OpenWrt и Home Aidvor</h1>
        <p>Gateway принимает данные от контроллера в локальной сети и передаёт их на cloud-сервер от своего имени. Это отдельный уровень между Arduino и интернетом, который упрощает эксплуатацию и повышает безопасность.</p>
    </section>
    <section class="block">
        <h2>Почему трафик идёт через proxy</h2>
        <ul>
            <li><strong>Ограничения Arduino UNO:</strong> UNO с Ethernet Shield работает по простому HTTP-контракту и не тратит ресурсы на сложную криптографию, управление сессиями и TLS.</li>
            <li><strong>Единая точка безопасности:</strong> HMAC-подпись выполняет gateway, а сервер доверяет только подписанным запросам от известного proxy_id.</li>
            <li><strong>Стабильный локальный endpoint:</strong> контроллер всегда отправляет на один адрес в LAN (например, <code>192.168.0.1:3001</code>), даже если внешний сервер меняется.</li>
            <li><strong>Offline-режим:</strong> при проблемах с интернетом или ошибке авторизации gateway может сохранить локальную работу и отдать контроллеру fallback-ответ.</li>
            <li><strong>Упрощение обновлений:</strong> изменения протокола и логики можно внедрять на gateway без перепрошивки всех контроллеров.</li>
            <li><strong>Масштабирование:</strong> несколько контроллеров могут работать через один роутер и единый механизм авторизации/мониторинга.</li>
        </ul>
    </section>
    <section class="block">
        <h2>Установка</h2>
        <ol>
            <li>Разверните runtime в <code>/opt/home-openwrt</code>.</li>
            <li>Подключите init сервис <code>home-aidvor</code>.</li>
            <li>Установите LuCI-страницу <code>Services → Home Aidvor</code>.</li>
            <li>Перезапустите сервис и проверьте <code>/api/system/status</code>.</li>
        </ol>
    </section>
    <section class="block">
        <h2>Минимальные настройки</h2>
        <div class="field-list">
            <div class="field-item">
                <p class="field-title"><code>cloud_base_url</code></p>
                <p class="field-desc">Базовый адрес cloud-сервера. Рекомендуется HTTPS. Безопасный пример: <code>https://home.aidvor.ru</code></p>
            </div>
            <div class="field-item">
                <p class="field-title"><code>port</code></p>
                <p class="field-desc">Локальный порт gateway в LAN, куда отправляет контроллер. Безопасный пример: <code>3001</code></p>
            </div>
            <div class="field-item">
                <p class="field-title"><code>manual_pins</code></p>
                <p class="field-desc">Список локально управляемых цифровых пинов (CSV) для offline-режима. Для вашей схемы: <code>D3,D5,D6,D9</code></p>
            </div>
            <div class="field-item">
                <p class="field-title"><code>manual_pin_invert</code></p>
                <p class="field-desc">Список пинов с инверсной логикой (CSV). Если инверсии нет: пусто. Если все реле инверсные: <code>D3,D5,D6,D9</code></p>
            </div>
            <div class="field-item">
                <p class="field-title"><code>proxy_id</code></p>
                <p class="field-desc">Идентификатор шлюза в HMAC-контракте; должен совпадать с настройкой на сервере. Безопасный пример: <code>getaway</code> или <code>greenhouse-gw-01</code></p>
            </div>
        <div class="field-item">
            <p class="field-title"><code>proxy_secret</code></p>
            <p class="field-desc">Секрет HMAC хранится в UCI и не показывается в форме. Обычно обновляется автоматически после успешной авторизации gateway на сервере.</p>
            <p class="field-desc">Как обновить/восстановить: <br>1) На странице Home Aidvor нажмите кнопку авторизации gateway и получите код. <br>2) Подтвердите код в профиле на <code>home.aidvor.ru</code>. <br>3) После статуса <code>approved</code> gateway получит новый секрет и сохранит его в UCI автоматически.</p>
        </div>
        </div>

    </section>
</div>
</body>
</html>
