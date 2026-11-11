-- Расширение схемы: вложения, теги, связи задач, шаблоны чек-листов,
-- напоминание / повтор задачи, примечание при переходе на проверку.
-- Выполнить один раз на существующей БД (при ошибке «Duplicate column» — колонка уже есть).

ALTER TABLE tasks ADD COLUMN remind_at DATE NULL COMMENT 'Напоминание по сроку' AFTER due_date;
ALTER TABLE tasks ADD COLUMN recurrence ENUM('none','weekly','monthly') NOT NULL DEFAULT 'none' AFTER remind_at;
ALTER TABLE tasks ADD COLUMN transition_note VARCHAR(500) NULL COMMENT 'Комментарий при смене статуса (год работы организации)' AFTER recurrence;

ALTER TABLE subtasks ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_done;

CREATE TABLE IF NOT EXISTS tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#64748b',
  UNIQUE KEY uq_tag_org_name (organization_id, name),
  KEY idx_tags_org (organization_id),
  CONSTRAINT fk_tags_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_tag_map (
  task_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (task_id, tag_id),
  KEY idx_ttm_tag (tag_id),
  CONSTRAINT fk_ttm_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_ttm_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  linked_task_id INT UNSIGNED NOT NULL,
  link_type ENUM('blocks','duplicates','relates') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tl_task (task_id),
  KEY idx_tl_linked (linked_task_id),
  UNIQUE KEY uq_tl (task_id, linked_task_id, link_type),
  CONSTRAINT fk_tl_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_tl_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_tl_linked FOREIGN KEY (linked_task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checklist_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  KEY idx_ct_org (organization_id),
  CONSTRAINT fk_ct_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checklist_template_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  title VARCHAR(500) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_cti_tpl (template_id),
  CONSTRAINT fk_cti_tpl FOREIGN KEY (template_id) REFERENCES checklist_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  comment_id BIGINT UNSIGNED NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(64) NOT NULL,
  mime VARCHAR(120) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ta_task (task_id),
  KEY idx_ta_comment (comment_id),
  CONSTRAINT fk_ta_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_comment FOREIGN KEY (comment_id) REFERENCES task_comments(id) ON DELETE SET NULL,
  CONSTRAINT fk_ta_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
