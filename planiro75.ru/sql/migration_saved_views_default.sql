-- Представление по умолчанию (одно на пользователя в организации).
ALTER TABLE task_saved_views
  ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER filters_json;
