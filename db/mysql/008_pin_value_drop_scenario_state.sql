SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SELECT COUNT(*) INTO @has_value_col
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'pin'
  AND column_name = 'value';

SET @sql = IF(@has_value_col = 0,
  'ALTER TABLE pin ADD COLUMN value DOUBLE NULL AFTER invert_digital_logic',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_value_updated_col
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'pin'
  AND column_name = 'value_updated_at';

SET @sql = IF(@has_value_updated_col = 0,
  'ALTER TABLE pin ADD COLUMN value_updated_at TIMESTAMP NULL AFTER value',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE pin p
INNER JOIN (
  SELECT t.pin_id, t.value, t.created_at
  FROM pin_data t
  INNER JOIN (
    SELECT pin_id, MAX(id) AS max_id
    FROM pin_data
    GROUP BY pin_id
  ) latest ON latest.max_id = t.id
) latest_data ON latest_data.pin_id = p.id
SET p.value = latest_data.value,
    p.value_updated_at = latest_data.created_at;

SELECT COUNT(*) INTO @has_state_table
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'controller_scenario_state';

SET @sql = IF(@has_state_table = 1, 'DROP TABLE controller_scenario_state', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
