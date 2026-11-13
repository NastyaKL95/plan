SET NAMES utf8mb4;

-- Блокировка входа без удаления пользователя и его задач.
ALTER TABLE users
  MODIFY COLUMN account_status ENUM('pending_email','pending_approval','active','blocked') NOT NULL DEFAULT 'active'
    COMMENT 'pending_email — ждёт письмо; pending_approval — ждёт админа; active — доступ; blocked — вход запрещён';
