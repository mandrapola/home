import pg from 'pg'

const { Pool } = pg

let pool: pg.Pool | null = null

export const getDbPool = () => {
  if (pool) {
    return pool
  }

  const databaseUrl = useRuntimeConfig().databaseUrl

  if (!databaseUrl) {
    throw createError({
      statusCode: 500,
      statusMessage: 'DATABASE_URL is not configured'
    })
  }

  pool = new Pool({ connectionString: databaseUrl })
  return pool
}
