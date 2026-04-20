local sys = require "luci.sys"

m = Map(
  "home-aidvor",
  "Home Aidvor",
  "Настройки локального gateway. Рекомендуется менять только необходимые поля. " ..
  "После сохранения сервис автоматически перезапускается."
)

s = m:section(NamedSection, "main", "home-aidvor", "Основные параметры")
s.addremove = false
s.anonymous = true

enabled = s:option(Flag, "enabled", "Включен")
enabled.default = enabled.enabled
enabled.rmempty = false
enabled.description = "Включает/выключает сервис Home Aidvor. По умолчанию: включен."

port = s:option(Value, "port", "Локальный порт")
port.datatype = "port"
port.default = "3001"
port.description = "Порт, на котором gateway принимает запросы от контроллера и UI в LAN. Безопасное значение: 3001."

cloud_base_url = s:option(Value, "cloud_base_url", "Cloud Base URL")
cloud_base_url.placeholder = "https://home.aidvor.ru"
cloud_base_url.description = "Базовый URL глобального сервера. Рекомендуется HTTPS. Пример: https://home.aidvor.ru"

cloud_health_path = s:option(Value, "cloud_health_path", "Cloud Health Path")
cloud_health_path.default = "/api/ping"
cloud_health_path.description = "Путь проверки доступности облака. Безопасное значение: /api/ping"

cloud_report_path = s:option(Value, "cloud_report_path", "Cloud Report Path")
cloud_report_path.default = "/api/controller/report"
cloud_report_path.description = "Путь отправки телеметрии контроллера в облако. Безопасное значение: /api/controller/report"

cloud_settings_path_template = s:option(Value, "cloud_settings_path_template", "Cloud Settings Path Template")
cloud_settings_path_template.default = "/api/controllers/{id}/settings"
cloud_settings_path_template.description = "Шаблон пути чтения настроек контроллера. Используйте {id} для UUID контроллера."

heartbeat_interval_ms = s:option(Value, "heartbeat_interval_ms", "Heartbeat Interval (ms)")
heartbeat_interval_ms.datatype = "uinteger"
heartbeat_interval_ms.default = "5000"
heartbeat_interval_ms.description = "Интервал проверки доступности облака. Безопасно: 5000-15000 мс (по умолчанию 5000)."

cloud_request_timeout_ms = s:option(Value, "cloud_request_timeout_ms", "Cloud Request Timeout (ms)")
cloud_request_timeout_ms.datatype = "uinteger"
cloud_request_timeout_ms.default = "1500"
cloud_request_timeout_ms.description = "Таймаут ожидания ответа от облака. Безопасно: 1000-3000 мс (по умолчанию 1500)."

storage_check_interval_ms = s:option(Value, "storage_check_interval_ms", "Storage Check Interval (ms)")
storage_check_interval_ms.datatype = "uinteger"
storage_check_interval_ms.default = "10000"
storage_check_interval_ms.description = "Частота проверки доступности локального хранилища. Безопасно: 5000-30000 мс."

storage_path = s:option(Value, "storage_path", "Storage Path")
storage_path.default = "/tmp/home-openwrt-storage"
storage_path.description = "Путь к локальному хранилищу очереди офлайн-данных. По умолчанию: /tmp/home-openwrt-storage"

manual_pins = s:option(Value, "manual_pins", "Manual Pins (CSV)")
manual_pins.default = "D3,D5,D6,D9"
manual_pins.description = "Список управляемых локальных цифровых пинов (CSV). Для вашей схемы обычно: D3,D5,D6,D9"

manual_pin_invert = s:option(Value, "manual_pin_invert", "Manual Pin Invert (CSV)")
manual_pin_invert.default = ""
manual_pin_invert.description = "Пины с инверсной логикой (CSV). Оставьте пустым, если инверсии нет."

default_send_interval_seconds = s:option(Value, "default_send_interval_seconds", "Default Send Interval (sec)")
default_send_interval_seconds.datatype = "uinteger"
default_send_interval_seconds.default = "5"
default_send_interval_seconds.description = "Интервал отправки, если сервер не задал другой. Безопасно: 5-30 сек (по умолчанию 5)."

hmac_enabled = s:option(Flag, "hmac_enabled", "Enable HMAC")
hmac_enabled.default = hmac_enabled.disabled
hmac_enabled.rmempty = false
hmac_enabled.description = "Подпись запросов между gateway и облаком. Рекомендуется: включено."

proxy_id = s:option(Value, "proxy_id", "Proxy ID")
proxy_id.default = "getaway"
proxy_id.description = "Идентификатор этого gateway в HMAC-контракте. Пример: getaway"

proxy_secret = s:option(Value, "proxy_secret", "Proxy Secret")
proxy_secret.password = true
proxy_secret.description = "Секрет HMAC. Должен совпадать с ключом на сервере для данного Proxy ID."

https_enabled = s:option(Flag, "https_enabled", "Enable HTTPS")
https_enabled.default = https_enabled.disabled
https_enabled.rmempty = false
https_enabled.description = "Включает HTTPS на локальном gateway. Требуются корректные cert/key пути."

https_port = s:option(Value, "https_port", "HTTPS Port")
https_port.datatype = "port"
https_port.default = "3443"
https_port.description = "Порт HTTPS gateway. Безопасное значение по умолчанию: 3443."

https_cert_path = s:option(Value, "https_cert_path", "HTTPS Cert Path")
https_cert_path.default = "/etc/ssl/custom/gateway.crt"
https_cert_path.description = "Путь к сертификату (.crt/.pem) для HTTPS."

https_key_path = s:option(Value, "https_key_path", "HTTPS Key Path")
https_key_path.default = "/etc/ssl/private/gateway.key"
https_key_path.description = "Путь к приватному ключу для HTTPS."

function m.on_after_commit(self)
  sys.call("/etc/init.d/home-aidvor restart >/dev/null 2>&1")
end

return m
