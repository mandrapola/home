SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS controller_data;
DROP VIEW IF EXISTS controller_pin_config;

DROP TABLE IF EXISTS scenario_condition;
DROP TABLE IF EXISTS scenario;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS pin_data;
DROP TABLE IF EXISTS pin;
DROP TABLE IF EXISTS controller_pairings;
DROP TABLE IF EXISTS controller_user;
DROP TABLE IF EXISTS controller;

DROP TABLE IF EXISTS failed_jobs;
DROP TABLE IF EXISTS job_batches;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS cache_locks;
DROP TABLE IF EXISTS cache;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  time_zone VARCHAR(64) NOT NULL DEFAULT 'Europe/Moscow',
  locale VARCHAR(8) NOT NULL DEFAULT 'ru',
  email_verified_at TIMESTAMP NULL,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
  id VARCHAR(255) NOT NULL PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  payload LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  KEY sessions_user_id_index (user_id),
  KEY sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache (
  `key` VARCHAR(255) NOT NULL PRIMARY KEY,
  `value` MEDIUMTEXT NOT NULL,
  expiration INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache_locks (
  `key` VARCHAR(255) NOT NULL PRIMARY KEY,
  owner VARCHAR(255) NOT NULL,
  expiration INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(255) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY jobs_queue_index (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_batches (
  id VARCHAR(255) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  total_jobs INT NOT NULL,
  pending_jobs INT NOT NULL,
  failed_jobs INT NOT NULL,
  failed_job_ids LONGTEXT NOT NULL,
  options MEDIUMTEXT NULL,
  cancelled_at INT NULL,
  created_at INT NOT NULL,
  finished_at INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid VARCHAR(255) NOT NULL,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload LONGTEXT NOT NULL,
  exception LONGTEXT NOT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY failed_jobs_uuid_unique (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE controller (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  discription TEXT NULL,
  send_interval_seconds INT NOT NULL DEFAULT 30,
  status VARCHAR(16) NOT NULL DEFAULT 'unclaimed',
  last_seen_at TIMESTAMP NULL,
  claimed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE controller_user (
  id CHAR(36) NOT NULL PRIMARY KEY,
  controller_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(16) NOT NULL DEFAULT 'owner',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uk_controller_user (controller_id, user_id),
  KEY idx_controller_user_role (user_id, role),
  CONSTRAINT fk_controller_user_controller FOREIGN KEY (controller_id) REFERENCES controller(id) ON DELETE CASCADE,
  CONSTRAINT fk_controller_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE controller_pairings (
  id CHAR(36) NOT NULL PRIMARY KEY,
  controller_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(4) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  expires_at TIMESTAMP NOT NULL,
  displayed_at TIMESTAMP NULL,
  claimed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY idx_pairing_active (controller_id, status, expires_at),
  KEY idx_pairing_user_status (user_id, status),
  CONSTRAINT fk_controller_pairings_controller FOREIGN KEY (controller_id) REFERENCES controller(id) ON DELETE CASCADE,
  CONSTRAINT fk_controller_pairings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pin (
  id CHAR(36) NOT NULL PRIMARY KEY,
  controller_id CHAR(36) NOT NULL,
  pin VARCHAR(64) NOT NULL,
  label VARCHAR(255) NOT NULL,
  unit VARCHAR(32) NULL,
  digital_style VARCHAR(32) NOT NULL DEFAULT 'sensor',
  value DOUBLE NULL,
  value_updated_at TIMESTAMP NULL,
  desired_digital_value TINYINT NULL,
  desired_digital_updated_at TIMESTAMP NULL,
  show_on_chart TINYINT(1) NOT NULL DEFAULT 0,
  show_on_report TINYINT(1) NOT NULL DEFAULT 1,
  is_monitored TINYINT(1) NOT NULL DEFAULT 0,
  chart_range_hours INT NOT NULL DEFAULT 1,
  enable_scenario TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uk_controller_pin (controller_id, pin),
  KEY idx_pin_config_controller_pin (controller_id, pin),
  CONSTRAINT fk_pin_controller FOREIGN KEY (controller_id) REFERENCES controller(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pin_data (
  id CHAR(36) NOT NULL PRIMARY KEY,
  pin_id CHAR(36) NOT NULL,
  value DOUBLE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pin_data_pin_created (pin_id, created_at),
  KEY idx_pin_data_created (created_at),
  CONSTRAINT fk_pin_data_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
  id CHAR(36) NOT NULL PRIMARY KEY,
  time_zone VARCHAR(64) NOT NULL DEFAULT 'Europe/Moscow'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scenario (
  id CHAR(36) NOT NULL PRIMARY KEY,
  pin_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_scenario_definition_pin_name (pin_id, name),
  CONSTRAINT fk_scenario_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scenario_condition (
  id CHAR(36) NOT NULL PRIMARY KEY,
  scenario_id CHAR(36) NOT NULL,
  pin_id CHAR(36) NOT NULL,
  operator VARCHAR(8) NOT NULL DEFAULT 'gt',
  threshold DOUBLE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_scenarios_definition (scenario_id),
  KEY idx_scenario_condition_pin (pin_id),
  CONSTRAINT fk_scenario_condition_pin FOREIGN KEY (pin_id) REFERENCES pin(id) ON DELETE CASCADE,
  CONSTRAINT fk_scenario_condition_scenario FOREIGN KEY (scenario_id) REFERENCES scenario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW controller_pin_config AS
SELECT p.id,
       c.id AS controller_id,
       p.pin,
       p.label,
       p.unit,
       p.digital_style,
       p.value,
       p.value_updated_at,
       p.desired_digital_value,
       p.desired_digital_updated_at,
       p.show_on_chart,
       p.show_on_report,
       p.is_monitored,
       p.chart_range_hours,
       p.enable_scenario
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
