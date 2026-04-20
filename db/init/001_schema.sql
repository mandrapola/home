CREATE TABLE IF NOT EXISTS controllers (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  discription TEXT,
  send_interval_seconds INTEGER NOT NULL DEFAULT 30,
  time_zone TEXT NOT NULL DEFAULT 'Europe/Moscow'
);

CREATE TABLE IF NOT EXISTS controller_data (
  id BIGSERIAL PRIMARY KEY,
  pin TEXT NOT NULL,
  value DOUBLE PRECISION NOT NULL,
  controller_id INTEGER NOT NULL REFERENCES controllers(id) ON DELETE CASCADE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS system_settings (
  id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  time_zone TEXT NOT NULL DEFAULT 'Europe/Moscow'
);

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
  show_on_dashboard BOOLEAN NOT NULL DEFAULT TRUE,
  show_on_chart BOOLEAN NOT NULL DEFAULT FALSE,
  chart_range_hours INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0,
  UNIQUE (controller_id, pin)
);

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
);

CREATE TABLE IF NOT EXISTS controller_scenario_state (
  scenario_id BIGINT PRIMARY KEY REFERENCES controller_scenarios(id) ON DELETE CASCADE,
  is_active BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO controllers (id, name, discription, send_interval_seconds, time_zone)
VALUES (1, 'arduino-uno-main', 'Arduino Uno web client controller', 30, 'Europe/Moscow')
ON CONFLICT (id) DO NOTHING;

INSERT INTO system_settings (id, time_zone)
VALUES (
  1,
  COALESCE(
    (SELECT time_zone FROM controllers ORDER BY id ASC LIMIT 1),
    'Europe/Moscow'
  )
)
ON CONFLICT (id) DO NOTHING;

INSERT INTO controller_pin_config (controller_id, pin, label, unit, multiplier, value_offset, precision, average_interval_minutes, value_labels, digital_style, invert_digital_logic, desired_digital_value, desired_digital_updated_at, show_on_dashboard, show_on_chart, chart_range_hours, sort_order)
VALUES
  (1, 'D3', 'Цифровой порт D3', NULL, 1, 0, 0, 5, '{"0":"Выключен","1":"Включен"}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, FALSE, 1, 0),
  (1, 'D4', 'Цифровой порт D4', NULL, 1, 0, 0, 5, '{"0":"Выключен","1":"Включен"}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, FALSE, 1, 1),
  (1, 'D5', 'Цифровой порт D5', NULL, 1, 0, 0, 5, '{"0":"Выключен","1":"Включен"}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, FALSE, 1, 2),
  (1, 'D6', 'Цифровой порт D6', NULL, 1, 0, 0, 5, '{"0":"Выключен","1":"Включен"}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, FALSE, 1, 3),
  (1, 'D7', 'Цифровой порт D7', NULL, 1, 0, 0, 5, '{"0":"Выключен","1":"Включен"}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, FALSE, 1, 4),
  (1, 'D8', 'Цифровой порт D8', NULL, 1, 0, 0, 5, '{"0":"Выключен","1":"Включен"}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, FALSE, 1, 5),
  (1, 'D9', 'Цифровой порт D9', NULL, 1, 0, 0, 5, '{"0":"Выключен","1":"Включен"}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, FALSE, 1, 6),
  (1, 'air_temperature', 'Температура воздуха', '°C', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, TRUE, 24, 7),
  (1, 'air_humidity', 'Влажность воздуха', '%', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, TRUE, 24, 8),
  (1, 'A0', 'Аналоговый порт A0', 'ADC', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, TRUE, 1, 9),
  (1, 'A1', 'Аналоговый порт A1', 'ADC', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, TRUE, 1, 10),
  (1, 'A2', 'Аналоговый порт A2', 'ADC', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, TRUE, 1, 11),
  (1, 'A3', 'Аналоговый порт A3', 'ADC', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, TRUE, 1, 12),
  (1, 'A4', 'Аналоговый порт A4', 'ADC', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, TRUE, 1, 13),
  (1, 'A5', 'Аналоговый порт A5', 'ADC', 1, 0, 0, 5, '{}'::jsonb, 'power', FALSE, NULL, NULL, TRUE, TRUE, 1, 14)
ON CONFLICT (controller_id, pin) DO NOTHING;
