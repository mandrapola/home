import { ensureControllerScenarioSchema } from '../../../../utils/controller-scenarios'

export default defineEventHandler(async (event) => {
  const controllerId = Number(getRouterParam(event, 'id'))
  const scenarioId = Number(getRouterParam(event, 'scenarioId'))

  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({ statusCode: 400, statusMessage: 'Controller id must be a positive integer' })
  }
  if (!Number.isInteger(scenarioId) || scenarioId <= 0) {
    throw createError({ statusCode: 400, statusMessage: 'Scenario id must be a positive integer' })
  }

  const pool = getDbPool()
  const client = await pool.connect()
  try {
    await ensureControllerScenarioSchema(client)
    await client.query(
      `DELETE FROM controller_scenarios
       WHERE id = $1 AND controller_id = $2`,
      [scenarioId, controllerId]
    )
    return { ok: true }
  } finally {
    client.release()
  }
})
