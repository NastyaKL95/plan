<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';
$localPath = dirname(__DIR__) . '/config/config.local.php';
$config = require $configPath;
if (is_file($localPath)) {
    $local = require $localPath;
    $config = array_replace_recursive($config, $local);
}

session_name($config['app']['session_name']);
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $https, true);
    }
    session_start();
}

require_once __DIR__ . '/db.php';

$db = db_connect($config['db']);

function app_config(): array
{
    global $config;
    return $config;
}

function db(): PDO
{
    global $db;
    return $db;
}

function planner_sql_slow_threshold_ms(): int
{
    $cfg = app_config();
    $v = isset($cfg['db']['slow_sql_ms']) ? (int) $cfg['db']['slow_sql_ms'] : 250;
    return $v > 0 ? $v : 250;
}

function planner_sql_explain_threshold_ms(): int
{
    $cfg = app_config();
    $v = isset($cfg['db']['slow_sql_explain_ms']) ? (int) $cfg['db']['slow_sql_explain_ms'] : 900;
    return $v > 0 ? $v : 900;
}

function planner_log_sql_metric(string $sql, array $params, float $elapsedMs, string $context, ?array $explainRows = null): void
{
    if ($elapsedMs < planner_sql_slow_threshold_ms()) {
        return;
    }
    $baseDir = dirname(__DIR__) . '/var/log';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
    }
    $payload = [
        'ts' => date('c'),
        'elapsed_ms' => round($elapsedMs, 2),
        'context' => $context,
        'sql' => $sql,
        'params_count' => count($params),
    ];
    if ($explainRows !== null) {
        $payload['explain'] = $explainRows;
    }
    @file_put_contents($baseDir . '/sql-slow.log', json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

function planner_timed_execute(PDOStatement $st, array $params, string $sql, string $context): void
{
    $t0 = microtime(true);
    $st->execute($params);
    $elapsedMs = (microtime(true) - $t0) * 1000;

    $explainRows = null;
    $trim = ltrim($sql);
    if ($elapsedMs >= planner_sql_explain_threshold_ms() && stripos($trim, 'select') === 0) {
        try {
            $exp = db()->prepare('EXPLAIN ' . $sql);
            $exp->execute($params);
            $explainRows = $exp->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $explainRows = [['error' => 'explain_failed', 'message' => $e->getMessage()]];
        }
    }
    planner_log_sql_metric($sql, $params, $elapsedMs, $context, $explainRows);
}

/**
 * Проверка схемы БД (кэш на один HTTP-запрос): до миграции ИРО колонок может не быть.
 *
 * @param 'users'|'projects'|'tasks'|'departments'|'department_heads'|'organizations' $table
 */
function planner_table_has_column(string $table, string $column): bool
{
    static $cache = [];
    $tables = [
        'users', 'projects', 'tasks', 'subtasks', 'departments', 'department_heads', 'organizations', 'task_comments',
        'tags', 'task_tag_map', 'task_links', 'task_attachments', 'checklist_templates', 'checklist_template_items',
        'project_portfolios', 'portfolio_projects', 'user_departments', 'user_max_prefs', 'max_settings', 'max_digest_queue', 'max_action_tokens',
    ];
    if (!in_array($table, $tables, true) || !preg_match('/^[a-z_]+$/', $column)) {
        return false;
    }
    $k = $table . '.' . $column;
    if (array_key_exists($k, $cache)) {
        return $cache[$k];
    }
    try {
        $st = db()->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $st->execute([$column]);
        $cache[$k] = (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$k] = false;
    }
    return $cache[$k];
}

/** Текущий рабочий год плана организации (колонка organizations.work_year или календарный год). */
function planner_org_work_year_for_org(int $orgId): int
{
    static $cache = [];
    if ($orgId <= 0) {
        return (int) date('Y');
    }
    if (array_key_exists($orgId, $cache)) {
        return $cache[$orgId];
    }
    if (!planner_table_has_column('organizations', 'work_year')) {
        return $cache[$orgId] = (int) date('Y');
    }
    $st = db()->prepare('SELECT work_year FROM organizations WHERE id = ? LIMIT 1');
    $st->execute([$orgId]);
    $y = $st->fetchColumn();
    return $cache[$orgId] = (($y !== false && $y !== null) ? (int) $y : (int) date('Y'));
}

/** Нормализация HH:MM или HH:MM:SS → HH:MM:SS; null если формат неверный. */
function planner_normalize_due_time_hm(?string $raw, string $fallbackHm = '18:00'): string
{
    $s = trim((string) ($raw));
    if ($s === '') {
        $s = trim($fallbackHm);
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m)) {
        $h = (int) $m[1];
        $min = (int) $m[2];
        $sec = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;
        if ($h >= 0 && $h <= 23 && $min >= 0 && $min <= 59 && $sec >= 0 && $sec <= 59) {
            return sprintf('%02d:%02d:%02d', $h, $min, $sec);
        }
    }

    return planner_normalize_due_time_hm($fallbackHm, '18:00');
}

