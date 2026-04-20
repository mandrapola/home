import http from 'node:http'
import https from 'node:https'
import { readFileSync } from 'node:fs'
import { createHash, createHmac, randomUUID } from 'node:crypto'
import { loadConfig } from './settings.js'
import { ModeManager } from './mode-manager.js'
import { createPinStateStore } from './pin-state-store.js'
import { renderDashboardHtml } from './dashboard-html.js'
import {
  collectBodyJson,
  collectBodyText,
  sendJson,
  sendText,
  notFound,
  methodNotAllowed,
  badRequest
} from './http-utils.js'

const config = loadConfig()
const modeManager = new ModeManager(config)
const pinStateStore = createPinStateStore(config.manualPins, config.manualPinInvert)

const toNumber = (value) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const isUuid = (value) => /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || '').trim())

const isCsvRequest = (req) => {
  const format = String(req.headers['x-smarthome-format'] || '').trim().toLowerCase()
  if (format === 'csv') {
    return true
  }
  const contentType = String(req.headers['content-type'] || '').trim().toLowerCase()
  return contentType.includes('text/plain') || contentType.includes('text/csv')
}

const parseCsvPairs = (text) => {
  const result = {}
  const tokens = String(text || '')
    .split(/[;\r\n]+/)
    .map((part) => part.trim())
    .filter((part) => part.length > 0)

  for (const token of tokens) {
    const index = token.indexOf('=')
    if (index < 1) {
      continue
    }
    const key = token.slice(0, index).trim()
    const value = token.slice(index + 1).trim()
    if (!key) {
      continue
    }
    result[key] = value
  }

  return result
}

const normalizeReadingsFromCsv = (pairs) =>
  Object.entries(pairs)
    .filter(([key]) => key !== 'controller_id')
    .map(([pin, value]) => ({ pin: String(pin).trim(), value: toNumber(value) }))
    .filter((item) => item.pin.length > 0 && item.value !== null)

const parseDigitalOutputsCsv = (text) => {
  const pairs = parseCsvPairs(text)
  const outputs = {}
  for (const [key, value] of Object.entries(pairs)) {
    if (key === 'send_interval_seconds' || key === 'pairing_code' || key === 'pairing_code_expires_at') {
      continue
    }
    const numeric = toNumber(value)
    if (numeric === null) {
      continue
    }
    outputs[key] = numeric > 0 ? 1 : 0
  }
  return outputs
}

const toRelayKey = (pin) => {
  const normalized = String(pin || '').trim().toUpperCase()
  if (normalized === 'D3') return 'relay_1'
  if (normalized === 'D5') return 'relay_2'
  if (normalized === 'D6') return 'relay_3'
  if (normalized === 'D9') return 'relay_4'
  if (normalized.startsWith('RELAY_')) return normalized.toLowerCase()
  return String(pin || '').trim().toLowerCase()
}

const toInternalDigitalPin = (pin) => {
  const normalized = String(pin || '').trim().toUpperCase()
  if (normalized === 'RELAY_1') return 'D3'
  if (normalized === 'RELAY_2') return 'D5'
  if (normalized === 'RELAY_3') return 'D6'
  if (normalized === 'RELAY_4') return 'D9'
  return normalized
}

const remapOutputsToRelay = (digitalOutputs) => {
  const result = {}
  for (const [pin, value] of Object.entries(digitalOutputs || {})) {
    result[toRelayKey(pin)] = Number(value) > 0 ? 1 : 0
  }
  return result
}

const remapOutputsToInternal = (digitalOutputs) => {
  const result = {}
  for (const [pin, value] of Object.entries(digitalOutputs || {})) {
    result[toInternalDigitalPin(pin)] = Number(value) > 0 ? 1 : 0
  }
  return result
}

