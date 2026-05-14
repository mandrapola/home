# AiDvor / Home Aidvor — Agent Context

## 1) Проект и цель
AiDvor — веб-платформа для удаленного управления DIY IoT-контроллерами (Arduino + ESP8266), мониторинга датчиков, сценариев автоматизации, интеграции с Яндекс Алисой и тарифной модели с оплатой.

Основной web/app домен в проде: `https://home.aidvor.ru`.

## 2) Текущая архитектура
Монорепо с несколькими частями:
- `php-app/` — Laravel приложение (основной backend + web UI + admin).
- `client/arduino/` — скетчи контроллеров.
- `db/mysql/` — SQL/bootstrap для локальной БД.
- `docker-compose.yml` — локальное окружение.

Текущая основная аппаратная схема:
- Arduino Uno читает датчики, управляет реле и формирует компактный CSV по UART.
- ESP8266 принимает CSV от Uno, подключается к Wi‑Fi, преобразует CSV в JSON report-contract, подписывает запрос HMAC и отправляет данные на сервер.
- Сервер возвращает JSON с `send_interval_seconds`, `digital_outputs`, `monitor`; ESP8266 преобразует его в CSV-команды для Uno.

Локально по умолчанию:
- Laravel: `http://localhost:3000`
- MySQL: `localhost:3306`

## 3) Стек и версии
- PHP: `^8.3` (из `php-app/composer.json`)
- Laravel: `laravel/framework ^13.0`
- Backpack: `backpack/crud ^7.0`, `backpack/theme-tabler ^2.0`
- ACL: `spatie/laravel-permission ^7.4`
- Frontend toolchain (`php-app/package.json`):
  - `vite ^8.0.0`
  - `tailwindcss ^3.1.0`
  - `laravel-vite-plugin ^3.0.0`

## 4) Ключевые домены

### 4.1 Контроллеры/пины/телеметрия
- Телеметрия поступает через API report endpoint `/api/controller/report`.
- Серверный report-contract ожидает JSON: `controller_id` + массив `readings[]`.
- ESP8266-прокси подписывает запрос HMAC-заголовками `X-Proxy-Id`, `X-Timestamp`, `X-Nonce`, `X-Signature`.
- На основе telemetry + scenarios формируются управляющие сигналы (`digital_outputs`).
- Для sensor-пинов может применяться преобразование/калибровка значений (в т.ч. для графиков, карточек, условий сценариев).

### 4.2 Pairing
- Привязка контроллеров к пользователю через pairing flow.
- Ограничения по тарифу применяются уже на этапе attach controller.

### 4.3 Алиса
- OAuth-linking аккаунта пользователя (`/profile/alice/connect`, callback).
- Smart Home API endpoints под middleware `alice.resolve` + `alice.access`.
- Собственный OAuth provider flow для Яндекс-бриджа (`/oauth/authorize`, `/oauth/token`).
- Уведомления о смене состояний (alerts/state notifications) при включенных флагах.

### 4.4 Тарифы и платежи
- Пользователь выбирает тариф (`selected_plan_id`), но эффективные лимиты зависят от статуса подписки.
- Если подписка pending/expired — действуют лимиты default free-плана.
- Оплата через YooKassa (HTTP API без SDK), webhook: `/api/payments/yookassa/webhook`.
- Dev-режим поддерживает тестовую локальную активацию тарифа.

## 5) Роли и доступ
- Проверка прав через роли Spatie, ключевая роль: `administrator`.
- Админка доступна только пользователям с админ-правами.
- Пользовательский UI не должен давать прямых ссылок в админку.

## 6) Админка (Backpack)
Сущности/разделы:
- Users
- Controllers
- Pins (часть pin-операций привязана к контроллерам)
- Plans
- Payments (read-only мониторинг транзакций)

Ожидаемое поведение:
- Управление Alice access для пользователей — только в админке.
- У пользователя в профиле — только кнопки connect/disconnect Алисы.
- Назначение тарифа пользователю — отдельная форма/действие в админке.

## 7) UI и темы
- Пользовательский сайт (не админка) поддерживает 3 режима темы: `light`, `dark`, `system`.
- Тема хранится в `localStorage` (`aidvor_theme_mode`), применяется через `data-theme`.
- На landing/welcome используется отдельный theme toggle в стиле лендинга.
- Стили лендинга разнесены по файлам:
  - `php-app/public/assets/landing-light.css`
  - `php-app/public/assets/landing-dark.css`

