# Smart Home Control (Nuxt 3)

Сайт управления умным домом на `Nuxt.js + Vue`.

## Запуск

```bash
npm install
npm run dev
```

Приложение поднимется обычно на `http://localhost:3000`.

## Запуск в Docker (dev)

```bash
docker compose up --build
```

Зависимости (`nuxt`, `vue` и остальные из `package.json`) устанавливаются внутри контейнера на этапе сборки образа.
Сайт будет доступен по адресу `http://localhost:3000`.
PostgreSQL будет доступен на `localhost:5432`.

### Доступ из локальной сети

- Docker публикует веб-сервер на всех интерфейсах хоста: `0.0.0.0:3000:3000`
- В дашборде отображается адрес сервера в локальной сети

Если нужно явно задать LAN IP (рекомендуется для Docker), запускайте так:

```bash
LAN_IP=192.168.1.50 docker compose up --build -d
```

Или автоматически подставить первый IP хоста (Linux):

```bash
LAN_IP=$(hostname -I | awk '{print $1}') docker compose up --build -d
```

Автоопределение также работает, но в некоторых окружениях Docker может вернуть внутренний адрес контейнера.

## API для контроллера Arduino

Endpoint:

```http
POST /api/controller/report
Content-Type: application/json
```

Готовый скетч для Arduino Uno:

`client/arduino/arduino_uno_report_client.ino`

Пример `curl` запроса:

```bash
curl -X POST http://localhost:3000/api/controller/report \
  -H "Content-Type: application/json" \
  -d '{
    "controller_id": 1,
    "thermometer": 24.8,
    "pressure": 745.2,
    "humidity": 41.3
  }'
```

Поддерживаемый payload (вариант с фиксированными датчиками):

```json
{
  "controller_id": 1,
  "thermometer": 24.8,
  "pressure": 745.2,
  "humidity": 41.3
}
```

Также можно отправлять массив:

```json
{
  "controller_id": 1,
  "readings": [
    { "pin": "thermometer", "value": 24.8 },
    { "pin": "pressure", "value": 745.2 },
    { "pin": "humidity", "value": 41.3 }
  ]
}
```

Ответ сервера (инструкция контроллеру пока заглушка):

```json
{}
```

## PostgreSQL схема

Таблица `controllers`:

- `id` - идентификатор записи
- `name` - краткое наименование
- `discription` - описание контроллера

Таблица `controller_data`:

- `id` - идентификатор записи
- `pin` - название датчика в системе
- `controller_id` - идентификатор контроллера (FK на `controllers.id`)
- `value` - значение датчика
- `created_at` - время получения показания

## Продакшн-образ Docker

Сборка:

```bash
docker build -t smart-home-control .
```

Запуск:

```bash
docker run --rm -p 3000:3000 smart-home-control
```

## API тест

Тест очистки истории пинов (регресс на lowercase-пины):

```bash
docker compose exec -T smart-home npm run test:api
```

## Что реализовано

- Дашборд с устройствами и ключевыми метриками дома
- Переключение состояния устройств
- Изменение уровня (например, яркости) через ползунок
- Раздел сценариев автоматизации
- Раздел расписания
- Раздел системных настроек

## Технологии

- Nuxt 3
- Vue 3
- TypeScript (в `script setup`)
