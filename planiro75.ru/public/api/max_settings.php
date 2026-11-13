<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

if (!user_may_manage_users($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}
$method = effective_request_method();
if ($method === 'GET') {
    $rt = planner_max_runtime_settings($orgId);
    json_response(['ok' => true, 'settings' => [
        'chat_id' => (string) ($rt['chat_id'] ?? ''),
        'timeout' => (int) ($rt['timeout'] ?? 12),
        'quiet_hours_enabled' => !empty($rt['quiet_hours_enabled']),
        'quiet_start' => (string) ($rt['quiet_start'] ?? '20:00'),
        'quiet_end' => (string) ($rt['quiet_end'] ?? '08:00'),
        'stale_days' => (int) ($rt['stale_days'] ?? 3),
        'has_token' => trim((string) ($rt['token'] ?? '')) !== '',
        'has_webhook_secret' => planner_table_exists('max_settings') && planner_table_has_column('max_settings', 'webhook_secret')
            ? (bool) db()->query("SELECT COUNT(*) FROM max_settings WHERE organization_id = {$orgId} AND webhook_secret <> ''")->fetchColumn()
            : false,
    ]]);
}
if ($method !== 'PATCH' && $method !== 'PUT') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
if (!planner_table_exists('max_settings')) {
    json_response(['ok' => false, 'error' => 'schema_missing'], 422);
}
$body = read_json_body();
$token = array_key_exists('token', $body) ? planner_max_normalize_token((string) $body['token']) : null;
$removeToken = !empty($body['remove_token']);
$webhookSecret = array_key_exists('webhook_secret', $body) ? trim((string) $body['webhook_secret']) : null;
$removeWebhookSecret = !empty($body['remove_webhook_secret']);
$chatId = trim((string) ($body['chat_id'] ?? ''));
$timeout = isset($body['timeout']) ? (int) $body['timeout'] : 12;
$quietEnabled = !empty($body['quiet_hours_enabled']) ? 1 : 0;
$quietStart = trim((string) ($body['quiet_start'] ?? '20:00'));
$quietEnd = trim((string) ($body['quiet_end'] ?? '08:00'));
$staleDays = isset($body['stale_days']) ? (int) $body['stale_days'] : 3;
if (!preg_match('/^\d{2}:\d{2}$/', $quietStart) || !preg_match('/^\d{2}:\d{2}$/', $quietEnd)) {
    json_response(['ok' => false, 'error' => 'bad_quiet_time'], 422);
}
$timeout = max(3, min(60, $timeout));
$staleDays = max(1, min(60, $staleDays));
$current = planner_max_runtime_settings($orgId);
$previousToken = (string) ($current['token'] ?? '');
$tokenToSave = $previousToken;
if ($removeToken) {
    $tokenToSave = '';
} elseif ($token !== null) {
    $tokenToSave = $token;
}
$tokenChanged = $token !== null && $tokenToSave !== $previousToken;
$hasWebhookSecretCol = planner_table_has_column('max_settings', 'webhook_secret');
$secretToSave = '';
if (planner_table_exists('max_settings') && $hasWebhookSecretCol) {
    $secretToSave = trim((string) (db()->query("SELECT webhook_secret FROM max_settings WHERE organization_id = {$orgId} LIMIT 1")->fetchColumn() ?: ''));
}
if ($removeWebhookSecret) {
    $secretToSave = '';
} elseif ($webhookSecret !== null) {
    $secretToSave = $webhookSecret;
}
$cols = ['organization_id', 'token', 'fallback_chat_id', 'timeout_seconds', 'quiet_hours_enabled', 'quiet_start', 'quiet_end', 'stale_days', 'updated_by'];
$vals = [$orgId, $tokenToSave, $chatId, $timeout, $quietEnabled, $quietStart, $quietEnd, $staleDays, $userId];
if ($hasWebhookSecretCol) {
    $cols[] = 'webhook_secret';
    $vals[] = $secretToSave;
}
$ph = implode(', ', array_fill(0, count($cols), '?'));
$updates = array_map(static fn ($c) => $c . '=VALUES(' . $c . ')', array_filter($cols, static fn ($c) => $c !== 'organization_id'));
$sql = 'INSERT INTO max_settings (' . implode(', ', $cols) . ') VALUES (' . $ph . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
db()->prepare($sql)->execute($vals);
log_activity($orgId, $userId, 'update', 'max_settings', null, 'Обновлены настройки MAX (token/chat/quiet_hours/sla).');
json_response([
    'ok' => true,
    'token_changed' => $tokenChanged,
    'reregister_webhook' => $tokenChanged && $tokenToSave !== '',
]);