const rewriteCsvResponseToRelay = (text) => {
  const pairs = parseCsvPairs(text)
  const parts = []

  if (pairs.send_interval_seconds !== undefined) {
    parts.push(`send_interval_seconds=${pairs.send_interval_seconds}`)
  }

  const remapped = remapOutputsToRelay(pairs)
  for (const [pin, value] of Object.entries(remapped)) {
    if (pin === 'send_interval_seconds' || pin === 'pairing_code' || pin === 'pairing_code_expires_at') {
      continue
    }
    parts.push(`${pin}=${value}`)
  }

  if (pairs.pairing_code !== undefined) {
    parts.push(`pairing_code=${pairs.pairing_code}`)
  }
  if (pairs.pairing_code_expires_at !== undefined) {
    parts.push(`pairing_code_expires_at=${pairs.pairing_code_expires_at}`)
  }

  return parts.join(';')
}

const normalizeReadings = (payload) => {
  if (Array.isArray(payload?.readings) && payload.readings.length > 0) {
    return payload.readings
      .map((item) => ({ pin: String(item?.pin ?? '').trim(), value: toNumber(item?.value) }))
      .filter((item) => item.pin.length > 0 && item.value !== null)
  }

  const mapped = []
  const thermometer = toNumber(payload?.thermometer ?? payload?.temperature)
  if (thermometer !== null) {
    mapped.push({ pin: 'thermometer', value: thermometer })
  }

  const pressure = toNumber(payload?.pressure)
  if (pressure !== null) {
    mapped.push({ pin: 'pressure', value: pressure })
  }

  const humidity = toNumber(payload?.humidity)
  if (humidity !== null) {
    mapped.push({ pin: 'humidity', value: humidity })
  }

  return mapped
}

const joinUrl = (baseUrl, path) => {
  if (!baseUrl) {
    return null
  }
  const normalizedBase = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  return `${normalizedBase}${normalizedPath}`
}

const buildForwardedHeaders = (req) => {
  const host = String(req.headers.host || '').trim()
  const xForwardedProto = String(req.headers['x-forwarded-proto'] || '').trim()
  const proto = xForwardedProto || (req.socket?.encrypted ? 'https' : 'http')
  const xForwardedPort = String(req.headers['x-forwarded-port'] || '').trim()
  const forwardedPort = xForwardedPort || (host.includes(':') ? host.split(':').pop() : '')

  const headers = {
    'x-forwarded-proto': proto,
    'x-forwarded-host': host,
  }

  if (forwardedPort) {
    headers['x-forwarded-port'] = forwardedPort
  }

  // Preserve external host so upstream generates correct absolute URLs.
  if (host) {
    headers.host = host
  }

  return headers
}

const rewriteUpstreamLocation = (req, location, cloudBaseUrl) => {
  const raw = String(location || '').trim()
  if (!raw) {
    return ''
  }

  const host = String(req.headers.host || '').trim()
  const proto = String(req.headers['x-forwarded-proto'] || '').trim() || (req.socket?.encrypted ? 'https' : 'http')
  const externalOrigin = host ? `${proto}://${host}` : ''
  const cloudOrigin = cloudBaseUrl ? String(cloudBaseUrl).replace(/\/$/, '') : ''

  if (!externalOrigin) {
    return raw
  }

  if (raw.startsWith('/')) {
    return `${externalOrigin}${raw}`
  }

  if (cloudOrigin && raw.startsWith(cloudOrigin)) {
    return `${externalOrigin}${raw.slice(cloudOrigin.length)}`
  }

  return raw
}

const rewriteHtmlOrigins = (req, body, cloudBaseUrl) => {
  const text = String(body ?? '')
  if (!text) {
    return text
  }

  const host = String(req.headers.host || '').trim()
  const proto = String(req.headers['x-forwarded-proto'] || '').trim() || (req.socket?.encrypted ? 'https' : 'http')
  const externalOrigin = host ? `${proto}://${host}` : ''
  if (!externalOrigin) {
    return text
  }

  let output = text
  const cloudOrigin = cloudBaseUrl ? String(cloudBaseUrl).replace(/\/$/, '') : ''
  if (cloudOrigin) {
    output = output.split(`${cloudOrigin}/`).join(`${externalOrigin}/`)
    output = output.split(cloudOrigin).join(externalOrigin)
  }

  // Fallback for common internal upstream hostname.
  output = output.split('http://server/').join(`${externalOrigin}/`)
  output = output.split('http://server').join(externalOrigin)

  return output
}

