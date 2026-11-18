<?php
declare(strict_types=1);

const CERT_ROOT = __DIR__ . '/..';
const CERT_PUBLIC = CERT_ROOT . '/public';
const CERT_STORAGE = CERT_ROOT . '/storage';

$GLOBALS['cert_config'] = require CERT_ROOT . '/config.php';
date_default_timezone_set((string) cfg('timezone'));
mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('certsess');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path' => base_path() . '/',
    ]);
    session_start();
}

function cfg(string $key)
{
    return $GLOBALS['cert_config'][$key] ?? null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, (string) cfg('db_user'), (string) cfg('db_pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    ensure_schema($pdo);

    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    $schema = CERT_ROOT . '/sql/schema.sql';
    $marker = CERT_STORAGE . '/.schema-' . substr(md5((string) filemtime($schema) . cfg('db_name')), 0, 12);
    if (is_file($marker)) {
        return;
    }
    $pdo->exec((string) file_get_contents($schema));
    @file_put_contents($marker, date('c'));
}

/** Путь приложения от корня сайта, например "" или "/certificates/public". */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $file = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $public = realpath(CERT_PUBLIC) ?: CERT_PUBLIC;
    $base = '';
    if ($file !== '' && str_starts_with($file, $public)) {
        $rel = str_replace('\\', '/', substr($file, strlen($public)));
        if ($rel !== '' && str_ends_with($script, $rel)) {
            $base = substr($script, 0, -strlen($rel));
        }
    }

    return $base = rtrim($base, '/');
}

function url(string $path): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function absolute_url(string $path): string
{
    $base = rtrim((string) cfg('base_url'), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = ($https ? 'https://' : 'http://') . $host . base_path();
    }

    return $base . '/' . ltrim($path, '/');
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : url($path)), true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Форма устарела. Обновите страницу и попробуйте снова.');
    }
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['text' => $message, 'type' => $type];

        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $f;
}

function current_admin(): ?array
{
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $st = db()->prepare('SELECT id, login FROM cert_admins WHERE id = ?');
    $st->execute([$id]);

    return $st->fetch() ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if ($admin === null) {
        $count = (int) db()->query('SELECT COUNT(*) FROM cert_admins')->fetchColumn();
        redirect($count === 0 ? 'admin/setup.php' : 'admin/login.php');
    }

    return $admin;
}

function random_code(int $length, string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'): string
{
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }

    return $out;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function format_dt(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);

    return $ts ? date('d.m.Y H:i', $ts) : '—';
}

function plural_ru(int $n, string $one, string $few, string $many): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $many;
    }
    if ($n1 > 1 && $n1 < 5) {
        return $few;
    }

    return $n1 === 1 ? $one : $many;
}
