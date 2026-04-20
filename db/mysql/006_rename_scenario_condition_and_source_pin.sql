SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SELECT COUNT(*) INTO @has_old_table
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'controller_scenarios';

SELECT COUNT(*) INTO @has_new_table
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition';

SET @sql = IF(@has_old_table = 1 AND @has_new_table = 0,
  'RENAME TABLE controller_scenarios TO scenario_condition',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_new_table
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition';

SELECT COUNT(*) INTO @has_scenario_definition_id
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND column_name = 'scenario_definition_id';

SELECT COUNT(*) INTO @has_scenario_id
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND column_name = 'scenario_id';

SELECT COUNT(*) INTO @has_pin_id
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND column_name = 'pin_id';

SET @sql = IF(@has_new_table = 1 AND @has_scenario_definition_id = 1,
  'ALTER TABLE scenario_condition CHANGE COLUMN scenario_definition_id scenario_id BIGINT NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_new_table = 1 AND @has_pin_id = 0,
  'ALTER TABLE scenario_condition ADD COLUMN pin_id BIGINT NULL AFTER scenario_id',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO pin (
  controller_id, pin, label, unit, multiplier, value_offset, precision_value,
  average_interval_minutes, value_labels, digital_style, invert_digital_logic,
  desired_digital_value, desired_digital_updated_at,
  show_on_dashboard, show_on_chart, chart_range_hours, sort_order
)
SELECT c.id, 'CURRENT_TIME', 'Текущее время', 's', 1, 0, 0,
       1, JSON_OBJECT(), 'parameter', 0,
       NULL, NULL,
       0, 0, 1, -10
FROM controller c
LEFT JOIN pin p
  ON p.controller_id = c.id
 AND p.pin = 'CURRENT_TIME'
WHERE p.id IS NULL;

SELECT COUNT(*) INTO @has_source_pin
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND column_name = 'source_pin';

SET @sql = IF(@has_new_table = 1 AND @has_source_pin = 1,
  'UPDATE scenario_condition sc
   INNER JOIN scenario d ON d.id = sc.scenario_id
   INNER JOIN pin p_target ON p_target.id = d.pin_id
   LEFT JOIN pin p_source
     ON p_source.controller_id = p_target.controller_id
    AND p_source.pin = sc.source_pin
   SET sc.pin_id = p_source.id',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_new_table = 1,
  'DELETE FROM scenario_condition WHERE pin_id IS NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_fk_scenarios_definition
FROM information_schema.table_constraints
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND constraint_type = 'FOREIGN KEY'
  AND constraint_name = 'fk_scenarios_definition';

SET @sql = IF(@has_new_table = 1 AND @has_fk_scenarios_definition = 1,
  'ALTER TABLE scenario_condition DROP FOREIGN KEY fk_scenarios_definition',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_idx_definition
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND index_name = 'idx_scenarios_definition';

SET @sql = IF(@has_new_table = 1 AND @has_idx_definition = 1,
  'ALTER TABLE scenario_condition DROP INDEX idx_scenarios_definition',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_idx_pin
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND index_name = 'idx_scenario_condition_pin';

SET @sql = IF(@has_new_table = 1 AND @has_idx_pin = 0,
  'ALTER TABLE scenario_condition ADD INDEX idx_scenario_condition_pin (pin_id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_idx_definition
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND index_name = 'idx_scenarios_definition';

SET @sql = IF(@has_new_table = 1 AND @has_idx_definition = 0,
  'ALTER TABLE scenario_condition ADD INDEX idx_scenarios_definition (scenario_id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_fk_pin
FROM information_schema.table_constraints
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND constraint_type = 'FOREIGN KEY'
  AND constraint_name = 'fk_scenario_condition_pin';

SET @sql = IF(@has_new_table = 1 AND @has_fk_pin = 0,
  'ALTER TABLE scenario_condition ADD CONSTRAINT fk_scenario_condition_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_fk_scenarios_definition
FROM information_schema.table_constraints
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND constraint_type = 'FOREIGN KEY'
  AND constraint_name = 'fk_scenarios_definition';

SET @sql = IF(@has_new_table = 1 AND @has_fk_scenarios_definition = 0,
  'ALTER TABLE scenario_condition ADD CONSTRAINT fk_scenarios_definition FOREIGN KEY (scenario_id) REFERENCES scenario(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_source_pin
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'scenario_condition'
  AND column_name = 'source_pin';

SET @sql = IF(@has_new_table = 1 AND @has_source_pin = 1,
  'ALTER TABLE scenario_condition DROP COLUMN source_pin',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_new_table = 1,
  'ALTER TABLE scenario_condition MODIFY COLUMN pin_id BIGINT NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