function planner_validate_org_due_time_hm(string $hm): bool
{
    return (bool) preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', trim($hm));
}

/** Время срока организации для даты без времени (импорт Excel, поле «только дата»). */
function planner_org_default_due_time_hms(int $orgId): string
{
    static $cache = [];
    if ($orgId <= 0) {
        return '18:00:00';
    }
    if (array_key_exists($orgId, $cache)) {
        return $cache[$orgId];
    }
    $hm = '18:00';
    if (planner_table_has_column('organizations', 'default_due_time')) {
        $st = db()->prepare('SELECT default_due_time FROM organizations WHERE id = ? LIMIT 1');
        $st->execute([$orgId]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null && trim((string) $v) !== '') {
            $hm = trim((string) $v);
        }
    }

    return $cache[$orgId] = planner_normalize_due_time_hm($hm);
}

/**
 * Ограничение выборок задач годом плана (задачи ведутся в рамках года организации).
 *
 * @return array{sql: string, params: array<int, mixed>}
 */
function planner_scope_org_work_year_task(int $orgId, string $alias = 't'): array
{
    if (!preg_match('/^[a-z_]+$/', $alias)) {
        $alias = 't';
    }
    if (!planner_table_has_column('tasks', 'work_year')) {
        return ['sql' => '', 'params' => []];
    }

    return ['sql' => ' AND ' . $alias . '.work_year = ? ', 'params' => [planner_org_work_year_for_org($orgId)]];
}

/**
 * @return array{sql: string, params: array<int, mixed>}
 */
function planner_scope_org_work_year_project(int $orgId, string $alias = 'p'): array
{
    if (!preg_match('/^[a-z_]+$/', $alias)) {
        $alias = 'p';
    }
    if (!planner_table_has_column('projects', 'work_year')) {
        return ['sql' => '', 'params' => []];
    }

    return ['sql' => ' AND ' . $alias . '.work_year = ? ', 'params' => [planner_org_work_year_for_org($orgId)]];
}

