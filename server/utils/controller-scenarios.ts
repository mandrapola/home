import type pg from 'pg'
import { ensureControllerPinConfigSchema } from './controller-pin-config'
import { getSystemTimeZone } from './system-settings'
import { secondsFromMidnightByTimeZone } from './time-zone'

export type ScenarioOperator = 'gt' | 'gte' | 'lt' | 'lte'

export interface ControllerScenario {
  id: number
  controller_id: number
  name: string
  source_pin: string
  operator: ScenarioOperator
  threshold: number
  hysteresis: number
  target_pin: string
  value_when_true: 0 | 1
  value_when_false: 0 | 1
  priority: number
  enabled: boolean
  current_state: 0 | 1
  created_at: string
  updated_at: string
}

interface ScenarioState {
  scenario_id: number
  is_active: boolean
}

type QueryClient = pg.Pool | pg.PoolClient

const isScenarioOperator = (value: string): value is ScenarioOperator =>
  value === 'gt' || value === 'gte' || value === 'lt' || value === 'lte'

const toBit = (value: unknown) => (Number(value) > 0 ? 1 : 0) as 0 | 1
const isDigitalPin = (pin: string) => /^D\d+$/i.test(pin.trim())
const derivedKeyPrefix = (controllerId: number) => `controller:${controllerId}:`

const buildDerivedKey = (controllerId: number, key: string) => `${derivedKeyPrefix(controllerId)}${key}`

const applyInvertLogic = (rawValue: number, invertLogic: boolean) => {
  const rawBit = rawValue > 0 ? 1 : 0
  return invertLogic ? (rawBit > 0 ? 0 : 1) : rawBit
}

let schemaInitPromise: Promise<void> | null = null

