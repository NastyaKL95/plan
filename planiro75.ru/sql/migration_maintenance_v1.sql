-- Режим технического обслуживания. Те же поля добавляются при открытии сайта, если есть право ALTER.
ALTER TABLE organizations
  ADD COLUMN maintenance_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 — сайт на техническом обслуживании',
  ADD COLUMN maintenance_message VARCHAR(500) NULL COMMENT 'Текст заглушки для сотрудников';
