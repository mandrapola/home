SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

DROP VIEW IF EXISTS controller_pin_config;
DROP VIEW IF EXISTS controller_data;

SET @drop_pin_code_col := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pin' AND column_name = 'pin_code'
    ),
    'ALTER TABLE pin DROP COLUMN pin_code',
    'SELECT 1'
  )
);
PREPARE stmt FROM @drop_pin_code_col; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @drop_pin_data_code_col := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pin_data' AND column_name = 'pin_data_code'
    ),
    'ALTER TABLE pin_data DROP COLUMN pin_data_code',
    'SELECT 1'
  )
);
PREPARE stmt FROM @drop_pin_data_code_col; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @drop_setting_code_col := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'system_settings' AND column_name = 'setting_code'
    ),
    'ALTER TABLE system_settings DROP COLUMN setting_code',
    'SELECT 1'
  )
);
PREPARE stmt FROM @drop_setting_code_col; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @drop_scenario_code_col := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'scenario' AND column_name = 'scenario_code'
    ),
    'ALTER TABLE scenario DROP COLUMN scenario_code',
    'SELECT 1'
  )
);
PREPARE stmt FROM @drop_scenario_code_col; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @drop_scenario_condition_code_col := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'scenario_condition' AND column_name = 'scenario_condition_code'
    ),
    'ALTER TABLE scenario_condition DROP COLUMN scenario_condition_code',
    'SELECT 1'
  )
);
PREPARE stmt FROM @drop_scenario_condition_code_col; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO system_settings (id, time_zone)
VALUES ('018ec300-0001-7001-8000-000000000002', 'Europe/Moscow')
ON DUPLICATE KEY UPDATE id = id;

CREATE OR REPLACE VIEW controller_pin_config AS
SELECT p.id,
       c.id AS controller_id,
       p.pin,
       p.label,
       p.unit,
       p.multiplier,
       p.value_offset,
       p.precision_value,
       p.average_interval_minutes,
       p.value_labels,
       p.digital_style,
       p.invert_digital_logic,
       p.value,
       p.value_updated_at,
       p.desired_digital_value,
       p.desired_digital_updated_at,
       p.show_on_dashboard,
       p.show_on_chart,
       p.chart_range_hours,
       p.enable_scenario,
       p.sort_order
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
