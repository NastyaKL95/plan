-- Связь пользователь <-> несколько подразделений
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS user_departments (
  user_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  role ENUM('member','head') NOT NULL DEFAULT 'member' COMMENT 'Роль сотрудника именно в этом подразделении',
  PRIMARY KEY (user_id, department_id),
  KEY idx_ud_department (department_id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ud_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Если таблица уже была без столбца role, добавляем его (CREATE IF NOT EXISTS схему не обновляет)
DROP PROCEDURE IF EXISTS planner_ensure_ud_role;
DELIMITER $$
CREATE PROCEDURE planner_ensure_ud_role()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_departments'
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_departments' AND COLUMN_NAME = 'role'
  ) THEN
    ALTER TABLE `user_departments`
      ADD COLUMN `role` ENUM('member','head') NOT NULL DEFAULT 'member'
      COMMENT 'Роль сотрудника именно в этом подразделении'
      AFTER `department_id`;
  END IF;
END$$
DELIMITER ;
CALL planner_ensure_ud_role();
DROP PROCEDURE IF EXISTS planner_ensure_ud_role;

-- Первичное заполнение из старого users.department_id
INSERT IGNORE INTO user_departments (user_id, department_id, role)
SELECT u.id, u.department_id,
       CASE WHEN u.role = 'head' THEN 'head' ELSE 'member' END
FROM users u
INNER JOIN departments d ON d.id = u.department_id
WHERE u.department_id IS NOT NULL;
