-- Индивидуальный план/отчёт преподавателя: виды работы в задаче и карточка сотрудника.
-- Повторный запуск безопасен: колонка добавляется только если её ещё нет.

SET @db := DATABASE();

-- tasks
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_edu') = 0,
  'ALTER TABLE tasks ADD COLUMN work_edu TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Учебная работа''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_edu_method') = 0,
  'ALTER TABLE tasks ADD COLUMN work_edu_method TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Учебно-методическая работа''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_research') = 0,
  'ALTER TABLE tasks ADD COLUMN work_research TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Научно-исследовательская работа''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_org_method') = 0,
  'ALTER TABLE tasks ADD COLUMN work_org_method TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Организационно-методическая работа''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_start') = 0,
  'ALTER TABLE tasks ADD COLUMN work_start DATE NULL COMMENT ''Дата начала выполнения для отчёта''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_hours') = 0,
  'ALTER TABLE tasks ADD COLUMN work_hours DECIMAL(8,2) NULL COMMENT ''Объём часов; полугодия считаются по сроку''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_period') = 0,
  'ALTER TABLE tasks ADD COLUMN work_period VARCHAR(160) NULL COMMENT ''Сроки выполнения для отчёта''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_indicator') = 0,
  'ALTER TABLE tasks ADD COLUMN work_indicator VARCHAR(255) NULL COMMENT ''Показатель выполнения''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_hours_first') = 0,
  'ALTER TABLE tasks ADD COLUMN work_hours_first DECIMAL(8,2) NULL COMMENT ''Часы за 1 полугодие''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'work_hours_second') = 0,
  'ALTER TABLE tasks ADD COLUMN work_hours_second DECIMAL(8,2) NULL COMMENT ''Часы за 2 полугодие''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'job_title') = 0,
  'ALTER TABLE users ADD COLUMN job_title VARCHAR(160) NULL COMMENT ''Должность для индивидуального отчёта''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'academic_degree') = 0,
  'ALTER TABLE users ADD COLUMN academic_degree VARCHAR(120) NULL COMMENT ''Учёная степень''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'academic_rank') = 0,
  'ALTER TABLE users ADD COLUMN academic_rank VARCHAR(120) NULL COMMENT ''Учёное звание''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'plan_hours_edu') = 0,
  'ALTER TABLE users ADD COLUMN plan_hours_edu DECIMAL(8,2) NULL COMMENT ''План часов: учебная работа''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'plan_hours_edu_method') = 0,
  'ALTER TABLE users ADD COLUMN plan_hours_edu_method DECIMAL(8,2) NULL COMMENT ''План часов: учебно-методическая работа''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'plan_hours_research') = 0,
  'ALTER TABLE users ADD COLUMN plan_hours_research DECIMAL(8,2) NULL COMMENT ''План часов: научно-исследовательская работа''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'plan_hours_org_method') = 0,
  'ALTER TABLE users ADD COLUMN plan_hours_org_method DECIMAL(8,2) NULL COMMENT ''План часов: организационно-методическая работа''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
