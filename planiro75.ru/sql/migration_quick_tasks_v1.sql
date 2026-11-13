-- Быстрые задачи / личный бэклог + политика видимости для руководителя
SET NAMES utf8mb4;

ALTER TABLE tasks
  ADD COLUMN is_quick_task TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Быстрая задача',
  ADD COLUMN quick_visible_managers TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 — видно руководителю отдела в отчётах';

ALTER TABLE tasks
  ADD INDEX idx_tasks_org_quick_created (organization_id, is_quick_task, created_at);

ALTER TABLE departments
  ADD COLUMN quick_tasks_managers_visible_default TINYINT(1) NOT NULL DEFAULT 1
  COMMENT 'Значение по умолчанию для «видно руководителю» при создании быстрой задачи в проекте отдела';
