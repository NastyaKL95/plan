-- Уведомления о комментариях к задачам (прочитано / не прочитано).
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(32) NOT NULL COMMENT 'comment и др.',
  task_id INT UNSIGNED NULL,
  comment_id INT UNSIGNED NULL,
  title VARCHAR(500) NOT NULL,
  body TEXT NULL,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user_read (user_id, read_at, created_at),
  KEY idx_notif_org (organization_id),
  CONSTRAINT fk_notif_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
