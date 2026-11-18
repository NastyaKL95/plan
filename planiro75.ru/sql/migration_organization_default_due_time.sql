-- Время срока по умолчанию (день без времени, импорт Excel): organizations.default_due_time HH:MM

SET @db_name := DATABASE();

SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'organizations'
    AND COLUMN_NAME = 'default_due_time'
);
SET @sql := IF(
  @has_col = 0,
  "ALTER TABLE organizations ADD COLUMN default_due_time CHAR(5) NOT NULL DEFAULT '18:00' COMMENT 'Время срока по умолчанию (HH:MM)' AFTER work_year",
  "SELECT 'organizations.default_due_time exists' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
