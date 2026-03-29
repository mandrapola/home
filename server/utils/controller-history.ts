import type pg from 'pg'

type QueryClient = pg.Pool | pg.PoolClient

export const HISTORY_RETENTION_HOURS = 24

export const pruneControllerHistory = async (client: QueryClient) => {
  await client.query(
    `DELETE FROM controller_data
     WHERE created_at < NOW() - INTERVAL '24 hours'`
  )
}
