import type pg from 'pg'

let controllerSchemaPromise: Promise<void> | null = null

export const ensureControllerSchema = async (pool: pg.Pool | pg.PoolClient) => {
  if (controllerSchemaPromise) {
    await controllerSchemaPromise
    return
  }

  controllerSchemaPromise = (async () => {
    await pool.query(`
      ALTER TABLE controllers
      ADD COLUMN IF NOT EXISTS send_interval_seconds INTEGER NOT NULL DEFAULT 30
    `)

    await pool.query(`
      ALTER TABLE controllers
      ADD COLUMN IF NOT EXISTS time_zone TEXT NOT NULL DEFAULT 'Europe/Moscow'
    `)
  })()

  await controllerSchemaPromise
}
