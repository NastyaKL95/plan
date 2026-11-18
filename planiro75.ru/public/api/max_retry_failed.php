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
$limit = isset($body['limit']) ? (int) $body['limit'] : 200;
$limit = max(1, min(1000, $limit));
$updated = planner_max_retry_failed_events($orgId, $limit);
log_activity($orgId, $userId, 'update', 'max_queue', null, 'Повторно поставлены failed события MAX: ' . $updated);
json_response(['ok' => true, 'retried' => $updated]);