/** @param 'users'|'projects'|... $table */
function planner_table_exists(string $table): bool
{
    static $cache = [];
    $tables = [
        'users', 'projects', 'tasks', 'subtasks', 'departments', 'department_heads', 'organizations', 'task_comments',
        'task_comment_reads', 'task_saved_views', 'task_comment_mentions',
        'tags', 'task_tag_map', 'task_links', 'task_attachments', 'checklist_templates', 'checklist_template_items',
        'task_participants', 'subtask_participants', 'task_automation_rules', 'task_templates', 'notifications', 'mail_queue',
        'project_portfolios', 'portfolio_projects', 'user_departments', 'user_max_prefs', 'max_settings', 'notification_events_dlq', 'max_digest_queue', 'max_action_tokens',
        'user_my_plan_stages', 'task_my_plan_placement', 'user_email_verification_tokens',
    ];
    if (!in_array($table, $tables, true)) {
        return false;
    }
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $st = db()->prepare('SHOW TABLES LIKE ?');
        $st->execute([$table]);
        $cache[$table] = (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $withDept = planner_table_has_column('users', 'department_id');
    $orgYearSel = planner_table_has_column('organizations', 'work_year')
        ? 'o.work_year AS organization_work_year'
        : 'YEAR(CURRENT_DATE) AS organization_work_year';
    $regCols = '';
    if (planner_table_has_column('users', 'account_status')) {
        $regCols .= ', u.account_status';
    }
    if (planner_table_has_column('users', 'email_verified_at')) {
        $regCols .= ', u.email_verified_at';
    }
    $sql = $withDept
        ? 'SELECT u.id, u.organization_id, u.email, u.name, u.role, u.department_id, o.name AS organization_name, '
        . $orgYearSel . $regCols . '
           FROM users u
           JOIN organizations o ON o.id = u.organization_id
           WHERE u.id = ? LIMIT 1'
        : 'SELECT u.id, u.organization_id, u.email, u.name, u.role, NULL AS department_id, o.name AS organization_name, '
        . $orgYearSel . $regCols . '
           FROM users u
           JOIN organizations o ON o.id = u.organization_id
           WHERE u.id = ? LIMIT 1';
    $st = db()->prepare($sql);
    $st->execute([(int) $_SESSION['user_id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function planner_app_root(): string
{
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = dirname($sn);
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '';
    }
    return rtrim($dir, '/');
}

function planner_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    return planner_app_root() . $path;
}

/** HTML-теги link для favicon (SVG). */
function planner_favicon_head_markup(): string
{
    $svg = htmlspecialchars(planner_url('/favicon.svg'), ENT_QUOTES, 'UTF-8');

    return '<link rel="icon" type="image/svg+xml" href="' . $svg . '">' . "\n  "
        . '<link rel="mask-icon" href="' . $svg . '" color="#2fc06e">';
}

/**
 * Отдать HTTP-ответ клиенту и продолжить работу (почта, MAX и т.д. без задержки закрытия UI).
 * В FPM — fastcgi_finish_request; иначе — сброс буферов и flush (Apache/mod_php и др.).
 */
function planner_http_release_client(): void
{
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
}

/** POST + ?__method=patch|put|delete или заголовок X-HTTP-Method-Override, если сервер не прокидывает PATCH. */
function effective_request_method(): string
{
    $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($m === 'POST') {
        $q = strtolower((string) ($_GET['__method'] ?? ''));
        if ($q === 'patch') {
            return 'PATCH';
        }
        if ($q === 'put') {
            return 'PUT';
        }
        if ($q === 'delete') {
            return 'DELETE';
        }
        $o = strtoupper(trim((string) ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '')));
        if (in_array($o, ['PATCH', 'PUT', 'DELETE'], true)) {
            return $o;
        }
    }
    return $m;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: ' . planner_url('/login.php'), true, 302);
        exit;
    }
    $block = planner_login_block_message($u);
    if ($block !== null) {
        $_SESSION['login_notice'] = $block;
        unset($_SESSION['user_id']);
        header('Location: ' . planner_url('/login.php'), true, 302);
        exit;
    }
    return $u;
}

function json_response(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function read_json_body(): array
{
    static $parsed = null;
    if ($parsed !== null) {
        return $parsed;
    }
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        $parsed = [];
        return $parsed;
    }
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(['ok' => false, 'error' => 'invalid_json'], 400);
    }
    $parsed = is_array($decoded) ? $decoded : [];
    return $parsed;
}

/** CSRF из заголовка X-CSRF-Token или поля _csrf / csrf_token в JSON-теле POST. */
function planner_csrf_sent_token(): string
{
    $sent = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($sent !== '') {
        return $sent;
    }
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return '';
    }
    $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($ct === '' || str_contains($ct, 'application/json')) {
        $body = read_json_body();
        $fromBody = trim((string) ($body['_csrf'] ?? $body['csrf_token'] ?? ''));
        if ($fromBody !== '') {
            return $fromBody;
        }
    }
    return '';
}

/** Токен для защиты от CSRF в JSON API (заголовок X-CSRF-Token). */
function planner_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['_planner_csrf']) || !is_string($_SESSION['_planner_csrf'])) {
        $_SESSION['_planner_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_planner_csrf'];
}

/** Новый токен после входа (защита от фиксации сессии). */
function planner_csrf_rotate(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['_planner_csrf'] = bin2hex(random_bytes(32));
}

/**
 * Для эндпоинтов под _guard.php: POST/PATCH/PUT/DELETE требуют заголовок X-CSRF-Token.
 */
function planner_require_csrf_for_mutating_api(): void
{
    $m = effective_request_method();
    if (!in_array($m, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }
    $expected = planner_csrf_token();
    if ($expected === '') {
        return;
    }
    $sent = planner_csrf_sent_token();
    if ($sent === '' || !hash_equals($expected, $sent)) {
        json_response([
            'ok' => false,
            'error' => 'csrf_mismatch',
            'csrf_token' => $expected,
        ], 419);
    }
}

function log_activity(int $orgId, ?int $userId, string $action, string $entityType, ?int $entityId, string $message): void
{
    $st = db()->prepare(
        'INSERT INTO activity_log (organization_id, user_id, action, entity_type, entity_id, message)
         VALUES (?,?,?,?,?,?)'
    );
    $st->execute([$orgId, $userId, $action, $entityType, $entityId, $message]);
}

require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/registration.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/storage.php';
