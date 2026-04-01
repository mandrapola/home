# Smart Home Control (PHP + MySQL + Blade)

Проект мигрирован с Nuxt на стек `PHP 8.2 + MySQL + Blade + JS polling` для запуска на обычном shared-hosting/VPS.

## Быстрый старт

```bash
docker compose up -d --build
```

Сервисы:
- `smart-home` (PHP/Apache): `http://localhost:3000`
- `mysql`: `localhost:3306`
- `home-openwrt` gateway: `http://localhost:3001`
- `cloudflared` (опционально, через `.env`)

Node в корне репозитория используется только как вспомогательный tooling (например, `npm run test:api`).

```bash
npm run test:api
```

Для запуска теста из контейнера `home-openwrt` укажите адрес PHP-сервера:

```bash
API_BASE_URL=http://smart-home npm run test:api
```

## Актуальная структура сервера

- `php-app/public/index.php` — роутинг и entrypoint
- `php-app/src/app.php` — серверная логика API
- `php-app/src/bootstrap.php` — подключение/инициализация
- `php-app/views/*.blade.php` — страницы Blade
- `php-app/public/assets/*.js` — клиентская логика страниц
- `db/mysql/001_schema.sql` — схема MySQL + seed

## Контракт контроллера

`POST /api/controller/report`

Пример запроса:

```bash
curl -X POST http://localhost:3000/api/controller/report \
  -H "Content-Type: application/json" \
  -d '{
    "controller_id": 1,
    "readings": [
      { "pin": "D3", "value": 0 },
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
    "D3": 0,
    "D4": 0,
    "D5": 0,
    "D6": 0
  }
}
```

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

## Home OpenWRT Gateway

`home-openwrt` работает как локальный шлюз:
- в `online` режиме проксирует запросы к глобальному серверу;
- в `offline` режиме отвечает локально и поддерживает ручное управление пинами.

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

Legacy-код Nuxt удален из корня проекта.

## Важное по БД

Для первой инициализации используется `db/mysql/001_schema.sql`.

Если volume ранее создавался под другую схему:

```bash
docker compose down
docker volume rm home_mysql_data
docker compose up -d --build
```
