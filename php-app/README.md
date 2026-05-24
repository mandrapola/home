# php-app (Laravel Server)

Laravel-бэкенд для проекта Home Aidvor (`home.aidvor.ru`).

## Роль в архитектуре

- принимает телеметрию от ESP8266-прокси;
- хранит контроллеры, пины, историю и сценарии;
- возвращает управляющие сигналы (`digital_outputs`);
- поддерживает pairing-код привязки контроллера;
- проверяет bearer token зарегистрированного ESP (`controller.token` middleware).

## Контракты

### ESP8266 -> Server

`POST /api/controller/provision` — JSON provisioning для непривязанного ESP с временным bearer token.

`POST /api/controller/report` — **только JSON** telemetry с индивидуальным bearer token.

Пример запроса:

```json
{
  "controller_id": "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f",
  "readings": [
    { "pin": "relay_1", "value": 0 },
    { "pin": "relay_2", "value": 1 },
    { "pin": "air_temperature", "value": 23.4 },
    { "pin": "air_humidity", "value": 41.2 }
  ]
}
```

Пример ответа:

```json
{
  "send_interval_seconds": 5,
  "digital_outputs": {
    "relay_1": 0,
    "relay_2": 1,
    "relay_3": 0,
    "relay_4": 1
  },
  "monitor": "4821"
}
```

Примечание:
- поле `monitor` всегда присутствует; при активной pending-сессии pairing в нем идет код привязки.
- `digital_outputs` возвращается только в формате `relay_* => 0|1`.
- до завершения регистрации ESP использует автоматически созданный временный bearer token в запросах `/api/controller/provision`.
- после подтверждения регистрации сервер возвращает `controller_id`, `api_token`; ESP сохраняет токен и далее отправляет `Authorization: Bearer <api_token>`.
- сервер постоянно хранит только SHA-256 hash индивидуального токена; зашифрованная копия удаляется после первого успешного bearer-запроса.
- `tls_insecure=1` в конфиге ESP допускается только для локального dev HTTPS; в production используется проверка `tls_fingerprint`.

### Arduino Uno -> ESP8266

Этот контракт реализуется ESP8266-прокси, формат остается CSV.
Сервер CSV не принимает.

Коды ошибок API:
- `401 controller_auth_failed` — отсутствует/неверен bearer token контроллера.
- `401 provision_auth_failed` — отсутствует/неверен временный provisioning token.
- `403 forbidden` — контроллер не зарегистрирован и не в pairing-режиме.
- `400 bad_request` — невалидный JSON.
- `400 empty_readings` — нет валидных числовых значений в `readings`.

Публичная страница с контрактом:
- `/home-arduino/server-contract`

## Основные endpoint'ы (Laravel)

- `GET /api/ping`
- `POST /api/controller/provision`
- `POST /api/controller/report`

## Тесты

Локально в контейнере:

```bash
docker compose exec -T server php artisan test
```

Точечный запуск:

```bash
docker compose exec -T server php artisan test --testsuite=Unit
```

## Важное по БД

Чистая схема для хостинга:

- `db/mysql/000_clean_hosting_schema.sql`

Основная миграция в Laravel:

- `database/migrations/0001_01_01_000000_create_zero_schema.php`
