interface ControllerRow {
  id: number
  name: string
  discription: string | null
}

interface ReadingRow {
  id: number
  pin: string
  value: number
  controller_id: number
  created_at: string
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

  const controllerResult = await pool.query<ControllerRow>(
    'SELECT id, name, discription FROM controllers WHERE id = $1',
    [controllerId]
  )

  if (controllerResult.rowCount === 0) {
    throw createError({
      statusCode: 404,
      statusMessage: `Controller ${controllerId} not found`
    })
  }

  const latestResult = await pool.query<ReadingRow>(
    `SELECT DISTINCT ON (pin) id, pin, value, controller_id, created_at
     FROM controller_data
     WHERE controller_id = $1
     ORDER BY pin, created_at DESC, id DESC`,
    [controllerId]
  )

  const historyResult = await pool.query<ReadingRow>(
    `SELECT id, pin, value, controller_id, created_at
     FROM controller_data
     WHERE controller_id = $1
     ORDER BY created_at DESC, id DESC
     LIMIT 30`,
    [controllerId]
  )

  return {
    controller: controllerResult.rows[0],
    latest: latestResult.rows,
    history: historyResult.rows
  }
})
