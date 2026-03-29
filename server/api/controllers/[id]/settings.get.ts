import { getControllerPinConfigs } from '../../../utils/controller-pin-config'
import { ensureControllerSchema } from '../../../utils/controller-schema'

interface ControllerRow {
  id: number
  name: string
  discription: string | null
  send_interval_seconds: number
}

export default defineEventHandler(async (event) => {
  const idParam = getRouterParam(event, 'id')
  const controllerId = Number(idParam)

  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Controller id must be a positive integer'
    })
  }

  const pool = getDbPool()
  await ensureControllerSchema(pool)

  const controllerResult = await pool.query<ControllerRow>(
    'SELECT id, name, discription, send_interval_seconds FROM controllers WHERE id = $1',
    [controllerId]
  )

  if (controllerResult.rowCount === 0) {
    throw createError({
      statusCode: 404,
      statusMessage: `Controller ${controllerId} not found`
    })
  }

  return {
    controller: controllerResult.rows[0],
    pinConfigs: await getControllerPinConfigs(pool, controllerId)
  }
})
