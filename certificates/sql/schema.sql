-- Схема создаётся автоматически при первом открытии. Повторный запуск безопасен.

CREATE TABLE IF NOT EXISTS cert_admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cert_admins_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cert_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  bg_file VARCHAR(80) NULL,
  bg_width INT UNSIGNED NULL,
  bg_height INT UNSIGNED NULL,
  inputs_json TEXT NOT NULL,
  fields_json TEXT NOT NULL,
  number_prefix VARCHAR(40) NOT NULL DEFAULT '',
  number_digits TINYINT UNSIGNED NOT NULL DEFAULT 4,
  next_number INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cert_links (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  token VARCHAR(32) NOT NULL,
  note VARCHAR(255) NULL,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  max_uses INT UNSIGNED NULL,
  access_code_hash VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cert_links_token (token),
  KEY idx_cert_links_template (template_id),
  CONSTRAINT fk_cert_links_template FOREIGN KEY (template_id) REFERENCES cert_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cert_issued (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  link_id INT UNSIGNED NULL,
  number VARCHAR(80) NOT NULL,
  code VARCHAR(16) NOT NULL,
  values_json TEXT NOT NULL,
  dedupe_hash CHAR(64) NOT NULL,
  issued_on DATE NOT NULL,
  ip VARCHAR(45) NULL,
  downloads INT UNSIGNED NOT NULL DEFAULT 0,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cert_issued_code (code),
  UNIQUE KEY uq_cert_issued_dedupe (template_id, dedupe_hash),
  KEY idx_cert_issued_template (template_id, id),
  KEY idx_cert_issued_link (link_id),
  KEY idx_cert_issued_ip (ip, created_at),
  CONSTRAINT fk_cert_issued_template FOREIGN KEY (template_id) REFERENCES cert_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
