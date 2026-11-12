<?php
declare(strict_types=1);

/**
 * Режим технического обслуживания организации.
 * Сотрудники видят заглушку, администратор продолжает работать и может выключить режим.
 */

function planner_maintenance_schema_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $hasEnabled = (bool) db()->query("SHOW COLUMNS FROM organizations LIKE 'maintenance_enabled'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasEnabled) {
            db()->exec(
                "ALTER TABLE organizations
                 ADD COLUMN maintenance_enabled TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT '1 — сайт на техническом обслуживании'"
            );
        }
        $hasMessage = (bool) db()->query("SHOW COLUMNS FROM organizations LIKE 'maintenance_message'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasMessage) {
            db()->exec(
                "ALTER TABLE organizations
                 ADD COLUMN maintenance_message VARCHAR(500) NULL
                 COMMENT 'Текст заглушки для сотрудников'"
            );
        }
        $ready = true;
    } catch (Throwable $e) {
        error_log('maintenance schema: ' . $e->getMessage());
        $ready = false;
    }

    return $ready;
}

/**
 * @return array{enabled: bool, message: string}
 */
function planner_maintenance_state(int $orgId, bool $refresh = false): array
{
    static $cache = [];
    if ($orgId <= 0) {
        return ['enabled' => false, 'message' => ''];
    }
    if ($refresh) {
        unset($cache[$orgId]);
    }
    if (isset($cache[$orgId])) {
        return $cache[$orgId];
    }
    if (!planner_maintenance_schema_ready()) {
        return $cache[$orgId] = ['enabled' => false, 'message' => ''];
    }
    $st = db()->prepare(
        'SELECT maintenance_enabled, maintenance_message FROM organizations WHERE id = ? LIMIT 1'
    );
    $st->execute([$orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $message = trim((string) ($row['maintenance_message'] ?? ''));

    return $cache[$orgId] = [
        'enabled' => (int) ($row['maintenance_enabled'] ?? 0) === 1,
        'message' => $message,
    ];
}

function planner_maintenance_is_enabled(int $orgId): bool
{
    return planner_maintenance_state($orgId)['enabled'];
}

function planner_maintenance_default_message(): string
{
    return 'Идут технические работы. Планировщик скоро снова будет доступен.';
}

function planner_maintenance_clip_message(string $raw): string
{
    $text = trim($raw);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 500, 'UTF-8');
    }

    return substr($text, 0, 500);
}

function planner_maintenance_role_may_pass(string $role): bool
{
    return in_array($role, ['admin', 'owner'], true);
}

function planner_maintenance_blocks_user(array $user): bool
{
    $orgId = (int) ($user['organization_id'] ?? 0);
    if (!planner_maintenance_is_enabled($orgId)) {
        return false;
    }

    return !planner_maintenance_role_may_pass((string) ($user['role'] ?? ''));
}

function planner_maintenance_blocks_user_id(int $orgId, int $userId): bool
{
    if (!planner_maintenance_is_enabled($orgId)) {
        return false;
    }
    if ($userId <= 0) {
        return true;
    }
    $st = db()->prepare('SELECT role FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$userId, $orgId]);
    $role = (string) ($st->fetchColumn() ?: '');

    return !planner_maintenance_role_may_pass($role);
}

/**
 * @param array{enabled?: bool, message?: string} $fields
 */
function planner_maintenance_save(int $orgId, array $fields): array
{
    if ($orgId <= 0 || !planner_maintenance_schema_ready()) {
        json_response(['ok' => false, 'error' => 'schema'], 503);
    }
    $current = planner_maintenance_state($orgId);
    $enabled = array_key_exists('enabled', $fields) ? (bool) $fields['enabled'] : $current['enabled'];
    $message = array_key_exists('message', $fields)
        ? planner_maintenance_clip_message((string) $fields['message'])
        : $current['message'];
    db()->prepare(
        'UPDATE organizations SET maintenance_enabled = ?, maintenance_message = ? WHERE id = ?'
    )->execute([$enabled ? 1 : 0, $message !== '' ? $message : null, $orgId]);
    planner_maintenance_state($orgId, true);

    return ['enabled' => $enabled, 'message' => $message];
}

function planner_maintenance_render_page(int $orgId, bool $loggedIn): void
{
    $state = planner_maintenance_state($orgId);
    $text = $state['message'] !== '' ? $state['message'] : planner_maintenance_default_message();
    $app = trim((string) (app_config()['app']['name'] ?? ''));
    if ($app === '') {
        $app = 'Вектор';
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 3600');
    header('Cache-Control: no-store');
    $title = htmlspecialchars('Техническое обслуживание', ENT_QUOTES, 'UTF-8');
    $appEsc = htmlspecialchars($app, ENT_QUOTES, 'UTF-8');
    $textEsc = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    $css = htmlspecialchars(planner_url('/assets/css/app.css'), ENT_QUOTES, 'UTF-8');
    $logout = htmlspecialchars(planner_url('/logout.php'), ENT_QUOTES, 'UTF-8');
    $login = htmlspecialchars(planner_url('/login.php'), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $title . ' — ' . $appEsc . '</title>';
    if (function_exists('planner_favicon_head_markup')) {
        echo planner_favicon_head_markup();
    }
    echo '<link rel="stylesheet" href="' . $css . '"></head>';
    echo '<body class="maint-page"><main class="maint-card">';
    echo '<div class="maint-brand">' . $appEsc . '</div>';
    echo '<h1 class="maint-title">Техническое обслуживание</h1>';
    echo '<p class="maint-text">' . $textEsc . '</p>';
    echo '<p class="maint-hint">Войти и работать сейчас может только администратор.</p>';
    if ($loggedIn) {
        echo '<a class="btn btn-secondary" href="' . $logout . '">Выйти</a>';
    } else {
        echo '<a class="btn btn-primary" href="' . $login . '">Вход для администратора</a>';
    }
    echo '</main></body></html>';
    exit;
}

function planner_maintenance_enforce_page(array $user): void
{
    if (!planner_maintenance_blocks_user($user)) {
        return;
    }
    planner_maintenance_render_page((int) ($user['organization_id'] ?? 0), true);
}

function planner_maintenance_enforce_api(array $user): void
{
    if (!planner_maintenance_blocks_user($user)) {
        return;
    }
    $state = planner_maintenance_state((int) ($user['organization_id'] ?? 0));
    $message = $state['message'] !== '' ? $state['message'] : planner_maintenance_default_message();
    json_response(['ok' => false, 'error' => 'maintenance', 'message' => $message], 503);
}
