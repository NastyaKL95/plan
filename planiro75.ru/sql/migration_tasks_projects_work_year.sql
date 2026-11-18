-- Задачи и проекты привязаны к «рабочему году» организации (план на год).
-- Желательно сначала: migration_organization_work_year.sql
-- Ошибка 1060 — колонка уже есть, пропустите соответствующий ALTER.

SET NAMES utf8mb4;

ALTER TABLE projects
  ADD COLUMN work_year SMALLINT UNSIGNED NOT NULL DEFAULT 2026
    COMMENT 'Год плана: проект в рамках года организации'
    AFTER organization_id;

ALTER TABLE tasks
  ADD COLUMN work_year SMALLINT UNSIGNED NOT NULL DEFAULT 2026
    COMMENT 'Год плана: задача в рамках года организации'
    AFTER organization_id;

UPDATE projects p
  INNER JOIN organizations o ON o.id = p.organization_id
  SET p.work_year = o.work_year;

UPDATE tasks t
  INNER JOIN organizations o ON o.id = t.organization_id
  SET t.work_year = COALESCE(
    (SELECT p.work_year FROM projects p WHERE p.id = t.project_id LIMIT 1),
    o.work_year
  );

CREATE INDEX idx_projects_org_year ON projects (organization_id, work_year);
CREATE INDEX idx_tasks_org_year ON tasks (organization_id, work_year);