const sha256Hex = (input) => createHash('sha256').update(String(input ?? ''), 'utf8').digest('hex')

const buildProxyHmacHeaders = (method, path, bodyText = '') => {
  if (!config.hmacEnabled || !config.proxyId || !config.proxySecret) {
    return {}
  }

  const timestamp = String(Math.floor(Date.now() / 1000))
  const nonce = randomUUID()
  const canonical = [
    String(method || 'GET').toUpperCase(),
    String(path || '/'),
    sha256Hex(bodyText),
    timestamp,
    nonce,
    config.proxyId
  ].join('\n')

  const signature = createHmac('sha256', config.proxySecret).update(canonical, 'utf8').digest('hex')
  return {
    'x-proxy-id': config.proxyId,
    'x-timestamp': timestamp,
    'x-nonce': nonce,
    'x-signature': signature
  }
}

const fetchWithTimeout = async (url, init = {}) => {
  const controller = new AbortController()
  const timeoutMs = Math.max(300, Number(config.cloudRequestTimeoutMs) || 1500)
  const timer = setTimeout(() => controller.abort(), timeoutMs)

  try {
    return await fetch(url, {
      ...init,
      signal: controller.signal
    })
  } finally {
    clearTimeout(timer)
  }
}

const offlineControllerResponse = () => ({
  send_interval_seconds: config.defaultSendIntervalSeconds,
  digital_outputs: pinStateStore.toDigitalOutputs()
})

const resolveControllerSettingsPath = (controllerId) => {
  const template = config.cloudSettingsPathTemplate || '/api/controllers/{id}/settings'
  const withId = template.includes('{id}')
    ? template.replaceAll('{id}', String(controllerId))
    : `${template.replace(/\/$/, '')}/${controllerId}/settings`
  return withId.startsWith('/') ? withId : `/${withId}`
}

const syncPinInversionFromCloud = async (controllerId) => {
  if (!config.cloudBaseUrl || !isUuid(controllerId)) {
    return
  }

  const path = resolveControllerSettingsPath(controllerId)
  const target = joinUrl(config.cloudBaseUrl, path)
  if (!target) {
    return
  }

  const response = await fetchWithTimeout(target, {
    method: 'GET',
    headers: {
      accept: 'application/json'
    }
  })

  if (!response.ok) {
    throw new Error(`cloud_settings_http_${response.status}`)
  }

  const body = await response.json()
  const pinConfigs = Array.isArray(body?.pinConfigs) ? body.pinConfigs : []
  const manualPins = new Set(config.manualPins.map((pin) => String(pin).trim().toUpperCase()))
  const invertedPins = new Set(
    pinConfigs
      .filter((item) => manualPins.has(String(item?.pin ?? '').trim().toUpperCase()))
      .filter((item) => Boolean(item?.invert_digital_logic))
      .map((item) => String(item.pin).trim().toUpperCase())
  )

  pinStateStore.setInvertedPins(invertedPins)
}

