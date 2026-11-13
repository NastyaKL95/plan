-- Миграция к схеме «институт + подразделения». Выполните в phpMyAdmin / клиенте MySQL по порядку.
-- Если колонка уже есть, строка ALTER выдаст ошибку 1060 — пропустите её.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_dept_org (organization_id),
  CONSTRAINT fk_dept_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Подразделение по умолчанию', 1 FROM organizations o
WHERE NOT EXISTS (SELECT 1 FROM departments d WHERE d.organization_id = o.id LIMIT 1);

CREATE TABLE IF NOT EXISTS department_heads (
  user_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, department_id),
  KEY idx_dh_dept (department_id),
  CONSTRAINT fk_dh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_dh_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_departments (
  user_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  role ENUM('member','head') NOT NULL DEFAULT 'member' COMMENT 'Роль сотрудника именно в этом подразделении',
  PRIMARY KEY (user_id, department_id),
  KEY idx_ud_department (department_id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ud_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Пользователи: колонка подразделения (исправляет Unknown column 'u.department_id')
ALTER TABLE users ADD COLUMN department_id INT UNSIGNED NULL AFTER role;
-- OAuth-авторизация: колонки для внешнего провайдера (например, Яндекс)
ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(32) NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN oauth_subject VARCHAR(191) NULL AFTER oauth_provider;

ALTER TABLE projects ADD COLUMN department_id INT UNSIGNED NULL AFTER organization_id;

UPDATE projects p
SET p.department_id = (
  SELECT MIN(d.id) FROM departments d WHERE d.organization_id = p.organization_id
)
WHERE p.department_id IS NULL;

ALTER TABLE projects MODIFY department_id INT UNSIGNED NOT NULL;

ALTER TABLE projects ADD KEY idx_projects_dept (department_id);

ALTER TABLE projects
  ADD CONSTRAINT fk_projects_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE;

ALTER TABLE users ADD KEY idx_users_dept (department_id);
ALTER TABLE users ADD UNIQUE KEY uq_users_oauth (oauth_provider, oauth_subject);

ALTER TABLE users
  ADD CONSTRAINT fk_users_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- Таблица user_departments могла существовать без столбца role
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

INSERT IGNORE INTO user_departments (user_id, department_id, role)
SELECT u.id, u.department_id,
       CASE WHEN u.role = 'head' THEN 'head' ELSE 'member' END
FROM users u
INNER JOIN departments d ON d.id = u.department_id
WHERE u.department_id IS NOT NULL;

UPDATE organizations SET name = 'Институт развития образования Забайкальского края' WHERE id > 0;

CREATE TABLE IF NOT EXISTS mail_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  to_email VARCHAR(255) NOT NULL,
  subject VARCHAR(500) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  meta_json JSON NULL,
  status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_mq_status_next (status, next_attempt_at, id),
  KEY idx_mq_org_status (organization_id, status, id),
  CONSTRAINT fk_mq_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
