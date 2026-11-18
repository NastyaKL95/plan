-- Персональные стадии «Мой план» (как в Битрикс24): название, цвет, порядок; позиция задачи на доске пользователя.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS user_my_plan_stages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#64748b',
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mps_org (organization_id),
  KEY idx_mps_user_sort (user_id, sort_order),
  CONSTRAINT fk_mps_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_my_plan_placement (
  organization_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  stage_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (task_id, user_id),
  KEY idx_tmp_stage (stage_id),
  CONSTRAINT fk_tmp_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_tmp_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_tmp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tmp_stage FOREIGN KEY (stage_id) REFERENCES user_my_plan_stages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
