import { getSystemTimeZone } from '../../utils/system-settings'

export default defineEventHandler(async () => {
  const pool = getDbPool()

  return {
    time_zone: await getSystemTimeZone(pool)
  }
})
