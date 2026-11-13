<?php
declare(strict_types=1);

/**
 * Локальная конфигурация OSPanel: при необходимости скопируйте в config.local.php
 */
return [
    'db' => [
        'host' => getenv('PLANNER_DB_HOST') ?: 'localhost',
        'port' => (int) (getenv('PLANNER_DB_PORT') ?: 3306),
        'name' => getenv('PLANNER_DB_NAME') ?: 'u3508000_planner',
        'user' => getenv('PLANNER_DB_USER') ?: 'u3508000_default',
        'pass' => getenv('PLANNER_DB_PASS') ?: '33zBqC0R1Wmzrxd7',
        'charset' => 'utf8mb4',
        // Пороги мониторинга SQL (мс): медленный запрос и авто-EXPLAIN для SELECT.
        'slow_sql_ms' => (int) (getenv('PLANNER_DB_SLOW_SQL_MS') ?: 250),
        'slow_sql_explain_ms' => (int) (getenv('PLANNER_DB_SLOW_SQL_EXPLAIN_MS') ?: 900),
    ],
    'app' => [
        'name' => '',
        'session_name' => 'planner_sess',
        /** Полный URL сайта без слэша в конце (для ссылок в MAX и др.): https://planner.example.ru */
        'public_origin' => (string) (getenv('PLANNER_PUBLIC_ORIGIN') ?: 'https://planiro75.ru'),
    ],
    /**
     * Список/доска: скрывать выполненные и отменённые, закрытые дольше N дней (по status_changed_at или updated_at).
     * 0 — не скрывать. Переопределение: GET include_archived_done=1 или чекбокс в расширенном поиске на странице задач.
     */
    'tasks' => [
        'hide_done_cancelled_after_days' => (int) (getenv('PLANNER_TASKS_HIDE_DONE_AFTER_DAYS') ?: 30),
    ],
    /** Регистрация: подтверждение email и активация администратором (нужна migration_user_registration_v1.sql). */
    'registration' => [
        /** false — закрыть публичную регистрацию (первый admin при пустой БД всё равно создаётся). Переопределение: PLANNER_REG_ENABLED=1 */
        'enabled' => in_array(strtolower((string) getenv('PLANNER_REG_ENABLED')), ['1', 'true', 'yes', 'on'], true),
        'verify_token_hours' => (int) (getenv('PLANNER_REG_VERIFY_HOURS') ?: 48),
        /** Разрешённые домены email при регистрации (пустой массив — без ограничений). */
        'allowed_domains' => ['iro75.ru'],
        /** Удалять pending_email без подтверждения старше N дней (0 — не удалять). */
        'purge_pending_email_after_days' => (int) (getenv('PLANNER_REG_PURGE_PENDING_DAYS') ?: 14),
    ],
    'max' => [
        /**
         * MAX: business.max.ru → Чат-боты → Интеграция (токен и chat_id группы, куда добавлен бот).
         * Включение: задайте token + chat_id (в env или config.local.php). Явно выключить: PLANNER_MAX_ENABLED=0 или 'disabled' => true.
         */
        'disabled' => in_array(strtolower((string) getenv('PLANNER_MAX_DISABLED')), ['1', 'true', 'yes', 'on'], true),
        'token' => (string) (getenv('PLANNER_MAX_TOKEN') ?: ''),
        'chat_id' => (string) (getenv('PLANNER_MAX_CHAT_ID') ?: ''),
        'timeout' => (int) (getenv('PLANNER_MAX_HTTP_TIMEOUT') ?: 12),
        'webhook_log' => !in_array(strtolower((string) getenv('PLANNER_MAX_WEBHOOK_LOG')), ['0', 'false', 'no', 'off'], true),
    ],
    'oauth' => [
        'yandex' => [
            'enabled' => in_array(strtolower((string) getenv('PLANNER_YANDEX_OAUTH_ENABLED')), ['1', 'true', 'yes', 'on'], true),
            'client_id' => (string) (getenv('PLANNER_YANDEX_OAUTH_CLIENT_ID') ?: ''),
            'client_secret' => (string) (getenv('PLANNER_YANDEX_OAUTH_CLIENT_SECRET') ?: ''),
            'redirect_uri' => (string) (getenv('PLANNER_YANDEX_OAUTH_REDIRECT_URI') ?: ''),
            'scope' => (string) (getenv('PLANNER_YANDEX_OAUTH_SCOPE') ?: 'login:email login:info'),
        ],
    ],
    'mail' => [
        'enabled' => in_array(strtolower((string) getenv('PLANNER_MAIL_ENABLED')), ['1', 'true', 'yes', 'on'], true),
        'from_email' => (string) (getenv('PLANNER_MAIL_FROM_EMAIL') ?: 'do@iro75.ru'),
        'from_name' => (string) (getenv('PLANNER_MAIL_FROM_NAME') ?: 'Planner'),
        'smtp_host' => (string) (getenv('PLANNER_SMTP_HOST') ?: 'smtp.yandex.ru'),
        'smtp_port' => (int) (getenv('PLANNER_SMTP_PORT') ?: 465),
        'smtp_encryption' => (string) (getenv('PLANNER_SMTP_ENCRYPTION') ?: 'ssl'), // ssl|tls
        'smtp_user' => (string) (getenv('PLANNER_SMTP_USER') ?: 'do@iro75.ru'),
        'smtp_pass' => (string) (getenv('PLANNER_SMTP_PASS') ?: 'orjizeqptfqshkwc'),
        'smtp_timeout' => (int) (getenv('PLANNER_SMTP_TIMEOUT') ?: 12),
    ],
];
