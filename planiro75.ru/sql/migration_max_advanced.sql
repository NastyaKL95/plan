SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notification_events_dlq (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  error_text TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ned_org_created (organization_id, created_at),
  CONSTRAINT fk_ned_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ned_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS max_digest_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  task_id INT UNSIGNED NULL,
  text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mdq_org_user (organization_id, user_id, created_at),
  CONSTRAINT fk_mdq_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mdq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS max_action_tokens (
  token VARCHAR(64) NOT NULL PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  action VARCHAR(32) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mat_org_user (organization_id, user_id, created_at),
  CONSTRAINT fk_mat_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_mat_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS max_settings (
  organization_id INT UNSIGNED NOT NULL PRIMARY KEY,
  token VARCHAR(255) NOT NULL DEFAULT '',
  fallback_chat_id VARCHAR(32) NOT NULL DEFAULT '',
  timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  quiet_hours_enabled TINYINT(1) NOT NULL DEFAULT 0,
  quiet_start CHAR(5) NOT NULL DEFAULT '20:00',
  quiet_end CHAR(5) NOT NULL DEFAULT '08:00',
  stale_days SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  webhook_secret VARCHAR(128) NOT NULL DEFAULT '',
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ms_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db_name := DATABASE();

SET @has_notify_sla_due_2h := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'user_max_prefs'
    AND COLUMN_NAME = 'notify_sla_due_2h'
);
SET @sql := IF(
  @has_notify_sla_due_2h = 0,
  "ALTER TABLE user_max_prefs ADD COLUMN notify_sla_due_2h TINYINT(1) NOT NULL DEFAULT 1",
  "SELECT 'user_max_prefs.notify_sla_due_2h exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_notify_sla_overdue_24h := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'user_max_prefs'
    AND COLUMN_NAME = 'notify_sla_overdue_24h'
);
SET @sql := IF(
  @has_notify_sla_overdue_24h = 0,
  "ALTER TABLE user_max_prefs ADD COLUMN notify_sla_overdue_24h TINYINT(1) NOT NULL DEFAULT 1",
  "SELECT 'user_max_prefs.notify_sla_overdue_24h exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_digest_enabled := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'user_max_prefs' AND COLUMN_NAME = 'digest_enabled'
);
SET @sql := IF(
  @has_digest_enabled = 0,
  "ALTER TABLE user_max_prefs ADD COLUMN digest_enabled TINYINT(1) NOT NULL DEFAULT 0",
  "SELECT 'user_max_prefs.digest_enabled exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_digest_interval_minutes := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'user_max_prefs' AND COLUMN_NAME = 'digest_interval_minutes'
);
SET @sql := IF(
  @has_digest_interval_minutes = 0,
  "ALTER TABLE user_max_prefs ADD COLUMN digest_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60",
  "SELECT 'user_max_prefs.digest_interval_minutes exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_quiet_mode_enabled := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'user_max_prefs' AND COLUMN_NAME = 'quiet_mode_enabled'
);
SET @sql := IF(
  @has_quiet_mode_enabled = 0,
  "ALTER TABLE user_max_prefs ADD COLUMN quiet_mode_enabled TINYINT(1) NOT NULL DEFAULT 0",
  "SELECT 'user_max_prefs.quiet_mode_enabled exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_quiet_start := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'user_max_prefs' AND COLUMN_NAME = 'quiet_start'
);
SET @sql := IF(
  @has_quiet_start = 0,
  "ALTER TABLE user_max_prefs ADD COLUMN quiet_start CHAR(5) NOT NULL DEFAULT '20:00'",
  "SELECT 'user_max_prefs.quiet_start exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_quiet_end := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'user_max_prefs' AND COLUMN_NAME = 'quiet_end'
);
SET @sql := IF(
  @has_quiet_end = 0,
  "ALTER TABLE user_max_prefs ADD COLUMN quiet_end CHAR(5) NOT NULL DEFAULT '08:00'",
  "SELECT 'user_max_prefs.quiet_end exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_ms_webhook_secret := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'max_settings' AND COLUMN_NAME = 'webhook_secret'
);
SET @sql := IF(
  @has_ms_webhook_secret = 0,
  "ALTER TABLE max_settings ADD COLUMN webhook_secret VARCHAR(128) NOT NULL DEFAULT ''",
  "SELECT 'max_settings.webhook_secret exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_notify_sla_stale_days := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'user_max_prefs'
    AND COLUMN_NAME = 'notify_sla_stale_days'
);
SET @sql := IF(
  @has_notify_sla_stale_days = 0,
  "ALTER TABLE user_max_prefs ADD COLUMN notify_sla_stale_days TINYINT(1) NOT NULL DEFAULT 1",
  "SELECT 'user_max_prefs.notify_sla_stale_days exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
