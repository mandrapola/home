import { ensureControllerScenarioSchema } from '../../../utils/controller-scenarios'

interface CreateScenarioPayload {
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
  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({ statusCode: 400, statusMessage: 'Controller id must be a positive integer' })
  }

  const body = await readBody<CreateScenarioPayload>(event)
  const name = String(body.name ?? '').trim()
  const sourcePin = String(body.source_pin ?? '').trim()
  const targetPin = String(body.target_pin ?? '').trim()

  if (!name || !sourcePin || !targetPin) {
    throw createError({
      statusCode: 400,
      statusMessage: 'name, source_pin and target_pin are required'
    })
  }

  const operator = normalizeOperator(body.operator)
  const threshold = toNumber(body.threshold, 0)
  const hysteresis = Math.max(0, toNumber(body.hysteresis, 0))
  const valueWhenTrue = toBit(body.value_when_true, 1)
  const valueWhenFalse = toBit(body.value_when_false, 0)
  const priority = Math.trunc(toNumber(body.priority, 100))
  const enabled = body.enabled == null ? true : Boolean(body.enabled)

  const pool = getDbPool()
  const client = await pool.connect()

  try {
    await ensureControllerScenarioSchema(client)
    const inserted = await client.query(
      `INSERT INTO controller_scenarios
        (controller_id, name, source_pin, operator, threshold, hysteresis, target_pin, value_when_true, value_when_false, priority, enabled, updated_at)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, NOW())
       RETURNING id`,
      [
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

    return {
      ok: true,
      scenario_id: Number(inserted.rows[0]?.id)
    }
  } finally {
    client.release()
  }
})
