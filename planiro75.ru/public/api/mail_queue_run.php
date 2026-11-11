<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!user_may_manage_users($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

$method = effective_request_method();
if (!in_array($method, ['POST', 'GET'], true)) {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!planner_table_exists('mail_queue')) {
    json_response(['ok' => false, 'error' => 'mail_queue_table_missing'], 422);
}

$limit = (int) ($_GET['limit'] ?? ($_POST['limit'] ?? 20));
$limit = max(1, min(100, $limit));

$result = planner_mail_queue_process($limit);
json_response([
    'ok' => true,
    'result' => $result,
]);
