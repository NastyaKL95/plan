SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS user_max_prefs (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_created TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_assigned TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_status_changed TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_due_changed TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_comment_mention TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_participant_added TINYINT(1) NOT NULL DEFAULT 1,
  notify_direct_message TINYINT(1) NOT NULL DEFAULT 1,
  notify_sla_due_2h TINYINT(1) NOT NULL DEFAULT 1,
  notify_sla_overdue_24h TINYINT(1) NOT NULL DEFAULT 1,
  notify_sla_stale_days TINYINT(1) NOT NULL DEFAULT 1,
  digest_enabled TINYINT(1) NOT NULL DEFAULT 0,
  digest_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  quiet_mode_enabled TINYINT(1) NOT NULL DEFAULT 0,
  quiet_start CHAR(5) NOT NULL DEFAULT '20:00',
  quiet_end CHAR(5) NOT NULL DEFAULT '08:00',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ump_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ump_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
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
