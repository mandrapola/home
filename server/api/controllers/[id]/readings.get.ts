import { pruneControllerHistory } from '../../../utils/controller-history'
import type { ControllerPinConfig } from '../../../utils/controller-pin-config'
import { formatControllerReading, getControllerPinConfigs } from '../../../utils/controller-pin-config'

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

interface ReadingResponseRow extends ReadingRow {
  label: string
  unit: string | null
  raw_value: number
  display_value: number
  display_text: string
  digital_style: string
  invert_digital_logic: boolean
  desired_digital_value: number | null
  desired_digital_updated_at: string | null
  power_on_duration_seconds: number | null
  show_on_dashboard: boolean
  show_on_chart: boolean
  chart_range_hours: number
  average_interval_minutes: number
  sort_order: number
}

const isAnalogPin = (pin: string) => {
  const normalized = pin.trim().toLowerCase()
  return /^a\d+$/.test(normalized) || normalized === 'air_temperature' || normalized === 'air_humidity'
}

const aggregateAnalogHistory = (
  rows: ReadingResponseRow[],
  configMap: Map<string, ControllerPinConfig>
) => {
  const analogRows = rows.filter((row) => isAnalogPin(row.pin))
  if (analogRows.length === 0) {
    return rows
  }

  const digitalRows = rows.filter((row) => !isAnalogPin(row.pin))
  const grouped = new Map<string, ReadingResponseRow[]>()
  for (const row of analogRows) {
    const list = grouped.get(row.pin)
    if (list) {
      list.push(row)
    } else {
      grouped.set(row.pin, [row])
    }
  }

  const aggregatedRows: ReadingResponseRow[] = []

  for (const [pin, pinRows] of grouped.entries()) {
    const sorted = [...pinRows].sort(
      (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    )
    const bucketMinutes = Math.max(1, Math.trunc(Number(sorted[0]?.average_interval_minutes ?? 5)))
    const bucketMs = bucketMinutes * 60 * 1000
    const buckets = new Map<
      number,
      { sum: number; count: number; maxId: number; controllerId: number; maxTs: number }
    >()

    for (const row of sorted) {
      const ts = new Date(row.created_at).getTime()
      if (!Number.isFinite(ts)) {
        continue
      }
      const bucketStart = Math.floor(ts / bucketMs) * bucketMs
      const entry = buckets.get(bucketStart)
      if (entry) {
        entry.sum += Number(row.raw_value)
        entry.count += 1
        entry.maxId = Math.max(entry.maxId, Number(row.id))
        entry.maxTs = Math.max(entry.maxTs, ts)
      } else {
        buckets.set(bucketStart, {
          sum: Number(row.raw_value),
          count: 1,
          maxId: Number(row.id),
          controllerId: row.controller_id,
          maxTs: ts
        })
      }
    }

    const pinConfig = configMap.get(pin)
    for (const [, bucket] of buckets.entries()) {
      const averageRaw = bucket.count > 0 ? bucket.sum / bucket.count : 0
      const formatted = formatControllerReading(
        {
          controller_id: bucket.controllerId,
          pin,
          value: averageRaw
        },
        configMap
      )

      aggregatedRows.push({
        id: bucket.maxId,
        pin,
        value: averageRaw,
        controller_id: bucket.controllerId,
        created_at: new Date(bucket.maxTs).toISOString(),
        label: formatted.label,
        unit: formatted.unit,
        raw_value: formatted.raw_value,
        display_value: formatted.display_value,
        display_text: formatted.display_text,
        digital_style: formatted.digital_style,
        invert_digital_logic: formatted.invert_digital_logic,
        desired_digital_value: formatted.desired_digital_value,
        desired_digital_updated_at: formatted.desired_digital_updated_at,
        power_on_duration_seconds: formatted.power_on_duration_seconds,
        show_on_dashboard: formatted.show_on_dashboard,
        show_on_chart: formatted.show_on_chart,
        chart_range_hours: formatted.chart_range_hours,
        average_interval_minutes: pinConfig?.average_interval_minutes ?? formatted.average_interval_minutes,
        sort_order: formatted.sort_order
      })
    }
  }

  return [...digitalRows, ...aggregatedRows].sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime() || Number(b.id) - Number(a.id)
  )
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
  await pruneControllerHistory(pool)

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

  const pinConfigs = await getControllerPinConfigs(pool, controllerId)
  const configMap = new Map(pinConfigs.map((config) => [config.pin, config]))
  const maxChartRangeHours = pinConfigs
    .filter((config) => config.show_on_chart)
    .reduce((maxHours, config) => Math.max(maxHours, config.chart_range_hours), 0)

  const historyResult =
    maxChartRangeHours > 0
      ? await pool.query<ReadingRow>(
          `SELECT id, pin, value, controller_id, created_at
           FROM controller_data
           WHERE controller_id = $1
             AND created_at >= NOW() - ($2::text || ' hours')::interval
           ORDER BY created_at DESC, id DESC`,
          [controllerId, String(maxChartRangeHours)]
        )
      : { rows: [] as ReadingRow[] }

  const mapReading = (row: ReadingRow): ReadingResponseRow => {
    const formatted = formatControllerReading(row, configMap)

    return {
      ...row,
      label: formatted.label,
      unit: formatted.unit,
      raw_value: formatted.raw_value,
      display_value: formatted.display_value,
      display_text: formatted.display_text,
      digital_style: formatted.digital_style,
      invert_digital_logic: formatted.invert_digital_logic,
      desired_digital_value: formatted.desired_digital_value,
      desired_digital_updated_at: formatted.desired_digital_updated_at,
      power_on_duration_seconds: formatted.power_on_duration_seconds,
      show_on_dashboard: formatted.show_on_dashboard,
      show_on_chart: formatted.show_on_chart,
      chart_range_hours: formatted.chart_range_hours,
      average_interval_minutes: formatted.average_interval_minutes,
      sort_order: formatted.sort_order
    }
  }

  const nowMs = Date.now()
  const historyRows = historyResult.rows
    .map(mapReading)
    .filter((row) => {
      if (!row.show_on_chart) {
        return false
      }

      const ageMs = nowMs - new Date(row.created_at).getTime()
      return ageMs <= row.chart_range_hours * 60 * 60 * 1000
    })

  return {
    controller: controllerResult.rows[0],
    latest: latestResult.rows
      .map(mapReading)
      .sort((a, b) => {
        const sortDiff = a.sort_order - b.sort_order
        if (sortDiff !== 0) {
          return sortDiff
        }
        return a.pin.localeCompare(b.pin, undefined, { numeric: true, sensitivity: 'base' })
      }),
    history: aggregateAnalogHistory(historyRows, configMap)
  }
})
