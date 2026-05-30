<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Сборка контроллера для теплицы — AiDvor Docs</title>
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
    @include('layouts.theme-init')
    <style>
        .wrap { width: min(1160px, 100% - 32px); margin: 0 auto; padding: 24px 0 40px; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .back { color: var(--accent); text-decoration: none; font-weight: 600; }
        .back:hover { text-decoration: underline; }
        .hero, .panel, .step, .cta, .note { border: 1px solid var(--line); background: var(--card); border-radius: 18px; box-shadow: 0 8px 22px rgba(17,34,68,.06); }
        .hero { padding: 24px; margin-bottom: 14px; }
        .hero h1 { margin: 0 0 10px; letter-spacing: -0.02em; }
        .hero p { color: var(--muted); margin: 0; line-height: 1.6; }
        .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        .anchor-nav { display: grid; grid-template-columns: repeat(5, minmax(120px, 1fr)); gap: 8px; margin-top: 14px; }
        .anchor-nav a { border: 1px solid var(--line); border-radius: 12px; padding: 8px 10px; text-align: center; font-size: 13px; color: var(--muted); text-decoration: none; background: var(--chip-bg); }
        .anchor-nav a:hover { color: var(--text); text-decoration: none; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .panel { padding: 18px; }
        h2 { margin: 0 0 10px; letter-spacing: -0.01em; }
        p, li { color: var(--muted); line-height: 1.6; }
        .parts-list { margin: 0; padding-left: 20px; }
        .pin-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .pin-table th, .pin-table td { padding: 10px 12px; border-bottom: 1px solid var(--line); text-align: left; }
        .pin-table tr:last-child td { border-bottom: 0; }
        .pin-table th { color: var(--text); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; background: var(--chip-bg); }
        .pin-chip { display: inline-flex; align-items: center; justify-content: center; min-width: 42px; padding: 4px 9px; border-radius: 999px; font-weight: 700; border: 1px solid var(--line); background: var(--chip-bg); color: var(--text); }
        .legend { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .legend-item { border: 1px solid var(--line); background: var(--chip-bg); border-radius: 999px; padding: 6px 10px; font-size: 13px; color: var(--muted); }
        .note { padding: 14px 16px; margin-bottom: 14px; }
        .note strong { color: var(--text); }
        .step-list { display: grid; gap: 12px; margin-bottom: 14px; }
        .step { padding: 18px; display: grid; grid-template-columns: 1fr 220px; gap: 14px; align-items: start; }
        .step h3 { margin: 0 0 8px; letter-spacing: -0.01em; }
        .step ul { margin: 8px 0 0; padding-left: 20px; }
        .step-num { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 999px; background: var(--accent); color: #fff; font-weight: 800; margin-right: 8px; font-size: 13px; }
        .step-diagram { border: 1px solid var(--line); background: var(--chip-bg); border-radius: 14px; min-height: 130px; padding: 10px; font-size: 13px; color: var(--muted); }
        .step-diagram code { color: var(--text); background: transparent; padding: 0; }
        .step-diagram svg { width: 100%; height: 108px; display: block; margin-top: 8px; }
        .d-board { fill: #1f90b6; }
        .d-mod { fill: #2f3d49; }
        .d-label { fill: var(--text); font-size: 11px; font-weight: 700; }
        .d-pin { fill: #f5f5f5; stroke: #0f172a; stroke-width: 1; }
        .d-pin-text { fill: #0f172a; font-size: 8px; font-weight: 700; }
        .d-wire-power { stroke: #e74c3c; stroke-width: 3; fill: none; }
        .d-wire-gnd { stroke: #121212; stroke-width: 3; fill: none; }
        .d-wire-sig { stroke: #2ecc71; stroke-width: 3; fill: none; }
        .mounting { margin-bottom: 14px; }
        .cta { padding: 22px; text-align: center; }
        .cta h2 { margin-bottom: 8px; }
        .cta-actions { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        .btn { text-decoration: none; display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--line); background: var(--chip-bg); color: var(--text); font-weight: 600; }
        .btn.btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn:hover { opacity: .92; color: inherit; }
        @media (max-width: 1080px) { .anchor-nav { grid-template-columns: repeat(3, minmax(120px, 1fr)); } }
        @media (max-width: 960px) { .grid-2, .step { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .anchor-nav { grid-template-columns: repeat(2, minmax(120px, 1fr)); } }
    </style>
</head>
<body class="theme-body">
<div class="wrap">
    <div class="top">
        <a class="back" href="{{ route('home-arduino') }}">← К разделам</a>
        @include('layouts.theme-switcher', ['compact' => true, 'id' => 'build_theme_switcher'])
    </div>

    <section class="hero">
        <h1>Сборка контроллера для теплицы</h1>
        <p>Пошаговая инструкция по сборке контроллера автополива и мониторинга теплицы для Home AiDvor. Контроллер измеряет температуру, влажность воздуха, влажность почвы и освещённость, показывает данные на дисплее и управляет четырьмя реле.</p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="/downloads/greenhouse-controller.ino">Скачать скетч</a>
            <a class="btn" href="/controllers/greenhouse">Готовый контроллер</a>
        </div>
        <nav class="anchor-nav">
            <a href="#parts">Комплектация</a>
            <a href="#pinout">Распиновка</a>
            <a href="#steps">Подключение</a>
            <a href="#mounting">Монтаж</a>
            <a href="#ready">Готовое решение</a>
        </nav>
    </section>

    <section class="grid-2" id="parts">
        <article class="panel" id="pinout">
            <h2>Комплектация</h2>
            <p>Для сборки используется только Wi‑Fi-вариант связи. Ethernet Shield в проекте не поддерживается.</p>
            <ul class="parts-list">
                <li>Arduino Uno</li>
                <li>Wireless Shield ESP8266‑12E UART WIFI</li>
                <li>Цифровой датчик DHT22</li>
                <li>Фоторезистор + резистор 10 кОм</li>
                <li>Дисплей TM1637</li>
                <li>SoilModule / Soil Moisture — датчик влажности почвы</li>
                <li>Модуль реле 4 канала</li>
            </ul>
        </article>

        <article class="panel">
            <h2>Назначение пинов Arduino Uno</h2>
            <table class="pin-table">
                <thead>
                <tr><th>Пин</th><th>Назначение</th></tr>
                </thead>
                <tbody>
                <tr><td><span class="pin-chip">D2</span></td><td>DHT22 DATA</td></tr>
                <tr><td><span class="pin-chip">D3</span></td><td>relay_1</td></tr>
                <tr><td><span class="pin-chip">D4</span></td><td>relay_2</td></tr>
                <tr><td><span class="pin-chip">D5</span></td><td>relay_3</td></tr>
                <tr><td><span class="pin-chip">D6</span></td><td>relay_4</td></tr>
                <tr><td><span class="pin-chip">A0</span></td><td>soil_moisture_raw</td></tr>
                <tr><td><span class="pin-chip">A1</span></td><td>light_level_raw</td></tr>
                <tr><td><span class="pin-chip">D7</span></td><td>TM1637 DIO</td></tr>
                <tr><td><span class="pin-chip">D8</span></td><td>TM1637 CLK</td></tr>
                <tr><td><span class="pin-chip">D10</span></td><td>ESP TX → Arduino RX</td></tr>
                <tr><td><span class="pin-chip">D11</span></td><td>ESP RX ← Arduino TX через делитель уровня</td></tr>
                </tbody>
            </table>
            <div class="legend">
                <span class="legend-item">5V — красный</span>
                <span class="legend-item">GND — чёрный</span>
                <span class="legend-item">Сигнал — зелёный</span>
            </div>
        </article>
    </section>

    <section class="note">
        <p><strong>Перед подключением:</strong> у модулей разных производителей порядок контактов может отличаться. Всегда сверяйтесь с маркировкой на вашей плате: <strong>VCC/5V</strong>, <strong>GND</strong>, <strong>DATA</strong>, <strong>AO</strong>, <strong>DIO</strong>, <strong>CLK</strong>, <strong>IN1–IN4</strong>.</p>
    </section>

    <section class="step-list" id="steps">
        <article class="step">
            <div>
                <h3><span class="step-num">1</span>Устанавливаем Wi‑Fi Shield ESP8266‑12E</h3>
                <p>ESP8266 подключается к Arduino Uno по UART: ESP TX к D10, ESP RX к D11 через делитель уровня или level shifter.</p>
                <ul>
                    <li>Совместите пины Shield с разъёмами Arduino Uno.</li>
                    <li>Установите Shield до полного посадочного положения.</li>
                    <li>Убедитесь, что земля ESP8266 и Arduino Uno объединена.</li>
                    <li>SSID, пароль Wi‑Fi и адрес сервера задаются в конфигурации ESP8266.</li>
                </ul>
            </div>
            <div class="step-diagram">
                <strong>Стек плат</strong><br>
                <code>ESP8266-12E Shield</code><br>
                ⬇<br>
                <code>Arduino Uno</code>
                <svg viewBox="0 0 260 110" aria-hidden="true">
                    <rect class="d-mod" x="18" y="18" width="92" height="36" rx="8"></rect>
                    <rect class="d-board" x="124" y="42" width="124" height="54" rx="8"></rect>
                    <text class="d-label" x="64" y="40" text-anchor="middle">Wi‑Fi Shield</text>
                    <text class="d-label" x="186" y="70" text-anchor="middle">Arduino Uno</text>
                    <path class="d-wire-sig" d="M110 44 L136 56"></path>
                    <circle class="d-pin" cx="136" cy="56" r="5"></circle><text class="d-pin-text" x="136" y="58" text-anchor="middle">D</text>
                </svg>
            </div>
        </article>

        <article class="step">
            <div>
                <h3><span class="step-num">2</span>Подключаем датчик DHT22</h3>
                <ul>
                    <li>5V → VCC датчика</li>
                    <li>GND → GND датчика</li>
                    <li>D2 → DATA датчика</li>
                </ul>
            </div>
            <div class="step-diagram">
                <strong>DHT22</strong><br>
                VCC → <code>5V</code><br>
                DATA → <code>D2</code><br>
                GND → <code>GND</code>
                <svg viewBox="0 0 260 110" aria-hidden="true">
                    <rect class="d-mod" x="10" y="28" width="74" height="52" rx="8"></rect>
                    <rect class="d-board" x="126" y="18" width="124" height="74" rx="8"></rect>
                    <text class="d-label" x="47" y="58" text-anchor="middle">DHT22</text>
                    <text class="d-label" x="188" y="48" text-anchor="middle">UNO</text>
                    <circle class="d-pin" cx="136" cy="30" r="5"></circle><text class="d-pin-text" x="136" y="32" text-anchor="middle">5V</text>
                    <circle class="d-pin" cx="162" cy="30" r="5"></circle><text class="d-pin-text" x="162" y="32" text-anchor="middle">G</text>
                    <circle class="d-pin" cx="208" cy="30" r="5"></circle><text class="d-pin-text" x="208" y="32" text-anchor="middle">D2</text>
                    <path class="d-wire-power" d="M84 38 L136 30"></path>
                    <path class="d-wire-sig" d="M84 54 L208 30"></path>
                    <path class="d-wire-gnd" d="M84 70 L162 30"></path>
                </svg>
            </div>
        </article>

        <article class="step">
            <div>
                <h3><span class="step-num">3</span>Подключаем фоторезистор</h3>
                <ul>
                    <li>5V → один вывод фоторезистора</li>
                    <li>A1 → точка между фоторезистором и резистором</li>
                    <li>Резистор 10 кОм → между A1 и GND</li>
                </ul>
            </div>
            <div class="step-diagram">
                <strong>LDR делитель</strong><br>
                LDR ↔ <code>5V</code><br>
                Узел делителя → <code>A1</code><br>
                R10k → <code>GND</code>
                <svg viewBox="0 0 260 110" aria-hidden="true">
                    <circle cx="42" cy="54" r="20" fill="#e8d38a"></circle>
                    <rect class="d-board" x="126" y="18" width="124" height="74" rx="8"></rect>
                    <text class="d-label" x="188" y="48" text-anchor="middle">UNO</text>
                    <circle class="d-pin" cx="136" cy="30" r="5"></circle><text class="d-pin-text" x="136" y="32" text-anchor="middle">5V</text>
                    <circle class="d-pin" cx="162" cy="30" r="5"></circle><text class="d-pin-text" x="162" y="32" text-anchor="middle">G</text>
                    <circle class="d-pin" cx="224" cy="80" r="5"></circle><text class="d-pin-text" x="224" y="82" text-anchor="middle">A1</text>
                    <path class="d-wire-power" d="M62 44 L136 30"></path>
                    <path class="d-wire-sig" d="M62 54 L224 80"></path>
                    <path class="d-wire-gnd" d="M62 64 L162 30"></path>
                </svg>
            </div>
        </article>

        <article class="step">
            <div>
                <h3><span class="step-num">4</span>Подключаем датчик влажности почвы</h3>
                <ul>
                    <li>5V → VCC датчика</li>
                    <li>GND → GND датчика</li>
                    <li>A0 → AO / AOUT / S датчика</li>
                </ul>
            </div>
            <div class="step-diagram">
                <strong>Soil Moisture</strong><br>
                VCC → <code>5V</code><br>
                AO → <code>A0</code><br>
                GND → <code>GND</code>
                <svg viewBox="0 0 260 110" aria-hidden="true">
                    <rect class="d-mod" x="14" y="28" width="78" height="52" rx="8"></rect>
                    <rect class="d-board" x="126" y="18" width="124" height="74" rx="8"></rect>
                    <text class="d-label" x="53" y="58" text-anchor="middle">Soil</text>
                    <text class="d-label" x="188" y="48" text-anchor="middle">UNO</text>
                    <circle class="d-pin" cx="136" cy="30" r="5"></circle><text class="d-pin-text" x="136" y="32" text-anchor="middle">5V</text>
                    <circle class="d-pin" cx="162" cy="30" r="5"></circle><text class="d-pin-text" x="162" y="32" text-anchor="middle">G</text>
                    <circle class="d-pin" cx="208" cy="80" r="5"></circle><text class="d-pin-text" x="208" y="82" text-anchor="middle">A0</text>
                    <path class="d-wire-power" d="M92 38 L136 30"></path>
                    <path class="d-wire-sig" d="M92 54 L208 80"></path>
                    <path class="d-wire-gnd" d="M92 70 L162 30"></path>
                </svg>
            </div>
        </article>

        <article class="step">
            <div>
                <h3><span class="step-num">5</span>Подключаем дисплей TM1637</h3>
                <ul>
                    <li>5V → VCC дисплея</li>
                    <li>GND → GND дисплея</li>
                    <li>D7 → DIO дисплея</li>
                    <li>D8 → CLK дисплея</li>
                </ul>
            </div>
            <div class="step-diagram">
                <strong>TM1637</strong><br>
                DIO → <code>D7</code><br>
                CLK → <code>D8</code><br>
                VCC/GND → <code>5V/GND</code>
                <svg viewBox="0 0 260 110" aria-hidden="true">
                    <rect x="12" y="36" width="88" height="34" rx="8" fill="#111a22"></rect>
                    <text x="56" y="58" text-anchor="middle" fill="#d7f8e6" font-size="15" font-family="monospace">88:88</text>
                    <rect class="d-board" x="126" y="18" width="124" height="74" rx="8"></rect>
                    <text class="d-label" x="188" y="48" text-anchor="middle">UNO</text>
                    <circle class="d-pin" cx="136" cy="30" r="5"></circle><text class="d-pin-text" x="136" y="32" text-anchor="middle">5V</text>
                    <circle class="d-pin" cx="162" cy="30" r="5"></circle><text class="d-pin-text" x="162" y="32" text-anchor="middle">G</text>
                    <circle class="d-pin" cx="194" cy="30" r="5"></circle><text class="d-pin-text" x="194" y="32" text-anchor="middle">D7</text>
                    <circle class="d-pin" cx="238" cy="30" r="5"></circle><text class="d-pin-text" x="238" y="32" text-anchor="middle">D8</text>
                    <path class="d-wire-power" d="M100 40 L136 30"></path>
                    <path class="d-wire-gnd" d="M100 68 L162 30"></path>
                    <path class="d-wire-sig" d="M100 52 L194 30"></path>
                    <path class="d-wire-sig" d="M100 58 L238 30"></path>
                </svg>
            </div>
        </article>

        <article class="step">
            <div>
                <h3><span class="step-num">6</span>Подключаем модуль реле 4 канала</h3>
                <ul>
                    <li>5V → VCC релейного модуля</li>
                    <li>GND → GND релейного модуля</li>
                    <li>D3 → IN1, D4 → IN2</li>
                    <li>D5 → IN3, D6 → IN4</li>
                </ul>
            </div>
            <div class="step-diagram">
                <strong>Relay 4CH</strong><br>
                IN1..IN4 → <code>D3,D4,D5,D6</code><br>
                Питание модуля → <code>5V/GND</code>
                <svg viewBox="0 0 260 110" aria-hidden="true">
                    <rect class="d-mod" x="10" y="22" width="92" height="64" rx="8"></rect>
                    <rect class="d-board" x="126" y="18" width="124" height="74" rx="8"></rect>
                    <text class="d-label" x="56" y="56" text-anchor="middle">Relay 4CH</text>
                    <text class="d-label" x="188" y="48" text-anchor="middle">UNO</text>
                    <circle class="d-pin" cx="136" cy="30" r="5"></circle><text class="d-pin-text" x="136" y="32" text-anchor="middle">5V</text>
                    <circle class="d-pin" cx="162" cy="30" r="5"></circle><text class="d-pin-text" x="162" y="32" text-anchor="middle">G</text>
                    <circle class="d-pin" cx="182" cy="30" r="5"></circle><text class="d-pin-text" x="182" y="32" text-anchor="middle">D3</text>
                    <circle class="d-pin" cx="200" cy="30" r="5"></circle><text class="d-pin-text" x="200" y="32" text-anchor="middle">D4</text>
                    <circle class="d-pin" cx="218" cy="30" r="5"></circle><text class="d-pin-text" x="218" y="32" text-anchor="middle">D5</text>
                    <circle class="d-pin" cx="236" cy="30" r="5"></circle><text class="d-pin-text" x="236" y="32" text-anchor="middle">D6</text>
                    <path class="d-wire-power" d="M102 36 L136 30"></path>
                    <path class="d-wire-gnd" d="M102 72 L162 30"></path>
                    <path class="d-wire-sig" d="M102 44 L182 30"></path>
                    <path class="d-wire-sig" d="M102 52 L200 30"></path>
                    <path class="d-wire-sig" d="M102 60 L218 30"></path>
                    <path class="d-wire-sig" d="M102 68 L236 30"></path>
                </svg>
            </div>
        </article>
    </section>

    <section class="panel" id="preflight">
        <h2>Проверка перед включением</h2>
        <ul class="parts-list">
            <li>Проверьте, что питание модулей соответствует схеме: <strong>5V</strong> и <strong>GND</strong> не перепутаны.</li>
            <li>Убедитесь, что сигнальные линии подключены к правильным пинам: D2, D3, D4, D5, D6, D7, D8, D10, D11, A0, A1.</li>
            <li>Проверьте, что датчик влажности почвы подключен именно к <strong>AO/AOUT</strong>, а не к цифровому выходу.</li>
            <li>Проверьте делитель фоторезистора: резистор 10 кОм должен идти между A1 и GND.</li>
            <li>Убедитесь, что релейный модуль запитан отдельно от нагрузки и не коммутирует питание Arduino напрямую.</li>
            <li>Проверьте, что в скетче указаны корректные Wi‑Fi параметры и адрес сервера.</li>
            <li>Перед первым запуском снимите нагрузку с реле и проверьте только логику переключения.</li>
        </ul>
    </section>

    <section class="panel mounting" id="mounting">
        <h2>Монтаж в теплице</h2>
        <p>Контроллер лучше размещать внутри теплицы в электрическом боксе. Это защищает электронику от влаги, случайных касаний и механических повреждений.</p>
        <ul class="parts-list">
            <li>Установите электрический бокс в сухом и доступном месте внутри теплицы.</li>
            <li>Arduino Uno, ESP8266 и модули крепите на DIN‑рейку.</li>
            <li>Внутри бокса разместите блок питания на 12 В.</li>
            <li>12 В пригодятся для питания водопроводных клапанов.</li>
            <li>Arduino управляет реле, а нагрузка 12 В питается от отдельного источника.</li>
        </ul>
    </section>

    <section class="cta" id="ready">
        <h2>Можно не собирать контроллер вручную</h2>
        <p>Если вы не хотите самостоятельно подбирать компоненты и проверять распиновку, можно использовать готовый контроллер для теплицы с поддержкой Home AiDvor.</p>
        <div class="cta-actions">
            <a class="btn btn-primary" href="/downloads/greenhouse-controller.ino">Скачать скетч для контроллера</a>
            <a class="btn" href="/controllers/greenhouse">Выбрать готовый контроллер</a>
        </div>
    </section>
</div>
@include('layouts.theme-runtime')
</body>
</html>