## 8) Важные конфиги и env
Laravel env (`php-app/.env`), ключевые группы:
- Alice:
  - `ALICE_ENABLED`
  - `ALICE_CLIENT_ID`, `ALICE_CLIENT_SECRET`
  - `ALICE_OAUTH_REDIRECT_URI`
  - `ALICE_PROVIDER_CLIENT_ID`, `ALICE_PROVIDER_CLIENT_SECRET`
  - `ALICE_PROVIDER_REDIRECT_URIS`
  - `ALICE_ALERTS_ENABLED`, `ALICE_SKILL_ID`, `ALICE_DIALOGS_OAUTH_TOKEN`
- Billing/Plans:
  - `DEFAULT_PLAN` (обычно `free`)
- YooKassa:
  - `YOOKASSA_ENABLED`
  - `YOOKASSA_SHOP_ID`, `YOOKASSA_SECRET_KEY`
  - `YOOKASSA_WEBHOOK_SECRET`
  - `YOOKASSA_RETURN_URL`
  - `YOOKASSA_API_BASE_URL`
- Controller proxy HMAC:
  - `PROXY_HMAC_ENABLED`
  - `PROXY_ID`
  - `PROXY_SECRET`

## 9) Документация в репо
- `README.md` (корневой)
- `INSTALL.md` (установка для пользователей)
- `controllers.md`
- `greenhouse.md`
- `php-app/README.md` (серверная часть/API)

## 10) Навигация по коду
- Web routes: `php-app/routes/web.php`
- API routes: `php-app/routes/api.php`
- Billing:
  - `php-app/app/Http/Controllers/PlanController.php`
  - `php-app/app/Services/Billing/PlanLimitService.php`
  - `php-app/app/Services/Billing/SubscriptionActivationService.php`
- YooKassa webhook:
  - `php-app/app/Http/Controllers/Api/YooKassaWebhookController.php`
- Alice:
  - `php-app/app/Http/Controllers/AliceLinkController.php`
  - `php-app/app/Http/Controllers/OAuth/AliceOAuthProviderController.php`
  - `php-app/app/Services/Alice/*`
  - `php-app/app/Http/Middleware/EnsureAliceAccessEnabled.php`
- Admin (Backpack):
  - `php-app/app/Http/Controllers/Admin/*`
- Pairing + report handling:
  - `php-app/app/Http/Controllers/PairingController.php`
  - `php-app/app/Http/Controllers/Api/ControllerReportController.php`
  - `php-app/app/Http/Middleware/VerifyProxyHmac.php`
  - `php-app/app/Listeners/ProcessControllerReadingsOnReport.php`
- Arduino/ESP8266:
  - `client/arduino/arduino_uno_greenhouse_uart_controller_v1/`
  - `client/arduino/esp8266_greenhouse_wifi_proxy_v1/`
- User pages/views:
  - `php-app/resources/views/*`

## 11) Операционные команды (локально)
```bash
# поднять окружение
docker compose up -d

# логи laravel контейнера
docker compose logs --tail=100 server

# artisan
docker compose exec -T server php artisan about
docker compose exec -T server php artisan migrate

# тесты backend
docker compose exec -T server php artisan test

# frontend assets (Laravel app) — запускать в контейнере server
docker compose exec -T server npm install
docker compose exec -T server npm run dev
docker compose exec -T server npm run build
```

## 12) Проверки UI/ассетов
Минимальный набор после UI-изменений:
1. Проверить сборку ассетов:
```bash
docker compose exec -T server npm run build
```
2. Очистить представления:
```bash
docker compose exec -T server php artisan view:clear
```
3. Открыть критические страницы:
- `/` (landing)
- `/plans` (тарифы)
- `/dashboard`
- `/profile`
4. Проверить темы `light/dark/system` и локали `ru/en`.

## 12.1 Примеры точечных тестов
Использовать вместо полного test-suite, когда меняется узкий модуль:

```bash
# конкретный тестовый класс
docker compose exec -T server php artisan test --filter=PlanControllerTest

# конкретный тестовый метод
docker compose exec -T server php artisan test --filter=test_user_can_select_plan

# запуск одного файла
docker compose exec -T server php artisan test tests/Feature/PlanControllerTest.php

# только feature-тесты
docker compose exec -T server php artisan test --testsuite=Feature

# только unit-тесты
docker compose exec -T server php artisan test --testsuite=Unit
```

## 12.2 Рекомендованный порядок проверок
После кодовых изменений выполнять проверки в таком порядке:

1. Сборка ассетов (если затрагивался UI):
```bash
docker compose exec -T server npm run build
```
2. Очистка кэша представлений:
```bash
docker compose exec -T server php artisan view:clear
```
3. Точечные тесты по затронутому модулю (предпочтительно):
```bash
docker compose exec -T server php artisan test --filter=<TargetTestOrMethod>
```
4. При необходимости — suite-level тесты:
```bash
docker compose exec -T server php artisan test --testsuite=Feature
```
5. Smoke-проверка страниц в браузере:
- `/`
- `/plans`
- `/dashboard`
- `/profile`
6. Дополнительно проверить:
- переключение тем `light/dark/system`,
- локали `ru/en`,
- целевые бизнес-флоу (например, выбор/оплата тарифа, Alice connect, админский доступ).

