const toNumber = (value, fallback) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

const parsePins = (value) => {
  const raw = String(value ?? 'D3,D5,D6,D9')
  return raw
    .split(',')
    .map((pin) => pin.trim().toUpperCase())
    .filter(Boolean)
}

const parseInvertedPins = (value) => {
  const raw = String(value ?? '')
  return new Set(
    raw
      .split(',')
      .map((pin) => pin.trim().toUpperCase())
      .filter(Boolean)
  )
}

export const loadConfig = () => {
  const cloudBaseUrl = String(process.env.CLOUD_BASE_URL ?? '').trim()
  const httpsEnabled = String(process.env.GATEWAY_HTTPS_ENABLED ?? '').trim().toLowerCase() === 'true'
  return {
    port: Math.max(1, Math.trunc(toNumber(process.env.PORT, 8081))),
    httpsEnabled,
    httpsPort: Math.max(1, Math.trunc(toNumber(process.env.GATEWAY_HTTPS_PORT, 3443))),
    httpsCertPath: String(process.env.GATEWAY_HTTPS_CERT_PATH ?? '').trim(),
    httpsKeyPath: String(process.env.GATEWAY_HTTPS_KEY_PATH ?? '').trim(),
    cloudBaseUrl: cloudBaseUrl || null,
    cloudHealthPath: String(process.env.CLOUD_HEALTH_PATH ?? '/api/ping').trim() || '/api/ping',
    cloudReportPath: String(process.env.CLOUD_REPORT_PATH ?? '/api/controller/report').trim() || '/api/controller/report',
    cloudSettingsPathTemplate:
      String(process.env.CLOUD_SETTINGS_PATH_TEMPLATE ?? '/api/controllers/{id}/settings').trim() ||
      '/api/controllers/{id}/settings',
    heartbeatIntervalMs: Math.max(1000, Math.trunc(toNumber(process.env.HEARTBEAT_INTERVAL_MS, 5000))),
    cloudRequestTimeoutMs: Math.max(300, Math.trunc(toNumber(process.env.CLOUD_REQUEST_TIMEOUT_MS, 1500))),
    storageCheckIntervalMs: Math.max(1000, Math.trunc(toNumber(process.env.STORAGE_CHECK_INTERVAL_MS, 10000))),
    storagePath: String(process.env.STORAGE_PATH ?? '/mnt/usb/home-openwrt').trim() || '/mnt/usb/home-openwrt',
    manualPins: parsePins(process.env.MANUAL_PINS),
    manualPinInvert: parseInvertedPins(process.env.MANUAL_PIN_INVERT),
    defaultSendIntervalSeconds: Math.max(1, Math.trunc(toNumber(process.env.DEFAULT_SEND_INTERVAL_SECONDS, 5))),
    proxyId: String(process.env.PROXY_HMAC_PROXY_ID ?? '').trim(),
    proxySecret: String(process.env.PROXY_HMAC_SECRET ?? '').trim(),
    hmacEnabled: String(process.env.PROXY_HMAC_ENABLED ?? '').trim().toLowerCase() === 'true'
  }
}
