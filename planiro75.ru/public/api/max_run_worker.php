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
$limit = isset($body['limit']) ? (int) $body['limit'] : 50;
$limit = max(1, min(500, $limit));

planner_max_mark_worker_heartbeat('manual_start');
$result = planner_max_process_queue($limit);
planner_max_mark_worker_heartbeat('manual_done');

json_response([
    'ok' => true,
    'result' => $result,
    'limit' => $limit,
]);