## 13) Текущие рабочие соглашения
1. Не возвращать `is_admin` в код/формы/проверки.
2. Для ACL опираться на Spatie roles/permissions.
3. Для тарифов в UI показывать name/price из БД (не хардкод code в пользовательском интерфейсе).
4. Лимиты применять централизованно через `PlanLimitService` и интеграционные точки (pairing, pin_data insert и т.д.).
5. Для Алисы:
   - доступ пользователя = `user.alice_enabled` AND разрешение текущего effective plan.
6. В пользовательском UI поддерживать локализацию (RU/EN) и theme consistency.
7. Если в задаче есть двусмысленность, сначала уточнить постановку.
8. Перед изменениями кода агент кратко излагает план. Подтверждение обязательно для чувствительных зон из раздела 14. Для обычных точечных правок по явно поставленной задаче можно вносить изменения без дополнительного подтверждения.

## 14) Запрещено менять без подтверждения
- Платежная логика и статусы активации подписок.
- OAuth/Alice провайдерный flow и security middleware.
- Проверки доступа/ролей/админские middleware.
- Формат API контрактов, используемых контроллерами и ESP8266-прокси.
- Миграции/структуру таблиц в прод-совместимых частях.
- Секреты/ключи/переменные окружения в репозитории.

## 15) Secrets policy
1. Никогда не коммитить реальные секреты, токены, пароли, ключи.
2. Использовать только `.env`/секрет-хранилище; в коде и docs — placeholders.
3. При выводе логов/примеров маскировать чувствительные значения.
4. Не копировать секреты из пользовательских сообщений в файлы репозитория.
5. Если секрет утек — отметить необходимость ротации (без публикации значения).
6. Новые env-переменные добавлять в `.env.example` с пустыми или placeholder-значениями.

## 16) Что обычно проверять при регрессиях
- 403/доступы в админке: есть ли роль `administrator` у пользователя.
- Пустые/битые стили: подключены ли `theme.css` + нужные landing css, очищен ли кэш.
- Алиса connect errors: корректность OAuth env и redirect URI совпадение в кабинете Яндекс.
- YooKassa: включен ли флаг, заполнены ли креды, приходит ли webhook.
- Лимиты: effective plan vs selected plan, статус подписки.

## 17) Последний известный фокус работ
- Редизайн landing и страницы тарифов по референсным HTML макетам.
- Компактный переключатель темы на главной.
- Полная локализация текстов пользовательского интерфейса.

## 18) Режим экономии токенов
Цель: минимизировать расход токенов без потери качества результата.

### 18.1 Правила работы
1. Одна итерация = одна конкретная цель.
2. Сначала `rg`/поиск, затем чтение только нужных диапазонов строк (`sed -n start,endp`).
3. Не перегенерировать файл полностью, если можно сделать точечный `apply_patch`.
4. Не запускать полный test-suite без необходимости. Сначала таргетные тесты по измененным модулям.
5. Ответы агента в коротком формате:
   - что изменено,
   - где изменено,
   - как проверить.
6. Не повторять контекст, уже описанный в этом файле.
7. Для UI-правок переиспользовать существующие стили/токены вместо дублирования CSS.

### 18.2 Формат короткого запроса к агенту
```text
Задача: <что сделать одним шагом>
Файлы: <список файлов или модуль>
Ограничения: <что нельзя менять>
Критерий готовности: <как понять, что задача завершена>
Проверка: <какую проверку/тест запустить>
Формат ответа: кратко (изменения + проверка)
```

### 18.3 Шаблон “по умолчанию”, если не указан формат
1. Изменено: <1-3 пункта>
2. Файлы: <пути>
3. Проверка: <команда/шаги>
4. Настройки: <имя agents файла используемого в работе>

### 18.4 Антипаттерны
- Большие объяснения без запроса пользователя.
- Чтение больших файлов целиком, если можно читать фрагменты.
- Одновременное смешение нескольких независимых задач в одном ходе.
- Полный рефакторинг “заодно” без отдельного подтверждения.

## 19) Критерии готовности изменений

Изменение считается завершенным, если:
- код изменён только в рамках задачи;
- не затронуты запрещённые зоны без подтверждения;
- выполнена указанная проверка;
- в ответе указаны изменённые файлы и команда проверки;
- если проверка не запускалась — явно указана причина.

---
Этот файл — основной контекст для Codex/агентов в проекте. Обновлять при изменениях архитектуры, маршрутов, ролей, тарифов, платежей и UI-соглашений.
