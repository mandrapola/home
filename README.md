# Smart Home Control (PHP + MySQL + Blade)

Проект мигрирован с Nuxt на стек `PHP 8.2 + MySQL + Blade + JS polling` для запуска на обычном shared-hosting/VPS.

## Быстрый старт

```bash
docker compose up -d --build
```

Сервисы:
- `server` (PHP/Apache): `http://localhost:3000`
- `db` (MySQL): `localhost:3306`
- `getaway` (home-openwrt gateway): `http://localhost:3001`

Node в корне репозитория используется только как вспомогательный tooling (например, `npm run test:api`).

```bash
npm run test:api
```

Для запуска теста из контейнера `getaway` укажите адрес PHP-сервера:

```bash
API_BASE_URL=http://server npm run test:api
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
    "controller_id": "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f",
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
  "digital_outputs": {},
  "pairing_code": "4821",
  "pairing_code_expires_at": "2026-04-05T10:30:00+00:00"
}
```

`pairing_code` передается только когда пользователь запустил привязку контроллера.

## Привязка контроллера к пользователю

1. Пользователь входит в веб-интерфейс (`/dashboard`) и выбирает контроллер.
2. Нажимает `Запросить 4-значный код`.
3. Сервер сохраняет pending-сессию привязки на 5 минут.
4. Контроллер получает `pairing_code` в ответе `/api/controller/report` и отображает его на TM1637.
5. Пользователь вводит код в форме и нажимает `Привязать контроллер`.
6. Сервер подтверждает код и создает связь `controller_user` с ролью `owner`.

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
