<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Сборка контроллера — home.arduino.ru</title>
    <link rel="stylesheet" href="{{ asset('assets/docs.css') }}">
</head>
<body>
<div class="wrap">
    <p><a class="back" href="{{ route('home-arduino') }}">← К разделам</a></p>
    <section class="block">
        <h1>Сборка контроллера на Arduino UNO</h1>
        <p>Базовая конфигурация для работы с Home Aidvor.</p>
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
