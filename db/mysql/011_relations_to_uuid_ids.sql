SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Drop old foreign keys on numeric code columns.
ALTER TABLE pin DROP FOREIGN KEY fk_pin_controller;
ALTER TABLE pin_data DROP FOREIGN KEY fk_pin_data_pin;
ALTER TABLE scenario DROP FOREIGN KEY fk_scenario_definition_pin;
ALTER TABLE scenario_condition DROP FOREIGN KEY fk_scenario_condition_pin;
ALTER TABLE scenario_condition DROP FOREIGN KEY fk_scenarios_definition;

-- pin.controller_id (controller_code -> controller.id)
ALTER TABLE pin ADD COLUMN controller_id_uuid CHAR(36) NULL AFTER pin_code;
UPDATE pin p
INNER JOIN controller c ON c.controller_code = p.controller_id
SET p.controller_id_uuid = c.id;
DELETE FROM pin WHERE controller_id_uuid IS NULL;
ALTER TABLE pin DROP INDEX uk_controller_pin;
ALTER TABLE pin DROP INDEX idx_pin_config_controller_sort;
ALTER TABLE pin DROP COLUMN controller_id;
ALTER TABLE pin CHANGE COLUMN controller_id_uuid controller_id CHAR(36) NOT NULL;
ALTER TABLE pin
  ADD UNIQUE KEY uk_controller_pin (controller_id, pin),
  ADD INDEX idx_pin_config_controller_sort (controller_id, sort_order, pin),
  ADD CONSTRAINT fk_pin_controller FOREIGN KEY (controller_id) REFERENCES controller(id) ON DELETE CASCADE;

-- pin_data.pin_id (pin_code -> pin.id)
ALTER TABLE pin_data ADD COLUMN pin_id_uuid CHAR(36) NULL AFTER pin_data_code;
UPDATE pin_data pd
INNER JOIN pin p ON p.pin_code = pd.pin_id
SET pd.pin_id_uuid = p.id;
DELETE FROM pin_data WHERE pin_id_uuid IS NULL;
ALTER TABLE pin_data DROP INDEX idx_pin_data_pin_created;
ALTER TABLE pin_data DROP COLUMN pin_id;
ALTER TABLE pin_data CHANGE COLUMN pin_id_uuid pin_id CHAR(36) NOT NULL;
ALTER TABLE pin_data
  ADD INDEX idx_pin_data_pin_created (pin_id, created_at),
  ADD CONSTRAINT fk_pin_data_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE;

-- scenario.pin_id (pin_code -> pin.id)
ALTER TABLE scenario ADD COLUMN pin_id_uuid CHAR(36) NULL AFTER scenario_code;
UPDATE scenario s
INNER JOIN pin p ON p.pin_code = s.pin_id
SET s.pin_id_uuid = p.id;
DELETE FROM scenario WHERE pin_id_uuid IS NULL;
ALTER TABLE scenario DROP INDEX uk_scenario_definition_pin_name;
ALTER TABLE scenario DROP COLUMN pin_id;
ALTER TABLE scenario CHANGE COLUMN pin_id_uuid pin_id CHAR(36) NOT NULL;
ALTER TABLE scenario
  ADD UNIQUE KEY uk_scenario_definition_pin_name (pin_id, name),
  ADD CONSTRAINT fk_scenario_definition_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE;

-- scenario_condition.scenario_id (scenario_code -> scenario.id)
-- scenario_condition.pin_id (pin_code -> pin.id)
ALTER TABLE scenario_condition
  ADD COLUMN scenario_id_uuid CHAR(36) NULL AFTER scenario_condition_code,
  ADD COLUMN pin_id_uuid CHAR(36) NULL AFTER scenario_id_uuid;
UPDATE scenario_condition sc
INNER JOIN scenario s ON s.scenario_code = sc.scenario_id
INNER JOIN pin p ON p.pin_code = sc.pin_id
SET sc.scenario_id_uuid = s.id,
    sc.pin_id_uuid = p.id;
DELETE FROM scenario_condition WHERE scenario_id_uuid IS NULL OR pin_id_uuid IS NULL;
ALTER TABLE scenario_condition DROP INDEX idx_scenarios_definition;
ALTER TABLE scenario_condition DROP INDEX idx_scenario_condition_pin;
ALTER TABLE scenario_condition DROP COLUMN scenario_id;
ALTER TABLE scenario_condition DROP COLUMN pin_id;
ALTER TABLE scenario_condition
  CHANGE COLUMN scenario_id_uuid scenario_id CHAR(36) NOT NULL,
  CHANGE COLUMN pin_id_uuid pin_id CHAR(36) NOT NULL;
ALTER TABLE scenario_condition
  ADD INDEX idx_scenarios_definition (scenario_id),
  ADD INDEX idx_scenario_condition_pin (pin_id),
  ADD CONSTRAINT fk_scenario_condition_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_scenarios_definition FOREIGN KEY (scenario_id) REFERENCES scenario(id) ON DELETE CASCADE;

-- Views with external controller numeric code compatibility.
CREATE OR REPLACE VIEW controller_pin_config AS
SELECT p.id,
       p.pin_code,
       c.controller_code AS controller_id,
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
SELECT pd.pin_data_code AS id,
       pd.id AS uuid,
       p.pin,
       pd.value,
       c.controller_code AS controller_id,
       pd.created_at
FROM pin_data pd
INNER JOIN pin p ON p.id = pd.pin_id
INNER JOIN controller c ON c.id = p.controller_id;
