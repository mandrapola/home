import { isDigitalPin } from '../../../../../utils/controller-pin-config'
import { ensureControllerSchema } from '../../../../../utils/controller-schema'

export default defineEventHandler(async (event) => {
  const controllerId = Number(getRouterParam(event, 'id'))
  const pin = String(getRouterParam(event, 'pin') ?? '').trim()
  const normalizedPin = pin.toUpperCase()

  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Controller id must be a positive integer'
    })
  }

  if (!pin || isDigitalPin(normalizedPin)) {
    throw createError({
      statusCode: 400,
      statusMessage: 'History cleanup is available only for analog pins'
    })
  }

  const pool = getDbPool()
  const client = await pool.connect()

  try {
    await ensureControllerSchema(client)
    await client.query('BEGIN')

    const controllerResult = await client.query<{ id: number }>(
      'SELECT id FROM controllers WHERE id = $1',
      [controllerId]
    )

    if (controllerResult.rowCount === 0) {
      throw createError({
        statusCode: 404,
        statusMessage: `Controller ${controllerId} not found`
      })
    }

    const deleteResult = await client.query(
      `DELETE FROM controller_data
       WHERE controller_id = $1 AND pin = $2`,
      [controllerId, pin]
    )

    await client.query('COMMIT')

    return {
      ok: true,
      pin,
      deleted: deleteResult.rowCount ?? 0
    }
  } catch (error) {
    await client.query('ROLLBACK')
    throw error
  } finally {
    client.release()
  }
})
