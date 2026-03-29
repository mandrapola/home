import type pg from 'pg'
import { ensureControllerSchema } from './controller-schema'
import { DEFAULT_CONTROLLER_TIME_ZONE, normalizeTimeZone } from './time-zone'

let systemSettingsSchemaPromise: Promise<void> | null = null

export const ensureSystemSettingsSchema = async (pool: pg.Pool | pg.PoolClient) => {
  if (systemSettingsSchemaPromise) {
    await systemSettingsSchemaPromise
    return
  }

  systemSettingsSchemaPromise = (async () => {
    await ensureControllerSchema(pool)

    await pool.query(`
      CREATE TABLE IF NOT EXISTS system_settings (
        id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
        time_zone TEXT NOT NULL DEFAULT '${DEFAULT_CONTROLLER_TIME_ZONE}'
      )
    `)

    await pool.query(
      `INSERT INTO system_settings (id, time_zone)
       VALUES (
         1,
         COALESCE(
           (SELECT time_zone FROM controllers ORDER BY id ASC LIMIT 1),
           $1
         )
       )
       ON CONFLICT (id) DO NOTHING`,
      [DEFAULT_CONTROLLER_TIME_ZONE]
    )
  })()

  await systemSettingsSchemaPromise
}

export const getSystemTimeZone = async (pool: pg.Pool | pg.PoolClient) => {
  await ensureSystemSettingsSchema(pool)

  const result = await pool.query<{ time_zone: string | null }>(
    'SELECT time_zone FROM system_settings WHERE id = 1'
  )

  return normalizeTimeZone(result.rows[0]?.time_zone)
}

export const setSystemTimeZone = async (pool: pg.Pool | pg.PoolClient, timeZone: string) => {
  await ensureSystemSettingsSchema(pool)

  const result = await pool.query<{ time_zone: string | null }>(
    `INSERT INTO system_settings (id, time_zone)
     VALUES (1, $1)
     ON CONFLICT (id) DO UPDATE SET time_zone = EXCLUDED.time_zone
     RETURNING time_zone`,
    [timeZone]
  )

  return normalizeTimeZone(result.rows[0]?.time_zone)
}
