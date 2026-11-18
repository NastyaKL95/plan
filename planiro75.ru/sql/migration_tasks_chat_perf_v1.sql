-- Список задач: индексы под фильтры; чат: упоминания в комментариях.
-- Безопасно для повторного запуска.

SET @db_name := DATABASE();

-- Упоминания пользователей в комментарии (формат текста: @[user:ID]).
CREATE TABLE IF NOT EXISTS task_comment_mentions (
  comment_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (comment_id, user_id),
  KEY idx_tcm_user (user_id),
  CONSTRAINT fk_tcm_comment FOREIGN KEY (comment_id) REFERENCES task_comments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tcm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tasks: фильтр «я автор» и срезы отчётов по статусу + сортировка.
SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'tasks' AND index_name = 'idx_tasks_org_author'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE tasks ADD INDEX idx_tasks_org_author (organization_id, author_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'tasks' AND index_name = 'idx_tasks_org_status_updated_id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE tasks ADD INDEX idx_tasks_org_status_updated_id (organization_id, status, updated_at, id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- projects: фильтр списка задач по подразделению (drill-down отчёта).
SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'projects' AND index_name = 'idx_projects_org_dept'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE projects ADD INDEX idx_projects_org_dept (organization_id, department_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- task_participants: ускорение EXISTS (user_id, task_id) — составной в дополнение к PK (task_id, user_id).
SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'task_participants' AND index_name = 'idx_tp_user_task'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE task_participants ADD INDEX idx_tp_user_task (user_id, task_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
