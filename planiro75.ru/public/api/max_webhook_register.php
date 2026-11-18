<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

if (!user_may_manage_users($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

$method = effective_request_method();
if ($method === 'GET') {
    $listed = planner_max_subscriptions_list($orgId);
    $secret = '';
    if (planner_table_exists('max_settings') && planner_table_has_column('max_settings', 'webhook_secret')) {
        $st = db()->prepare('SELECT webhook_secret FROM max_settings WHERE organization_id = ? LIMIT 1');
        $st->execute([$orgId]);
        $secret = trim((string) ($st->fetchColumn() ?: ''));
    }
    json_response([
        'ok' => true,
        'webhook_url' => planner_max_webhook_endpoint_url(),
        'has_webhook_secret' => $secret !== '',
        'webhook_secret_len' => strlen($secret),
        'update_types' => planner_max_webhook_update_types(),
        'subscriptions' => $listed['ok'] ? ($listed['items'] ?? []) : [],
        'subscriptions_error' => $listed['ok'] ? null : ($listed['error'] ?? 'api_error'),
        'max_enabled' => planner_max_enabled(),
    ]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$body = read_json_body();
$result = planner_max_webhook_register(
    $orgId,
    $userId,
    isset($body['webhook_url']) ? trim((string) $body['webhook_url']) : null,
    isset($body['webhook_secret']) ? trim((string) $body['webhook_secret']) : null,
    !empty($body['regenerate_secret'])
);
if (empty($result['ok'])) {
    json_response([
        'ok' => false,
        'error' => (string) ($result['error'] ?? 'register_failed'),
        'message' => (string) ($result['message'] ?? 'Не удалось зарегистрировать webhook в MAX.'),
    ], 422);
}

json_response(['ok' => true] + $result);
