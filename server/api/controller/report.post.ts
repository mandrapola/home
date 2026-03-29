interface SensorReading {
  pin: string
  value: number
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

  try {
    await client.query('BEGIN')

    const controllerCheck = await client.query<{ id: number }>(
      'SELECT id FROM controllers WHERE id = $1',
      [controllerId]
    )

    if (controllerCheck.rowCount === 0) {
      console.warn(`[controller/report] controller not found id=${controllerId} ip=${ip}`)
      throw createError({
        statusCode: 404,
        statusMessage: `Controller ${controllerId} not found`
      })
    }

    for (const reading of readings) {
      await client.query(
        'INSERT INTO controller_data (pin, value, controller_id) VALUES ($1, $2, $3)',
        [reading.pin, reading.value, controllerId]
      )
    }

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

  return {}
})
