<?php
declare(strict_types=1);

/**
 * Уведомления в чат MAX (чат-бот): platform-api.max.ru
 * @see https://dev.max.ru/docs-api/methods/POST/messages
 */

/** Токен в заголовке не должен содержать пробелов/переносов (иначе 401). */
function planner_max_normalize_token(string $raw): string
{
    return (string) preg_replace('/\s+/', '', $raw);
}

/**
 * @return array<string,mixed>
 */
function planner_max_runtime_settings(?int $orgId = null): array
{
    $cfg = app_config()['max'] ?? [];
    $out = [
        'token' => planner_max_normalize_token((string) ($cfg['token'] ?? '')),
        'chat_id' => trim((string) ($cfg['chat_id'] ?? '')),
        'timeout' => (int) (($cfg['timeout'] ?? 12) ?: 12),
        'quiet_hours_enabled' => false,
        'quiet_start' => '20:00',
        'quiet_end' => '08:00',
        'stale_days' => 3,
    ];
    if ($orgId === null || $orgId <= 0 || !planner_table_exists('max_settings')) {
        return $out;
    }
    try {
        $st = db()->prepare(
            'SELECT token, fallback_chat_id, timeout_seconds, quiet_hours_enabled, quiet_start, quiet_end, stale_days
             FROM max_settings
             WHERE organization_id = ?
             LIMIT 1'
        );
        $st->execute([$orgId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $out;
        }
        $dbToken = planner_max_normalize_token((string) ($row['token'] ?? ''));
        if ($dbToken !== '') {
            $out['token'] = $dbToken;
        }
        $dbChat = trim((string) ($row['fallback_chat_id'] ?? ''));
        if ($dbChat !== '') {
            $out['chat_id'] = $dbChat;
        }
        $dbTimeout = (int) ($row['timeout_seconds'] ?? 0);
        if ($dbTimeout > 0) {
            $out['timeout'] = $dbTimeout;
        }
        $out['quiet_hours_enabled'] = (int) ($row['quiet_hours_enabled'] ?? 0) === 1;
        $qs = trim((string) ($row['quiet_start'] ?? ''));
        $qe = trim((string) ($row['quiet_end'] ?? ''));
        if (preg_match('/^\d{2}:\d{2}$/', $qs)) {
            $out['quiet_start'] = $qs;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $qe)) {
            $out['quiet_end'] = $qe;
        }
        $sd = (int) ($row['stale_days'] ?? 0);
        if ($sd > 0) {
            $out['stale_days'] = $sd;
        }
    } catch (Throwable $e) {
        // keep defaults
    }
    return $out;
}

function planner_max_is_critical_event(string $eventType): bool
{
    return in_array($eventType, ['task_comment_mention', 'task_assigned', 'sla_overdue_24h'], true);
}

function planner_max_is_quiet_hours_now(string $startHm, string $endHm): bool
{
    $now = (int) date('G') * 60 + (int) date('i');
    [$sh, $sm] = array_map('intval', explode(':', $startHm));
    [$eh, $em] = array_map('intval', explode(':', $endHm));
    $start = $sh * 60 + $sm;
    $end = $eh * 60 + $em;
    if ($start === $end) {
        return false;
    }
    if ($start < $end) {
        return $now >= $start && $now < $end;
    }
    return $now >= $start || $now < $end;
}

function planner_max_quiet_hours_delay_seconds(string $endHm): int
{
    [$eh, $em] = array_map('intval', explode(':', $endHm));
    $nowM = (int) date('G') * 60 + (int) date('i');
    $endM = $eh * 60 + $em;
    $deltaM = $endM - $nowM;
    if ($deltaM <= 0) {
        $deltaM += 24 * 60;
    }
    return max(60, $deltaM * 60);
}

