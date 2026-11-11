SET NAMES utf8mb4;

SET @db_name := DATABASE();
SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'max_chat_id'
);

SET @sql := IF(
  @has_col = 0,
  "ALTER TABLE users ADD COLUMN max_chat_id VARCHAR(32) NULL COMMENT 'Персональный chat_id в MAX для уведомлений от бота' AFTER name",
  "SELECT 'users.max_chat_id already exists' AS info"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
