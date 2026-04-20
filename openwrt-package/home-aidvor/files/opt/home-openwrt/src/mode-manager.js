import { isWritableDirectory } from './storage.js'

const joinUrl = (baseUrl, path) => {
  if (!baseUrl) {
    return null
  }

  const normalizedBase = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  return `${normalizedBase}${normalizedPath}`
}

export class ModeManager {
  constructor(config) {
    this.config = config
    this.cloudReachable = false
    this.storageAvailable = false
    this.lastCloudOkAt = null
    this.lastCloudError = null
    this.heartbeatTimer = null
    this.storageTimer = null
  }

  get storageMode() {
    return this.storageAvailable ? 'full_offline' : 'manual_offline'
  }

  get mode() {
    return this.cloudReachable ? 'online' : 'offline'
  }

  getStatus() {
    return {
      mode: this.mode,
      storageMode: this.storageMode,
      cloudReachable: this.cloudReachable,
      storageAvailable: this.storageAvailable,
      lastCloudOkAt: this.lastCloudOkAt,
      lastCloudError: this.lastCloudError
    }
  }

  markCloudOk() {
    this.cloudReachable = true
    this.lastCloudError = null
    this.lastCloudOkAt = new Date()
  }

  markCloudError(reason = 'cloud_request_failed') {
    this.cloudReachable = false
    this.lastCloudError = String(reason)
  }

  async fetchWithTimeout(url, init = {}) {
    const controller = new AbortController()
    const timeoutMs = Math.max(300, Number(this.config.cloudRequestTimeoutMs) || 1500)
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

  async checkStorage() {
    this.storageAvailable = await isWritableDirectory(this.config.storagePath)
  }

  async checkCloud() {
    const target = joinUrl(this.config.cloudBaseUrl, this.config.cloudHealthPath)
    if (!target) {
      this.cloudReachable = false
      this.lastCloudError = 'cloud_base_url_not_configured'
      return
    }

    try {
      const response = await this.fetchWithTimeout(target, {
        method: 'GET',
        headers: {
          accept: 'application/json'
        }
      })

      if (!response.ok) {
        this.markCloudError(`http_${response.status}`)
        return
      }

      this.markCloudOk()
    } catch (error) {
      if (error instanceof Error && error.name === 'AbortError') {
        this.markCloudError('timeout')
        return
      }
      this.markCloudError(error instanceof Error ? error.message : 'cloud_check_failed')
    }
  }

  async start() {
    await this.checkStorage()
    await this.checkCloud()

    this.storageTimer = setInterval(() => {
      this.checkStorage()
    }, this.config.storageCheckIntervalMs)

    this.heartbeatTimer = setInterval(() => {
      this.checkCloud()
    }, this.config.heartbeatIntervalMs)
  }

  stop() {
    if (this.storageTimer) {
      clearInterval(this.storageTimer)
      this.storageTimer = null
    }
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer)
      this.heartbeatTimer = null
    }
  }
}