const proxyToCloud = async (req, res, cloudBaseUrl, pathname, search = '', bodyText = '') => {
  const target = joinUrl(cloudBaseUrl, pathname + search)
  if (!target) {
    return false
  }

  const headers = {
    accept: req.headers.accept ?? '*/*',
    ...buildProxyHmacHeaders(req.method ?? 'GET', pathname, bodyText),
    ...buildForwardedHeaders(req)
  }
  if (req.headers['content-type']) {
    headers['content-type'] = req.headers['content-type']
  }
  if (req.headers['x-csrf-token']) {
    headers['x-csrf-token'] = req.headers['x-csrf-token']
  }
  if (req.headers['x-xsrf-token']) {
    headers['x-xsrf-token'] = req.headers['x-xsrf-token']
  }
  if (req.headers['x-requested-with']) {
    headers['x-requested-with'] = req.headers['x-requested-with']
  }
  if (req.headers.cookie) {
    headers.cookie = req.headers.cookie
  }
  if (req.headers.origin) {
    headers.origin = req.headers.origin
  }
  if (req.headers.referer) {
    headers.referer = req.headers.referer
  }
  if (req.headers['user-agent']) {
    headers['user-agent'] = req.headers['user-agent']
  }

  try {
    const response = await fetchWithTimeout(target, {
      method: req.method ?? 'GET',
      redirect: 'manual',
      headers,
      body:
        bodyText.length > 0 && req.method !== 'GET' && req.method !== 'HEAD'
          ? bodyText
          : undefined
    })

    const body = await response.text()
    const contentType = response.headers.get('content-type') ?? 'text/plain; charset=utf-8'
    const normalizedContentType = String(contentType).toLowerCase()
    const responseBody = normalizedContentType.includes('text/html')
      ? rewriteHtmlOrigins(req, body, cloudBaseUrl)
      : body
    const location = response.headers.get('location')
    const rewrittenLocation = rewriteUpstreamLocation(req, location, cloudBaseUrl)
    const extraHeaders = rewrittenLocation ? { location: rewrittenLocation } : {}
    const setCookies = typeof response.headers.getSetCookie === 'function'
      ? response.headers.getSetCookie()
      : []
    if (Array.isArray(setCookies) && setCookies.length > 0) {
      extraHeaders['set-cookie'] = setCookies
    } else {
      const mergedSetCookie = response.headers.get('set-cookie')
      if (mergedSetCookie) {
        extraHeaders['set-cookie'] = mergedSetCookie
      }
    }
    sendText(res, response.status, responseBody, contentType, extraHeaders)
    modeManager.markCloudOk()
    return true
  } catch (error) {
    if (error instanceof Error && error.name === 'AbortError') {
      modeManager.markCloudError('proxy_timeout')
    } else {
      modeManager.markCloudError(error instanceof Error ? error.message : 'proxy_failed')
    }
    return false
  }
}

