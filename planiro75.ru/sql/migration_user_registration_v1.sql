SET NAMES utf8mb4;

-- Подтверждение email и активация администратором (роль + подразделение).
ALTER TABLE users
  ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL COMMENT 'Когда подтверждён email' AFTER password_hash,
  ADD COLUMN account_status ENUM('pending_email','pending_approval','active') NOT NULL DEFAULT 'active'
    COMMENT 'pending_email — ждёт письмо; pending_approval — ждёт админа; active — доступ в систему'
    AFTER email_verified_at;

UPDATE users
SET account_status = 'active',
    email_verified_at = COALESCE(email_verified_at, created_at)
WHERE account_status = 'active' OR account_status IS NULL;

CREATE TABLE IF NOT EXISTS user_email_verification_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_uevt_token (token),
  KEY idx_uevt_user (user_id),
  KEY idx_uevt_expires (expires_at),
  CONSTRAINT fk_uevt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
