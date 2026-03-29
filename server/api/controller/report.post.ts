import { ensureControllerPinConfigs } from '../../utils/controller-pin-config'
import { pruneControllerHistory } from '../../utils/controller-history'
import { applyControllerScenarios } from '../../utils/controller-scenarios'
import { ensureControllerSchema } from '../../utils/controller-schema'

interface SensorReading {
  pin: string
  value: number
}

interface DigitalOutputCommand {
  pin: string
  value: 0 | 1
}

interface ControllerPayload {
  controller_id?: number | string
  readings?: Array<{ pin?: string; value?: number | string }>
  thermometer?: number | string
  temperature?: number | string
  pressure?: number | string
  humidity?: number | string
}

const toNumber = (value: unknown) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const normalizeReadings = (payload: ControllerPayload): SensorReading[] => {
  if (Array.isArray(payload.readings) && payload.readings.length > 0) {
    return payload.readings
      .map((item) => ({ pin: item.pin?.trim() ?? '', value: toNumber(item.value) }))
      .filter((item): item is SensorReading => item.pin.length > 0 && item.value !== null)
  }

  const mapped: SensorReading[] = []

  const thermometer = toNumber(payload.thermometer ?? payload.temperature)
  if (thermometer !== null) {
    mapped.push({ pin: 'thermometer', value: thermometer })
  }

  const pressure = toNumber(payload.pressure)
  if (pressure !== null) {
    mapped.push({ pin: 'pressure', value: pressure })
  }

  const humidity = toNumber(payload.humidity)
  if (humidity !== null) {
    mapped.push({ pin: 'humidity', value: humidity })
  }

  return mapped
}

export default defineEventHandler(async (event) => {
  const body = await readBody<ControllerPayload>(event)
  const controllerId = Number(body.controller_id)
  const ip =
    getHeader(event, 'x-forwarded-for') ||
    event.node.req.socket.remoteAddress ||
    'unknown'

  console.info(
    `[controller/report] incoming request ip=${ip} controller_id=${String(body.controller_id ?? '')} body=${JSON.stringify(body)}`
  )

  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    console.warn(`[controller/report] invalid controller_id from ip=${ip}`)
    throw createError({
      statusCode: 400,
      statusMessage: 'controller_id must be a positive integer'
    })
  }

  const readings = normalizeReadings(body)

  if (readings.length === 0) {
    console.warn(`[controller/report] no valid readings from ip=${ip} controller_id=${controllerId}`)
    throw createError({
      statusCode: 400,
      statusMessage: 'No valid sensor readings provided'
    })
  }

  const pool = getDbPool()
  const client = await pool.connect()
  let sendIntervalSeconds = 30
  let digitalOutputs: Record<string, 0 | 1> = {}

  try {
    await ensureControllerSchema(client)
    await client.query('BEGIN')

    let controllerCheck = await client.query<{ id: number; send_interval_seconds: number }>(
      'SELECT id, send_interval_seconds FROM controllers WHERE id = $1',
      [controllerId]
    )

    if (controllerCheck.rowCount === 0) {
      await client.query(
        `INSERT INTO controllers (id, name, discription, send_interval_seconds)
         VALUES ($1, $2, $3, 30)
         ON CONFLICT (id) DO NOTHING`,
        [
          controllerId,
          `controller-${controllerId}`,
          `Auto-registered controller from ${ip}`
        ]
      )

      controllerCheck = await client.query<{ id: number; send_interval_seconds: number }>(
        'SELECT id, send_interval_seconds FROM controllers WHERE id = $1',
        [controllerId]
      )

      console.info(`[controller/report] auto-registered controller id=${controllerId} ip=${ip}`)
    }

    sendIntervalSeconds = controllerCheck.rows[0].send_interval_seconds

    await ensureControllerPinConfigs(
      client,
      controllerId,
      readings.map((reading) => reading.pin)
    )

    for (const reading of readings) {
      await client.query(
        'INSERT INTO controller_data (pin, value, controller_id) VALUES ($1, $2, $3)',
        [reading.pin, reading.value, controllerId]
      )
    }

    await pruneControllerHistory(client)

    await applyControllerScenarios(client, controllerId, readings)

    await client.query(
      `UPDATE controller_pin_config
       SET desired_digital_value = 0,
           desired_digital_updated_at = NULL
       WHERE controller_id = $1
         AND digital_style = 'power'
         AND desired_digital_value = 1
         AND power_on_duration_seconds IS NOT NULL
         AND desired_digital_updated_at IS NOT NULL
         AND desired_digital_updated_at + (power_on_duration_seconds::text || ' seconds')::interval <= NOW()`,
      [controllerId]
    )

    const digitalOutputsResult = await client.query<DigitalOutputCommand>(
      `SELECT pin,
              CASE
                WHEN COALESCE(invert_digital_logic, FALSE)
                  THEN CASE WHEN desired_digital_value > 0 THEN 0 ELSE 1 END
                ELSE CASE WHEN desired_digital_value > 0 THEN 1 ELSE 0 END
              END AS value
       FROM controller_pin_config
       WHERE controller_id = $1
         AND digital_style = 'power'
         AND desired_digital_value IS NOT NULL
         AND pin ~ '^D[0-9]+$'
       ORDER BY sort_order ASC, pin ASC`,
      [controllerId]
    )

    digitalOutputs = digitalOutputsResult.rows.reduce<Record<string, 0 | 1>>((acc, row) => {
      acc[row.pin] = row.value > 0 ? 1 : 0
      return acc
    }, {})

    await client.query('COMMIT')
    console.info(
      `[controller/report] stored readings=${readings.length} controller_id=${controllerId} ip=${ip}`
    )
  } catch (error) {
    await client.query('ROLLBACK')
    console.error(`[controller/report] db error controller_id=${controllerId} ip=${ip}`, error)
    throw error
  } finally {
    client.release()
  }

  return {
    send_interval_seconds: sendIntervalSeconds,
    digital_outputs: digitalOutputs
  }
})
