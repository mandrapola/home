SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS controller (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  discription TEXT NULL,
  send_interval_seconds INT NOT NULL DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pin (
  id CHAR(36) NOT NULL PRIMARY KEY,
  controller_id CHAR(36) NOT NULL,
  pin VARCHAR(64) NOT NULL,
  label VARCHAR(255) NOT NULL,
  unit VARCHAR(32) NULL,
  average_interval_minutes INT NOT NULL DEFAULT 5,
  digital_style VARCHAR(32) NOT NULL DEFAULT 'sensor',
  invert_digital_logic TINYINT(1) NOT NULL DEFAULT 0,
  value DOUBLE NULL,
  value_updated_at TIMESTAMP NULL,
  desired_digital_value TINYINT NULL,
  desired_digital_updated_at TIMESTAMP NULL,
  power_on_duration_seconds INT NULL,
  show_on_dashboard TINYINT(1) NOT NULL DEFAULT 1,
  show_on_chart TINYINT(1) NOT NULL DEFAULT 0,
  chart_range_hours INT NOT NULL DEFAULT 1,
  enable_scenario TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uk_controller_pin (controller_id, pin),
  INDEX idx_pin_config_controller_pin (controller_id, pin),
  CONSTRAINT fk_pin_controller FOREIGN KEY (controller_id) REFERENCES controller(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pin_data (
  id CHAR(36) NOT NULL PRIMARY KEY,
  pin_id CHAR(36) NOT NULL,
  value DOUBLE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pin_data_pin_created (pin_id, created_at),
  INDEX idx_pin_data_created (created_at),
  CONSTRAINT fk_pin_data_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
  id CHAR(36) NOT NULL PRIMARY KEY,
  time_zone VARCHAR(64) NOT NULL DEFAULT 'Europe/Moscow'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scenario (
  id CHAR(36) NOT NULL PRIMARY KEY,
  pin_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_scenario_definition_pin_name (pin_id, name),
  CONSTRAINT fk_scenario_definition_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scenario_condition (
  id CHAR(36) NOT NULL PRIMARY KEY,
  scenario_id CHAR(36) NOT NULL,
  pin_id CHAR(36) NOT NULL,
  operator VARCHAR(8) NOT NULL DEFAULT 'gt',
  threshold DOUBLE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_scenarios_definition (scenario_id),
  INDEX idx_scenario_condition_pin (pin_id),
  CONSTRAINT fk_scenario_condition_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE,
  CONSTRAINT fk_scenarios_definition FOREIGN KEY (scenario_id) REFERENCES scenario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO controller (id, name, discription, send_interval_seconds)
VALUES ('018ec300-0001-7001-8000-000000000001', 'arduino-uno-main', 'Arduino Uno web client controller', 5)
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO system_settings (id, time_zone)
VALUES ('018ec300-0001-7001-8000-000000000002', 'Europe/Moscow')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO pin (
  id, controller_id, pin, label, unit,
  average_interval_minutes, digital_style, invert_digital_logic,
  desired_digital_value, desired_digital_updated_at, power_on_duration_seconds,
  show_on_dashboard, show_on_chart, chart_range_hours, enable_scenario
)
VALUES
  ('018ec300-0001-7001-8000-000000000101', '018ec300-0001-7001-8000-000000000001', 'D3', 'Цифровой порт D3', NULL, 5, 'power', 0, 0, NULL, NULL, 1, 0, 1, 1),
  ('018ec300-0001-7001-8000-000000000102', '018ec300-0001-7001-8000-000000000001', 'D4', 'Цифровой порт D4', NULL, 5, 'power', 0, 0, NULL, NULL, 1, 0, 1, 1),
  ('018ec300-0001-7001-8000-000000000103', '018ec300-0001-7001-8000-000000000001', 'D5', 'Цифровой порт D5', NULL, 5, 'power', 0, 0, NULL, NULL, 1, 0, 1, 1),
  ('018ec300-0001-7001-8000-000000000104', '018ec300-0001-7001-8000-000000000001', 'D6', 'Цифровой порт D6', NULL, 5, 'power', 0, 0, NULL, NULL, 1, 0, 1, 1),
  ('018ec300-0001-7001-8000-000000000105', '018ec300-0001-7001-8000-000000000001', 'CURRENT_TIME', 'Текущее время', 's', 1, 'sensor', 0, NULL, NULL, NULL, 0, 0, 1, 1),
  ('018ec300-0001-7001-8000-000000000106', '018ec300-0001-7001-8000-000000000001', 'air_temperature', 'Температура воздуха', '°C', 5, 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 1),
  ('018ec300-0001-7001-8000-000000000107', '018ec300-0001-7001-8000-000000000001', 'air_humidity', 'Влажность воздуха', '%', 5, 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 1),
  ('018ec300-0001-7001-8000-000000000108', '018ec300-0001-7001-8000-000000000001', 'A0', 'Аналоговый порт A0', 'ADC', 5, 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 1),
  ('018ec300-0001-7001-8000-000000000109', '018ec300-0001-7001-8000-000000000001', 'A1', 'Аналоговый порт A1', 'ADC', 5, 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 1),
  ('018ec300-0001-7001-8000-00000000010a', '018ec300-0001-7001-8000-000000000001', 'A2', 'Аналоговый порт A2', 'ADC', 5, 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 1),
  ('018ec300-0001-7001-8000-00000000010b', '018ec300-0001-7001-8000-000000000001', 'A3', 'Аналоговый порт A3', 'ADC', 5, 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 1),
  ('018ec300-0001-7001-8000-00000000010c', '018ec300-0001-7001-8000-000000000001', 'A4', 'Аналоговый порт A4', 'ADC', 5, 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 1),
  ('018ec300-0001-7001-8000-00000000010d', '018ec300-0001-7001-8000-000000000001', 'A5', 'Аналоговый порт A5', 'ADC', 5, 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 1)
ON DUPLICATE KEY UPDATE pin = pin;

CREATE OR REPLACE VIEW controller_pin_config AS
SELECT p.id,
       c.id AS controller_id,
       p.pin,
       p.label,
       p.unit,
       p.average_interval_minutes,
       p.digital_style,
       p.invert_digital_logic,
       p.value,
       p.value_updated_at,
       p.desired_digital_value,
       p.desired_digital_updated_at,
       p.power_on_duration_seconds,
       p.show_on_dashboard,
       p.show_on_chart,
       p.chart_range_hours,
       p.enable_scenario
FROM pin p
INNER JOIN controller c ON c.id = p.controller_id;

CREATE OR REPLACE VIEW controller_data AS
SELECT pd.id,
       p.pin,
       pd.value,
       c.id AS controller_id,
       pd.created_at
FROM pin_data pd
INNER JOIN pin p ON p.id = pd.pin_id
INNER JOIN controller c ON c.id = p.controller_id;
