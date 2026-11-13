-- Индивидуальный план/отчёт: вид работы и часы у пунктов чек-листа.
-- Если у пунктов задачи заданы вид и часы, в план/отчёт идут пункты, а не задача целиком.
-- Повторный запуск безопасен: колонка добавляется только если её ещё нет.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'subtasks' AND COLUMN_NAME = 'work_kind') = 0,
  'ALTER TABLE subtasks ADD COLUMN work_kind VARCHAR(32) NULL COMMENT ''Вид работы для плана/отчёта: work_edu, work_edu_method, work_research, work_org_method''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'subtasks' AND COLUMN_NAME = 'work_hours') = 0,
  'ALTER TABLE subtasks ADD COLUMN work_hours DECIMAL(8,2) NULL COMMENT ''Часы пункта; если заданы, пункт идёт в план/отчёт вместо задачи''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'subtasks' AND COLUMN_NAME = 'work_indicator') = 0,
  'ALTER TABLE subtasks ADD COLUMN work_indicator VARCHAR(255) NULL COMMENT ''Показатель выполнения пункта''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'subtasks' AND COLUMN_NAME = 'work_start') = 0,
  'ALTER TABLE subtasks ADD COLUMN work_start DATE NULL COMMENT ''Начало выполнения пункта''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'subtasks' AND COLUMN_NAME = 'work_end') = 0,
  'ALTER TABLE subtasks ADD COLUMN work_end DATE NULL COMMENT ''Окончание выполнения пункта''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
