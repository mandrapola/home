import os from 'node:os'
import { existsSync } from 'node:fs'

const isPrivateIpv4 = (ip: string) => {
  if (ip.startsWith('10.')) {
    return true
  }

  if (ip.startsWith('192.168.')) {
    return true
  }

  if (ip.startsWith('172.')) {
    const secondOctet = Number(ip.split('.')[1] || '0')
    return secondOctet >= 16 && secondOctet <= 31
  }

  return false
}

const detectLanIp = () => {
  const interfaces = os.networkInterfaces()

  for (const [name, entries] of Object.entries(interfaces)) {
    if (!entries || name.startsWith('docker') || name.startsWith('veth') || name.startsWith('br-')) {
      continue
    }

    for (const entry of entries) {
      if (entry.family === 'IPv4' && !entry.internal && isPrivateIpv4(entry.address)) {
        return entry.address
      }
    }
  }

  return null
}

const isIpHost = (value: string) => /^\d{1,3}(\.\d{1,3}){3}$/.test(value)

export default defineEventHandler((event) => {
  const config = useRuntimeConfig()
  const headerHost = getHeader(event, 'x-forwarded-host') || getHeader(event, 'host') || ''
  const hostWithoutPort = headerHost.split(':')[0]
  const inDocker = existsSync('/.dockerenv')

  const hostIp = isIpHost(hostWithoutPort) ? hostWithoutPort : null
  const autoIp = inDocker ? null : detectLanIp()
  const lanIp = hostIp || config.public.lanIp || autoIp || null
  const port = config.public.lanIp ? 3000 : (headerHost.split(':')[1] || '3000')

  return {
    lanIp,
    accessUrl: lanIp ? `http://${lanIp}:${port}` : null
  }
})
