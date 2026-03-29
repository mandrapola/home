const DEFAULT_TIME_ZONE = 'Europe/Moscow'

export const isValidTimeZone = (value: string) => {
  try {
    new Intl.DateTimeFormat('en-US', { timeZone: value })
    return true
  } catch {
    return false
  }
}

export const normalizeTimeZone = (value: unknown, fallback = DEFAULT_TIME_ZONE) => {
  const candidate = String(value ?? '').trim()
  if (candidate && isValidTimeZone(candidate)) {
    return candidate
  }

  return fallback
}

export const secondsFromMidnightByTimeZone = (date: Date, timeZone: string) => {
  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })

  const parts = formatter.formatToParts(date)
  const hour = Number(parts.find((part) => part.type === 'hour')?.value ?? '0')
  const minute = Number(parts.find((part) => part.type === 'minute')?.value ?? '0')
  const second = Number(parts.find((part) => part.type === 'second')?.value ?? '0')

  return hour * 3600 + minute * 60 + second
}

export const DEFAULT_CONTROLLER_TIME_ZONE = DEFAULT_TIME_ZONE
