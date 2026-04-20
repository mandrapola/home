# home-openwrt

Локальный edge-gateway для OpenWrt.

## Назначение
- принимать отчеты от Arduino (`/api/controller/report`)
- работать автономно при недоступности глобального сервера
- синхронизировать телеметрию и команды с глобальным сервером

## Зафиксированные требования
- См. [docs/requirements.md](docs/requirements.md)

## Реализовано сейчас
- режимы `online` / `offline`
- подрежимы хранения `full_offline` / `manual_offline`
- проверка доступности облака (heartbeat)
- проверка доступности внешнего хранилища (rw-check)
- API ручного управления локальными пинами
- endpoint `POST /api/controller/report` с тем же контрактом, что у глобального сервера
  - при доступном глобальном сервере работает как proxy (запрос и ответ транзитом)
  - при недоступном глобальном сервере формирует локальный fallback-ответ
- инверсия digital-пинов синхронизируется с глобального сервера (`/api/controllers/{id}/settings`)

## Быстрый старт (локально, для разработки)
```bash
cd home-openwrt
npm run dev
```

Проверка:
```bash
curl -s http://127.0.0.1:8081/api/system/status
```

## API
- `GET /api/ping`
- `GET /api/system/status`
- `GET /api/local/pins`
- `PUT /api/local/pins/:pin/state`
  - body: `{ "value": 0|1, "source": "manual_ui" }`
- `POST /api/controller/report`
  - request: как у глобального сервера (`controller_id`, `readings`/legacy fields)
  - response: `{ "send_interval_seconds": number, "digital_outputs": { "D3": 0|1, ... } }`

## Переменные окружения
- `PORT` (по умолчанию `8081`)
- `CLOUD_BASE_URL` (например `http://192.168.1.10:3000`)
- `CLOUD_HEALTH_PATH` (по умолчанию `/api/ping`)
- `CLOUD_REPORT_PATH` (по умолчанию `/api/controller/report`)
- `CLOUD_SETTINGS_PATH_TEMPLATE` (по умолчанию `/api/controllers/{id}/settings`)
- `HEARTBEAT_INTERVAL_MS` (по умолчанию `5000`)
- `CLOUD_REQUEST_TIMEOUT_MS` (по умолчанию `1500`, таймаут cloud-запросов для быстрого fallback)
- `GATEWAY_HTTPS_ENABLED` (по умолчанию `false`, включить HTTPS на локальном gateway)
- `GATEWAY_HTTPS_PORT` (по умолчанию `3443`, HTTPS-порт gateway)
- `GATEWAY_HTTPS_CERT_PATH` (путь к `.crt` для HTTPS gateway)
- `GATEWAY_HTTPS_KEY_PATH` (путь к `.key` для HTTPS gateway)
- `STORAGE_PATH` (по умолчанию `/mnt/usb/home-openwrt`)
- `STORAGE_CHECK_INTERVAL_MS` (по умолчанию `10000`)
- `MANUAL_PINS` (по умолчанию `D3,D5,D6,D9`)
- `MANUAL_PIN_INVERT` (опциональный bootstrap для offline старта до первой синхронизации с cloud)
- `DEFAULT_SEND_INTERVAL_SECONDS` (по умолчанию `5`)

## Установка на OpenWrt (как сервис)

Ниже порядок установки с примерами команд.

### 1) Подготовить архив на ПК
```bash
cd home-openwrt
tar -czf home-openwrt-runtime.tgz src package.json README.md config scripts
```

### 2) Загрузить архив на роутер
```bash
scp ./home-openwrt-runtime.tgz root@192.168.0.1:/tmp/
```

### 3) Развернуть gateway на роутере
```sh
mkdir -p /opt/home-openwrt
tar -xzf /tmp/home-openwrt-runtime.tgz -C /opt/home-openwrt
```

### 4) Установить минимальные пакеты OpenWrt
```sh
opkg update
opkg install node ca-bundle curl
```

