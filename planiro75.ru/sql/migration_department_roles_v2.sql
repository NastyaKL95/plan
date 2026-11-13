-- Роли в подразделении: user / manager / administrator (вместо member / head)
SET NAMES utf8mb4;

UPDATE user_departments SET role = 'user' WHERE role = 'member';
UPDATE user_departments SET role = 'manager' WHERE role = 'head';

ALTER TABLE user_departments
  MODIFY COLUMN role ENUM('user','manager','administrator') NOT NULL DEFAULT 'user'
  COMMENT 'Роль в подразделении: пользователь, менеджер, администратор';

-- Глобальная роль «руководитель» больше не используется — права переносятся в подразделения
UPDATE users SET role = 'member' WHERE role = 'head';