const requestHandler = async (req, res) => {
  const method = req.method ?? 'GET'
  const requestUrl = new URL(req.url ?? '/', 'http://127.0.0.1')
  const pathname = requestUrl.pathname
  const search = requestUrl.search
  const status = modeManager.getStatus()

  if (method === 'GET' && pathname === '/api/system/status') {
    return sendJson(res, 200, {
      mode: status.mode,
      cloud_reachable: status.cloudReachable,
      storage_mode: status.storageMode,
      storage_available: status.storageAvailable,
      storage_path: config.storagePath,
      last_cloud_ok_at: status.lastCloudOkAt ? status.lastCloudOkAt.toISOString() : null,
      last_cloud_error: status.lastCloudError ?? null,
      queue_size: status.storageMode === 'full_offline' ? 0 : null,
      updated_at: new Date().toISOString()
    })
  }

  if (!pathname.startsWith('/api')) {
    if (status.mode === 'online' && config.cloudBaseUrl) {
      const bodyText =
        method !== 'GET' && method !== 'HEAD'
          ? await collectBodyText(req).catch(() => '')
          : ''
      const proxied = await proxyToCloud(req, res, config.cloudBaseUrl, pathname, search, bodyText)
      if (proxied) {
        return
      }
    }
  }

  if (method === 'GET' && pathname === '/') {
    const body = renderDashboardHtml()
    res.writeHead(200, {
      'content-type': 'text/html; charset=utf-8',
      'content-length': Buffer.byteLength(body)
    })
    res.end(body)
    return
  }

  if (method === 'GET' && pathname === '/api/ping') {
    return sendJson(res, 200, {
      ok: true,
      service: 'home-openwrt',
      mode: modeManager.getStatus().mode
    })
  }

  if (method === 'GET' && pathname === '/api/local/pins') {
    return sendJson(res, 200, {
      pins: pinStateStore.list(),
      updated_at: new Date().toISOString()
    })
  }

  if (method === 'POST' && pathname === '/api/controller/report') {
    let bodyText = ''
    let payload = null
    const csvMode = isCsvRequest(req)

    try {
      bodyText = await collectBodyText(req)
      if (csvMode) {
        payload = parseCsvPairs(bodyText)
      } else {
        payload = bodyText.length > 0 ? JSON.parse(bodyText) : {}
      }
    } catch (error) {
      return badRequest(res, error instanceof Error ? error.message : csvMode ? 'Invalid CSV payload.' : 'Invalid JSON payload.')
    }

    const controllerId = String(payload?.controller_id ?? '').trim()
    if (!isUuid(controllerId)) {
      return badRequest(res, 'controller_id must be UUID')
    }

    const readings = csvMode ? normalizeReadingsFromCsv(payload) : normalizeReadings(payload)
    if (readings.length === 0) {
      return badRequest(res, 'No valid sensor readings provided')
    }

    const cloudReportUrl = joinUrl(config.cloudBaseUrl, config.cloudReportPath)

    if (status.mode === 'online' && cloudReportUrl) {
      try {
        try {
          await syncPinInversionFromCloud(controllerId)
        } catch {
          // keep last known inversion map if cloud settings endpoint is temporarily unavailable
        }

        const cloudResponse = await fetchWithTimeout(cloudReportUrl, {
          method: 'POST',
          headers: {
            'content-type': csvMode ? 'text/plain' : 'application/json',
            accept: csvMode ? 'text/plain' : 'application/json',
            ...(csvMode ? { 'x-smarthome-format': 'csv' } : {}),
            ...buildProxyHmacHeaders('POST', config.cloudReportPath, bodyText)
          },
          body: bodyText
        })

        const responseText = await cloudResponse.text()
        const responseContentType =
          cloudResponse.headers.get('content-type') ?? 'application/json; charset=utf-8'

        if (cloudResponse.ok) {
          modeManager.markCloudOk()
          try {
            if (csvMode) {
              const outputs = remapOutputsToInternal(parseDigitalOutputsCsv(responseText))
              if (Object.keys(outputs).length > 0) {
                pinStateStore.applyDigitalOutputs(outputs, 'cloud')
              }
            } else {
              const body = responseText.length > 0 ? JSON.parse(responseText) : {}
              if (body && typeof body === 'object' && body.digital_outputs && typeof body.digital_outputs === 'object') {
                pinStateStore.applyDigitalOutputs(remapOutputsToInternal(body.digital_outputs), 'cloud')
              }
            }
          } catch {
            // pass-through response below even if it is not valid JSON
          }
        }
        if (csvMode) {
          return sendText(res, cloudResponse.status, rewriteCsvResponseToRelay(responseText), 'text/plain; charset=utf-8')
        }

        if (responseText.length > 0 && responseContentType.toLowerCase().includes('application/json')) {
          try {
            const body = JSON.parse(responseText)
            if (body && typeof body === 'object' && body.digital_outputs && typeof body.digital_outputs === 'object') {
              body.digital_outputs = remapOutputsToRelay(body.digital_outputs)
              return sendText(res, cloudResponse.status, JSON.stringify(body), responseContentType)
            }
          } catch {
            // keep pass-through below
          }
        }

        return sendText(res, cloudResponse.status, responseText, responseContentType)
      } catch (error) {
        if (error instanceof Error && error.name === 'AbortError') {
          modeManager.markCloudError('report_timeout')
        } else {
          modeManager.markCloudError(error instanceof Error ? error.message : 'report_proxy_failed')
        }
        // fallback to local response
      }
    }

    if (csvMode) {
      const local = offlineControllerResponse()
      const parts = [`send_interval_seconds=${local.send_interval_seconds}`]
      for (const [pin, value] of Object.entries(remapOutputsToRelay(local.digital_outputs || {}))) {
        parts.push(`${pin}=${value}`)
      }
      return sendText(res, 200, parts.join(';'), 'text/plain; charset=utf-8')
    }

    const local = offlineControllerResponse()
    return sendJson(res, 200, {
      ...local,
      digital_outputs: remapOutputsToRelay(local.digital_outputs || {})
    })
  }

  if (pathname.startsWith('/api') && status.mode === 'online' && config.cloudBaseUrl) {
    const bodyText =
      method !== 'GET' && method !== 'HEAD'
        ? await collectBodyText(req).catch(() => '')
        : ''
    const proxied = await proxyToCloud(req, res, config.cloudBaseUrl, pathname, search, bodyText)
    if (proxied) {
      return
    }
    return sendJson(res, 502, {
      error: 'cloud_proxy_failed',
      message: 'Unable to proxy request to global server.'
    })
  }

  if (pathname.startsWith('/api/local/pins/') && pathname.endsWith('/state')) {
    if (method !== 'PUT') {
      return methodNotAllowed(res, ['PUT'])
    }

    const status = modeManager.getStatus()
    if (status.mode === 'online') {
      return sendJson(res, 409, {
        error: 'manual_control_denied_in_online_mode',
        message: 'Manual local pin control is allowed only in offline mode.'
      })
    }

    const pin = pathname.slice('/api/local/pins/'.length, -'/state'.length).trim().toUpperCase()
    if (!pinStateStore.hasPin(pin)) {
      return sendJson(res, 404, {
        error: 'pin_not_found',
        message: `Pin ${pin} is not configured for manual control.`
      })
    }

    try {
      const payload = await collectBodyJson(req)
      const value = Number(payload?.value)
      if (!Number.isFinite(value)) {
        return badRequest(res, 'Field "value" must be a number (0 or 1).')
      }

      const normalized = value > 0 ? 1 : 0
      const pinState = pinStateStore.set(pin, normalized, payload?.source ?? 'manual_ui')

      return sendJson(res, 200, {
        ok: true,
        pin: pinState.pin,
        value: pinState.value,
        raw_value: pinState.rawValue,
        inverted: pinState.inverted,
        updated_at: pinState.updatedAt
      })
    } catch (error) {
      return badRequest(res, error instanceof Error ? error.message : 'Invalid JSON payload.')
    }
  }

  return notFound(res)
}