### 5) Создать UCI-конфиг `/etc/config/home-aidvor`
Рекомендуемый способ (через UCI, для OpenWrt):
```sh
cp /opt/home-openwrt/scripts/openwrt/home-aidvor.uci /etc/config/home-aidvor
uci commit home-aidvor
```

Опционально можно оставить legacy `.env` (например, для секретов), но при старте сервис будет генерировать runtime-конфиг из UCI в `/var/etc/home-aidvor.env` и использовать его как приоритетный.

### 5.1) (Опционально) Включить HTTPS на gateway
Пример создания самоподписанного сертификата:
```sh
mkdir -p /etc/ssl/custom /etc/ssl/private
openssl req -x509 -newkey rsa:2048 -nodes -days 3650 \
  -keyout /etc/ssl/private/gateway.key \
  -out /etc/ssl/custom/gateway.crt \
  -subj "/CN=192.168.0.1"
```

Добавьте в UCI:
```sh
uci set home-aidvor.main.https_enabled='1'
uci set home-aidvor.main.https_port='3443'
uci set home-aidvor.main.https_cert_path='/etc/ssl/custom/gateway.crt'
uci set home-aidvor.main.https_key_path='/etc/ssl/private/gateway.key'
uci commit home-aidvor
```

### 6) Подключить init.d сервис (Home Aidvor)
```sh
cp /opt/home-openwrt/scripts/openwrt/home-aidvor.init /etc/init.d/home-aidvor
chmod +x /etc/init.d/home-aidvor
/etc/init.d/home-aidvor enable
/etc/init.d/home-aidvor start
```

### 6.1) (Опционально) Добавить страницу в LuCI
```sh
mkdir -p /usr/lib/lua/luci/controller
mkdir -p /usr/lib/lua/luci/model/cbi
cp /opt/home-openwrt/scripts/openwrt/luci/controller/home_aidvor.lua /usr/lib/lua/luci/controller/home_aidvor.lua
cp /opt/home-openwrt/scripts/openwrt/luci/model/cbi/home_aidvor.lua /usr/lib/lua/luci/model/cbi/home_aidvor.lua
/etc/init.d/uhttpd restart
rm -rf /tmp/luci-*
```

После этого в LuCI появится пункт: `Services -> Home Aidvor`.

### 7) Открыть доступ к порту gateway из LAN
```sh
uci add firewall rule
uci set firewall.@rule[-1].name='Allow-Home-Aidvor'
uci set firewall.@rule[-1].src='lan'
uci set firewall.@rule[-1].proto='tcp'
uci set firewall.@rule[-1].dest_port='3001'
uci set firewall.@rule[-1].target='ACCEPT'
uci commit firewall
/etc/init.d/firewall restart
```

### 8) Проверить работу
На роутере:
```sh
/etc/init.d/home-aidvor status
logread -e home-aidvor
curl -s http://127.0.0.1:3001/api/system/status
cat /var/etc/home-aidvor.env
```

С ПК в LAN:
```bash
curl -s http://192.168.0.1:3001/api/system/status
```

## Обновление gateway на роутере
После изменений в коде:
```bash
cd home-openwrt
tar -czf home-openwrt-runtime.tgz src package.json README.md config scripts
scp ./home-openwrt-runtime.tgz root@192.168.0.1:/tmp/
ssh root@192.168.0.1 "tar -xzf /tmp/home-openwrt-runtime.tgz -C /opt/home-openwrt && /etc/init.d/home-aidvor restart"
```

Если у вас уже установлен старый сервис `home-gateway`, его можно отключить и заменить:
```sh
/etc/init.d/home-gateway disable
/etc/init.d/home-gateway stop
rm -f /etc/init.d/home-gateway
cp /opt/home-openwrt/scripts/openwrt/home-aidvor.init /etc/init.d/home-aidvor
chmod +x /etc/init.d/home-aidvor
/etc/init.d/home-aidvor enable
/etc/init.d/home-aidvor start
```
