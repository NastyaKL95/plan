-- Расширенный поиск: сохранённые представления; прочтение комментариев в задаче.
-- Выполнить один раз на существующей БД.

CREATE TABLE IF NOT EXISTS task_saved_views (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  scope ENUM('personal','team') NOT NULL DEFAULT 'personal',
  department_id INT UNSIGNED NULL COMMENT 'Для team: виден коллегам из отдела; NULL — вся организация (только для руководителей — проверка в приложении)',
  filters_json TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tsv_org_user (organization_id, user_id),
  KEY idx_tsv_org_scope (organization_id, scope),
  CONSTRAINT fk_tsv_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_tsv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tsv_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_comment_reads (
  user_id INT UNSIGNED NOT NULL,
  comment_id BIGINT UNSIGNED NOT NULL,
  read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, comment_id),
  KEY idx_tcr_comment (comment_id),
  CONSTRAINT fk_tcr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tcr_comment FOREIGN KEY (comment_id) REFERENCES task_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
