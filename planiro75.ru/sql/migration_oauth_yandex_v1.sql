-- Миграция для входа через OAuth (Яндекс).
-- Выполните один раз в нужной БД.
-- Если колонка/индекс уже существует, MySQL вернет 1060/1061 — это допустимо.
SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(32) NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN oauth_subject VARCHAR(191) NULL AFTER oauth_provider;
ALTER TABLE users ADD UNIQUE KEY uq_users_oauth (oauth_provider, oauth_subject);
