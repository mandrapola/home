SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

ALTER TABLE controller ADD COLUMN pk_uuid CHAR(36) NULL FIRST;
UPDATE controller
SET pk_uuid = LOWER(CONCAT(
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 1, 8), '-',
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 9, 4), '-',
  '7', LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  ELT(FLOOR(RAND() * 4) + 1, '8', '9', 'a', 'b'), LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  LPAD(HEX(FLOOR(RAND() * 281474976710656)), 12, '0')
))
WHERE pk_uuid IS NULL;
ALTER TABLE controller
  ADD UNIQUE KEY uk_controller_id (id),
  DROP PRIMARY KEY,
  MODIFY COLUMN pk_uuid CHAR(36) NOT NULL,
  ADD PRIMARY KEY (pk_uuid);

ALTER TABLE pin ADD COLUMN pk_uuid CHAR(36) NULL FIRST;
UPDATE pin
SET pk_uuid = LOWER(CONCAT(
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 1, 8), '-',
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 9, 4), '-',
  '7', LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  ELT(FLOOR(RAND() * 4) + 1, '8', '9', 'a', 'b'), LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  LPAD(HEX(FLOOR(RAND() * 281474976710656)), 12, '0')
))
WHERE pk_uuid IS NULL;
ALTER TABLE pin
  ADD UNIQUE KEY uk_pin_id (id),
  DROP PRIMARY KEY,
  MODIFY COLUMN pk_uuid CHAR(36) NOT NULL,
  ADD PRIMARY KEY (pk_uuid);

ALTER TABLE pin_data ADD COLUMN pk_uuid CHAR(36) NULL FIRST;
UPDATE pin_data
SET pk_uuid = LOWER(CONCAT(
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 1, 8), '-',
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 9, 4), '-',
  '7', LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  ELT(FLOOR(RAND() * 4) + 1, '8', '9', 'a', 'b'), LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  LPAD(HEX(FLOOR(RAND() * 281474976710656)), 12, '0')
))
WHERE pk_uuid IS NULL;
ALTER TABLE pin_data
  ADD UNIQUE KEY uk_pin_data_id (id),
  DROP PRIMARY KEY,
  MODIFY COLUMN pk_uuid CHAR(36) NOT NULL,
  ADD PRIMARY KEY (pk_uuid);

ALTER TABLE system_settings ADD COLUMN pk_uuid CHAR(36) NULL FIRST;
UPDATE system_settings
SET pk_uuid = LOWER(CONCAT(
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 1, 8), '-',
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 9, 4), '-',
  '7', LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  ELT(FLOOR(RAND() * 4) + 1, '8', '9', 'a', 'b'), LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  LPAD(HEX(FLOOR(RAND() * 281474976710656)), 12, '0')
))
WHERE pk_uuid IS NULL;
ALTER TABLE system_settings
  ADD UNIQUE KEY uk_system_settings_id (id),
  DROP PRIMARY KEY,
  MODIFY COLUMN pk_uuid CHAR(36) NOT NULL,
  ADD PRIMARY KEY (pk_uuid);

ALTER TABLE scenario ADD COLUMN pk_uuid CHAR(36) NULL FIRST;
UPDATE scenario
SET pk_uuid = LOWER(CONCAT(
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 1, 8), '-',
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 9, 4), '-',
  '7', LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  ELT(FLOOR(RAND() * 4) + 1, '8', '9', 'a', 'b'), LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  LPAD(HEX(FLOOR(RAND() * 281474976710656)), 12, '0')
))
WHERE pk_uuid IS NULL;
ALTER TABLE scenario
  ADD UNIQUE KEY uk_scenario_id (id),
  DROP PRIMARY KEY,
  MODIFY COLUMN pk_uuid CHAR(36) NOT NULL,
  ADD PRIMARY KEY (pk_uuid);

ALTER TABLE scenario_condition ADD COLUMN pk_uuid CHAR(36) NULL FIRST;
UPDATE scenario_condition
SET pk_uuid = LOWER(CONCAT(
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 1, 8), '-',
  SUBSTR(LPAD(HEX(FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000)), 12, '0'), 9, 4), '-',
  '7', LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  ELT(FLOOR(RAND() * 4) + 1, '8', '9', 'a', 'b'), LPAD(HEX(FLOOR(RAND() * 4096)), 3, '0'), '-',
  LPAD(HEX(FLOOR(RAND() * 281474976710656)), 12, '0')
))
WHERE pk_uuid IS NULL;
ALTER TABLE scenario_condition
  ADD UNIQUE KEY uk_scenario_condition_id (id),
  DROP PRIMARY KEY,
  MODIFY COLUMN pk_uuid CHAR(36) NOT NULL,
  ADD PRIMARY KEY (pk_uuid);