function planner_max_flush_due_digests(int $limitUsers = 200): int
{
    if (!planner_table_exists('max_digest_queue')) {
        return 0;
    }
    $limitUsers = max(1, min(1000, $limitUsers));
    $sql = "SELECT dq.organization_id, dq.user_id, MIN(dq.created_at) AS first_at, COUNT(*) AS cnt
            FROM max_digest_queue dq
            GROUP BY dq.organization_id, dq.user_id
            ORDER BY first_at ASC
            LIMIT $limitUsers";
    $groups = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0;
    foreach ($groups as $g) {
        $orgId = (int) ($g['organization_id'] ?? 0);
        $userId = (int) ($g['user_id'] ?? 0);
        if ($orgId <= 0 || $userId <= 0) {
            continue;
        }
        $prefs = planner_max_user_prefs($orgId, $userId);
        if (empty($prefs['digest_enabled'])) {
            continue;
        }
        $interval = max(15, min(1440, (int) ($prefs['digest_interval_minutes'] ?? 60)));
        $ageSql = db()->prepare(
            "SELECT TIMESTAMPDIFF(MINUTE, MIN(created_at), NOW()) FROM max_digest_queue WHERE organization_id = ? AND user_id = ?"
        );
        $ageSql->execute([$orgId, $userId]);
        $age = (int) ($ageSql->fetchColumn() ?: 0);
        if ($age < $interval) {
            continue;
        }
        $st = db()->prepare(
            "SELECT event_type, task_id, text
             FROM max_digest_queue
             WHERE organization_id = ? AND user_id = ?
             ORDER BY id ASC
             LIMIT 80"
        );
        $st->execute([$orgId, $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            continue;
        }
        $lines = [];
        foreach ($rows as $r) {
            $ev = (string) ($r['event_type'] ?? 'event');
            $tid = (int) ($r['task_id'] ?? 0);
            $txt = trim((string) ($r['text'] ?? ''));
            if ($txt !== '') {
                $txt = preg_replace('/\s+/', ' ', $txt);
            }
            $short = function_exists('mb_substr') ? mb_substr($txt, 0, 120, 'UTF-8') : substr($txt, 0, 120);
            $lines[] = '• [' . $ev . '] ' . ($tid > 0 ? ('#' . $tid . ' ') : '') . $short;
        }
        $msg = "🧾 Дайджест Planner (" . count($rows) . ")\n" . implode("\n", $lines);
        planner_max_enqueue_event($orgId, $userId, 0, 'digest_summary', $msg, ['count' => count($rows)], 60);
        db()->prepare("DELETE FROM max_digest_queue WHERE organization_id = ? AND user_id = ?")->execute([$orgId, $userId]);
        $sent++;
    }
    return $sent;
}

function planner_max_enabled(): bool
{
    $m = app_config()['max'] ?? [];
    if (!empty($m['disabled'])) {
        return false;
    }
    $env = getenv('PLANNER_MAX_ENABLED');
    if ($env !== false && $env !== '') {
        if (!in_array(strtolower((string) $env), ['1', 'true', 'yes', 'on'], true)) {
            return false;
        }
    }
    $token = planner_max_normalize_token((string) ($m['token'] ?? ''));
    if ($token === '' && function_exists('current_user')) {
        $u = current_user();
        if (is_array($u) && !empty($u['organization_id'])) {
            $runtime = planner_max_runtime_settings((int) $u['organization_id']);
            $token = planner_max_normalize_token((string) ($runtime['token'] ?? ''));
        }
    }
    if ($token === '' && planner_table_exists('max_settings')) {
        try {
            $st = db()->query("SELECT token FROM max_settings WHERE token <> '' ORDER BY updated_at DESC LIMIT 1");
            $token = planner_max_normalize_token((string) ($st->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            // ignore
        }
    }
    return $token !== '';
}

/** Типы событий MAX для входящего webhook (команды и кнопки). */
function planner_max_webhook_update_types(): array
{
    return [
        'message_created',
        'message_callback',
        'bot_started',
        'bot_added',
    ];
}

/** Корректный ли публичный URL webhook (защита от бага CLI / старого кода на сервере). */
function planner_max_webhook_url_is_valid(string $url): bool
{
    $url = trim($url);
    if ($url === '' || !str_starts_with(strtolower($url), 'https://')) {
        return false;
    }
    if (str_contains($url, 'ruwww') || str_contains($url, '/cron/api/') || str_contains($url, '/cron/')) {
        return false;
    }

    return (bool) preg_match('#/api/max_webhook\.php$#i', $url);
}

/** Публичный HTTPS URL webhook без secret (секрет передаётся в заголовке X-Max-Bot-Api-Secret). */
function planner_max_webhook_endpoint_url(): string
{
    $envUrl = trim((string) (getenv('PLANNER_MAX_WEBHOOK_URL') ?: ''));
    if ($envUrl !== '' && planner_max_webhook_url_is_valid($envUrl)) {
        return $envUrl;
    }
    $cfg = app_config();
    $explicit = trim((string) (($cfg['max']['webhook_url'] ?? '') ?: ''));
    if ($explicit !== '' && planner_max_webhook_url_is_valid($explicit)) {
        return $explicit;
    }
    if (!function_exists('planner_public_site_url')) {
        return '';
    }
    $built = planner_public_site_url('/api/max_webhook.php');

    return planner_max_webhook_url_is_valid($built) ? $built : '';
}

/**
 * @return array{ok: bool, http_code: int, data: mixed, error?: string, raw?: string}
 */
function planner_max_api_request(string $method, string $apiPath, ?array $jsonBody = null, ?int $orgId = null): array
{
    $runtime = planner_max_runtime_settings($orgId);
    $token = planner_max_normalize_token((string) ($runtime['token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'no_token'];
    }
    $apiPath = '/' . ltrim($apiPath, '/');
    $url = 'https://platform-api.max.ru' . $apiPath;
    $method = strtoupper($method);
    $headers = [
        'Authorization: ' . $token,
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
    ];
    $payload = '';
    if ($jsonBody !== null) {
        try {
            $payload = json_encode($jsonBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            return ['ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'json_encode'];
        }
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'curl_missing'];
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'curl_init'];
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(5, (int) (($runtime['timeout'] ?? 12) ?: 12)),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($payload !== '') {
        $opts[CURLOPT_POSTFIELDS] = $payload;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['ok' => false, 'http_code' => $code, 'data' => null, 'error' => $err !== '' ? $err : 'curl_failed'];
    }
    $decoded = null;
    if (is_string($raw) && $raw !== '') {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = $raw;
        }
    }
    $ok = $code >= 200 && $code < 300;
    return [
        'ok' => $ok,
        'http_code' => $code,
        'data' => $decoded,
        'raw' => is_string($raw) ? $raw : '',
        'error' => $ok ? null : ('http_' . $code),
    ];
}

/** @return array{ok: bool, items: array, error?: string} */
function planner_max_subscriptions_list(?int $orgId = null): array
{
    $res = planner_max_api_request('GET', '/subscriptions', null, $orgId);
    if (!$res['ok']) {
        return ['ok' => false, 'items' => [], 'error' => (string) ($res['error'] ?? 'api_error'), 'http_code' => $res['http_code']];
    }
    $data = $res['data'];
    if (is_array($data) && isset($data['subscriptions']) && is_array($data['subscriptions'])) {
        return ['ok' => true, 'items' => $data['subscriptions']];
    }
    if (is_array($data) && array_is_list($data)) {
        return ['ok' => true, 'items' => $data];
    }

    return ['ok' => true, 'items' => is_array($data) ? [$data] : []];
}

/** Секрет webhook по правилам MAX: 5–256 символов, [a-zA-Z0-9_-]. */
function planner_max_webhook_secret_is_valid(string $secret): bool
{
    $secret = trim($secret);

    return $secret !== '' && (bool) preg_match('/^[a-zA-Z0-9_-]{5,256}$/', $secret);
}

function planner_max_generate_webhook_secret(): string
{
    return bin2hex(random_bytes(16));
}

/** @return array{ok: bool, http_code?: int, error?: string} */
function planner_max_subscription_delete(string $webhookUrl, ?int $orgId = null): array
{
    $webhookUrl = trim($webhookUrl);
    if ($webhookUrl === '') {
        return ['ok' => false, 'error' => 'empty_url'];
    }
    $res = planner_max_api_request('DELETE', '/subscriptions?url=' . rawurlencode($webhookUrl), null, $orgId);

    return ['ok' => !empty($res['ok']), 'http_code' => (int) ($res['http_code'] ?? 0), 'error' => $res['error'] ?? null];
}

/** Снимает все webhook-подписки бота (чтобы не осталась старая с другим secret). */
function planner_max_subscriptions_clear(?int $orgId = null): array
{
    $listed = planner_max_subscriptions_list($orgId);
    if (!$listed['ok']) {
        return ['ok' => false, 'removed' => 0, 'error' => (string) ($listed['error'] ?? 'list_failed')];
    }
    $removed = 0;
    foreach ($listed['items'] as $sub) {
        if (!is_array($sub)) {
            continue;
        }
        $subUrl = trim((string) ($sub['url'] ?? ''));
        if ($subUrl === '') {
            continue;
        }
        $del = planner_max_subscription_delete($subUrl, $orgId);
        if (!empty($del['ok'])) {
            $removed++;
        }
    }

    return ['ok' => true, 'removed' => $removed];
}

function planner_max_ensure_webhook_secret(int $orgId, int $actorUserId, bool $forceNew = false): string
{
    if (!planner_table_exists('max_settings') || !planner_table_has_column('max_settings', 'webhook_secret')) {
        return '';
    }
    $st = db()->prepare('SELECT webhook_secret FROM max_settings WHERE organization_id = ? LIMIT 1');
    $st->execute([$orgId]);
    $secret = trim((string) ($st->fetchColumn() ?: ''));
    if (!$forceNew && planner_max_webhook_secret_is_valid($secret)) {
        return $secret;
    }
    $secret = planner_max_generate_webhook_secret();
    $st = db()->prepare(
        'INSERT INTO max_settings (organization_id, token, fallback_chat_id, webhook_secret, updated_by)
         VALUES (?, \'\', \'\', ?, ?)
         ON DUPLICATE KEY UPDATE webhook_secret = VALUES(webhook_secret), updated_by = VALUES(updated_by)'
    );
    $st->execute([$orgId, $secret, $actorUserId > 0 ? $actorUserId : null]);
    return $secret;
}

/**
 * Регистрирует webhook в MAX (после смены токена это обязательно).
 *
 * @return array{ok: bool, webhook_url?: string, secret_saved?: bool, subscriptions?: array, error?: string, message?: string}
 */
function planner_max_webhook_register(
    int $orgId,
    int $actorUserId,
    ?string $webhookUrl = null,
    ?string $webhookSecret = null,
    bool $regenerateSecret = false
): array {
    if (!planner_max_enabled()) {
        return ['ok' => false, 'error' => 'not_configured', 'message' => 'Не задан токен MAX (config или max_settings).'];
    }
    $url = trim((string) ($webhookUrl ?? ''));
    if ($url === '') {
        $url = planner_max_webhook_endpoint_url();
    }
    if ($url === '' || !planner_max_webhook_url_is_valid($url)) {
        return [
            'ok' => false,
            'error' => 'bad_webhook_url',
            'message' => 'Некорректный webhook URL. Задайте PLANNER_MAX_WEBHOOK_URL или max.webhook_url в config.local.php, '
                . 'либо передайте --url=https://planiro75.ru/public/api/max_webhook.php . '
                . 'Нельзя использовать URL с /cron/ или ruwww (ошибка старого CLI).',
            'computed_url' => $url,
            'expected_example' => 'https://planiro75.ru/public/api/max_webhook.php',
        ];
    }
    $secret = trim((string) ($webhookSecret ?? ''));
    if ($regenerateSecret) {
        $secret = planner_max_ensure_webhook_secret($orgId, $actorUserId, true);
    } elseif ($secret === '') {
        $secret = planner_max_ensure_webhook_secret($orgId, $actorUserId);
    } elseif (!planner_max_webhook_secret_is_valid($secret)) {
        return [
            'ok' => false,
            'error' => 'bad_secret_format',
            'message' => 'Секрет webhook: только латиница, цифры, дефис и подчёркивание, от 5 до 256 символов.',
        ];
    } elseif (planner_table_exists('max_settings') && planner_table_has_column('max_settings', 'webhook_secret')) {
        $st = db()->prepare(
            'INSERT INTO max_settings (organization_id, token, fallback_chat_id, webhook_secret, updated_by)
             VALUES (?, \'\', \'\', ?, ?)
             ON DUPLICATE KEY UPDATE webhook_secret = VALUES(webhook_secret), updated_by = VALUES(updated_by)'
        );
        $st->execute([$orgId, $secret, $actorUserId > 0 ? $actorUserId : null]);
    }
    if ($secret === '') {
        return ['ok' => false, 'error' => 'no_secret', 'message' => 'Нужна миграция max_settings.webhook_secret.'];
    }
    $cleared = planner_max_subscriptions_clear($orgId);
    $body = [
        'url' => $url,
        'update_types' => planner_max_webhook_update_types(),
        'secret' => $secret,
    ];
    $res = planner_max_api_request('POST', '/subscriptions', $body, $orgId);
    if (!$res['ok']) {
        $msg = 'MAX API отклонил регистрацию webhook (HTTP ' . (int) $res['http_code'] . ').';
        if (is_string($res['raw'] ?? null) && trim((string) $res['raw']) !== '') {
            $msg .= ' ' . trim((string) $res['raw']);
        }
        return ['ok' => false, 'error' => 'api_error', 'message' => $msg, 'http_code' => $res['http_code']];
    }
    $listed = planner_max_subscriptions_list($orgId);
    log_activity($orgId, $actorUserId, 'update', 'max_settings', null, 'Зарегистрирован MAX webhook: ' . $url);
    return [
        'ok' => true,
        'webhook_url' => $url,
        'webhook_secret' => $secret,
        'webhook_secret_len' => strlen($secret),
        'subscriptions_removed' => (int) ($cleared['removed'] ?? 0),
        'secret_hint' => 'Секрет сохранён в max_settings (' . strlen($secret) . ' симв.) — MAX шлёт его в X-Max-Bot-Api-Secret.',
        'subscriptions' => $listed['items'] ?? [],
        'update_types' => planner_max_webhook_update_types(),
    ];
}

/** Абсолютная ссылка на задачу, если задан app.public_origin / PLANNER_PUBLIC_ORIGIN. */
function planner_public_task_link(int $taskId): string
{
    if (function_exists('planner_public_site_url')) {
        $link = planner_public_site_url('/tasks.php?open=' . $taskId);
        if (str_starts_with(strtolower($link), 'http')) {
            return $link;
        }
    }

    return planner_url('/tasks.php?open=' . $taskId);
}

/**
 * @return list<string>
 */
function planner_max_task_assistant_names(int $taskId): array
{
    if (!planner_table_exists('task_participants')) {
        return [];
    }
    $st = db()->prepare(
        "SELECT u.name FROM task_participants tp
         INNER JOIN users u ON u.id = tp.user_id
         WHERE tp.task_id = ? AND tp.role = 'assistant'
         ORDER BY u.name"
    );
    $st->execute([$taskId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $n = trim((string) ($r['name'] ?? ''));
        if ($n !== '') {
            $out[] = $n;
        }
    }

    return $out;
}

/**
 * @return int[]
 */
function planner_max_task_assistant_ids(int $taskId): array
{
    if (!planner_table_exists('task_participants')) {
        return [];
    }
    $st = db()->prepare(
        "SELECT user_id FROM task_participants WHERE task_id = ? AND role = 'assistant'"
    );
    $st->execute([$taskId]);
    return array_values(array_unique(array_filter(array_map(
        static fn (array $r): int => (int) ($r['user_id'] ?? 0),
        $st->fetchAll(PDO::FETCH_ASSOC)
    ), static fn (int $id): bool => $id > 0)));
}

function planner_max_user_chat_id(int $orgId, int $userId): string
{
    $runtime = planner_max_runtime_settings($orgId);
    $fallback = trim((string) ($runtime['chat_id'] ?? ''));
    if ($userId <= 0 || !planner_table_has_column('users', 'max_chat_id')) {
        return $fallback;
    }
    $st = db()->prepare('SELECT max_chat_id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$userId, $orgId]);
    $personal = trim((string) ($st->fetchColumn() ?: ''));
    return $personal !== '' ? $personal : $fallback;
}

function planner_max_events_table_ready(): bool
{
    if (planner_table_exists('notification_events')) {
        return true;
    }
    // Fallback: в некоторых окружениях SHOW TABLES может быть ограничен правами,
    // при этом прямой SELECT по существующей таблице работает.
    try {
        db()->query('SELECT id FROM notification_events LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Карта событий MAX -> поля настроек подписок в user_max_prefs.
 *
 * @return array<string,string>
 */
function planner_max_event_pref_column_map(): array
{
    return [
        'task_created' => 'notify_task_created',
        'task_assigned' => 'notify_task_assigned',
        'task_status_changed' => 'notify_task_status_changed',
        'task_due_changed' => 'notify_task_due_changed',
        'task_comment_mention' => 'notify_task_comment_mention',
        'task_participant_added' => 'notify_task_participant_added',
        'direct_message' => 'notify_direct_message',
        'sla_due_2h' => 'notify_sla_due_2h',
        'sla_overdue_24h' => 'notify_sla_overdue_24h',
        'sla_stale_days' => 'notify_sla_stale_days',
        'digest_summary' => 'notify_direct_message',
    ];
}

/**
 * @return array<string,bool>
 */
function planner_max_default_user_prefs(): array
{
    return [
        'enabled' => true,
        'notify_task_created' => true,
        'notify_task_assigned' => true,
        'notify_task_status_changed' => true,
        'notify_task_due_changed' => true,
        'notify_task_comment_mention' => true,
        'notify_task_participant_added' => true,
        'notify_direct_message' => true,
        'notify_sla_due_2h' => true,
        'notify_sla_overdue_24h' => true,
        'notify_sla_stale_days' => true,
        'digest_enabled' => false,
        'digest_interval_minutes' => 60,
        'quiet_mode_enabled' => false,
        'quiet_start' => '20:00',
        'quiet_end' => '08:00',
    ];
}

/**
 * @return array<string,bool>
 */
function planner_max_user_prefs(int $orgId, int $userId): array
{
    $defaults = planner_max_default_user_prefs();
    if ($orgId <= 0 || $userId <= 0 || !planner_table_exists('user_max_prefs')) {
        return $defaults;
    }
    $out = $defaults;
    try {
        $cols = ['enabled', 'notify_task_created', 'notify_task_assigned', 'notify_task_status_changed', 'notify_task_due_changed', 'notify_task_comment_mention', 'notify_task_participant_added', 'notify_direct_message', 'notify_sla_due_2h', 'notify_sla_overdue_24h', 'notify_sla_stale_days', 'digest_enabled', 'digest_interval_minutes', 'quiet_mode_enabled', 'quiet_start', 'quiet_end'];
        $sel = [];
        foreach ($cols as $c) {
            if (planner_table_has_column('user_max_prefs', $c)) {
                $sel[] = $c;
            }
        }
        if ($sel === []) {
            return $out;
        }
        $st = db()->prepare(
            'SELECT ' . implode(', ', $sel) . '
             FROM user_max_prefs
             WHERE organization_id = ? AND user_id = ? LIMIT 1'
        );
        $st->execute([$orgId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $out;
        }
        foreach (array_keys($defaults) as $k) {
            if (array_key_exists($k, $row)) {
                if (in_array($k, ['quiet_start', 'quiet_end'], true)) {
                    $v = trim((string) $row[$k]);
                    if (preg_match('/^\d{2}:\d{2}$/', $v)) {
                        $out[$k] = $v;
                    }
                } elseif ($k === 'digest_interval_minutes') {
                    $out[$k] = max(15, min(1440, (int) $row[$k]));
                } else {
                    $out[$k] = (int) $row[$k] === 1;
                }
            }
        }
    } catch (Throwable $e) {
        return $defaults;
    }
    return $out;
}

function planner_max_find_user_by_chat_id(string $chatId): ?array
{
    $chatId = trim($chatId);
    if ($chatId === '' || !planner_table_has_column('users', 'max_chat_id')) {
        return null;
    }
    $st = db()->prepare(
        'SELECT id, organization_id, name, role, max_chat_id
         FROM users
         WHERE max_chat_id = ?
         ORDER BY id ASC
         LIMIT 1'
    );
    $st->execute([$chatId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Поиск пользователя по chat_id диалога или user_id отправителя (в личке MAX они часто совпадают).
 *
 * @return array{user: array, reply_chat_id: string}|null
 */
function planner_max_find_user_for_webhook(string $chatId, string $senderUserId = '', array $extraLookupIds = []): ?array
{
    $chatId = trim($chatId);
    $senderUserId = trim($senderUserId);
    $candidates = [];
    foreach (array_merge([$chatId, $senderUserId], $extraLookupIds) as $id) {
        $id = trim((string) $id);
        if ($id !== '' && !in_array($id, $candidates, true)) {
            $candidates[] = $id;
        }
    }
    foreach ($candidates as $id) {
        $u = planner_max_find_user_by_chat_id($id);
        if ($u) {
            $stored = trim((string) ($u['max_chat_id'] ?? ''));
            return [
                'user' => $u,
                'reply_chat_id' => $stored !== '' ? $stored : $id,
            ];
        }
    }

    return null;
}

/**
 * @return array{token:string,expires_at:string}|array{}
 */
function planner_max_create_action_token(int $orgId, int $userId, int $taskId, string $action, int $ttlSec = 86400): array
{
    if (!planner_table_exists('max_action_tokens')) {
        return [];
    }
    $token = bin2hex(random_bytes(24));
    $ttlSec = max(300, min(604800, $ttlSec));
    $st = db()->prepare(
        'INSERT INTO max_action_tokens (token, organization_id, user_id, task_id, action, expires_at, used_at, created_at)
         VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NULL, NOW())'
    );
    $st->execute([$token, $orgId, $userId, $taskId, $action, $ttlSec]);
    $exp = db()->query("SELECT DATE_FORMAT(DATE_ADD(NOW(), INTERVAL {$ttlSec} SECOND), '%Y-%m-%d %H:%i:%s')")->fetchColumn();
    return ['token' => $token, 'expires_at' => (string) $exp];
}

function planner_max_user_pref_allows_event(int $orgId, int $userId, string $eventType): bool
{
    $prefs = planner_max_user_prefs($orgId, $userId);
    if (empty($prefs['enabled'])) {
        return false;
    }
    $map = planner_max_event_pref_column_map();
    $key = $map[$eventType] ?? '';
    if ($key === '') {
        return true;
    }
    return !empty($prefs[$key]);
}

function planner_max_worker_heartbeat_path(): string
{
    return dirname(__DIR__) . '/var/log/max-worker-heartbeat.json';
}

function planner_max_mark_worker_heartbeat(string $source = 'worker'): void
{
    $baseDir = dirname(__DIR__) . '/var/log';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
    }
    $payload = [
        'ts' => date('c'),
        'epoch' => time(),
        'source' => trim($source) !== '' ? $source : 'worker',
        'pid' => function_exists('getmypid') ? (int) getmypid() : null,
    ];
    @file_put_contents(
        planner_max_worker_heartbeat_path(),
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );
}

/**
 * @return array<string,mixed>
 */
function planner_max_queue_metrics(int $orgId, int $stalePendingMinutes = 15): array
{
    $stalePendingMinutes = max(1, min(1440, $stalePendingMinutes));
    $out = [
        'queue_enabled' => planner_max_enabled(),
        'events_table_ready' => planner_max_events_table_ready(),
        'counts' => [
            'pending' => 0,
            'pending_ready' => 0,
            'pending_deferred' => 0,
            'sent' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'total' => 0,
        ],
        'pending_oldest_age_minutes' => null,
        'heartbeat' => [
            'ok' => false,
            'last_ts' => null,
            'age_minutes' => null,
        ],
        'alerts' => [],
    ];

    if (!$out['events_table_ready']) {
        $out['alerts'][] = 'Таблица notification_events отсутствует: мониторинг ограничен.';
        return $out;
    }

    $st = db()->prepare(
        "SELECT status, COUNT(*) AS cnt
         FROM notification_events
         WHERE organization_id = ?
         GROUP BY status"
    );
    $st->execute([$orgId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $status = (string) ($r['status'] ?? '');
        $cnt = (int) ($r['cnt'] ?? 0);
        if (array_key_exists($status, $out['counts'])) {
            $out['counts'][$status] = $cnt;
        }
    }
    $out['counts']['total'] = (int) (
        $out['counts']['pending']
        + $out['counts']['sent']
        + $out['counts']['failed']
        + $out['counts']['cancelled']
    );

    $stReadyCnt = db()->prepare(
        "SELECT COUNT(*) FROM notification_events
         WHERE organization_id = ? AND status = 'pending'
           AND (next_retry_at IS NULL OR next_retry_at <= NOW())"
    );
    $stReadyCnt->execute([$orgId]);
    $out['counts']['pending_ready'] = (int) $stReadyCnt->fetchColumn();

    $stDefCnt = db()->prepare(
        "SELECT COUNT(*) FROM notification_events
         WHERE organization_id = ? AND status = 'pending' AND next_retry_at > NOW()"
    );
    $stDefCnt->execute([$orgId]);
    $out['counts']['pending_deferred'] = (int) $stDefCnt->fetchColumn();

    $stOld = db()->prepare(
        "SELECT TIMESTAMPDIFF(MINUTE, MIN(created_at), NOW()) AS age_min
         FROM notification_events
         WHERE organization_id = ? AND status = 'pending'
           AND (next_retry_at IS NULL OR next_retry_at <= NOW())"
    );
    $stOld->execute([$orgId]);
    $oldAge = $stOld->fetchColumn();
    $out['pending_oldest_age_minutes'] = $oldAge !== false && $oldAge !== null ? (int) $oldAge : null;

    $hbPath = planner_max_worker_heartbeat_path();
    if (is_file($hbPath)) {
        $raw = @file_get_contents($hbPath);
        if (is_string($raw) && $raw !== '') {
            try {
                $hb = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $lastEpoch = isset($hb['epoch']) ? (int) $hb['epoch'] : 0;
                $lastTs = isset($hb['ts']) ? (string) $hb['ts'] : null;
                if ($lastEpoch > 0) {
                    $ageMin = (int) floor((time() - $lastEpoch) / 60);
                    $out['heartbeat'] = [
                        'ok' => $ageMin <= $stalePendingMinutes,
                        'last_ts' => $lastTs,
                        'age_minutes' => $ageMin,
                    ];
                }
            } catch (Throwable $e) {
                // ignore malformed heartbeat file
            }
        }
    }

    if ((int) $out['counts']['failed'] > 0) {
        $out['alerts'][] = 'Есть неотправленные сообщения MAX со статусом failed.';
    }
    if (
        (int) $out['counts']['pending_ready'] > 0
        && $out['pending_oldest_age_minutes'] !== null
        && (int) $out['pending_oldest_age_minutes'] > $stalePendingMinutes
    ) {
        $out['alerts'][] = 'В очереди есть готовые к отправке pending старше '
            . $stalePendingMinutes
            . ' мин. Нажмите «Прогнать воркер сейчас» или включите cron '
            . '(public/cron/max_notify_worker.php каждую минуту).';
    }
    if ((int) $out['counts']['pending_deferred'] > 0 && (int) $out['counts']['pending_ready'] === 0) {
        $out['alerts'][] = 'Часть pending отложена (quiet hours / повтор): '
            . (int) $out['counts']['pending_deferred']
            . ' шт. — это нормально до наступления next_retry_at.';
    }
    if (
        !$out['heartbeat']['ok']
        && (int) $out['counts']['pending_ready'] > 0
    ) {
        $out['alerts'][] = 'Нет свежего heartbeat воркера MAX: проверьте cron/планировщик '
            . '(var/log/max-worker-heartbeat.json).';
    }

    return $out;
}

function planner_max_retry_failed_events(int $orgId, int $limit = 100): int
{
    if (!planner_max_events_table_ready()) {
        return 0;
    }
    $limit = max(1, min(1000, $limit));
    $ids = db()->prepare(
        "SELECT id FROM notification_events
         WHERE organization_id = ? AND status = 'failed'
         ORDER BY id DESC
         LIMIT $limit"
    );
    $ids->execute([$orgId]);
    $rows = $ids->fetchAll(PDO::FETCH_ASSOC);
    $n = 0;
    $up = db()->prepare(
        "UPDATE notification_events
         SET status = 'pending', next_retry_at = NOW(), last_error = NULL
         WHERE id = ?"
    );
    foreach ($rows as $r) {
        $id = (int) ($r['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $up->execute([$id]);
        $n++;
    }
    return $n;
}

/**
 * @return array{total:int,items:array<int,array<string,mixed>>}
 */
function planner_max_queue_list(int $orgId, string $status = '', string $eventType = '', string $userQuery = '', int $limit = 100): array
{
    if (!planner_max_events_table_ready()) {
        return ['total' => 0, 'items' => []];
    }
    $limit = max(1, min(500, $limit));
    $where = ['ne.organization_id = ?'];
    $params = [$orgId];
    if ($status !== '' && in_array($status, ['pending', 'sent', 'failed', 'cancelled'], true)) {
        $where[] = 'ne.status = ?';
        $params[] = $status;
    }
    if ($eventType !== '') {
        $where[] = 'ne.event_type = ?';
        $params[] = $eventType;
    }
    if ($userQuery !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
        $like = '%' . $userQuery . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $wSql = implode(' AND ', $where);
    $stCnt = db()->prepare("SELECT COUNT(*) FROM notification_events ne LEFT JOIN users u ON u.id = ne.user_id WHERE $wSql");
    $stCnt->execute($params);
    $total = (int) ($stCnt->fetchColumn() ?: 0);
    $sql = "SELECT ne.id, ne.status, ne.event_type, ne.task_id, ne.attempts, ne.next_retry_at, ne.sent_at, ne.last_error, ne.created_at,
                   ne.payload_json, u.name AS user_name, u.email AS user_email
            FROM notification_events ne
            LEFT JOIN users u ON u.id = ne.user_id
            WHERE $wSql
            ORDER BY ne.id DESC
            LIMIT $limit";
    $st = db()->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $text = '';
        $payload = json_decode((string) ($item['payload_json'] ?? ''), true);
        if (is_array($payload)) {
            $text = trim((string) ($payload['text'] ?? ''));
        }
        $item['text'] = $text;
    }
    unset($item);

    return ['total' => $total, 'items' => $items];
}

/**
 * @return list<array<string,mixed>>
 */
function planner_max_digest_list(int $orgId, int $limit = 200): array
{
    if ($orgId <= 0 || !planner_table_exists('max_digest_queue')) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    $st = db()->prepare(
        "SELECT d.id, d.event_type, d.task_id, d.text, d.created_at, u.name AS user_name, u.email AS user_email
         FROM max_digest_queue d
         LEFT JOIN users u ON u.id = d.user_id
         WHERE d.organization_id = ?
         ORDER BY d.id DESC
         LIMIT $limit"
    );
    $st->execute([$orgId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Отмена, повтор или повторная отправка одного события очереди MAX.
 *
 * @return 'ok'|'not_found'|'bad_action'|'bad_status'
 */
function planner_max_queue_apply_action(int $orgId, int $eventId, string $action): string
{
    if ($orgId <= 0 || $eventId <= 0 || !planner_max_events_table_ready()) {
        return 'not_found';
    }
    if (!in_array($action, ['cancel', 'retry', 'resend'], true)) {
        return 'bad_action';
    }
    $st = db()->prepare(
        'SELECT id, status FROM notification_events WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $st->execute([$eventId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return 'not_found';
    }
    $status = (string) ($row['status'] ?? '');
    if ($action === 'cancel') {
        if ($status !== 'pending') {
            return 'bad_status';
        }
        db()->prepare(
            "UPDATE notification_events
             SET status = 'cancelled', next_retry_at = NULL, last_error = ?
             WHERE id = ? AND organization_id = ? AND status = 'pending'"
        )->execute(['Отменено администратором', $eventId, $orgId]);

        return 'ok';
    }
    if ($action === 'retry' && !in_array($status, ['failed', 'cancelled'], true)) {
        return 'bad_status';
    }
    if ($action === 'resend' && $status !== 'sent') {
        return 'bad_status';
    }
    db()->prepare(
        "UPDATE notification_events
         SET status = 'pending', attempts = 0, next_retry_at = NOW(), sent_at = NULL, last_error = NULL
         WHERE id = ? AND organization_id = ?"
    )->execute([$eventId, $orgId]);

    return 'ok';
}

function planner_max_digest_cancel(int $orgId, int $digestId): bool
{
    if ($orgId <= 0 || $digestId <= 0 || !planner_table_exists('max_digest_queue')) {
        return false;
    }
    $st = db()->prepare('DELETE FROM max_digest_queue WHERE id = ? AND organization_id = ?');
    $st->execute([$digestId, $orgId]);

    return $st->rowCount() > 0;
}

/**
 * Старый fallback: буфер в памяти запроса + отправка после ответа клиенту.
 */
function planner_max_legacy_runtime_queue(string $chatId, string $text): void
{
    $chatId = trim($chatId);
    $text = trim($text);
    if ($chatId === '' || $text === '') {
        return;
    }
    if (!isset($GLOBALS['_planner_max_queue']) || !is_array($GLOBALS['_planner_max_queue'])) {
        $GLOBALS['_planner_max_queue'] = [];
    }
    $GLOBALS['_planner_max_queue'][] = ['chat_id' => $chatId, 'text' => $text];
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;
    register_shutdown_function(static function (): void {
        if (!isset($GLOBALS['_planner_max_queue']) || !is_array($GLOBALS['_planner_max_queue'])) {
            return;
        }
        $msgs = $GLOBALS['_planner_max_queue'];
        $GLOBALS['_planner_max_queue'] = [];
        if ($msgs === []) {
            return;
        }
        try {
            if (function_exists('planner_http_release_client')) {
                planner_http_release_client();
            }
            @ignore_user_abort(true);
            @set_time_limit(30);
            foreach ($msgs as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $chatId = trim((string) ($item['chat_id'] ?? ''));
                $text = trim((string) ($item['text'] ?? ''));
                if ($chatId === '' || $text === '') {
                    continue;
                }
                try {
                    planner_max_send_chat_message($chatId, $text);
                } catch (Throwable $e) {
                    planner_max_log_failure(0, $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            planner_max_log_failure(0, $e->getMessage());
        }
    });
}

function planner_max_dedupe_key(string $eventType, int $orgId, int $userId, int $taskId, int $bucketSec = 120): string
{
    $bucketSec = max(30, min(3600, $bucketSec));
    $bucket = (int) floor(time() / $bucketSec);
    return implode(':', [$eventType, $orgId, $userId, $taskId, $bucket]);
}

/**
 * @param array<string,mixed> $payload
 */
function planner_max_enqueue_event(
    int $orgId,
    int $userId,
    int $taskId,
    string $eventType,
    string $text,
    array $payload = [],
    int $bucketSec = 120
): void {
    if (!planner_max_enabled()) {
        return;
    }
    if (!planner_max_user_pref_allows_event($orgId, $userId, $eventType)) {
        return;
    }
    $chatId = planner_max_user_chat_id($orgId, $userId);
    if ($chatId === '') {
        return;
    }
    $text = trim($text);
    if ($text === '') {
        return;
    }
    $payload['text'] = $text;
    $payload['chat_id'] = $chatId;
    $payload['event_type'] = $eventType;
    $payload['task_id'] = $taskId > 0 ? $taskId : null;
    if (!planner_max_events_table_ready()) {
        planner_max_legacy_runtime_queue($chatId, $text);
        return;
    }
    $prefsForDigest = planner_max_user_prefs($orgId, $userId);
    if (
        !planner_max_is_critical_event($eventType)
        && !empty($prefsForDigest['digest_enabled'])
        && planner_table_exists('max_digest_queue')
        && $eventType !== 'digest_summary'
        && $eventType !== 'direct_message'
    ) {
        db()->prepare(
            'INSERT INTO max_digest_queue (organization_id, user_id, event_type, task_id, text, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$orgId, $userId, $eventType, $taskId > 0 ? $taskId : null, $text]);
        return;
    }
    $dedupeKey = planner_max_dedupe_key($eventType, $orgId, $userId, max(0, $taskId), $bucketSec);
    $payload['message_id'] = hash('sha256', $dedupeKey);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $sql = "INSERT INTO notification_events
        (organization_id, user_id, task_id, event_type, dedupe_key, payload_json, status, attempts, next_retry_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
          payload_json = VALUES(payload_json),
          status = CASE WHEN status IN ('failed','cancelled') THEN 'pending' ELSE status END,
          next_retry_at = NOW()";
    db()->prepare($sql)->execute([
        $orgId,
        $userId,
        $taskId > 0 ? $taskId : null,
        $eventType,
        $dedupeKey,
        $json,
    ]);
    static $kickRegistered = false;
    if ($kickRegistered) {
        return;
    }
    $kickRegistered = true;
    register_shutdown_function(static function (): void {
        try {
            if (function_exists('planner_http_release_client')) {
                planner_http_release_client();
            }
            @ignore_user_abort(true);
            @set_time_limit(15);
            planner_max_process_queue(50, 3);
        } catch (Throwable $e) {
            planner_max_log_failure(0, 'queue_kick: ' . $e->getMessage());
        }
    });
}

/**
 * Поставить сообщение в очередь: уйдёт в MAX после ответа клиенту (не тормозит сохранение задачи).
 */
function planner_max_queue_user_message(int $orgId, int $userId, string $text): void
{
    planner_max_enqueue_event($orgId, $userId, 0, 'direct_message', $text, [], 120);
}

/** Ошибки, при которых повтор бесполезен (неверный chat_id, чат не найден). */
function planner_max_is_permanent_send_error(string $message): bool
{
    $m = strtolower($message);

    return str_contains($m, 'chat.not.found')
        || (str_contains($m, '404') && str_contains($m, 'chat'));
}

function planner_max_retry_delay_seconds(int $attempts): int
{
    if ($attempts <= 1) {
        return 60;
    }
    if ($attempts === 2) {
        return 300;
    }
    if ($attempts === 3) {
        return 900;
    }
    return 3600;
}

function planner_max_process_queue(int $limit = 100, int $maxBatches = 8): array
{
    $limit = max(1, min(500, $limit));
    $maxBatches = max(1, min(20, $maxBatches));
    if (!planner_max_enabled() || !planner_max_events_table_ready()) {
        return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'batches' => 0];
    }
    planner_max_enqueue_sla_escalations();
    planner_max_flush_due_digests();
    $sql = "SELECT id, organization_id, user_id, event_type, payload_json, attempts
            FROM notification_events
            WHERE status = 'pending' AND (next_retry_at IS NULL OR next_retry_at <= NOW())
            ORDER BY id ASC LIMIT $limit";
    $out = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'batches' => 0];
    for ($batchNo = 0; $batchNo < $maxBatches; $batchNo++) {
        $rows = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            break;
        }
        $out['batches']++;
        $out['processed'] += count($rows);
        foreach ($rows as $r) {
        $id = (int) ($r['id'] ?? 0);
        $orgId = (int) ($r['organization_id'] ?? 0);
        $userId = (int) ($r['user_id'] ?? 0);
        $attempts = (int) ($r['attempts'] ?? 0);
        $payload = [];
        try {
            $payload = json_decode((string) ($r['payload_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $payload = [];
        }
        $chatId = trim((string) ($payload['chat_id'] ?? planner_max_user_chat_id($orgId, $userId)));
        $text = trim((string) ($payload['text'] ?? ''));
        $eventType = trim((string) ($r['event_type'] ?? ''));
        $messageId = trim((string) ($payload['message_id'] ?? ''));
        if ($messageId === '') {
            $messageId = hash('sha256', (string) $id . ':' . $eventType);
        }
        if ($chatId === '' || $text === '') {
            db()->prepare("UPDATE notification_events SET status = 'cancelled', last_error = ? WHERE id = ?")
                ->execute(['empty_chat_or_text', $id]);
            $out['skipped']++;
            continue;
        }
        try {
            $rt = planner_max_runtime_settings($orgId);
            if (
                !empty($rt['quiet_hours_enabled'])
                && !planner_max_is_critical_event($eventType)
                && planner_max_is_quiet_hours_now((string) $rt['quiet_start'], (string) $rt['quiet_end'])
            ) {
                $qDelay = planner_max_quiet_hours_delay_seconds((string) $rt['quiet_end']);
                db()->prepare("UPDATE notification_events
                    SET next_retry_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ?
                    WHERE id = ?")
                    ->execute([$qDelay, 'deferred_by_quiet_hours', $id]);
                $out['skipped']++;
                continue;
            }
            $uprefs = planner_max_user_prefs($orgId, $userId);
            if (
                !empty($uprefs['quiet_mode_enabled'])
                && !planner_max_is_critical_event($eventType)
                && planner_max_is_quiet_hours_now((string) $uprefs['quiet_start'], (string) $uprefs['quiet_end'])
            ) {
                $qDelay = planner_max_quiet_hours_delay_seconds((string) $uprefs['quiet_end']);
                db()->prepare("UPDATE notification_events
                    SET next_retry_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ?
                    WHERE id = ?")
                    ->execute([$qDelay, 'deferred_by_user_quiet_mode', $id]);
                $out['skipped']++;
                continue;
            }
            planner_max_send_chat_message($chatId, $text, $orgId, $messageId);
            db()->prepare("UPDATE notification_events SET status = 'sent', sent_at = NOW(), attempts = attempts + 1, last_error = NULL WHERE id = ?")
                ->execute([$id]);
            $out['sent']++;
        } catch (Throwable $e) {
            $errMsg = (string) $e->getMessage();
            $nextAttempts = $attempts + 1;
            if (planner_max_is_permanent_send_error($errMsg)) {
                db()->prepare("UPDATE notification_events
                    SET status = 'failed', attempts = ?, next_retry_at = NULL, last_error = ?
                    WHERE id = ?")
                    ->execute([max(6, $nextAttempts), $errMsg, $id]);
                planner_max_move_event_to_dlq($id, $orgId, $userId, $eventType, $payload, $errMsg);
                $out['failed']++;
                continue;
            }
            $delay = planner_max_retry_delay_seconds($nextAttempts);
            $status = $nextAttempts >= 6 ? 'failed' : 'pending';
            db()->prepare("UPDATE notification_events
                SET status = ?, attempts = ?, next_retry_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ?
                WHERE id = ?")
                ->execute([$status, $nextAttempts, $delay, $errMsg, $id]);
            if ($status === 'failed') {
                planner_max_move_event_to_dlq($id, $orgId, $userId, $eventType, $payload, $errMsg);
            }
            $out['failed']++;
        }
        }
        if (count($rows) < $limit) {
            break;
        }
    }
    if ($out['processed'] > 0) {
        planner_max_mark_worker_heartbeat('queue');
    }

    return $out;
}

function planner_max_send_chat_message(string $chatId, string $text, int $orgId = 0, string $messageId = ''): void
{
    if (!planner_max_enabled()) {
        return;
    }
    $chatId = trim($chatId);
    if ($chatId === '') {
        return;
    }
    $text = trim($text);
    if ($text === '') {
        return;
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 3800) {
        $text = mb_substr($text, 0, 3800, 'UTF-8') . '…';
    } elseif (strlen($text) > 3800) {
        $text = substr($text, 0, 3800) . '…';
    }

    $runtime = planner_max_runtime_settings($orgId > 0 ? $orgId : null);
    $token = planner_max_normalize_token((string) ($runtime['token'] ?? ''));
    $url = 'https://platform-api.max.ru/messages?chat_id=' . rawurlencode($chatId);

    $payload = json_encode(['text' => $text, 'notify' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $timeout = (int) (($runtime['timeout'] ?? 12) ?: 12);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }
        $headers = [
            'Authorization: ' . $token,
            'Content-Type: application/json; charset=utf-8',
        ];
        if ($messageId !== '') {
            $headers[] = 'X-Idempotency-Key: ' . $messageId;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            return;
        }
        $detail = $err !== '' ? $err : (string) $raw;
        planner_max_log_failure($code, $detail);
        throw new RuntimeException('MAX API error ' . $code . ': ' . $detail);
    }

    $hdr = "Authorization: {$token}\r\nContent-Type: application/json; charset=utf-8\r\n";
    if ($messageId !== '') {
        $hdr .= "X-Idempotency-Key: {$messageId}\r\n";
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $hdr,
            'content' => $payload,
            'timeout' => (float) $timeout,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', (string) $http_response_header[0], $mh)) {
        $code = (int) $mh[1];
    }
    if ($code >= 200 && $code < 300) {
        return;
    }
    $detail = $raw !== false ? (string) $raw : 'file_get_contents failed';
    planner_max_log_failure($code, $detail);
    throw new RuntimeException('MAX API error ' . $code . ': ' . $detail);
}

/**
 * @param array<string,mixed> $payload
 */
function planner_max_move_event_to_dlq(
    int $eventId,
    int $orgId,
    int $userId,
    string $eventType,
    array $payload,
    string $errorText
): void {
    if (!planner_table_exists('notification_events_dlq')) {
        return;
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $st = db()->prepare(
        'INSERT INTO notification_events_dlq
         (event_id, organization_id, user_id, event_type, payload_json, error_text, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $st->execute([$eventId, $orgId, $userId, $eventType, $json, $errorText]);
}

function planner_max_enqueue_sla_escalations(): void
{
    if (!planner_max_enabled() || !planner_table_exists('tasks')) {
        return;
    }
    $rt = planner_max_runtime_settings();
    $staleDays = max(1, min(60, (int) ($rt['stale_days'] ?? 3)));
    $sql = "SELECT t.id, t.organization_id, t.title, t.due_date, t.updated_at, t.assignee_id
            FROM tasks t
            WHERE t.assignee_id IS NOT NULL
              AND t.status IN ('new', 'in_progress', 'review')
              AND (
                (t.due_date IS NOT NULL AND t.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR))
                OR (t.due_date IS NOT NULL AND t.due_date <= DATE_SUB(NOW(), INTERVAL 24 HOUR))
                OR (t.updated_at <= DATE_SUB(NOW(), INTERVAL {$staleDays} DAY))
              )
            ORDER BY t.id DESC
            LIMIT 200";
    $rows = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $orgId = (int) ($r['organization_id'] ?? 0);
        $taskId = (int) ($r['id'] ?? 0);
        $uid = (int) ($r['assignee_id'] ?? 0);
        if ($orgId <= 0 || $taskId <= 0 || $uid <= 0) {
            continue;
        }
        $title = trim((string) ($r['title'] ?? ''));
        if ($title === '') {
            $title = 'Задача #' . $taskId;
        }
        $due = trim((string) ($r['due_date'] ?? ''));
        $updated = trim((string) ($r['updated_at'] ?? ''));
        if ($due !== '' && strtotime($due) !== false) {
            $ts = (int) strtotime($due);
            if ($ts > time() && $ts <= time() + 7200) {
                $msg = "⚠ SLA: срок скоро\nЗадача: {$title}\nСрок: " . substr($due, 0, 16)
                    . "\nДействие: проверьте приоритет и завершите вовремя.\nОткрыть: " . planner_public_task_link($taskId);
                planner_max_enqueue_event($orgId, $uid, $taskId, 'sla_due_2h', $msg, ['due' => $due], 1800);
            }
            if ($ts <= time() - 86400) {
                $msg = "🚨 SLA: просрочка > 24ч\nЗадача: {$title}\nСрок: " . substr($due, 0, 16)
                    . "\nДействие: согласуйте новый срок или завершите задачу.\nОткрыть: " . planner_public_task_link($taskId);
                $doneTok = planner_max_create_action_token($orgId, $uid, $taskId, 'done');
                if ($doneTok !== []) {
                    $msg .= "\n✅ Выполнено: " . planner_public_site_url('/max_action.php?token=' . rawurlencode((string) $doneTok['token']));
                }
                planner_max_enqueue_event($orgId, $uid, $taskId, 'sla_overdue_24h', $msg, ['due' => $due], 3600);
            }
        }
        if ($updated !== '' && strtotime($updated) !== false && strtotime($updated) <= time() - ($staleDays * 86400)) {
            $msg = "📌 SLA: нет движения {$staleDays}+ дн.\nЗадача: {$title}\nПоследнее изменение: " . substr($updated, 0, 16)
                . "\nДействие: обновите статус или добавьте комментарий.\nОткрыть: " . planner_public_task_link($taskId);
            planner_max_enqueue_event($orgId, $uid, $taskId, 'sla_stale_days', $msg, ['updated_at' => $updated], 3600);
        }
    }
}

function planner_max_log_failure(int $httpCode, string $detail): void
{
    $baseDir = dirname(__DIR__) . '/var/log';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
    }
    $line = json_encode([
        'ts' => date('c'),
        'http' => $httpCode,
        'detail' => $detail !== '' ? (function_exists('mb_substr') ? mb_substr($detail, 0, 500, 'UTF-8') : substr($detail, 0, 500)) : null,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents($baseDir . '/max-notify.log', $line, FILE_APPEND);
}

/**
 * Унифицированный формат MAX-сообщения:
 * 1) заголовок; 2) контекст (кто/что); 3) следующее действие; 4) ссылка.
 *
 * @param array<string,string> $context
 */
function planner_max_build_message(
    string $title,
    array $context,
    string $nextAction,
    string $openUrl,
    string $emoji = '🔔'
): string {
    $lines = [$emoji . ' ' . trim($title)];
    foreach ($context as $k => $v) {
        $key = trim((string) $k);
        $val = trim((string) $v);
        if ($key === '' || $val === '') {
            continue;
        }
        $lines[] = $key . ': ' . $val;
    }
    $na = trim($nextAction);
    if ($na !== '') {
        $lines[] = 'Дальше: ' . $na;
    }
    $url = trim($openUrl);
    if ($url !== '') {
        $lines[] = 'Открыть: ' . $url;
    }
    return implode("\n", $lines);
}

function planner_notify_max_task_created(int $orgId, int $taskId): void
{
    if (!planner_max_enabled()) {
        return;
    }
    $st = db()->prepare(
        'SELECT t.title, t.assignee_id, p.name AS project_name, au.name AS author_name
         FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN users au ON au.id = t.author_id
         WHERE t.id = ? AND t.organization_id = ? LIMIT 1'
    );
    $st->execute([$taskId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $title = trim((string) ($row['title'] ?? ''));
    if ($title === '') {
        $title = 'Задача #' . $taskId;
    }
    $projectName = trim((string) ($row['project_name'] ?? ''));
    $authorName = trim((string) ($row['author_name'] ?? ''));
    if ($authorName === '') {
        $authorName = 'Пользователь';
    }
    $assigneeLine = '';
    $aid = isset($row['assignee_id']) ? (int) $row['assignee_id'] : 0;
    if ($aid > 0) {
        $stA = db()->prepare('SELECT name FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
        $stA->execute([$aid, $orgId]);
        $an = trim((string) ($stA->fetchColumn() ?: ''));
        if ($an !== '') {
            $assigneeLine = "\nИсполнитель: " . $an;
        }
    }
    $assistants = planner_max_task_assistant_names($taskId);
    $asLine = $assistants !== [] ? "\nСоисполнители: " . implode(', ', $assistants) : '';

    $ctx = [
        'Задача' => $title,
        'Проект' => $projectName,
        'Кто изменил' => $authorName,
    ];
    if ($assigneeLine !== '') {
        $ctx['Исполнитель'] = trim(str_replace("\nИсполнитель: ", '', $assigneeLine));
    }
    if ($asLine !== '') {
        $ctx['Соисполнители'] = trim(str_replace("\nСоисполнители: ", '', $asLine));
    }
    $msg = planner_max_build_message(
        'Создана новая задача',
        $ctx,
        'Проверьте детали и подтвердите план работ.',
        planner_public_task_link($taskId),
        '📋'
    );

    $recipientIds = [];
    $aid = isset($row['assignee_id']) ? (int) $row['assignee_id'] : 0;
    if ($aid > 0) {
        $recipientIds[] = $aid;
    }
    $recipientIds = array_merge($recipientIds, planner_max_task_assistant_ids($taskId));
    $recipientIds = array_values(array_unique(array_filter($recipientIds, static fn (int $id): bool => $id > 0)));
    foreach ($recipientIds as $uid) {
        planner_max_enqueue_event($orgId, $uid, $taskId, 'task_created', $msg, [
            'author_name' => $authorName,
            'assignee_id' => $aid > 0 ? $aid : null,
        ], 120);
    }
}

/**
 * @param int[] $addedUserIds
 */
function planner_notify_max_assistants_added(
    int $orgId,
    int $taskId,
    array $addedUserIds,
    int $actorUserId,
    string $taskTitle
): void {
    if (!planner_max_enabled()) {
        return;
    }
    $addedUserIds = array_values(array_unique(array_filter(array_map('intval', $addedUserIds), static fn (int $i) => $i > 0)));
    if ($addedUserIds === []) {
        return;
    }
    $ph = implode(',', array_fill(0, count($addedUserIds), '?'));
    $st = db()->prepare("SELECT name FROM users WHERE organization_id = ? AND id IN ($ph) ORDER BY name");
    $st->execute(array_merge([$orgId], $addedUserIds));
    $names = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $n = trim((string) ($r['name'] ?? ''));
        if ($n !== '') {
            $names[] = $n;
        }
    }
    if ($names === []) {
        return;
    }
    $stActor = db()->prepare('SELECT name FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $stActor->execute([$actorUserId, $orgId]);
    $actorName = trim((string) ($stActor->fetchColumn() ?: 'Пользователь'));
    $t = trim($taskTitle);
    if ($t === '') {
        $t = 'Задача #' . $taskId;
    }
    $msg = planner_max_build_message(
        'Обновлены соисполнители',
        [
            'Задача' => $t,
            'Что изменилось' => 'Добавлены: ' . implode(', ', $names),
            'Кто изменил' => $actorName,
        ],
        'Проверьте свою роль в задаче и ближайшие шаги.',
        planner_public_task_link($taskId),
        '👥'
    );

    foreach ($addedUserIds as $uid) {
        planner_max_enqueue_event($orgId, (int) $uid, $taskId, 'task_participant_added', $msg, [
            'added_user_ids' => $addedUserIds,
            'actor_user_id' => $actorUserId,
        ], 120);
    }
}

function planner_notify_max_task_assigned(int $orgId, int $taskId, int $assigneeId, int $actorUserId): void
{
    if (!planner_max_enabled() || $assigneeId <= 0 || $assigneeId === $actorUserId) {
        return;
    }
    $st = db()->prepare(
        'SELECT t.title, p.name AS project_name, au.name AS actor_name
         FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN users au ON au.id = ?
         WHERE t.id = ? AND t.organization_id = ? LIMIT 1'
    );
    $st->execute([$actorUserId, $taskId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $title = trim((string) ($row['title'] ?? ''));
    if ($title === '') {
        $title = 'Задача #' . $taskId;
    }
    $projectName = trim((string) ($row['project_name'] ?? ''));
    $actorName = trim((string) ($row['actor_name'] ?? 'Пользователь'));
    $msg = planner_max_build_message(
        'Вы назначены исполнителем',
        [
            'Задача' => $title,
            'Проект' => $projectName,
            'Кто изменил' => $actorName,
            'Что изменилось' => 'Вы назначены ответственным',
        ],
        'Откройте задачу и переведите в "В работе", когда начнете.',
        planner_public_task_link($taskId),
        '👤'
    );
    $openTok = planner_max_create_action_token($orgId, $assigneeId, $taskId, 'open');
    $takeTok = planner_max_create_action_token($orgId, $assigneeId, $taskId, 'take');
    $doneTok = planner_max_create_action_token($orgId, $assigneeId, $taskId, 'done');
    if ($openTok !== []) {
        $msg .= "\n🔗 Открыть: " . planner_public_site_url('/max_action.php?token=' . rawurlencode((string) $openTok['token']));
    }
    if ($takeTok !== []) {
        $msg .= "\n🟦 Взять в работу: " . planner_public_site_url('/max_action.php?token=' . rawurlencode((string) $takeTok['token']));
    }
    if ($doneTok !== []) {
        $msg .= "\n✅ Отметить выполненной: " . planner_public_site_url('/max_action.php?token=' . rawurlencode((string) $doneTok['token']));
    }
    planner_max_enqueue_event($orgId, $assigneeId, $taskId, 'task_assigned', $msg, [
        'actor_user_id' => $actorUserId,
        'assignee_id' => $assigneeId,
    ], 120);
}

function planner_notify_max_task_status_changed(int $orgId, int $taskId, string $fromStatus, string $toStatus, int $actorUserId): void
{
    if (!planner_max_enabled() || $fromStatus === $toStatus) {
        return;
    }
    $st = db()->prepare(
        'SELECT title, assignee_id, author_id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $st->execute([$taskId, $orgId]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t) {
        return;
    }
    $title = trim((string) ($t['title'] ?? ''));
    if ($title === '') {
        $title = 'Задача #' . $taskId;
    }
    $labels = [
        'new' => 'Новая',
        'in_progress' => 'В работе',
        'review' => 'На проверке',
        'done' => 'Выполнена',
        'cancelled' => 'Отменена',
    ];
    $fromRu = $labels[$fromStatus] ?? $fromStatus;
    $toRu = $labels[$toStatus] ?? $toStatus;
    $recipientIds = [];
    $aid = isset($t['assignee_id']) ? (int) $t['assignee_id'] : 0;
    $authorId = isset($t['author_id']) ? (int) $t['author_id'] : 0;
    if ($aid > 0 && $aid !== $actorUserId) {
        $recipientIds[] = $aid;
    }
    if ($authorId > 0 && $authorId !== $actorUserId) {
        $recipientIds[] = $authorId;
    }
    $recipientIds = array_values(array_unique($recipientIds));
    $msg = planner_max_build_message(
        'Изменён статус задачи',
        [
            'Задача' => $title,
            'Что изменилось' => "Статус: {$fromRu} → {$toRu}",
        ],
        'Проверьте, требуется ли ваша реакция на новый статус.',
        planner_public_task_link($taskId),
        '🔄'
    );
    foreach ($recipientIds as $uid) {
        planner_max_enqueue_event($orgId, $uid, $taskId, 'task_status_changed', $msg, [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actorUserId,
        ], 120);
    }
}

function planner_notify_max_task_due_changed(int $orgId, int $taskId, ?string $beforeDue, ?string $afterDue, int $actorUserId): void
{
    $b = trim((string) ($beforeDue ?? ''));
    $a = trim((string) ($afterDue ?? ''));
    if (!planner_max_enabled() || $b === $a) {
        return;
    }
    $st = db()->prepare('SELECT title, assignee_id, author_id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$taskId, $orgId]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t) {
        return;
    }
    $title = trim((string) ($t['title'] ?? ''));
    if ($title === '') {
        $title = 'Задача #' . $taskId;
    }
    $fmt = static function (string $x): string {
        return $x !== '' ? str_replace('T', ' ', substr($x, 0, 16)) : 'не указан';
    };
    $recipientIds = [];
    foreach (['assignee_id', 'author_id'] as $col) {
        $uid = isset($t[$col]) ? (int) $t[$col] : 0;
        if ($uid > 0 && $uid !== $actorUserId) {
            $recipientIds[] = $uid;
        }
    }
    $recipientIds = array_values(array_unique($recipientIds));
    $msg = planner_max_build_message(
        'Изменён срок задачи',
        [
            'Задача' => $title,
            'Что изменилось' => 'Срок: ' . $fmt($b) . ' → ' . $fmt($a),
        ],
        'Проверьте приоритет и скорректируйте план выполнения.',
        planner_public_task_link($taskId),
        '⏰'
    );
    foreach ($recipientIds as $uid) {
        planner_max_enqueue_event($orgId, $uid, $taskId, 'task_due_changed', $msg, [
            'before_due' => $b !== '' ? $b : null,
            'after_due' => $a !== '' ? $a : null,
            'actor_user_id' => $actorUserId,
        ], 120);
    }
}

/**
 * @param int[] $mentionUserIds
 */
function planner_notify_max_comment_mentions(
    int $orgId,
    int $actorUserId,
    int $taskId,
    array $mentionUserIds,
    string $taskTitle,
    string $commentPreview
): void {
    if (!planner_max_enabled()) {
        return;
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $mentionUserIds), static fn (int $i): bool => $i > 0)));
    if ($ids === []) {
        return;
    }
    $title = trim($taskTitle);
    if ($title === '') {
        $title = 'Задача #' . $taskId;
    }
    $stActor = db()->prepare('SELECT name FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $stActor->execute([$actorUserId, $orgId]);
    $actor = trim((string) ($stActor->fetchColumn() ?: 'Пользователь'));
    $prev = trim($commentPreview);
    if ($prev !== '') {
        $prev = str_replace(["\r", "\n"], ' ', $prev);
    }
    $msg = planner_max_build_message(
        'Вас упомянули в комментарии',
        [
            'Задача' => $title,
            'Кто изменил' => $actor,
            'Что изменилось' => $prev !== '' ? ('Комментарий: ' . $prev) : 'Добавлено упоминание',
        ],
        'Откройте задачу и ответьте в комментариях.',
        planner_public_task_link($taskId),
        '💬'
    );
    foreach ($ids as $uid) {
        if ($uid === $actorUserId) {
            continue;
        }
        planner_max_enqueue_event($orgId, $uid, $taskId, 'task_comment_mention', $msg, [
            'actor_user_id' => $actorUserId,
        ], 120);
    }
}
