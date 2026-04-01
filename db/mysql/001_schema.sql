SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS controllers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  discription TEXT NULL,
  send_interval_seconds INT NOT NULL DEFAULT 30,
  time_zone VARCHAR(64) NOT NULL DEFAULT 'Europe/Moscow'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS controller_data (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  pin VARCHAR(64) NOT NULL,
  value DOUBLE NOT NULL,
  controller_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_controller_data_controller_pin_created (controller_id, pin, created_at),
  INDEX idx_controller_data_controller_created (controller_id, created_at),
  CONSTRAINT fk_controller_data_controller FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
  id TINYINT PRIMARY KEY,
  time_zone VARCHAR(64) NOT NULL DEFAULT 'Europe/Moscow'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS controller_pin_config (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  controller_id INT NOT NULL,
  pin VARCHAR(64) NOT NULL,
  label VARCHAR(255) NOT NULL,
  unit VARCHAR(32) NULL,
  multiplier DOUBLE NOT NULL DEFAULT 1,
  value_offset DOUBLE NOT NULL DEFAULT 0,
  precision_value INT NOT NULL DEFAULT 0,
  average_interval_minutes INT NOT NULL DEFAULT 5,
  value_labels JSON NOT NULL,
  digital_style VARCHAR(32) NOT NULL DEFAULT 'power',
  invert_digital_logic TINYINT(1) NOT NULL DEFAULT 0,
  desired_digital_value TINYINT NULL,
  desired_digital_updated_at TIMESTAMP NULL,
  power_on_duration_seconds INT NULL,
  show_on_dashboard TINYINT(1) NOT NULL DEFAULT 1,
  show_on_chart TINYINT(1) NOT NULL DEFAULT 0,
  chart_range_hours INT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uk_controller_pin (controller_id, pin),
  INDEX idx_pin_config_controller_sort (controller_id, sort_order, pin),
  CONSTRAINT fk_pin_config_controller FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS controller_scenarios (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  controller_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  source_pin VARCHAR(64) NOT NULL,
  operator VARCHAR(8) NOT NULL DEFAULT 'gt',
  threshold DOUBLE NOT NULL,
  hysteresis DOUBLE NOT NULL DEFAULT 0,
  target_pin VARCHAR(64) NOT NULL,
  value_when_true TINYINT NOT NULL DEFAULT 1,
  value_when_false TINYINT NOT NULL DEFAULT 0,
  priority INT NOT NULL DEFAULT 100,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_scenarios_controller FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS controller_scenario_state (
  scenario_id BIGINT PRIMARY KEY,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_scenario_state_scenario FOREIGN KEY (scenario_id) REFERENCES controller_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO controllers (id, name, discription, send_interval_seconds, time_zone)
VALUES (1, 'arduino-uno-main', 'Arduino Uno web client controller', 5, 'Europe/Moscow')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO system_settings (id, time_zone)
VALUES (1, 'Europe/Moscow')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO controller_pin_config (
  controller_id, pin, label, unit, multiplier, value_offset, precision_value,
  average_interval_minutes, value_labels, digital_style, invert_digital_logic,
  desired_digital_value, desired_digital_updated_at, power_on_duration_seconds,
  show_on_dashboard, show_on_chart, chart_range_hours, sort_order
)
VALUES
  (1, 'D3', 'Цифровой порт D3', NULL, 1, 0, 0, 5, JSON_OBJECT('0','Выключен','1','Включен'), 'power', 0, 0, NULL, NULL, 1, 0, 1, 0),
  (1, 'D4', 'Цифровой порт D4', NULL, 1, 0, 0, 5, JSON_OBJECT('0','Выключен','1','Включен'), 'power', 0, 0, NULL, NULL, 1, 0, 1, 1),
  (1, 'D5', 'Цифровой порт D5', NULL, 1, 0, 0, 5, JSON_OBJECT('0','Выключен','1','Включен'), 'power', 0, 0, NULL, NULL, 1, 0, 1, 2),
  (1, 'D6', 'Цифровой порт D6', NULL, 1, 0, 0, 5, JSON_OBJECT('0','Выключен','1','Включен'), 'power', 0, 0, NULL, NULL, 1, 0, 1, 3),
  (1, 'air_temperature', 'Температура воздуха', '°C', 1, 0, 1, 5, JSON_OBJECT(), 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 10),
  (1, 'air_humidity', 'Влажность воздуха', '%', 1, 0, 1, 5, JSON_OBJECT(), 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 11),
  (1, 'A0', 'Аналоговый порт A0', 'ADC', 1, 0, 0, 5, JSON_OBJECT(), 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 20),
  (1, 'A1', 'Аналоговый порт A1', 'ADC', 1, 0, 0, 5, JSON_OBJECT(), 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 21),
  (1, 'A2', 'Аналоговый порт A2', 'ADC', 1, 0, 0, 5, JSON_OBJECT(), 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 22),
  (1, 'A3', 'Аналоговый порт A3', 'ADC', 1, 0, 0, 5, JSON_OBJECT(), 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 23),
  (1, 'A4', 'Аналоговый порт A4', 'ADC', 1, 0, 0, 5, JSON_OBJECT(), 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 24),
  (1, 'A5', 'Аналоговый порт A5', 'ADC', 1, 0, 0, 5, JSON_OBJECT(), 'sensor', 0, NULL, NULL, NULL, 1, 1, 24, 25)
ON DUPLICATE KEY UPDATE id = id;
