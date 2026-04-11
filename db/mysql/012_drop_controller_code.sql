SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Remove numeric controller code; UUID id is the only controller identifier.
ALTER TABLE controller DROP COLUMN controller_code;

CREATE OR REPLACE VIEW controller_pin_config AS
SELECT p.id,
       p.pin_code,
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
       p.power_on_duration_seconds,
       p.show_on_dashboard,
       p.show_on_chart,
       p.chart_range_hours,
       p.enable_scenario,
       p.sort_order
FROM pin p
INNER JOIN controller c ON c.id = p.controller_id;

CREATE OR REPLACE VIEW controller_data AS
SELECT pd.pin_data_code AS id,
       pd.id AS uuid,
       p.pin,
       pd.value,
       c.id AS controller_id,
       pd.created_at
FROM pin_data pd
INNER JOIN pin p ON p.id = pd.pin_id
INNER JOIN controller c ON c.id = p.controller_id;
