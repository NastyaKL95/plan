-- Базовые типы событий, добавляются один раз при первичной настройке.

INSERT IGNORE INTO vitrina_categories (slug, name, color, icon, sort_order) VALUES
  ('conference', 'Конференции', '#2b8cff', 'conference', 10),
  ('seminar', 'Семинары', '#8b5cf6', 'seminar', 20),
  ('webinar', 'Вебинары', '#14b8a6', 'webinar', 30),
  ('symposium', 'Симпозиумы', '#f59e0b', 'symposium', 40),
  ('contest', 'Конкурсы', '#ef4f6b', 'contest', 50),
  ('other', 'Другое', '#64748b', 'other', 90);
