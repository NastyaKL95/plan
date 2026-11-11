<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

if (!user_may_manage_users($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}
if (effective_request_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$body = read_json_body();
$source = trim((string) ($body['source'] ?? 'event'));
$id = (int) ($body['id'] ?? 0);
$action = trim((string) ($body['action'] ?? ''));
if ($id <= 0) {
    json_response(['ok' => false, 'error' => 'bad_id'], 422);
}

if ($source === 'digest') {
    if ($action !== 'cancel') {
        json_response(['ok' => false, 'error' => 'bad_action'], 422);
    }
    if (!planner_max_digest_cancel($orgId, $id)) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    log_activity($orgId, $userId, 'delete', 'max_digest', $id, 'Убрано уведомление из дайджеста MAX');
    json_response(['ok' => true]);
}

if ($source !== 'event') {
    json_response(['ok' => false, 'error' => 'bad_action'], 422);
}

$result = planner_max_queue_apply_action($orgId, $id, $action);
if ($result !== 'ok') {
    $code = $result === 'not_found' ? 404 : 422;
    json_response(['ok' => false, 'error' => $result], $code);
}

$labels = [
    'cancel' => 'Отменено уведомление MAX',
    'retry' => 'Повторно поставлено уведомление MAX',
    'resend' => 'Повторно отправляется уведомление MAX',
];
log_activity($orgId, $userId, 'update', 'max_queue', $id, ($labels[$action] ?? 'Изменено уведомление MAX') . ' #' . $id);
json_response(['ok' => true]);
