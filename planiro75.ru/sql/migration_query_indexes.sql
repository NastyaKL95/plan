-- Оптимизация частых выборок задач/чек-листов/комментариев.
-- Безопасно для повторного запуска: индексы добавляются только при отсутствии.

SET @db_name := DATABASE();

-- tasks: быстрые счетчики "создано сегодня" и сортировка в списках.
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'tasks' AND index_name = 'idx_tasks_org_created'
);
SET @sql := IF(
  @has_idx = 0,
  'ALTER TABLE tasks ADD INDEX idx_tasks_org_created (organization_id, created_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'tasks' AND index_name = 'idx_tasks_org_updated'
);
SET @sql := IF(
  @has_idx = 0,
  'ALTER TABLE tasks ADD INDEX idx_tasks_org_updated (organization_id, updated_at, id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tasks: выборки "дедлайны у исполнителя" и фильтры по статусам.
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'tasks' AND index_name = 'idx_tasks_org_assignee_status_due'
);
SET @sql := IF(
  @has_idx = 0,
  'ALTER TABLE tasks ADD INDEX idx_tasks_org_assignee_status_due (organization_id, assignee_id, status, due_date)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- subtasks: стабильный порядок в чек-листе.
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'subtasks' AND index_name = 'idx_subtasks_task_sort'
);
SET @sql := IF(
  @has_idx = 0,
  'ALTER TABLE subtasks ADD INDEX idx_subtasks_task_sort (task_id, sort_order, id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- task_comments: чтение ленты комментариев по задаче.
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @db_name AND table_name = 'task_comments' AND index_name = 'idx_tc_task_id'
);
SET @sql := IF(
  @has_idx = 0,
  'ALTER TABLE task_comments ADD INDEX idx_tc_task_id (task_id, id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

