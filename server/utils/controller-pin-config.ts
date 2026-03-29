import type pg from 'pg'

export interface ControllerPinConfig {
  pin: string
  label: string
  unit: string | null
  multiplier: number
  offset: number
  precision: number
  average_interval_minutes: number
  value_labels: Record<string, string>
  digital_style: string
  invert_digital_logic: boolean
  desired_digital_value: number | null
  desired_digital_updated_at: string | null
  power_on_duration_seconds: number | null
  show_on_dashboard: boolean
  show_on_chart: boolean
  chart_range_hours: number
  sort_order: number
}

export interface FormattedReading {
  pin: string
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

let schemaInitPromise: Promise<void> | null = null
type QueryClient = pg.Pool | pg.PoolClient

const isAnalogPin = (pin: string) => {
  const normalizedPin = pin.trim().toLowerCase()
  return /^a\d+$/.test(normalizedPin) || normalizedPin === 'air_temperature' || normalizedPin === 'air_humidity'
}
export const isDigitalPin = (pin: string) => /^D\d+$/i.test(pin.trim())

const roundToPrecision = (value: number, precision: number) => {
  if (!Number.isInteger(precision) || precision < 0) {
    return value
  }

  return Number(value.toFixed(precision))
}

const createDefaultArduinoConfigs = (): ControllerPinConfig[] => {
  const digitalPins = Array.from({ length: 7 }, (_, index) => index + 3)
  const analogPins = Array.from({ length: 6 }, (_, index) => index)

  return [
    ...digitalPins.map((pin, index) => ({
      pin: `D${pin}`,
      label: `Цифровой порт D${pin}`,
      unit: null,
      multiplier: 1,
      offset: 0,
      precision: 0,
      average_interval_minutes: 5,
      value_labels: {
        '0': 'Выключен',
        '1': 'Включен'
      },
      digital_style: 'signal',
      invert_digital_logic: false,
      desired_digital_value: null,
      desired_digital_updated_at: null,
      power_on_duration_seconds: null,
      show_on_dashboard: true,
      show_on_chart: false,
      chart_range_hours: 1,
      sort_order: index
    })),
    {
      pin: 'air_temperature',
      label: 'Температура воздуха',
      unit: '°C',
      multiplier: 1,
      offset: 0,
      precision: 0,
      average_interval_minutes: 5,
      value_labels: {},
      digital_style: 'power',
      invert_digital_logic: false,
      desired_digital_value: null,
      desired_digital_updated_at: null,
      power_on_duration_seconds: null,
      show_on_dashboard: true,
      show_on_chart: true,
      chart_range_hours: 24,
      sort_order: digitalPins.length
    },
    {
      pin: 'air_humidity',
      label: 'Влажность воздуха',
      unit: '%',
      multiplier: 1,
      offset: 0,
      precision: 0,
      average_interval_minutes: 5,
      value_labels: {},
      digital_style: 'power',
      invert_digital_logic: false,
      desired_digital_value: null,
      desired_digital_updated_at: null,
      power_on_duration_seconds: null,
      show_on_dashboard: true,
      show_on_chart: true,
      chart_range_hours: 24,
      sort_order: digitalPins.length + 1
    },
    ...analogPins.map((pin, index) => ({
      pin: `A${pin}`,
      label: `Аналоговый порт A${pin}`,
      unit: 'ADC',
      multiplier: 1,
      offset: 0,
      precision: 0,
      average_interval_minutes: 5,
      value_labels: {},
      digital_style: 'power',
      invert_digital_logic: false,
      desired_digital_value: null,
      desired_digital_updated_at: null,
      power_on_duration_seconds: null,
      show_on_dashboard: true,
      show_on_chart: true,
      chart_range_hours: 1,
      sort_order: digitalPins.length + 2 + index
    }))
  ]
}

const createDefaultPinConfig = (pin: string, sortOrder: number): ControllerPinConfig => {
  const normalizedPin = pin.trim()

  if (isDigitalPin(normalizedPin)) {
    return {
      pin: normalizedPin,
      label: `Цифровой порт ${normalizedPin.toUpperCase()}`,
      unit: null,
      multiplier: 1,
      offset: 0,
      precision: 0,
      average_interval_minutes: 5,
      value_labels: {
        '0': 'Выключен',
        '1': 'Включен'
      },
      digital_style: 'signal',
      invert_digital_logic: false,
      desired_digital_value: null,
      desired_digital_updated_at: null,
      power_on_duration_seconds: null,
      show_on_dashboard: true,
      show_on_chart: false,
      chart_range_hours: 1,
      sort_order: sortOrder
    }
  }

  if (isAnalogPin(normalizedPin)) {
    if (normalizedPin === 'air_temperature') {
      return {
        pin: normalizedPin,
        label: 'Температура воздуха',
        unit: '°C',
        multiplier: 1,
        offset: 0,
        precision: 0,
        average_interval_minutes: 5,
        value_labels: {},
        digital_style: 'power',
        invert_digital_logic: false,
        desired_digital_value: null,
        desired_digital_updated_at: null,
        power_on_duration_seconds: null,
        show_on_dashboard: true,
        show_on_chart: true,
        chart_range_hours: 24,
        sort_order: sortOrder
      }
    }

    if (normalizedPin === 'air_humidity') {
      return {
        pin: normalizedPin,
        label: 'Влажность воздуха',
        unit: '%',
        multiplier: 1,
        offset: 0,
        precision: 0,
        average_interval_minutes: 5,
        value_labels: {},
        digital_style: 'power',
        invert_digital_logic: false,
        desired_digital_value: null,
        desired_digital_updated_at: null,
        power_on_duration_seconds: null,
        show_on_dashboard: true,
        show_on_chart: true,
        chart_range_hours: 24,
        sort_order: sortOrder
      }
    }

    return {
      pin: normalizedPin,
      label: `Аналоговый порт ${normalizedPin.toUpperCase()}`,
      unit: 'ADC',
      multiplier: 1,
      offset: 0,
      precision: 0,
      average_interval_minutes: 5,
      value_labels: {},
      digital_style: 'power',
      invert_digital_logic: false,
      desired_digital_value: null,
      desired_digital_updated_at: null,
      power_on_duration_seconds: null,
      show_on_dashboard: true,
      show_on_chart: true,
      chart_range_hours: 1,
      sort_order: sortOrder
    }
  }

  return {
    pin: normalizedPin,
    label: normalizedPin,
    unit: null,
    multiplier: 1,
    offset: 0,
    precision: 0,
    average_interval_minutes: 5,
    value_labels: {},
    digital_style: 'power',
    invert_digital_logic: false,
    desired_digital_value: null,
    desired_digital_updated_at: null,
    power_on_duration_seconds: null,
    show_on_dashboard: true,
    show_on_chart: false,
    chart_range_hours: 1,
    sort_order: sortOrder
  }
}

const defaultControllerPinConfigs: Record<number, ControllerPinConfig[]> = {
  1: createDefaultArduinoConfigs()
}

const normalizeConfigRow = (row: Record<string, unknown>): ControllerPinConfig => {
  const pin = String(row.pin ?? '').trim()
  const analogPin = isAnalogPin(pin)
  const desiredUpdatedAt =
    row.desired_digital_updated_at == null
      ? null
      : row.desired_digital_updated_at instanceof Date
        ? row.desired_digital_updated_at.toISOString()
        : String(row.desired_digital_updated_at)

  return {
    pin,
    label: String(row.label ?? '').trim(),
    unit: row.unit == null || String(row.unit).trim().length === 0 ? null : String(row.unit).trim(),
    multiplier: Number(row.multiplier ?? 1),
    offset: Number(row.offset ?? 0),
    precision: Number(row.precision ?? 0),
    average_interval_minutes: analogPin ? Math.max(1, Math.trunc(Number(row.average_interval_minutes ?? 5))) : 5,
    value_labels:
      row.value_labels && typeof row.value_labels === 'object' && !Array.isArray(row.value_labels)
        ? (row.value_labels as Record<string, string>)
        : {},
    digital_style: String(row.digital_style ?? 'power'),
    invert_digital_logic: Boolean(row.invert_digital_logic),
    desired_digital_value:
      row.desired_digital_value == null ? null : Number(row.desired_digital_value) > 0 ? 1 : 0,
    desired_digital_updated_at: desiredUpdatedAt,
    power_on_duration_seconds:
      row.power_on_duration_seconds == null
        ? null
        : Math.max(1, Math.trunc(Number(row.power_on_duration_seconds))),
    show_on_dashboard: Boolean(row.show_on_dashboard),
    show_on_chart: analogPin ? Boolean(row.show_on_chart) : false,
    chart_range_hours: analogPin ? Number(row.chart_range_hours ?? 1) : 1,
    sort_order: Number(row.sort_order ?? 0)
  }
}

export const ensureControllerPinConfigSchema = async (pool: QueryClient) => {
  if (schemaInitPromise) {
    await schemaInitPromise
    return
  }

  schemaInitPromise = (async () => {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS controller_pin_config (
        id BIGSERIAL PRIMARY KEY,
        controller_id INTEGER NOT NULL REFERENCES controllers(id) ON DELETE CASCADE,
        pin TEXT NOT NULL,
        label TEXT NOT NULL,
        unit TEXT,
        multiplier DOUBLE PRECISION NOT NULL DEFAULT 1,
        value_offset DOUBLE PRECISION NOT NULL DEFAULT 0,
        precision INTEGER NOT NULL DEFAULT 0,
        average_interval_minutes INTEGER NOT NULL DEFAULT 5,
        value_labels JSONB NOT NULL DEFAULT '{}'::jsonb,
        digital_style TEXT NOT NULL DEFAULT 'power',
        invert_digital_logic BOOLEAN NOT NULL DEFAULT FALSE,
        desired_digital_value INTEGER,
        desired_digital_updated_at TIMESTAMPTZ,
        power_on_duration_seconds INTEGER,
        show_on_dashboard BOOLEAN NOT NULL DEFAULT TRUE,
        show_on_chart BOOLEAN NOT NULL DEFAULT FALSE,
        chart_range_hours INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        UNIQUE (controller_id, pin)
      )
    `)

    await pool.query(`
      ALTER TABLE controller_pin_config
      ADD COLUMN IF NOT EXISTS invert_digital_logic BOOLEAN NOT NULL DEFAULT FALSE
    `)

    await pool.query(`
      ALTER TABLE controller_pin_config
      ADD COLUMN IF NOT EXISTS digital_style TEXT NOT NULL DEFAULT 'power'
    `)

    await pool.query(`
      ALTER TABLE controller_pin_config
      ADD COLUMN IF NOT EXISTS desired_digital_value INTEGER
    `)

    await pool.query(`
      ALTER TABLE controller_pin_config
      ADD COLUMN IF NOT EXISTS desired_digital_updated_at TIMESTAMPTZ
    `)

    await pool.query(`
      ALTER TABLE controller_pin_config
      ADD COLUMN IF NOT EXISTS power_on_duration_seconds INTEGER
    `)

    await pool.query(`
      ALTER TABLE controller_pin_config
      ADD COLUMN IF NOT EXISTS show_on_chart BOOLEAN NOT NULL DEFAULT FALSE
    `)

    await pool.query(`
      ALTER TABLE controller_pin_config
      ADD COLUMN IF NOT EXISTS chart_range_hours INTEGER NOT NULL DEFAULT 1
    `)

    await pool.query(`
      ALTER TABLE controller_pin_config
      ADD COLUMN IF NOT EXISTS average_interval_minutes INTEGER NOT NULL DEFAULT 5
    `)

    for (const [controllerId, configs] of Object.entries(defaultControllerPinConfigs)) {
      for (const config of configs) {
        await pool.query(
          `INSERT INTO controller_pin_config
            (controller_id, pin, label, unit, multiplier, value_offset, precision, average_interval_minutes, value_labels, digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at, power_on_duration_seconds, show_on_dashboard, show_on_chart, chart_range_hours, sort_order)
           VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9::jsonb, $10, $11, $12, NULL, $13, $14, $15, $16, $17)
           ON CONFLICT (controller_id, pin) DO NOTHING`,
          [
            Number(controllerId),
            config.pin,
            config.label,
            config.unit,
            config.multiplier,
            config.offset,
            config.precision,
            config.average_interval_minutes,
            JSON.stringify(config.value_labels),
            config.digital_style,
            config.invert_digital_logic,
            config.desired_digital_value,
            config.power_on_duration_seconds,
            config.show_on_dashboard,
            config.show_on_chart,
            config.chart_range_hours,
            config.sort_order
          ]
        )
      }
    }

    await pool.query(
      `DELETE FROM controller_pin_config
       WHERE controller_id = 1 AND pin = 'D2'`
    )

    await pool.query(
      `DELETE FROM controller_data
       WHERE controller_id = 1 AND pin = 'D2'`
    )

    await pool.query(
      `INSERT INTO controller_pin_config
        (controller_id, pin, label, unit, multiplier, value_offset, precision, average_interval_minutes, value_labels, digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at, power_on_duration_seconds, show_on_dashboard, show_on_chart, chart_range_hours, sort_order)
       VALUES
        (1, 'air_temperature', 'Температура воздуха', '°C', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, NULL, TRUE, TRUE, 24, 7),
        (1, 'air_humidity', 'Влажность воздуха', '%', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, NULL, TRUE, TRUE, 24, 8)
       ON CONFLICT (controller_id, pin) DO NOTHING`
    )
  })()

  await schemaInitPromise
}

export const getControllerPinConfigs = async (pool: QueryClient, controllerId: number) => {
  await ensureControllerPinConfigSchema(pool)

  const result = await pool.query(
    `SELECT pin, label, unit, multiplier, value_offset AS offset, precision, average_interval_minutes, value_labels, digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at, power_on_duration_seconds, show_on_dashboard, show_on_chart, chart_range_hours, sort_order
     FROM controller_pin_config
     WHERE controller_id = $1
     ORDER BY sort_order ASC, pin ASC`,
    [controllerId]
  )

  return result.rows.map((row) => normalizeConfigRow(row))
}

export const replaceControllerPinConfigs = async (
  client: pg.PoolClient,
  controllerId: number,
  configs: ControllerPinConfig[]
) => {
  await ensureControllerPinConfigSchema(client)

  await client.query('DELETE FROM controller_pin_config WHERE controller_id = $1', [controllerId])

  for (const config of configs) {
    await client.query(
      `INSERT INTO controller_pin_config
        (controller_id, pin, label, unit, multiplier, value_offset, precision, average_interval_minutes, value_labels, digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at, power_on_duration_seconds, show_on_dashboard, show_on_chart, chart_range_hours, sort_order)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9::jsonb, $10, $11, $12, $13, $14, $15, $16, $17, $18)`,
      [
        controllerId,
        config.pin,
        config.label,
        config.unit,
        config.multiplier,
        config.offset,
        config.precision,
        config.average_interval_minutes,
        JSON.stringify(config.value_labels),
        config.digital_style,
        config.invert_digital_logic,
        config.desired_digital_value,
        config.desired_digital_updated_at,
        config.power_on_duration_seconds,
        config.show_on_dashboard,
        config.show_on_chart,
        config.chart_range_hours,
        config.sort_order
      ]
    )
  }
}

export const ensureControllerPinConfigs = async (
  client: pg.PoolClient,
  controllerId: number,
  pins: string[]
) => {
  await ensureControllerPinConfigSchema(client)

  const normalizedPins = [...new Set(pins.map((pin) => pin.trim()).filter((pin) => pin.length > 0))]
  if (normalizedPins.length === 0) {
    return
  }

  const existingResult = await client.query<{ pin: string; sort_order: number }>(
    `SELECT pin, sort_order
     FROM controller_pin_config
     WHERE controller_id = $1`,
    [controllerId]
  )

  const existingPins = new Set(existingResult.rows.map((row) => row.pin))
  let nextSortOrder =
    existingResult.rows.reduce((maxOrder, row) => Math.max(maxOrder, row.sort_order), -1) + 1

  for (const pin of normalizedPins) {
    if (existingPins.has(pin)) {
      continue
    }

    const config = createDefaultPinConfig(pin, nextSortOrder)

    await client.query(
      `INSERT INTO controller_pin_config
        (controller_id, pin, label, unit, multiplier, value_offset, precision, average_interval_minutes, value_labels, digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at, power_on_duration_seconds, show_on_dashboard, show_on_chart, chart_range_hours, sort_order)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9::jsonb, $10, $11, $12, NULL, $13, $14, $15, $16, $17)
       ON CONFLICT (controller_id, pin) DO NOTHING`,
      [
        controllerId,
        config.pin,
        config.label,
        config.unit,
        config.multiplier,
        config.offset,
        config.precision,
        config.average_interval_minutes,
        JSON.stringify(config.value_labels),
        config.digital_style,
        config.invert_digital_logic,
        config.desired_digital_value,
        config.power_on_duration_seconds,
        config.show_on_dashboard,
        config.show_on_chart,
        config.chart_range_hours,
        config.sort_order
      ]
    )

    existingPins.add(pin)
    nextSortOrder += 1
  }
}

export const formatControllerReading = (
  reading: {
    controller_id: number
    pin: string
    value: number
  },
  configMap: Map<string, ControllerPinConfig>
): FormattedReading => {
  const pinConfig = configMap.get(reading.pin)
  const analogPin = isAnalogPin(reading.pin)
  const isPowerDigitalPin = isDigitalPin(reading.pin) && (pinConfig?.digital_style ?? 'power') === 'power'
  const rawDigitalValue = reading.value > 0 ? 1 : 0
  const logicalDigitalValue =
    isPowerDigitalPin && pinConfig?.invert_digital_logic ? (rawDigitalValue > 0 ? 0 : 1) : rawDigitalValue
  const displayValue = isPowerDigitalPin
    ? logicalDigitalValue
    : roundToPrecision(
        reading.value * (pinConfig?.multiplier ?? 1) + (pinConfig?.offset ?? 0),
        pinConfig?.precision ?? 0
      )
  const displayText =
    pinConfig?.value_labels[String(displayValue)] ??
    (pinConfig?.unit ? `${displayValue} ${pinConfig.unit}` : String(displayValue))

  return {
    pin: reading.pin,
    label: pinConfig?.label ?? reading.pin,
    unit: pinConfig?.unit ?? null,
    raw_value: reading.value,
    display_value: displayValue,
    display_text: displayText,
    digital_style: pinConfig?.digital_style ?? 'power',
    invert_digital_logic: pinConfig?.invert_digital_logic ?? false,
    desired_digital_value: pinConfig?.desired_digital_value ?? null,
    desired_digital_updated_at: pinConfig?.desired_digital_updated_at ?? null,
    power_on_duration_seconds: pinConfig?.power_on_duration_seconds ?? null,
    show_on_dashboard: pinConfig?.show_on_dashboard ?? true,
    show_on_chart: analogPin ? (pinConfig?.show_on_chart ?? false) : false,
    chart_range_hours: analogPin ? (pinConfig?.chart_range_hours ?? 1) : 1,
    average_interval_minutes: analogPin ? Math.max(1, Math.trunc(pinConfig?.average_interval_minutes ?? 5)) : 5,
    sort_order: pinConfig?.sort_order ?? Number.MAX_SAFE_INTEGER
  }
}
