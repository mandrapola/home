<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Контракт API — AiDvor</title>
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
    <style>
        .wrap { width: min(1100px, 100% - 32px); margin: 0 auto; padding: 24px 0 36px; }
        .back { color: var(--accent); text-decoration: none; font-weight: 600; }
        .back:hover { text-decoration: underline; }
        .block { border: 1px solid var(--line); background: #fff; border-radius: 16px; box-shadow: 0 8px 22px rgba(17,34,68,.06); padding: 16px 18px; margin-bottom: 12px; }
        h1, h2, h3 { margin: 0 0 10px; letter-spacing: -0.01em; }
        p, li { color: var(--muted); line-height: 1.6; }
        ul { margin: 0; padding-left: 20px; }
        pre { border: 1px solid var(--line); border-radius: 12px; padding: 10px; background: #f8fbff; color: var(--text); overflow: auto; }
        code { color: var(--text); }
        .field-note { margin-top: 12px; border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; background: #f8fbff; }
        .api-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 0.93rem; }
        .api-table th, .api-table td { border: 1px solid var(--line); padding: 8px 10px; vertical-align: top; color: var(--text); }
        .api-table th { text-align: left; background: #f5f8fc; }
        .api-table td { color: var(--muted); }
        .api-table td code { color: var(--text); }
    </style>
</head>
<body class="theme-body">
<div class="wrap">
    <p><a class="back" href="{{ route('home-arduino') }}">← К разделам</a></p>

    <section class="block">
        <h1>Контракт запроса к серверу</h1>
        <p>Основной endpoint для контроллера: <code>POST /api/controller/report</code>.</p>
        <p><strong>Актуально:</strong> сервер принимает только <strong>JSON</strong>. CSV используется только между Arduino и gateway.</p>
        <p>Поток данных: <code>Arduino (CSV) → Gateway (конвертация) → Server (JSON)</code>.</p>
    </section>

    <section class="block">
        <h2>Контракт запроса Gateway → Server (JSON)</h2>
        <pre>{
  "controller_id": "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f",
  "readings": [
    { "pin": "relay_1", "value": 0 },
    { "pin": "relay_2", "value": 1 },
    { "pin": "soil_moisture_raw", "value": 345 },
    { "pin": "air_humidity", "value": 40.0 },
    { "pin": "air_temperature", "value": 23.6 }
  ]
}</pre>
        <table class="api-table">
            <thead>
            <tr>
                <th>Поле</th>
                <th>Тип</th>
                <th>Обязательно</th>
                <th>Описание</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code>controller_id</code></td>
                <td>UUID</td>
                <td>Да</td>
                <td>Идентификатор контроллера.</td>
            </tr>
            <tr>
                <td><code>readings</code></td>
                <td>массив объектов</td>
                <td>Нет</td>
                <td>Список показаний. Каждый элемент: <code>{ pin, value }</code>.</td>
            </tr>
            <tr>
                <td><code>readings[].pin</code></td>
                <td>строка</td>
                <td>Да (внутри readings)</td>
                <td>Идентификатор пина (<code>relay_*</code>, <code>*_raw</code>, <code>air_*</code>).</td>
            </tr>
            <tr>
                <td><code>readings[].value</code></td>
                <td>число</td>
                <td>Да (внутри readings)</td>
                <td>Числовое значение показания.</td>
            </tr>
            </tbody>
        </table>
        <p class="field-note">Важно: кроме <code>controller_id</code> сервер ожидает минимум одно валидное числовое измерение, иначе вернёт ошибку <code>empty_readings</code>.</p>
    </section>

    <section class="block">
        <h2>Контракт ответа Server → Gateway (JSON)</h2>
        <pre>{
  "send_interval_seconds": 5,
  "digital_outputs": {
    "relay_1": 1,
    "relay_2": 0,
    "relay_3": 1,
    "relay_4": 0
  },
  "monitor": "1234"
}</pre>
        <table class="api-table">
            <thead>
            <tr>
                <th>Поле</th>
                <th>Тип</th>
                <th>Описание</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code>send_interval_seconds</code></td>
                <td>целое</td>
                <td>Следующий интервал отправки данных контроллером.</td>
            </tr>
            <tr>
                <td><code>digital_outputs</code></td>
                <td>объект</td>
                <td>Ключи <code>relay_*</code> и значения 0/1 для установки на контроллере.</td>
            </tr>
            <tr>
                <td><code>monitor</code></td>
                <td>строка/null</td>
                <td>Строка для TM1637: при привязке это 4-значный код, в обычном режиме — мониторинг сенсоров/время.</td>
            </tr>
            </tbody>
        </table>
    </section>

    <section class="block">
        <h2>Контракт Controller → Gateway (CSV)</h2>
        <p>CSV остается для Arduino UNO (экономия RAM/Flash). Gateway преобразует CSV в JSON перед отправкой на сервер.</p>
        <pre>controller_id=&lt;uuid&gt;;relay_1=0;relay_2=1;relay_3=0;relay_4=0;soil_moisture_raw=345;air_humidity=40.0;air_temperature=23.6</pre>
        <p>Пример ответа gateway контроллеру:</p>
        <pre>send_interval_seconds=5;relay_1=1;relay_2=0;relay_3=1;relay_4=0;monitor=1234</pre>
    </section>

    <section class="block">
        <h2>Ошибки и коды</h2>
        <ul>
            <li><code>401 proxy_auth_failed</code> — HMAC-подпись отсутствует/некорректна.</li>
            <li><code>403 forbidden</code> — контроллер не зарегистрирован и не в режиме привязки.</li>
            <li><code>400 bad_request</code> — некорректный формат payload.</li>
            <li><code>400 empty_readings</code> — нет валидных числовых readings.</li>
        </ul>
    </section>
</div>
</body>
</html>
