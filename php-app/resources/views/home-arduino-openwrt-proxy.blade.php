<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OpenWrt Proxy — AiDvor</title>
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
    <style>
        .docs-wrap {
            width: min(1100px, 100% - 32px);
            margin: 0 auto;
            padding: 20px 0 36px;
        }
        .docs-back {
            display: inline-flex;
            text-decoration: none;
            color: var(--accent);
            margin-bottom: 12px;
            font-weight: 600;
        }
        .docs-block {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 8px 24px rgba(17, 34, 68, 0.06);
            margin-bottom: 14px;
        }
        .docs-block h1,
        .docs-block h2 {
            margin: 0 0 10px;
            letter-spacing: -0.02em;
        }
        .docs-block p,
        .docs-block li {
            color: var(--muted);
            line-height: 1.6;
        }
        .docs-block ul,
        .docs-block ol {
            margin: 0;
            padding-left: 20px;
        }
        .field-list {
            display: grid;
            gap: 12px;
            margin-top: 8px;
        }
        .field-item {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px 12px;
            background: #f8fbff;
        }
        .field-title {
            margin: 0 0 6px;
            font-size: 1.02rem;
            color: var(--text);
            font-weight: 700;
        }
        .field-desc {
            margin: 0;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .dl-list a {
            color: var(--accent);
            text-decoration: none;
        }
        .dl-list a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="theme-body">
<div class="docs-wrap">
    <a class="docs-back" href="{{ route('home-arduino') }}">← К разделам</a>

    <section class="docs-block">
        <h1>Прокси OpenWrt и AiDvor</h1>
        <p>Gateway принимает данные от контроллера в локальной сети и передаёт их на cloud-сервер от своего имени. Это отдельный уровень между Arduino и интернетом, который упрощает эксплуатацию и повышает безопасность.</p>
    </section>

    <section class="docs-block">
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

    <section class="docs-block">
        <h2>Установка</h2>
        <ol>
            <li>Разверните runtime в <code>/opt/home-openwrt</code>.</li>
            <li>Подключите init сервис <code>home-aidvor</code>.</li>
            <li>Установите LuCI-страницу <code>Services → AiDvor</code>.</li>
            <li>Перезапустите сервис и проверьте <code>/api/system/status</code>.</li>
        </ol>
    </section>

    @php
        $downloadBaseUrl = rtrim((string) config('openwrt-downloads.base_url', '/downloads/openwrt'), '/');
        $packageName = (string) config('openwrt-downloads.package_name', 'home-aidvor');
        $packageVersion = (string) config('openwrt-downloads.version', '24.10.5');
        $architectures = (array) config('openwrt-downloads.architectures', []);
    @endphp
    <section class="docs-block">
        <h2>Скачать .ipk</h2>
        <p>Выберите пакет для архитектуры вашего роутера (см. <code>opkg print-architecture</code>).</p>
        <ul class="dl-list">
            @foreach ($architectures as $arch)
                @php
                    $file = sprintf('%s_%s_%s.ipk', $packageName, $packageVersion, $arch);
                    $url = $downloadBaseUrl . '/' . $file;
                @endphp
                <li>
                    <strong>{{ $arch }}</strong>:
                    <a href="{{ $url }}">{{ $file }}</a>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="docs-block">
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
                <p class="field-title">Логика реле</p>
                <p class="field-desc">Инверсия логики реле настраивается только в скетче контроллера. Gateway и сервер всегда работают в модели: <code>1 = Включено</code>, <code>0 = Выключено</code>.</p>
            </div>
            <div class="field-item">
                <p class="field-title"><code>proxy_id</code></p>
                <p class="field-desc">Идентификатор шлюза в HMAC-контракте; должен совпадать с настройкой на сервере. Безопасный пример: <code>getaway</code> или <code>greenhouse-gw-01</code></p>
            </div>
        <div class="field-item">
            <p class="field-title"><code>proxy_secret</code></p>
            <p class="field-desc">Секрет HMAC хранится в UCI и не показывается в форме. Обычно обновляется автоматически после успешной авторизации gateway на сервере.</p>
            <p class="field-desc">Как обновить/восстановить: <br>1) На странице AiDvor нажмите кнопку авторизации gateway и получите код. <br>2) Подтвердите код в профиле на <code>home.aidvor.ru</code>. <br>3) После статуса <code>approved</code> gateway получит новый секрет и сохранит его в UCI автоматически.</p>
        </div>
        </div>

    </section>
</div>
</body>
</html>
