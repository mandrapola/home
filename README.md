# Smart Home Control (PHP + MySQL + Blade)

Проект мигрирован с Nuxt на стек `PHP 8.2 + MySQL + Blade + JS polling` для запуска на обычном shared-hosting/VPS.

## Быстрый старт

```bash
docker compose up -d --build
```

Сервисы:
- `server` (PHP/Apache): `http://localhost:3000`
- `db` (MySQL): `localhost:3306`

Node в корне репозитория используется только как вспомогательный tooling (например, `npm run test:api`).

```bash
npm run test:api
```

## Актуальная структура сервера

- `php-app/public/index.php` — Laravel entrypoint
- `php-app/app/Http/Controllers/*` — HTTP/API логика
- `php-app/app/Services/*` — сервисы доменной логики
- `php-app/resources/views/*.blade.php` — Blade страницы
- `php-app/public/assets/*` — клиентские скрипты/стили
- `db/mysql/000_clean_hosting_schema.sql` — чистая схема БД для хостинга

## Контракты обмена

### 1) ESP Server-> Server (`POST /api/controller/report`) — JSON-only

Пример запроса (в cloud):

```bash
curl -X POST http://localhost:3000/api/controller/report \
  -H "Content-Type: application/json" \
  -d '{
    "controller_id": "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f",
    "readings": [
      { "pin": "relay_1", "value": 0 },
      { "pin": "air_temperature", "value": 23.4 },
      { "pin": "air_humidity", "value": 41.2 }
    ]
  }'
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
  "monitor": "4821",
  "controller_id": "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f"
}
```

`monitor` содержит код привязки в процессе регистрации контроллера.
`digital_outputs` содержит целевые значения `relay_*` для контроллера.

Аутентификация ESP:
- до завершения регистрации ESP обращается к `/api/controller/provision` с автоматически созданным временным provisioning bearer token;
- после подтверждения кода сервер возвращает ESP `controller_id` и индивидуальный `api_token`;
- ESP сохраняет токен в `config.txt` и отправляет дальнейшие запросы с `Authorization: Bearer <api_token>`;
- сервер постоянно хранит только SHA-256 hash токена; зашифрованная копия хранится до первого успешного bearer-запроса ESP.
- для локальной разработки ESP может использовать `tls_insecure=1`; в production требуется `tls_insecure=0` и `tls_fingerprint`.

### 2) Controller -> ESP — CSV

Arduino отправляет CSV-строку в формате:

```text
controller_id=<uuid>;relay_1=0;relay_2=1;...;air_temperature=23.4;air_humidity=41.2
```

ESP конвертирует эту строку в JSON для cloud-сервера и конвертирует JSON-ответ cloud обратно в CSV для контроллера.

Коды ошибок API сервера:
- `401 controller_auth_failed` — отсутствует/неверен bearer token контроллера.
- `401 provision_auth_failed` — отсутствует/неверен временный provisioning token.
- `403 forbidden` — контроллер не зарегистрирован и не в режиме привязки.
- `400 bad_request` — некорректный JSON payload.
- `400 empty_readings` — отсутствуют валидные числовые readings.

## Привязка контроллера к пользователю

1. Незарегистрированный ESP создает временный provisioning token, передает `device_uid` на `/api/controller/provision` и получает первый 4-значный код в `monitor`.
2. Пользователь вводит первый код в форме добавления контроллера.
3. Сервер отправляет второй код на дисплей контроллера для подтверждения физического доступа.
4. После ввода второго кода сервер создает `controller`, указывает владельца в `controller.user_id` и выпускает индивидуальный bearer token.
5. В следующем provision-ответе ESP получает и сохраняет `controller_id`, `api_token`, затем переходит на `/api/controller/report`.

## Страницы UI (PHP)

- `GET /` — дашборд контроллеров
- `GET /scenes` — сценарии
- `GET /parameters` — расчетные параметры
- `GET /schedule` — локальное расписание (localStorage)
- `GET /settings` — системные настройки

## API (PHP)

Системные:
- `GET /api/ping`
- `GET /api/controllers`
- `GET /api/settings/timezone`
- `PUT /api/settings/timezone`

Показания/пины:
- `POST /api/controller/report`
- `GET /api/controllers/{id}/readings`
- `GET /api/controllers/{id}/settings`
- `PUT /api/controllers/{id}/settings`
- `PUT /api/controllers/{id}/pins/{pin}/state`
- `DELETE /api/controllers/{id}/pins/{pin}/history` (только аналоговые пины)

Сценарии:
- `GET /api/scenarios`
- `GET /api/pins`
- `GET /api/controllers/{id}/scenarios`
- `POST /api/controllers/{id}/scenarios`
- `PUT /api/controllers/{id}/scenarios/{scenarioId}`
- `DELETE /api/controllers/{id}/scenarios/{scenarioId}`

Параметры:
- `GET /api/controllers/{id}/parameters`
  - `controller:{id}:current_time`
  - `controller:{id}:avg_pin:{pin}`
  - `controller:{id}:pin_state:{pin}`
  - `controller:{id}:pin_on_seconds_24h:{pin}`

## ESP Server

`ESP Server` работает как локальный шлюз:
- в `online` режиме проксирует запросы к глобальному серверу;
- в `offline` режиме отвечает локально и поддерживает ручное управление пинами.
- для контроллера сохраняет CSV-протокол, для cloud-сервера использует JSON-протокол.

Основные endpoint'ы gateway:
- `GET /api/system/status`
- `GET /api/local/pins`
- `PUT /api/local/pins/{pin}/state`
- `POST /api/controller/report`

## Статус миграции

Миграция на PHP завершена:
- дашборд (карточки, управление пинами, настройки контроллера/пина, графики истории);
- сценарии (UI + CRUD + применение в `report`);
- параметры и таймзона;
- расписание (клиентский режим);
- API для контроллера и управления пинами.

Старый Nuxt-код удален из корня проекта.

## Важное по БД

Для первой инициализации используется `db/mysql/001_schema.sql`.

Для установки на обычный хостинг (чистая структура без данных, совместимо с MySQL/MariaDB) используйте:

- `db/mysql/000_clean_hosting_schema.sql`

## Деплой на хостинг по FTP

1. Подготовьте конфиг:
```bash
cp deploy/ftp.env.example deploy/ftp.env
```

2. Заполните `deploy/ftp.env` параметрами вашего хостинга.

3. Запустите деплой:
```bash
bash scripts/deploy-ftp.sh
```

Скрипт копирует папку `php-app` в `FTP_REMOTE_DIR` и не затирает локальные чувствительные файлы (`.env`) и runtime-кеш/логи.

Если volume ранее создавался под другую схему:

```bash
docker compose down
docker volume rm home_mysql_data
docker compose up -d --build
```
