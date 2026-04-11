SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Drop FKs that reference numeric id columns before rename.
ALTER TABLE pin DROP FOREIGN KEY fk_pin_config_controller;
ALTER TABLE pin_data DROP FOREIGN KEY fk_pin_data_pin;
ALTER TABLE scenario DROP FOREIGN KEY fk_scenario_definition_pin;
ALTER TABLE scenario_condition DROP FOREIGN KEY fk_scenario_condition_pin;
ALTER TABLE scenario_condition DROP FOREIGN KEY fk_scenarios_definition;

-- Rename columns: numeric id -> *_code, pk_uuid -> id (UUID7).
ALTER TABLE controller
  CHANGE COLUMN id controller_code INT NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN pk_uuid id CHAR(36) NOT NULL;

ALTER TABLE pin
  CHANGE COLUMN id pin_code BIGINT NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN pk_uuid id CHAR(36) NOT NULL;

ALTER TABLE pin_data
  CHANGE COLUMN id pin_data_code BIGINT NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN pk_uuid id CHAR(36) NOT NULL;

ALTER TABLE system_settings
  CHANGE COLUMN id setting_code TINYINT NOT NULL,
  CHANGE COLUMN pk_uuid id CHAR(36) NOT NULL;

ALTER TABLE scenario
  CHANGE COLUMN id scenario_code BIGINT NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN pk_uuid id CHAR(36) NOT NULL;

ALTER TABLE scenario_condition
  CHANGE COLUMN id scenario_condition_code BIGINT NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN pk_uuid id CHAR(36) NOT NULL;

-- Rebuild unique indexes on numeric codes.
ALTER TABLE controller
  DROP INDEX uk_controller_id,
  ADD UNIQUE KEY uk_controller_code (controller_code);

ALTER TABLE pin
  DROP INDEX uk_pin_id,
  ADD UNIQUE KEY uk_pin_code (pin_code);

ALTER TABLE pin_data
  DROP INDEX uk_pin_data_id,
  ADD UNIQUE KEY uk_pin_data_code (pin_data_code);

ALTER TABLE system_settings
  DROP INDEX uk_system_settings_id,
  ADD UNIQUE KEY uk_system_settings_code (setting_code);

ALTER TABLE scenario
  DROP INDEX uk_scenario_id,
  ADD UNIQUE KEY uk_scenario_code (scenario_code);

ALTER TABLE scenario_condition
  DROP INDEX uk_scenario_condition_id,
  ADD UNIQUE KEY uk_scenario_condition_code (scenario_condition_code);

-- Recreate FKs to new numeric code columns.
ALTER TABLE pin
  ADD CONSTRAINT fk_pin_controller FOREIGN KEY (controller_id) REFERENCES controller(controller_code) ON DELETE CASCADE;

ALTER TABLE pin_data
  ADD CONSTRAINT fk_pin_data_pin FOREIGN KEY (pin_id) REFERENCES pin(pin_code) ON DELETE CASCADE;

ALTER TABLE scenario
  ADD CONSTRAINT fk_scenario_definition_pin FOREIGN KEY (pin_id) REFERENCES pin(pin_code) ON DELETE CASCADE;

ALTER TABLE scenario_condition
  ADD CONSTRAINT fk_scenario_condition_pin FOREIGN KEY (pin_id) REFERENCES pin(pin_code) ON DELETE CASCADE,
  ADD CONSTRAINT fk_scenarios_definition FOREIGN KEY (scenario_id) REFERENCES scenario(scenario_code) ON DELETE CASCADE;