const httpServer = http.createServer(requestHandler)

let httpsServer = null
if (config.httpsEnabled) {
  if (!config.httpsCertPath || !config.httpsKeyPath) {
    throw new Error('HTTPS enabled but certificate/key path is missing.')
  }

  const cert = readFileSync(config.httpsCertPath, 'utf8')
  const key = readFileSync(config.httpsKeyPath, 'utf8')
  httpsServer = https.createServer({ cert, key }, requestHandler)
}

const startServers = async () => {
  await new Promise((resolve) => httpServer.listen(config.port, '0.0.0.0', resolve))
  if (httpsServer) {
    await new Promise((resolve) => httpsServer.listen(config.httpsPort, '0.0.0.0', resolve))
  }
  await modeManager.start()

  if (httpsServer) {
    console.log(
      `[home-openwrt] listening http:${config.port} + https:${config.httpsPort} | cloud=${config.cloudBaseUrl || 'disabled'} | storage=${config.storagePath}`
    )
  } else {
    console.log(
      `[home-openwrt] listening http:${config.port} | cloud=${config.cloudBaseUrl || 'disabled'} | storage=${config.storagePath}`
    )
  }
}

void startServers()

const shutdown = async () => {
  modeManager.stop()
  const closers = []
  closers.push(new Promise((resolve) => httpServer.close(() => resolve())))
  if (httpsServer) {
    closers.push(new Promise((resolve) => httpsServer.close(() => resolve())))
  }
  await Promise.all(closers)
  process.exit(0)
}

process.on('SIGINT', shutdown)
process.on('SIGTERM', shutdown)
