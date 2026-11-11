-- ИРО Забайкальского края: одна организация, подразделения, проекты и задачи
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS subtasks;
DROP TABLE IF EXISTS task_attachments;
DROP TABLE IF EXISTS task_comments;
DROP TABLE IF EXISTS task_tag_map;
DROP TABLE IF EXISTS task_links;
DROP TABLE IF EXISTS subtask_participants;
DROP TABLE IF EXISTS checklist_template_items;
DROP TABLE IF EXISTS checklist_templates;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS portfolio_projects;
DROP TABLE IF EXISTS project_portfolios;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS department_heads;
DROP TABLE IF EXISTS user_departments;
DROP TABLE IF EXISTS mail_queue;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS organizations;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE organizations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  work_year SMALLINT UNSIGNED NOT NULL DEFAULT 2026 COMMENT 'Год работы организации',
  default_due_time CHAR(5) NOT NULL DEFAULT '18:00' COMMENT 'Время срока по умолчанию (HH:MM)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_dept_org (organization_id),
  CONSTRAINT fk_dept_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  oauth_provider VARCHAR(32) NULL COMMENT 'Провайдер внешней авторизации (например: yandex)',
  oauth_subject VARCHAR(191) NULL COMMENT 'Уникальный ID пользователя у внешнего провайдера',
  name VARCHAR(120) NOT NULL,
  job_title VARCHAR(160) NULL COMMENT 'Должность для индивидуального отчёта',
  academic_degree VARCHAR(120) NULL COMMENT 'Учёная степень',
  academic_rank VARCHAR(120) NULL COMMENT 'Учёное звание',
  plan_hours_edu DECIMAL(8,2) NULL COMMENT 'План часов: учебная работа',
  plan_hours_edu_method DECIMAL(8,2) NULL COMMENT 'План часов: учебно-методическая работа',
  plan_hours_research DECIMAL(8,2) NULL COMMENT 'План часов: научно-исследовательская работа',
  plan_hours_org_method DECIMAL(8,2) NULL COMMENT 'План часов: организационно-методическая работа',
  max_chat_id VARCHAR(32) NULL COMMENT 'Персональный chat_id в MAX для уведомлений от бота',
  role ENUM('rectorate','head','member','admin') NOT NULL DEFAULT 'member',
  department_id INT UNSIGNED NULL COMMENT 'Основное подразделение сотрудника; у ректората может быть NULL',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_oauth (oauth_provider, oauth_subject),
  KEY idx_users_org (organization_id),
  KEY idx_users_dept (department_id),
  CONSTRAINT fk_users_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_users_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE department_heads (
  user_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, department_id),
  KEY idx_dh_dept (department_id),
  CONSTRAINT fk_dh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_dh_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_departments (
  user_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  role ENUM('user','manager','administrator') NOT NULL DEFAULT 'user' COMMENT 'Роль в подразделении: пользователь, менеджер, администратор',
  PRIMARY KEY (user_id, department_id),
  KEY idx_ud_department (department_id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ud_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  work_year SMALLINT UNSIGNED NOT NULL DEFAULT 2026 COMMENT 'Год плана организации',
  department_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  color CHAR(7) NOT NULL DEFAULT '#2563eb',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_projects_org (organization_id),
  KEY idx_projects_org_year (organization_id, work_year),
  KEY idx_projects_dept (department_id),
  CONSTRAINT fk_projects_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_projects_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_portfolios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  parent_portfolio_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  color CHAR(7) NOT NULL DEFAULT '#0ea5e9',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pf_org (organization_id),
  KEY idx_pf_parent (parent_portfolio_id),
  CONSTRAINT fk_pf_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_pf_parent FOREIGN KEY (parent_portfolio_id) REFERENCES project_portfolios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portfolio_projects (
  portfolio_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (portfolio_id, project_id),
  KEY idx_pp_project (project_id),
  CONSTRAINT fk_pp_portfolio FOREIGN KEY (portfolio_id) REFERENCES project_portfolios(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  work_year SMALLINT UNSIGNED NOT NULL DEFAULT 2026 COMMENT 'Год плана организации',
  project_id INT UNSIGNED NULL,
  title VARCHAR(500) NOT NULL,
  description MEDIUMTEXT NULL,
  status ENUM('new','in_progress','review','done','cancelled') NOT NULL DEFAULT 'new',
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  author_id INT UNSIGNED NOT NULL,
  assignee_id INT UNSIGNED NULL,
  visible_department TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Видит вся кафедра проекта',
  visible_institute TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Видит весь институт',
  work_edu TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Учебная работа',
  work_edu_method TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Учебно-методическая работа',
  work_research TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Научно-исследовательская работа',
  work_org_method TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Организационно-методическая работа',
  work_start DATE NULL COMMENT 'Дата начала выполнения для отчёта',
  work_period VARCHAR(160) NULL COMMENT 'Срок выполнения: месяцы от начала до крайнего срока',
  work_indicator VARCHAR(255) NULL COMMENT 'Показатель выполнения',
  work_hours DECIMAL(8,2) NULL COMMENT 'Объём часов; полугодия считаются по сроку',
  work_hours_first DECIMAL(8,2) NULL COMMENT 'Часы за 1 полугодие',
  work_hours_second DECIMAL(8,2) NULL COMMENT 'Часы за 2 полугодие',
  due_date DATE NULL,
  remind_at DATE NULL COMMENT 'Напоминание по сроку (дата)',
  recurrence ENUM('none','weekly','monthly') NOT NULL DEFAULT 'none',
  transition_note VARCHAR(500) NULL COMMENT 'Заметка при последнем смене статуса (год работы организации)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tasks_org_status (organization_id, status),
  KEY idx_tasks_org_year (organization_id, work_year),
  KEY idx_tasks_org_created (organization_id, created_at),
  KEY idx_tasks_org_updated (organization_id, updated_at, id),
  KEY idx_tasks_org_assignee_status_due (organization_id, assignee_id, status, due_date),
  KEY idx_tasks_assignee (assignee_id),
  KEY idx_tasks_project (project_id),
  KEY idx_tasks_remind (organization_id, remind_at),
  CONSTRAINT fk_tasks_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_assignee FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#64748b',
  UNIQUE KEY uq_tag_org_name (organization_id, name),
  KEY idx_tags_org (organization_id),
  CONSTRAINT fk_tags_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_tag_map (
  task_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (task_id, tag_id),
  KEY idx_ttm_tag (tag_id),
  CONSTRAINT fk_ttm_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_ttm_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_links (
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

CREATE TABLE checklist_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  KEY idx_ct_org (organization_id),
  CONSTRAINT fk_ct_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_template_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  title VARCHAR(500) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_cti_tpl (template_id),
  CONSTRAINT fk_cti_tpl FOREIGN KEY (template_id) REFERENCES checklist_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tc_task_created (task_id, created_at),
  KEY idx_tc_task_id (task_id, id),
  CONSTRAINT fk_tc_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_attachments (
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

CREATE TABLE subtasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  title VARCHAR(500) NOT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_subtasks_task (task_id),
  KEY idx_subtasks_task_sort (task_id, sort_order, id),
  CONSTRAINT fk_subtasks_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subtask_participants (
  subtask_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('assistant') NOT NULL DEFAULT 'assistant',
  PRIMARY KEY (subtask_id, user_id),
  KEY idx_spt_user (user_id),
  CONSTRAINT fk_spt_subtask FOREIGN KEY (subtask_id) REFERENCES subtasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_spt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(32) NOT NULL,
  entity_id INT UNSIGNED NULL,
  message VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_org (organization_id, created_at),
  CONSTRAINT fk_activity_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_queue (
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

CREATE TABLE notification_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NULL,
  event_type VARCHAR(64) NOT NULL,
  dedupe_key VARCHAR(190) NOT NULL,
  payload_json JSON NOT NULL,
  status ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_retry_at DATETIME NULL,
  sent_at DATETIME NULL,
  last_error TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ne_dedupe (dedupe_key),
  KEY idx_ne_pending (status, next_retry_at, id),
  KEY idx_ne_org_user (organization_id, user_id, created_at),
  KEY idx_ne_org_status (organization_id, status, id),
  CONSTRAINT fk_ne_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ne_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ne_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_events_dlq (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  error_text TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ned_org_created (organization_id, created_at),
  CONSTRAINT fk_ned_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ned_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE max_digest_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  task_id INT UNSIGNED NULL,
  text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mdq_org_user (organization_id, user_id, created_at),
  CONSTRAINT fk_mdq_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mdq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE max_action_tokens (
  token VARCHAR(64) NOT NULL PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  action VARCHAR(32) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mat_org_user (organization_id, user_id, created_at),
  CONSTRAINT fk_mat_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_mat_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_max_prefs (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_created TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_assigned TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_status_changed TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_due_changed TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_comment_mention TINYINT(1) NOT NULL DEFAULT 1,
  notify_task_participant_added TINYINT(1) NOT NULL DEFAULT 1,
  notify_direct_message TINYINT(1) NOT NULL DEFAULT 1,
  notify_sla_due_2h TINYINT(1) NOT NULL DEFAULT 1,
  notify_sla_overdue_24h TINYINT(1) NOT NULL DEFAULT 1,
  notify_sla_stale_days TINYINT(1) NOT NULL DEFAULT 1,
  digest_enabled TINYINT(1) NOT NULL DEFAULT 0,
  digest_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  quiet_mode_enabled TINYINT(1) NOT NULL DEFAULT 0,
  quiet_start CHAR(5) NOT NULL DEFAULT '20:00',
  quiet_end CHAR(5) NOT NULL DEFAULT '08:00',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ump_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ump_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE max_settings (
  organization_id INT UNSIGNED NOT NULL PRIMARY KEY,
  token VARCHAR(255) NOT NULL DEFAULT '',
  fallback_chat_id VARCHAR(32) NOT NULL DEFAULT '',
  timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  quiet_hours_enabled TINYINT(1) NOT NULL DEFAULT 0,
  quiet_start CHAR(5) NOT NULL DEFAULT '20:00',
  quiet_end CHAR(5) NOT NULL DEFAULT '08:00',
  stale_days SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  webhook_secret VARCHAR(128) NOT NULL DEFAULT '',
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ms_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO organizations (name, work_year) VALUES ('Институт развития образования Забайкальского края', YEAR(CURRENT_DATE));

-- Структурные подразделения: https://irozk.ru/sveden/struct
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Ректорат', 1 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Кадрово-юридический отдел', 2 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Отдел материально-технического обеспечения', 3 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Управление по сопровождению образовательной деятельности', 4 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Центр информационно-издательской деятельности', 5 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Кафедра педагогики, психологии и образовательного менеджмента', 6 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Кафедра воспитания и дополнительного образования', 7 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Кафедра общего образования', 8 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Центр программно-технического обеспечения', 9 FROM organizations LIMIT 1;
INSERT INTO departments (organization_id, name, sort_order)
SELECT id, 'Центр инновационной деятельности', 10 FROM organizations LIMIT 1;

INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#2563eb'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 1 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#0d9488'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 2 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#7c3aed'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 3 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#ea580c'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 4 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#db2777'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 5 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#0891b2'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 6 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#65a30d'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 7 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#ca8a04'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 8 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#9333ea'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 9 LIMIT 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, o.work_year, d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#e11d48'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 10 LIMIT 1;

INSERT INTO tags (organization_id, name, color)
SELECT id, 'Срочно', '#dc2626' FROM organizations LIMIT 1;
INSERT INTO tags (organization_id, name, color)
SELECT id, 'Отчётность', '#2563eb' FROM organizations LIMIT 1;
INSERT INTO tags (organization_id, name, color)
SELECT id, 'Документ', '#0d9488' FROM organizations LIMIT 1;

INSERT INTO checklist_templates (organization_id, name)
SELECT id, 'Подготовка отчёта' FROM organizations LIMIT 1;
INSERT INTO checklist_template_items (template_id, title, sort_order)
SELECT t.id, 'Собрать данные', 0 FROM checklist_templates t ORDER BY t.id DESC LIMIT 1;
INSERT INTO checklist_template_items (template_id, title, sort_order)
SELECT t.id, 'Проверить расчёты', 1 FROM checklist_templates t ORDER BY t.id DESC LIMIT 1;
INSERT INTO checklist_template_items (template_id, title, sort_order)
SELECT t.id, 'Согласовать с руководителем', 2 FROM checklist_templates t ORDER BY t.id DESC LIMIT 1;

-- Назначение руководителя подразделения (после появления пользователей), пример:
-- INSERT INTO department_heads (user_id, department_id) VALUES (2, 1);
