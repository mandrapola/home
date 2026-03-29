import { isValidTimeZone } from '../../utils/time-zone'
import { setSystemTimeZone } from '../../utils/system-settings'

interface TimeZonePayload {
  time_zone?: string
}

export default defineEventHandler(async (event) => {
  const body = await readBody<TimeZonePayload>(event)
  const requestedTimeZone = String(body.time_zone ?? '').trim()

  if (!requestedTimeZone || !isValidTimeZone(requestedTimeZone)) {
    throw createError({
      statusCode: 400,
      statusMessage: 'time_zone must be a valid IANA timezone (e.g. Europe/Moscow)'
    })
  }

  const pool = getDbPool()

  return {
    ok: true,
    time_zone: await setSystemTimeZone(pool, requestedTimeZone)
  }
})
