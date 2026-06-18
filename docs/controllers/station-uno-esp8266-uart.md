# Контроллер станции (Arduino Uno + ESP8266 UART)

## Скетчи
- Arduino Uno: `client/controller/station/v1/arduino_uno_station_uart_controller_v1/`
- ESP8266 proxy: `client/controller/station/v1/esp8266_station_wifi_proxy_v1/`

## Назначение
Arduino Uno читает датчики станции, управляет 8 реле, показывает `monitor` на TM1637 и обменивается CSV с ESP8266 по UART. ESP8266 отвечает за Wi-Fi, локальные страницы, provisioning/report API и преобразование CSV <-> JSON report-contract.

## Фиксированная распиновка
- `D2` -> `relay_1`
- `D3` -> `relay_2`
- `D4` -> `relay_3`
- `D5` -> `relay_4`
- `D6` -> `relay_5`
- `D7` -> `relay_6`
- `D8` -> `relay_7`
- `D9` -> `relay_8`
- `D10` -> `ESP TX` (Arduino RX)
- `D11` -> `ESP RX` (Arduino TX через делитель уровня 5V -> 3.3V)
- `D12` -> `FS-IR02` digital output (`tank_level`)
- `D13` -> свободен / встроенный LED
- `A0` -> `DS18B20` data (OneWire, `water_temperature`)
- `A1` -> фоторезистор, аналоговый выход (`light_level_raw`)
- `A2` -> `TM1637 DIO`
- `A3` -> `TM1637 CLK`
- `A4` -> I2C `SDA` (`BMP280`: `atm_pressure`, `air_temperature`)
- `A5` -> I2C `SCL` (`BMP280`: `atm_pressure`, `air_temperature`)

## Линии питания
- `5V` -> `Relay VCC`, `TM1637 VCC`, `DS18B20 VCC`, `BMP280`/датчики при совместимом модуле питания
- `3.3V` -> ESP8266 и датчики/модули, которым требуется 3.3V
- `GND` -> общая земля всех подключенных модулей

## Ключи телеметрии (поле `readings[].pin` на сервере)
- `relay_1` -> фактическое состояние выхода `D2`
- `relay_2` -> фактическое состояние выхода `D3`
- `relay_3` -> фактическое состояние выхода `D4`
- `relay_4` -> фактическое состояние выхода `D5`
- `relay_5` -> фактическое состояние выхода `D6`
- `relay_6` -> фактическое состояние выхода `D7`
- `relay_7` -> фактическое состояние выхода `D8`
- `relay_8` -> фактическое состояние выхода `D9`
- `tank_level` -> бинарный уровень бака с `FS-IR02` (`D12`, active low)
- `light_level_raw` -> сырое значение фоторезистора (`A1`)
- `atm_pressure` -> атмосферное давление с `BMP280` (`A4/A5`, hPa)
- `air_temperature` -> температура воздуха с `BMP280` (`A4/A5`)
- `water_temperature` -> температура воды с `DS18B20` (`A0`)

## Примечания
- `D0/D1` оставить под USB Serial и прошивку Arduino Uno.
- `D10/D11` заняты обменом с ESP8266 через `SoftwareSerial`.
- Линия `Arduino TX -> ESP RX` должна идти через делитель уровня или level shifter.
- Реле активны низким уровнем (`RELAY_ACTIVE_LOW = true`).
- `tank_level` инвертируется как active low (`TANK_LEVEL_ACTIVE_LOW = true`).
- Скетч станции нужно синхронизировать с этой целевой распиновкой перед прошивкой.
- При потере облака/ошибке ESP8266 отправляет Uno безопасное выключение всех 8 реле и `monitor=E<status>`.
- TM1637 используется для отображения `monitor` из ответа сервера или локальных ошибок.
