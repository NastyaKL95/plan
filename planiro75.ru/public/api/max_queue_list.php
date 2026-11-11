<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

if (!user_may_manage_users($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}
if (effective_request_method() !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
$status = trim((string) ($_GET['status'] ?? ''));
$eventType = trim((string) ($_GET['event_type'] ?? ''));
$userQ = trim((string) ($_GET['user_q'] ?? ''));
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$data = planner_max_queue_list($orgId, $status, $eventType, $userQ, $limit);
json_response(['ok' => true, 'total' => $data['total'], 'items' => $data['items']]);
