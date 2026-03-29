import { getControllerScenarioDerivedReadings } from '../../../utils/controller-scenarios'
import { getControllerPinConfigs } from '../../../utils/controller-pin-config'
import { ensureControllerSchema } from '../../../utils/controller-schema'

interface ControllerRow {
  id: number
  name: string
}

interface ScenarioParameter {
  key: string
  label: string
  value: number
  unit: string | null
}

interface ParameterTransform {
  multiplier: number
  offset: number
  unit: string | null
  label: string
  average_interval_minutes: number
}

const digitalPinSort = (left: string, right: string) =>
  left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' })

const stripControllerPrefix = (key: string) => {
  const match = key.match(/^controller:\d+:(.+)$/)
  return match ? match[1] : key
}

const normalizePinKey = (pin: string) => {
  const normalized = pin.trim()
  if (/^a\d+$/i.test(normalized)) {
    return normalized.toUpperCase()
  }
  return normalized.toLowerCase()
}

const applyTransform = (value: number, transform: ParameterTransform | undefined) =>
  transform ? value * transform.multiplier + transform.offset : value

const buildParametersList = (
  controllerId: number,
  values: Map<string, number>,
  transforms: Map<string, ParameterTransform>
): ScenarioParameter[] => {
  const parameters: ScenarioParameter[] = []
  const controllerPrefix = `controller:${controllerId}:`

  for (const [key, value] of values.entries()) {
    if (!key.startsWith(controllerPrefix)) {
      continue
    }

    const normalized = stripControllerPrefix(key)

    if (normalized === 'current_time' && Number.isFinite(value)) {
      parameters.push({ key, label: 'Текущее время', value, unit: null })
    }

    if (normalized.startsWith('avg_pin:')) {
      const pin = normalized.slice('avg_pin:'.length).trim()
      if (!pin) {
        continue
      }
      const transform = transforms.get(normalizePinKey(pin))
      const transformedValue = applyTransform(value, transform)
      if (!Number.isFinite(transformedValue)) {
        continue
      }

      const intervalMinutes = Math.max(1, Math.trunc(Number(transform?.average_interval_minutes ?? 5)))
      const label = `${transform?.label || pin} за последние ${intervalMinutes} мин`
      parameters.push({
        key,
        label,
        value: transformedValue,
        unit: transform?.unit ?? null
      })
    }
  }

  const pinStateEntries = Array.from(values.entries())
    .map(([key, value]) => {
      const normalized = stripControllerPrefix(key)
      if (!normalized.startsWith('pin_state:')) {
        return null
      }
      return { key, pin: normalized.slice('pin_state:'.length).toUpperCase(), value }
    })
    .filter((entry): entry is { key: string; pin: string; value: number } => entry !== null)
    .sort((a, b) => digitalPinSort(a.pin, b.pin))

  for (const entry of pinStateEntries) {
    parameters.push({
      key: entry.key,
      label: `Состояние пина ${entry.pin} (с учетом инверсии)`,
      value: entry.value,
      unit: null
    })
  }

  const pinOnTimeEntries = Array.from(values.entries())
    .map(([key, value]) => {
      const normalized = stripControllerPrefix(key)
      if (!normalized.startsWith('pin_on_seconds_24h:')) {
        return null
      }
      return { key, pin: normalized.slice('pin_on_seconds_24h:'.length).toUpperCase(), value }
    })
    .filter((entry): entry is { key: string; pin: string; value: number } => entry !== null)
    .sort((a, b) => digitalPinSort(a.pin, b.pin))

  for (const entry of pinOnTimeEntries) {
    parameters.push({
      key: entry.key,
      label: `Время включения пина ${entry.pin} за 24 часа`,
      value: entry.value,
      unit: 'с'
    })
  }

  return parameters
}

export default defineEventHandler(async (event) => {
  const controllerId = Number(getRouterParam(event, 'id'))
  if (!Number.isInteger(controllerId) || controllerId <= 0) {
    throw createError({ statusCode: 400, statusMessage: 'Controller id must be a positive integer' })
  }

  const pool = getDbPool()
  await ensureControllerSchema(pool)

  const controllerResult = await pool.query<ControllerRow>(
    'SELECT id, name FROM controllers WHERE id = $1',
    [controllerId]
  )

  if (controllerResult.rowCount === 0) {
    throw createError({ statusCode: 404, statusMessage: `Controller ${controllerId} not found` })
  }

  const values = await getControllerScenarioDerivedReadings(pool, controllerId)
  const pinConfigs = await getControllerPinConfigs(pool, controllerId)
  const transforms = new Map(
    pinConfigs.map((config) => [
      normalizePinKey(config.pin),
      {
        multiplier: Number(config.multiplier ?? 1),
        offset: Number(config.offset ?? 0),
        unit: config.unit ?? null,
        label: config.label,
        average_interval_minutes: Math.max(1, Math.trunc(Number(config.average_interval_minutes ?? 5)))
      }
    ])
  )

  return {
    controller: controllerResult.rows[0],
    parameters: buildParametersList(controllerId, values, transforms),
    updated_at: new Date().toISOString()
  }
})
