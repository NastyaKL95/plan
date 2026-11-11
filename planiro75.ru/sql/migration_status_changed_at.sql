-- Дата входа в текущий статус (для отчётов «пробуксовка» и аналитики).
-- Выполнить один раз.

ALTER TABLE tasks
  ADD COLUMN status_changed_at DATETIME NULL DEFAULT NULL COMMENT 'Когда задача перешла в текущий status' AFTER updated_at;

UPDATE tasks SET status_changed_at = COALESCE(updated_at, created_at) WHERE status_changed_at IS NULL;
