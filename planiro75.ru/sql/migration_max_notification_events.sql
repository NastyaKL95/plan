SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notification_events (
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

