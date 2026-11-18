-- Срок с временем (DATETIME), шаблоны задач.
-- Выполнить один раз на существующей БД.

ALTER TABLE tasks MODIFY COLUMN due_date DATETIME NULL COMMENT 'Срок (дата и время)';

CREATE TABLE IF NOT EXISTS task_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  title VARCHAR(500) NOT NULL,
  description MEDIUMTEXT NULL,
  status ENUM('new','in_progress','review','done','cancelled') NOT NULL DEFAULT 'new',
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  assignee_id INT UNSIGNED NULL,
  tag_ids_json JSON NULL COMMENT 'Массив id меток',
  subtasks_json JSON NULL COMMENT 'Массив строк подзадач',
  assistant_ids_json JSON NULL,
  observer_ids_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tt_org (organization_id),
  CONSTRAINT fk_tt_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_tt_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_tt_assignee FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
