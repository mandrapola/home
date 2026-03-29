import { getControllerScenarios } from '../../../utils/controller-scenarios'

export default defineEventHandler(async (event) => {
  const controllerId = Number(getRouterParam(event, 'id'))
  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({ statusCode: 400, statusMessage: 'Controller id must be a positive integer' })
  }

  const pool = getDbPool()
  const scenarios = await getControllerScenarios(pool, controllerId)
  return { scenarios }
})
