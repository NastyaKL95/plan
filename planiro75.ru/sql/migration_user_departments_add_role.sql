-- Быстрое исправление ошибки #1054 «Неизвестный столбец 'role'»:
-- выполните один раз в нужной базе. Если появится «Duplicate column» — столбец уже есть, дальше не нужно.
SET NAMES utf8mb4;

ALTER TABLE `user_departments`
  ADD COLUMN `role` ENUM('member','head') NOT NULL DEFAULT 'member'
  COMMENT 'Роль сотрудника именно в этом подразделении'
  AFTER `department_id`;
