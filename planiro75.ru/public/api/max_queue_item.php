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
$scope = trim((string) ($body['scope'] ?? 'ids'));
$ids = [];
if (isset($body['ids']) && is_array($body['ids'])) {
    foreach ($body['ids'] as $rawId) {
        $one = (int) $rawId;
        if ($one > 0) {
            $ids[] = $one;
        }
    }
}
if ($ids !== [] || $scope === 'filter') {
    if (!in_array($action, ['delete', 'retry'], true)) {
        json_response(['ok' => false, 'error' => 'bad_action'], 422);
    }
    if ($source === 'digest') {
        if ($action !== 'delete') {
            json_response(['ok' => false, 'error' => 'bad_action'], 422);
        }
        $affected = $scope === 'filter'
            ? planner_max_digest_cancel_all($orgId)
            : planner_max_digest_cancel_many($orgId, $ids);
        log_activity($orgId, $userId, 'delete', 'max_digest', null, 'Убрано уведомлений из дайджеста MAX: ' . $affected);
        json_response(['ok' => true, 'affected' => $affected]);
    }
    if ($source !== 'event') {
        json_response(['ok' => false, 'error' => 'bad_action'], 422);
    }
    if ($scope === 'filter') {
        $result = planner_max_queue_apply_filter(
            $orgId,
            trim((string) ($body['status'] ?? '')),
            trim((string) ($body['event_type'] ?? '')),
            trim((string) ($body['user_q'] ?? '')),
            $action
        );
        $affected = (int) ($result['affected'] ?? 0);
    } else {
        if ($ids === []) {
            json_response(['ok' => false, 'error' => 'bad_id'], 422);
        }
        $result = planner_max_queue_apply_bulk($orgId, $ids, $action);
        $affected = (int) ($result['affected'] ?? 0);
    }
    $verb = $action === 'delete' ? 'Удалено уведомлений MAX' : 'Снова поставлено в очередь уведомлений MAX';
    log_activity($orgId, $userId, $action === 'delete' ? 'delete' : 'update', 'max_queue', null, $verb . ': ' . $affected);
    json_response(['ok' => true, 'affected' => $affected, 'skipped' => (int) ($result['skipped'] ?? 0)]);
}
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
