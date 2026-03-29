import { ensureControllerSchema } from '../../utils/controller-schema'

interface ControllerRow {
  id: number
  name: string
  discription: string | null
  send_interval_seconds: number
}

export default defineEventHandler(async () => {
  const pool = getDbPool()
  await ensureControllerSchema(pool)

  const result = await pool.query<ControllerRow>(
    'SELECT id, name, discription, send_interval_seconds FROM controllers ORDER BY id ASC'
  )

  return {
    controllers: result.rows
  }
})
