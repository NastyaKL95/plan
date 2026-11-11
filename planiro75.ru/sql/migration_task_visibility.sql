-- Видимость задач: на кафедру и на институт.
-- Выполнить один раз на существующей БД.

ALTER TABLE tasks
  ADD COLUMN visible_department TINYINT(1) NOT NULL DEFAULT 0 AFTER assignee_id;

ALTER TABLE tasks
  ADD COLUMN visible_institute TINYINT(1) NOT NULL DEFAULT 0 AFTER visible_department;
