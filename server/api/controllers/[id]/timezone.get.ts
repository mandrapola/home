import { getSystemTimeZone } from '../../../utils/system-settings'

export default defineEventHandler(async (event) => {
  const controllerId = Number(getRouterParam(event, 'id'))
  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({ statusCode: 400, statusMessage: 'Controller id must be a positive integer' })
  }

  const pool = getDbPool()

  return {
    controller_id: controllerId,
    time_zone: await getSystemTimeZone(pool)
  }
})
