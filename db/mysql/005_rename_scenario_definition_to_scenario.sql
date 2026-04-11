SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @has_old := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'scenario_definition'
);

SET @has_new := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'scenario'
);

SET @rename_sql := IF(@has_old > 0 AND @has_new = 0, 'RENAME TABLE scenario_definition TO scenario', 'SELECT 1');
PREPARE stmt FROM @rename_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
