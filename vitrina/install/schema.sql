-- Таблицы витрины событий. Префикс vitrina_ задаётся в config.php (table_prefix).

CREATE TABLE IF NOT EXISTS vitrina_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL,
  name VARCHAR(100) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#64748b',
  icon VARCHAR(32) NOT NULL DEFAULT 'other',
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vitrina_admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vitrina_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(300) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  start_time TIME NULL,
  end_date DATE NOT NULL,
  end_time TIME NULL,
  format ENUM('offline','online','hybrid') NOT NULL DEFAULT 'offline',
  place VARCHAR(300) NOT NULL DEFAULT '',
  excerpt VARCHAR(400) NOT NULL DEFAULT '',
  description MEDIUMTEXT NOT NULL,
  link_url VARCHAR(500) NOT NULL DEFAULT '',
  link_label VARCHAR(60) NOT NULL DEFAULT '',
  image VARCHAR(255) NOT NULL DEFAULT '',
  image_thumb VARCHAR(255) NOT NULL DEFAULT '',
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  KEY idx_start (start_date),
  KEY idx_pub_start (is_published, start_date),
  KEY idx_category (category_id),
  CONSTRAINT fk_vitrina_events_category FOREIGN KEY (category_id) REFERENCES vitrina_categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vitrina_login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO vitrina_categories (slug, name, color, icon, sort_order) VALUES
  ('conference', 'Конференции', '#2b8cff', 'conference', 10),
  ('seminar', 'Семинары', '#8b5cf6', 'seminar', 20),
  ('webinar', 'Вебинары', '#14b8a6', 'webinar', 30),
  ('symposium', 'Симпозиумы', '#f59e0b', 'symposium', 40),
  ('contest', 'Конкурсы', '#ef4f6b', 'contest', 50),
  ('other', 'Другое', '#64748b', 'other', 90);
