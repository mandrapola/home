import type { ControllerPinConfig } from '../../../utils/controller-pin-config'
import { getControllerPinConfigs, replaceControllerPinConfigs } from '../../../utils/controller-pin-config'
import { ensureControllerSchema } from '../../../utils/controller-schema'

interface SettingsPayload {
  pinConfigs?: Array<Partial<ControllerPinConfig>>
  send_interval_seconds?: number | string
  name?: string
  discription?: string | null
}

const toFiniteNumber = (value: unknown, fallback: number) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

const normalizeValueLabels = (value: unknown): Record<string, string> => {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(value).map(([key, label]) => [String(key), String(label)])
  )
}

const isDigitalPin = (pin: string) => /^D\d+$/i.test(pin.trim())

const toBinary = (value: unknown) => (Number(value) > 0 ? 1 : 0)

const stableValueLabels = (value: Record<string, string>) =>
  JSON.stringify(
    Object.entries(value)
      .sort(([a], [b]) => a.localeCompare(b))
      .reduce<Record<string, string>>((acc, [key, label]) => {
        acc[key] = label
        return acc
      }, {})
  )

const hasConfigChanged = (next: ControllerPinConfig, prev: ControllerPinConfig) => {
  return (
    next.label !== prev.label ||
    next.unit !== prev.unit ||
    next.multiplier !== prev.multiplier ||
    next.offset !== prev.offset ||
    next.precision !== prev.precision ||
    next.average_interval_minutes !== prev.average_interval_minutes ||
    next.digital_style !== prev.digital_style ||
    next.invert_digital_logic !== prev.invert_digital_logic ||
    next.power_on_duration_seconds !== prev.power_on_duration_seconds ||
    next.show_on_dashboard !== prev.show_on_dashboard ||
    next.show_on_chart !== prev.show_on_chart ||
    next.chart_range_hours !== prev.chart_range_hours ||
    next.sort_order !== prev.sort_order ||
    stableValueLabels(next.value_labels) !== stableValueLabels(prev.value_labels)
  )
}

const normalizeConfig = (config: Partial<ControllerPinConfig>, index: number): ControllerPinConfig | null => {
  const pin = String(config.pin ?? '').trim()
  const label = String(config.label ?? '').trim()
  const normalizedPin = pin.toLowerCase()
  const analogPin =
    /^a\d+$/.test(normalizedPin) ||
    normalizedPin === 'air_temperature' ||
    normalizedPin === 'air_humidity'
  const digitalStyle =
    typeof config.digital_style === 'string' && config.digital_style.trim().length > 0
      ? config.digital_style.trim()
      : 'power'

  if (!pin || !label) {
    return null
  }

  return {
    pin,
    label,
    unit: config.unit == null || String(config.unit).trim().length === 0 ? null : String(config.unit).trim(),
    multiplier: toFiniteNumber(config.multiplier, 1),
    offset: toFiniteNumber(config.offset, 0),
    precision: Math.max(0, Math.trunc(toFiniteNumber(config.precision, 0))),
    average_interval_minutes: analogPin ? Math.max(1, Math.trunc(toFiniteNumber(config.average_interval_minutes, 5))) : 5,
    value_labels: normalizeValueLabels(config.value_labels),
    digital_style: analogPin ? 'power' : digitalStyle,
    invert_digital_logic: analogPin || digitalStyle !== 'power' ? false : Boolean(config.invert_digital_logic),
    desired_digital_value:
      analogPin
        ? null
        : digitalStyle === 'power'
          ? toBinary(config.desired_digital_value)
          : null,
    desired_digital_updated_at: null,
    power_on_duration_seconds:
      analogPin || digitalStyle !== 'power' || config.power_on_duration_seconds == null
        ? null
        : Math.max(1, Math.trunc(toFiniteNumber(config.power_on_duration_seconds, 1))),
    show_on_dashboard: Boolean(config.show_on_dashboard),
    show_on_chart: analogPin ? Boolean(config.show_on_chart) : false,
    chart_range_hours: analogPin ? Math.max(1, Math.trunc(toFiniteNumber(config.chart_range_hours, 1))) : 1,
    sort_order: Math.trunc(toFiniteNumber(config.sort_order, index))
  }
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

  const body = await readBody<SettingsPayload>(event)
  const sendIntervalSeconds = Math.max(1, Math.trunc(toFiniteNumber(body.send_interval_seconds, 30)))
  const controllerName = String(body.name ?? '').trim()
  const controllerDescription =
    body.discription == null || String(body.discription).trim().length === 0
      ? null
      : String(body.discription).trim()
  const pinConfigs = Array.isArray(body.pinConfigs)
    ? body.pinConfigs
        .map((config, index) => normalizeConfig(config, index))
        .filter((config): config is ControllerPinConfig => config !== null)
    : []

  if (!controllerName) {
    throw createError({
      statusCode: 400,
      statusMessage: 'name must not be empty'
    })
  }

  if (pinConfigs.length === 0) {
    throw createError({
      statusCode: 400,
      statusMessage: 'pinConfigs must contain at least one valid pin configuration'
    })
  }

  const pool = getDbPool()
  const client = await pool.connect()

  try {
    await ensureControllerSchema(client)
    await client.query('BEGIN')

    const controllerResult = await client.query<{ id: number }>(
      'SELECT id FROM controllers WHERE id = $1',
      [controllerId]
    )

    if (controllerResult.rowCount === 0) {
      throw createError({
        statusCode: 404,
        statusMessage: `Controller ${controllerId} not found`
      })
    }

    await client.query(
      'UPDATE controllers SET name = $2, discription = $3, send_interval_seconds = $4 WHERE id = $1',
      [controllerId, controllerName, controllerDescription, sendIntervalSeconds]
    )

    const existingConfigs = await getControllerPinConfigs(client, controllerId)
    const existingByPin = new Map(existingConfigs.map((config) => [config.pin, config]))

    const pinConfigsToSave = pinConfigs.map((config) => {
      const previous = existingByPin.get(config.pin)
      const isPowerDigital = isDigitalPin(config.pin) && config.digital_style === 'power'

      if (!isPowerDigital) {
        return {
          ...config,
          desired_digital_value: previous?.desired_digital_value ?? config.desired_digital_value,
          desired_digital_updated_at: previous?.desired_digital_updated_at ?? null
        }
      }

      if (!previous) {
        return {
          ...config,
          desired_digital_value: 0,
          desired_digital_updated_at: null
        }
      }

      const changed = hasConfigChanged(config, previous)
      return {
        ...config,
        desired_digital_value: changed ? 0 : previous.desired_digital_value,
        desired_digital_updated_at: changed ? null : previous.desired_digital_updated_at
      }
    })

    await replaceControllerPinConfigs(client, controllerId, pinConfigsToSave)

    await client.query('COMMIT')
  } catch (error) {
    await client.query('ROLLBACK')
    throw error
  } finally {
    client.release()
  }

  return { ok: true }
})
