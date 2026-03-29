import { ensureControllerScenarioSchema } from '../../../../utils/controller-scenarios'

interface UpdateScenarioPayload {
  name?: string
  source_pin?: string
  operator?: 'gt' | 'gte' | 'lt' | 'lte'
  threshold?: number | string
  hysteresis?: number | string
  target_pin?: string
  value_when_true?: number | string
  value_when_false?: number | string
  priority?: number | string
  enabled?: boolean
}

const toNumber = (value: unknown, fallback: number) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

const toBit = (value: unknown, fallback: 0 | 1) => {
  if (value == null) {
    return fallback
  }
  return Number(value) > 0 ? 1 : 0
}

const normalizeOperator = (value: unknown) => {
  const operator = String(value ?? 'gt').trim()
  return operator === 'gt' || operator === 'gte' || operator === 'lt' || operator === 'lte'
    ? operator
    : 'gt'
}

export default defineEventHandler(async (event) => {
  const controllerId = Number(getRouterParam(event, 'id'))
  const scenarioId = Number(getRouterParam(event, 'scenarioId'))
  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({ statusCode: 400, statusMessage: 'Controller id must be a positive integer' })
  }
  if (!Number.isInteger(scenarioId) || scenarioId <= 0) {
    throw createError({ statusCode: 400, statusMessage: 'Scenario id must be a positive integer' })
  }

  const body = await readBody<UpdateScenarioPayload>(event)
  const pool = getDbPool()
  const client = await pool.connect()

  try {
    await ensureControllerScenarioSchema(client)
    const current = await client.query(
      `SELECT id, name, source_pin, operator, threshold, hysteresis, target_pin,
              value_when_true, value_when_false, priority, enabled
       FROM controller_scenarios
       WHERE id = $1 AND controller_id = $2`,
      [scenarioId, controllerId]
    )

    if (current.rowCount === 0) {
      throw createError({ statusCode: 404, statusMessage: 'Scenario not found' })
    }

    const row = current.rows[0] as Record<string, unknown>
    const name = body.name == null ? String(row.name) : String(body.name).trim()
    const sourcePin = body.source_pin == null ? String(row.source_pin) : String(body.source_pin).trim()
    const targetPin = body.target_pin == null ? String(row.target_pin) : String(body.target_pin).trim()

    if (!name || !sourcePin || !targetPin) {
      throw createError({
        statusCode: 400,
        statusMessage: 'name, source_pin and target_pin must not be empty'
      })
    }

    const operator = body.operator == null ? normalizeOperator(row.operator) : normalizeOperator(body.operator)
    const threshold = body.threshold == null ? toNumber(row.threshold, 0) : toNumber(body.threshold, 0)
    const hysteresis =
      body.hysteresis == null ? Math.max(0, toNumber(row.hysteresis, 0)) : Math.max(0, toNumber(body.hysteresis, 0))
    const valueWhenTrue =
      body.value_when_true == null ? toBit(row.value_when_true, 1) : toBit(body.value_when_true, 1)
    const valueWhenFalse =
      body.value_when_false == null ? toBit(row.value_when_false, 0) : toBit(body.value_when_false, 0)
    const priority = body.priority == null ? Math.trunc(toNumber(row.priority, 100)) : Math.trunc(toNumber(body.priority, 100))
    const enabled = body.enabled == null ? Boolean(row.enabled) : Boolean(body.enabled)

    await client.query(
      `UPDATE controller_scenarios
       SET name = $3,
           source_pin = $4,
           operator = $5,
           threshold = $6,
           hysteresis = $7,
           target_pin = $8,
           value_when_true = $9,
           value_when_false = $10,
           priority = $11,
           enabled = $12,
           updated_at = NOW()
       WHERE id = $1 AND controller_id = $2`,
      [
        scenarioId,
        controllerId,
        name,
        sourcePin,
        operator,
        threshold,
        hysteresis,
        targetPin,
        valueWhenTrue,
        valueWhenFalse,
        priority,
        enabled
      ]
    )

    return { ok: true }
  } finally {
    client.release()
  }
})