export const ensureControllerScenarioSchema = async (pool: QueryClient) => {
  if (schemaInitPromise) {
    await schemaInitPromise
    return
  }

  schemaInitPromise = (async () => {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS controller_scenarios (
        id BIGSERIAL PRIMARY KEY,
        controller_id INTEGER NOT NULL REFERENCES controllers(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        source_pin TEXT NOT NULL,
        operator TEXT NOT NULL DEFAULT 'gt',
        threshold DOUBLE PRECISION NOT NULL,
        hysteresis DOUBLE PRECISION NOT NULL DEFAULT 0,
        target_pin TEXT NOT NULL,
        value_when_true INTEGER NOT NULL DEFAULT 1,
        value_when_false INTEGER NOT NULL DEFAULT 0,
        priority INTEGER NOT NULL DEFAULT 100,
        enabled BOOLEAN NOT NULL DEFAULT TRUE,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
      )
    `)

    await pool.query(`
      CREATE TABLE IF NOT EXISTS controller_scenario_state (
        scenario_id BIGINT PRIMARY KEY REFERENCES controller_scenarios(id) ON DELETE CASCADE,
        is_active BOOLEAN NOT NULL DEFAULT FALSE,
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
      )
    `)
  })()

  await schemaInitPromise
}

const normalizeScenarioRow = (row: Record<string, unknown>): ControllerScenario => {
  const operator = String(row.operator ?? 'gt').trim()

  return {
    id: Number(row.id),
    controller_id: Number(row.controller_id),
    name: String(row.name ?? '').trim(),
    source_pin: String(row.source_pin ?? '').trim(),
    operator: isScenarioOperator(operator) ? operator : 'gt',
    threshold: Number(row.threshold ?? 0),
    hysteresis: Math.max(0, Number(row.hysteresis ?? 0)),
    target_pin: String(row.target_pin ?? '').trim(),
    value_when_true: toBit(row.value_when_true),
    value_when_false: toBit(row.value_when_false),
    priority: Math.trunc(Number(row.priority ?? 100)),
    enabled: Boolean(row.enabled),
    current_state: toBit(row.current_state),
    created_at:
      row.created_at instanceof Date ? row.created_at.toISOString() : String(row.created_at ?? ''),
    updated_at:
      row.updated_at instanceof Date ? row.updated_at.toISOString() : String(row.updated_at ?? '')
  }
}

export const getControllerScenarios = async (pool: QueryClient, controllerId: number) => {
  await ensureControllerScenarioSchema(pool)
  const result = await pool.query(
    `SELECT scenario.id,
            scenario.controller_id,
            scenario.name,
            scenario.source_pin,
            scenario.operator,
            scenario.threshold,
            scenario.hysteresis,
            scenario.target_pin,
            scenario.value_when_true,
            scenario.value_when_false,
            scenario.priority,
            scenario.enabled,
            COALESCE(state.is_active, FALSE) AS current_state,
            scenario.created_at,
            scenario.updated_at
     FROM controller_scenarios AS scenario
     LEFT JOIN controller_scenario_state AS state
       ON state.scenario_id = scenario.id
     WHERE scenario.controller_id = $1
     ORDER BY scenario.priority ASC, scenario.id ASC`,
    [controllerId]
  )

  return result.rows.map((row) => normalizeScenarioRow(row))
}

interface PinLogicConfig {
  pin: string
  invert_digital_logic: boolean
}

interface AnalogSourceConfig {
  pin: string
  multiplier: number
  offset: number
  average_interval_minutes: number
}

interface DigitalValuePoint {
  pin: string
  value: number
  created_at: string
}

const getDigitalPinLogicConfigs = async (client: QueryClient, controllerId: number) => {
  const result = await client.query<PinLogicConfig>(
    `SELECT pin, COALESCE(invert_digital_logic, FALSE) AS invert_digital_logic
     FROM controller_pin_config
     WHERE controller_id = $1
       AND pin ~ '^D[0-9]+$'`,
    [controllerId]
  )

  return result.rows.map((row) => ({
    pin: String(row.pin).trim().toUpperCase(),
    invert_digital_logic: Boolean(row.invert_digital_logic)
  }))
}

const normalizeAnalogPinKey = (pin: string) => {
  const normalized = pin.trim()
  if (/^a\d+$/i.test(normalized)) {
    return normalized.toUpperCase()
  }
  return normalized.toLowerCase()
}

const getAnalogSourceConfigs = async (client: QueryClient, controllerId: number) => {
  const result = await client.query<AnalogSourceConfig>(
    `SELECT pin, multiplier, value_offset AS offset, average_interval_minutes
     FROM controller_pin_config
     WHERE controller_id = $1
       AND (pin ~* '^A[0-9]+$' OR pin IN ('air_temperature', 'air_humidity'))`,
    [controllerId]
  )

  return new Map(
    result.rows.map((row) => [
      normalizeAnalogPinKey(String(row.pin ?? '')),
      {
        pin: String(row.pin ?? '').trim(),
        multiplier: Number(row.multiplier ?? 1),
        offset: Number(row.offset ?? 0),
        average_interval_minutes: Math.max(1, Math.trunc(Number(row.average_interval_minutes ?? 5)))
      }
    ])
  )
}

const normalizeSourceKey = (sourcePin: string) => sourcePin.replace(/^controller:\d+:/, '').trim()

const resolveAnalogConfigKeyForSource = (sourcePin: string) => {
  const normalized = normalizeSourceKey(sourcePin)

  if (normalized.startsWith('avg_pin:')) {
    const pin = normalized.slice('avg_pin:'.length).trim()
    if (!pin) {
      return null
    }
    return pin
  }

  if (normalized === 'current_time' || normalized.startsWith('pin_state:') || normalized.startsWith('pin_on_seconds_24h:')) {
    return null
  }
  if (isDigitalPin(normalized)) {
    return null
  }

  if (/^a\d+$/i.test(normalized) || normalized === 'air_temperature' || normalized === 'air_humidity') {
    return normalized
  }

  return null
}

const mapScenarioSourceValue = (
  sourcePin: string,
  sourceValue: number,
  analogSourceConfigs: Map<string, AnalogSourceConfig>
) => {
  const analogConfigKey = resolveAnalogConfigKeyForSource(sourcePin)
  if (!analogConfigKey) {
    return sourceValue
  }

  const config = analogSourceConfigs.get(normalizeAnalogPinKey(analogConfigKey))
  if (!config) {
    return sourceValue
  }

  return sourceValue * config.multiplier + config.offset
}

const buildDigitalCurrentStateMap = async (
  client: QueryClient,
  controllerId: number,
  digitalPins: PinLogicConfig[],
  incomingReadings: Map<string, number>
) => {
  const map = new Map<string, number>()
  const pins = digitalPins.map((item) => item.pin)
  if (pins.length === 0) {
    return map
  }

  const incomingPinMap = new Map<string, number>()
  for (const [pin, value] of incomingReadings.entries()) {
    if (isDigitalPin(pin)) {
      incomingPinMap.set(pin.trim().toUpperCase(), value)
    }
  }

  const pinsWithoutIncoming = pins.filter((pin) => !incomingPinMap.has(pin))

  const latestDbRows =
    pinsWithoutIncoming.length > 0
      ? await client.query<DigitalValuePoint>(
          `SELECT DISTINCT ON (pin) pin, value, created_at
           FROM controller_data
           WHERE controller_id = $1
             AND pin = ANY($2::text[])
           ORDER BY pin, created_at DESC, id DESC`,
          [controllerId, pinsWithoutIncoming]
        )
      : { rows: [] as DigitalValuePoint[] }

  const latestDbMap = new Map<string, number>(
    latestDbRows.rows.map((row) => [String(row.pin).trim().toUpperCase(), Number(row.value)])
  )

  for (const pinConfig of digitalPins) {
    const rawValue = incomingPinMap.get(pinConfig.pin) ?? latestDbMap.get(pinConfig.pin)
    if (rawValue == null || !Number.isFinite(rawValue)) {
      continue
    }

    map.set(pinConfig.pin, applyInvertLogic(rawValue, pinConfig.invert_digital_logic))
  }

  return map
}

const buildDigitalOnTimeSeconds24hMap = async (
  client: QueryClient,
  controllerId: number,
  digitalPins: PinLogicConfig[]
) => {
  const result = new Map<string, number>()
  const pins = digitalPins.map((item) => item.pin)
  if (pins.length === 0) {
    return result
  }

  const windowStart = Date.now() - 24 * 60 * 60 * 1000
  const windowEnd = Date.now()

  const prevRows = await client.query<DigitalValuePoint>(
    `SELECT DISTINCT ON (pin) pin, value, created_at
     FROM controller_data
     WHERE controller_id = $1
       AND pin = ANY($2::text[])
       AND created_at < NOW() - INTERVAL '24 hours'
     ORDER BY pin, created_at DESC, id DESC`,
    [controllerId, pins]
  )

  const inWindowRows = await client.query<DigitalValuePoint>(
    `SELECT pin, value, created_at
     FROM controller_data
     WHERE controller_id = $1
       AND pin = ANY($2::text[])
       AND created_at >= NOW() - INTERVAL '24 hours'
     ORDER BY pin ASC, created_at ASC, id ASC`,
    [controllerId, pins]
  )

  const pinConfigMap = new Map<string, PinLogicConfig>(digitalPins.map((item) => [item.pin, item]))
  const timeline = new Map<string, Array<{ ts: number; state: number }>>()

  for (const row of prevRows.rows) {
    const pin = String(row.pin).trim().toUpperCase()
    const config = pinConfigMap.get(pin)
    if (!config) {
      continue
    }

    const entry = timeline.get(pin) ?? []
    entry.push({
      ts: windowStart,
      state: applyInvertLogic(Number(row.value), config.invert_digital_logic)
    })
    timeline.set(pin, entry)
  }

  for (const row of inWindowRows.rows) {
    const pin = String(row.pin).trim().toUpperCase()
    const config = pinConfigMap.get(pin)
    if (!config) {
      continue
    }

    const entry = timeline.get(pin) ?? []
    entry.push({
      ts: new Date(row.created_at).getTime(),
      state: applyInvertLogic(Number(row.value), config.invert_digital_logic)
    })
    timeline.set(pin, entry)
  }

  for (const pin of pins) {
    const points = timeline.get(pin)
    if (!points || points.length === 0) {
      result.set(pin, 0)
      continue
    }

    points.sort((a, b) => a.ts - b.ts)
    let onMs = 0

    for (let index = 0; index < points.length; index += 1) {
      const current = points[index]
      const next = points[index + 1]
      const intervalStart = Math.max(current.ts, windowStart)
      const intervalEnd = Math.min(next ? next.ts : windowEnd, windowEnd)

      if (intervalEnd > intervalStart && current.state > 0) {
        onMs += intervalEnd - intervalStart
      }
    }

    result.set(pin, Math.max(0, Math.round(onMs / 1000)))
  }

  return result
}

export const getControllerScenarioDerivedReadings = async (
  client: QueryClient,
  controllerId: number,
  readings: Map<string, number> = new Map<string, number>()
) => {
  await ensureControllerPinConfigSchema(client)

  const derived = new Map<string, number>()
  const systemTimeZone = await getSystemTimeZone(client)
  derived.set(
    buildDerivedKey(controllerId, 'current_time'),
    secondsFromMidnightByTimeZone(new Date(), systemTimeZone)
  )

  const analogSourceConfigs = await getAnalogSourceConfigs(client, controllerId)
  if (analogSourceConfigs.size > 0) {
    const analogPins = Array.from(analogSourceConfigs.values()).map((item) => item.pin)
    const maxAverageIntervalMinutes = Math.max(
      1,
      ...Array.from(analogSourceConfigs.values()).map((item) => item.average_interval_minutes)
    )

    const analogRows = await client.query<{ pin: string; value: number; created_at: string }>(
      `SELECT pin, value, created_at
       FROM controller_data
       WHERE controller_id = $1
         AND pin = ANY($2::text[])
         AND created_at >= NOW() - ($3::text || ' minutes')::interval`,
      [controllerId, analogPins, String(maxAverageIntervalMinutes)]
    )

    const nowMs = Date.now()
    const grouped = new Map<string, number[]>()
    for (const row of analogRows.rows) {
      const normalizedPin = normalizeAnalogPinKey(String(row.pin ?? ''))
      const config = analogSourceConfigs.get(normalizedPin)
      if (!config) {
        continue
      }

      const ts = new Date(row.created_at).getTime()
      if (!Number.isFinite(ts)) {
        continue
      }

      const maxAgeMs = config.average_interval_minutes * 60 * 1000
      if (nowMs - ts > maxAgeMs) {
        continue
      }

      const list = grouped.get(normalizedPin)
      if (list) {
        list.push(Number(row.value))
      } else {
        grouped.set(normalizedPin, [Number(row.value)])
      }
    }

    for (const [normalizedPin, values] of grouped.entries()) {
      if (values.length === 0) {
        continue
      }
      const average = values.reduce((sum, value) => sum + value, 0) / values.length
      const pin = analogSourceConfigs.get(normalizedPin)?.pin ?? normalizedPin
      const avgKey = `avg_pin:${pin}`
      derived.set(buildDerivedKey(controllerId, avgKey), average)
      derived.set(avgKey, average)
    }
  }

  const digitalPins = await getDigitalPinLogicConfigs(client, controllerId)
  const pinStateMap = await buildDigitalCurrentStateMap(client, controllerId, digitalPins, readings)
  const pinOnTimeMap = await buildDigitalOnTimeSeconds24hMap(client, controllerId, digitalPins)

  for (const [pin, state] of pinStateMap.entries()) {
    derived.set(buildDerivedKey(controllerId, `pin_state:${pin}`), state)
  }
  for (const [pin, seconds] of pinOnTimeMap.entries()) {
    derived.set(buildDerivedKey(controllerId, `pin_on_seconds_24h:${pin}`), seconds)
  }

  return derived
}

const evaluateScenarioActive = (
  scenario: ControllerScenario,
  sourceValue: number,
  wasActive: boolean
) => {
  const hysteresis = Math.max(0, scenario.hysteresis)
  const threshold = scenario.threshold

  switch (scenario.operator) {
    case 'gt':
      return wasActive ? sourceValue > threshold - hysteresis : sourceValue > threshold + hysteresis
    case 'gte':
      return sourceValue >= threshold
    case 'lt':
      return wasActive ? sourceValue < threshold + hysteresis : sourceValue < threshold - hysteresis
    case 'lte':
      return sourceValue <= threshold
    default:
      return false
  }
}

export const applyControllerScenarios = async (
  client: pg.PoolClient,
  controllerId: number,
  readings: Array<{ pin: string; value: number }>
) => {
  await ensureControllerScenarioSchema(client)

  const scenarios = await getControllerScenarios(client, controllerId)
  if (scenarios.length === 0) {
    return
  }

  const statesResult = await client.query<ScenarioState>(
    `SELECT scenario_id, is_active
     FROM controller_scenario_state
     WHERE scenario_id = ANY($1::bigint[])`,
    [scenarios.map((scenario) => scenario.id)]
  )
  const stateMap = new Map<number, boolean>(
    statesResult.rows.map((row) => [Number(row.scenario_id), Boolean(row.is_active)])
  )

  const readingMap = new Map<string, number>(readings.map((reading) => [reading.pin, reading.value]))
  const derivedReadings = await getControllerScenarioDerivedReadings(client, controllerId, readingMap)
  for (const [key, value] of derivedReadings.entries()) {
    readingMap.set(key, value)
  }
  const targetDecisions = new Map<string, 0 | 1>()
  const targetPinLogicMap = new Map<string, boolean>(
    (await getDigitalPinLogicConfigs(client, controllerId)).map((item) => [item.pin, item.invert_digital_logic])
  )
  const analogSourceConfigs = await getAnalogSourceConfigs(client, controllerId)

  for (const scenario of scenarios) {
    if (!scenario.enabled) {
      continue
    }

    const sourceKey = scenario.source_pin.trim()
    const sourceValue = readingMap.get(sourceKey)
    if (sourceValue == null || !Number.isFinite(sourceValue)) {
      continue
    }

    const wasActive = stateMap.get(scenario.id) ?? false
    const scenarioSourceValue = mapScenarioSourceValue(sourceKey, sourceValue, analogSourceConfigs)
    const isActive = evaluateScenarioActive(scenario, scenarioSourceValue, wasActive)
    const nextValueRaw = isActive ? scenario.value_when_true : scenario.value_when_false
    const normalizedTargetPin = scenario.target_pin.trim().toUpperCase()
    const targetInvert = targetPinLogicMap.get(normalizedTargetPin) ?? false
    const nextDesiredValue = (targetInvert
      ? (nextValueRaw > 0 ? 0 : 1)
      : nextValueRaw) as 0 | 1

    if (!targetDecisions.has(normalizedTargetPin)) {
      targetDecisions.set(normalizedTargetPin, nextDesiredValue)
    }

    await client.query(
      `INSERT INTO controller_scenario_state (scenario_id, is_active, updated_at)
       VALUES ($1, $2, NOW())
       ON CONFLICT (scenario_id)
       DO UPDATE SET is_active = EXCLUDED.is_active, updated_at = EXCLUDED.updated_at`,
      [scenario.id, isActive]
    )
  }

  for (const [targetPin, desiredValue] of targetDecisions.entries()) {
    await client.query(
      `UPDATE controller_pin_config
       SET desired_digital_value = $3,
           desired_digital_updated_at =
             CASE WHEN desired_digital_value IS DISTINCT FROM $3 THEN NOW() ELSE desired_digital_updated_at END
       WHERE controller_id = $1
         AND pin = $2
         AND digital_style = 'power'`,
      [controllerId, targetPin, desiredValue]
    )
  }
}
