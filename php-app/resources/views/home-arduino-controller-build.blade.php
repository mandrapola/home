<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Сборка контроллера — AiDvor Docs</title>
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
    <style>
        .wrap { width: min(1100px, 100% - 32px); margin: 0 auto; padding: 24px 0 36px; }
        .back { color: var(--accent); text-decoration: none; font-weight: 600; }
        .back:hover { text-decoration: underline; }
        .block { border: 1px solid var(--line); background: #fff; border-radius: 16px; box-shadow: 0 8px 22px rgba(17,34,68,.06); padding: 16px 18px; margin-bottom: 12px; }
        h1, h2 { margin: 0 0 10px; letter-spacing: -0.01em; }
        p, li { color: var(--muted); line-height: 1.6; }
        ul { margin: 0; padding-left: 20px; }
        pre { border: 1px solid var(--line); border-radius: 12px; padding: 10px; background: #f8fbff; color: var(--text); overflow: auto; }
    </style>
</head>
<body class="theme-body">
<div class="wrap">
    <p><a class="back" href="{{ route('home-arduino') }}">← К разделам</a></p>
    <section class="block">
        <h1>Сборка контроллера на Arduino UNO</h1>
        <p>Базовая конфигурация для работы с AiDvor.</p>
    </section>
    <section class="block">
        <h2>Комплектующие</h2>
        <ul>
            <li>Arduino UNO + Ethernet Shield</li>
            <li>DHT11 (D2)</li>
            <li>4-канальный релейный модуль</li>
            <li>Аналоговые датчики по необходимости (A0-A5)</li>
            <li>Блок питания 5V и общий GND</li>
        </ul>
    </section>
    <section class="block">
        <h2>Распиновка</h2>
        <pre>D2 -> DHT11 DATA
D3 -> relay_1
D5 -> relay_2
D6 -> relay_3
D9 -> relay_4
A0 -> soil_moisture_raw
A1 -> light_level_raw
A2 -> tank_level_raw
A3 -> water_pressure_raw
A4 -> analog_spare_1_raw
A5 -> analog_spare_2_raw
SPI reserved: D10,D11,D12,D13</pre>
    </section>
</div>
</body>
</html>
