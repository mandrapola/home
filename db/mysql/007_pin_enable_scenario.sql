SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SELECT COUNT(*) INTO @has_enable_scenario
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'pin'
  AND column_name = 'enable_scenario';

SET @sql = IF(@has_enable_scenario = 0,
  'ALTER TABLE pin ADD COLUMN enable_scenario TINYINT(1) NOT NULL DEFAULT 1 AFTER chart_range_hours',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_groups
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'controller_scenario_groups';

SET @sql = IF(@has_groups = 1,
  'UPDATE pin p
   INNER JOIN controller_scenario_groups sg
     ON sg.controller_id = p.controller_id
    AND sg.target_pin = p.pin
   SET p.enable_scenario = sg.enabled',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_groups = 1,
  'DROP TABLE controller_scenario_groups',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
