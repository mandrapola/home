interface ControllerRow {
  id: number
  name: string
  discription: string | null
}

export default defineEventHandler(async () => {
  const pool = getDbPool()

  const result = await pool.query<ControllerRow>(
    'SELECT id, name, discription FROM controllers ORDER BY id ASC'
  )

  return {
    controllers: result.rows
  }
})
