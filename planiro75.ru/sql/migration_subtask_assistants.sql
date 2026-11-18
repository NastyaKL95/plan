-- Соисполнители для каждого пункта чек-листа (subtasks).
-- Выполнить один раз на существующей БД.

CREATE TABLE IF NOT EXISTS subtask_participants (
  subtask_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('assistant') NOT NULL DEFAULT 'assistant',
  PRIMARY KEY (subtask_id, user_id),
  KEY idx_spt_user (user_id),
  CONSTRAINT fk_spt_subtask FOREIGN KEY (subtask_id) REFERENCES subtasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_spt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
