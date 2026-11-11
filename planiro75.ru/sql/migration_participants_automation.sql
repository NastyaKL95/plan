-- Участники задачи (соисполнители / наблюдатели) и правила автоматизации при смене статуса.
-- Выполнить один раз на существующей БД.

CREATE TABLE IF NOT EXISTS task_participants (
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('assistant','observer') NOT NULL,
  PRIMARY KEY (task_id, user_id),
  KEY idx_tp_user (user_id),
  CONSTRAINT fk_tp_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_tp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_automation_rules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NULL COMMENT 'NULL — все проекты организации',
  trigger_status ENUM('new','in_progress','review','done','cancelled') NOT NULL,
  action_assignee_id INT UNSIGNED NULL,
  action_comment TEXT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_tar_org_status (organization_id, trigger_status),
  KEY idx_tar_proj (project_id),
  CONSTRAINT fk_tar_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_tar_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
