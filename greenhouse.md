# Контроллер Теплицы (Arduino Uno + Ethernet Shield)

## 1. Назначение контроллера
Контроллер теплицы предназначен для:
- сбора базовой телеметрии по воздуху и двум аналоговым каналам (`soil_moisture_raw`, `light_level_raw`),
- передачи фактических состояний реле,
- получения команд управления реле от сервера,
- отображения значения `monitor` (код привязки/время/число) на `TM1637`.

Тип контроллера: жестко типизированный (не универсальный). Список пинов и ключей телеметрии фиксирован.

## 2. Датчики, реле и распиновка

### 2.1 Служебные линии Ethernet Shield (SPI)
- `D10` -> `SS` (служебный)
- `D11` -> `MOSI`
- `D12` -> `MISO`
- `D13` -> `SCK`

### 2.2 Датчики
- `DHT22`:
  - `DATA` -> `D2`
  - ключи: `air_temperature`, `air_humidity`
- Аналоговые входы:
  - `A0` -> `soil_moisture_raw`
  - `A1` -> `light_level_raw`

### 2.3 Реле
- `relay_1` -> `D3`
- `relay_2` -> `D9`
- `relay_3` -> `D5`
- `relay_4` -> `D6`

### 2.4 Дисплей (опционально)
- `TM1637 DIO` -> `D7`
- `TM1637 CLK` -> `D8`

### 2.5 Питание
- `5V` -> `DHT22 VCC`, `Relay VCC`, `TM1637 VCC`
- `GND` -> общая земля всех модулей

## 3. Ключи телеметрии
Контроллер отправляет только эти ключи:
- `controller_id`
- `relay_1`
- `relay_2`
- `relay_3`
- `relay_4`
- `soil_moisture_raw`
- `light_level_raw`
- `air_humidity`
- `air_temperature`

## 4. Контракт обмена данными

### 4.1 Протокол
- Транспорт: HTTP
- Endpoint: `POST /api/controller/report`
- Между `controller -> gateway`: CSV в `text/plain`
- Между `gateway -> server`: JSON в `application/json`

### 4.2 Запрос от контроллера к gateway (CSV)
Пример:

```text
controller_id=019d5529-ceee-7748-b9a8-a2e3ce1e8b8f;relay_1=0;relay_2=1;relay_3=0;relay_4=1;soil_moisture_raw=412;light_level_raw=285;air_humidity=37.0;air_temperature=25.4
```

Требования:
- `relay_*` передаются как фактическое логическое состояние: `1=включено`, `0=выключено`.
- `soil_moisture_raw`, `light_level_raw` передаются как `ADC` значения.
- `air_humidity`, `air_temperature` передаются из `DHT22` (если чтение недоступно, используется последняя валидная пара).

### 4.3 Запрос от gateway к server (JSON)
Пример:

```json
{
  "controller_id": "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f",
  "readings": [
    { "pin": "relay_1", "value": 0 },
    { "pin": "relay_2", "value": 1 },
    { "pin": "relay_3", "value": 0 },
    { "pin": "relay_4", "value": 1 },
    { "pin": "soil_moisture_raw", "value": 412 },
    { "pin": "light_level_raw", "value": 285 },
    { "pin": "air_humidity", "value": 37.0 },
    { "pin": "air_temperature", "value": 25.4 }
  ]
}
```

Требования:
- `controller_id` обязателен.
- `readings` — массив объектов `{ pin, value }`.
- Передаются только ключи, перечисленные в разделе 3.

### 4.4 Ответ от server к gateway (JSON)
Пример:

```json
{
  "send_interval_seconds": 5,
  "relay_1": 1,
  "relay_2": 0,
  "relay_3": 1,
  "relay_4": 0,
  "monitor": "1234"
}
```

### 4.5 Ответ от gateway к контроллеру (CSV)
Пример:

```text
send_interval_seconds=5;relay_1=1;relay_2=0;relay_3=1;relay_4=0;monitor=1234
```

Поддерживаемые ключи ответа:
- `send_interval_seconds` — период следующей отправки отчета.
- `relay_1..relay_4` — требуемые состояния выходов.
- `monitor` — значение для отображения на `TM1637`:
  - 4 цифры кода привязки,
  - время `HH:MM`,
  - или числовое значение мониторинга.

### 4.6 Обработка ошибок
- При ошибке подключения или HTTP `>= 400` контроллер:
  - переводит все реле в выключенное состояние,
  - показывает ошибку на `TM1637` (`Err`/`E<code>` в зависимости от типа ошибки).
