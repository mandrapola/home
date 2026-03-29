import { ensureControllerPinConfigSchema, isDigitalPin } from '../../../../../utils/controller-pin-config'
import { ensureControllerSchema } from '../../../../../utils/controller-schema'

interface StatePayload {
  value?: number | string | boolean
}

const normalizeDesiredValue = (value: unknown): 0 | 1 | null => {
  if (typeof value === 'boolean') {
    return value ? 1 : 0
  }

  const parsed = Number(value)
  if (!Number.isFinite(parsed)) {
    return null
  }

  return parsed > 0 ? 1 : 0
}

export default defineEventHandler(async (event) => {
  const controllerId = Number(getRouterParam(event, 'id'))
  const pin = String(getRouterParam(event, 'pin') ?? '').trim().toUpperCase()

  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Controller id must be a positive integer'
    })
  }

  if (!isDigitalPin(pin)) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Pin must be a digital pin'
    })
  }

  const body = await readBody<StatePayload>(event)
  const desiredValue = normalizeDesiredValue(body.value)

  if (desiredValue === null) {
    throw createError({
      statusCode: 400,
      statusMessage: 'value must be 0 or 1'
    })
  }

  const pool = getDbPool()
  const client = await pool.connect()

  try {
    await ensureControllerSchema(client)
    await ensureControllerPinConfigSchema(client)
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

    const pinConfigResult = await client.query<{ pin: string; digital_style: string }>(
      `SELECT pin, digital_style
       FROM controller_pin_config
       WHERE controller_id = $1 AND pin = $2`,
      [controllerId, pin]
    )

    if (pinConfigResult.rowCount === 0) {
      throw createError({
        statusCode: 404,
        statusMessage: `Pin ${pin} not found`
      })
    }

    if (pinConfigResult.rows[0].digital_style !== 'power') {
      throw createError({
        statusCode: 400,
        statusMessage: `Pin ${pin} does not support power control`
      })
    }

    await client.query(
      `UPDATE controller_pin_config
       SET desired_digital_value = $3,
           desired_digital_updated_at = CASE WHEN $3 > 0 THEN NOW() ELSE NULL END
       WHERE controller_id = $1 AND pin = $2`,
      [controllerId, pin, desiredValue]
    )

    await client.query('COMMIT')
  } catch (error) {
    await client.query('ROLLBACK')
    throw error
  } finally {
    client.release()
  }

  return { ok: true, pin, value: desiredValue }
})
