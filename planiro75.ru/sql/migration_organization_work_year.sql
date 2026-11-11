-- Год работы организации (календарный / учебный год для планирования и подписей).
-- Если колонка уже есть (1060), пропустите файл.
-- Далее для привязки задач и проектов к году плана выполните: migration_tasks_projects_work_year.sql

SET NAMES utf8mb4;

ALTER TABLE organizations
  ADD COLUMN work_year SMALLINT UNSIGNED NOT NULL DEFAULT 2026
    COMMENT 'Год работы организации'
    AFTER name;

-- При необходимости: UPDATE organizations SET work_year = YEAR(CURDATE()) WHERE id = …;
