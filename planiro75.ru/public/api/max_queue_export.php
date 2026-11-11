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
$data = planner_max_queue_list($orgId, $status, $eventType, $userQ, 500);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="max_queue_export.csv"');
$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF"); // BOM, чтобы Excel распознал UTF-8
fputcsv($out, ['id', 'status', 'event_type', 'user_name', 'user_email', 'attempts', 'next_retry_at', 'sent_at', 'last_error', 'payload_json', 'created_at'], ';');
foreach ($data['items'] as $it) {
    fputcsv($out, [
        (string) ($it['id'] ?? ''),
        (string) ($it['status'] ?? ''),
        (string) ($it['event_type'] ?? ''),
        (string) ($it['user_name'] ?? ''),
        (string) ($it['user_email'] ?? ''),
        (string) ($it['attempts'] ?? ''),
        (string) ($it['next_retry_at'] ?? ''),
        (string) ($it['sent_at'] ?? ''),
        (string) ($it['last_error'] ?? ''),
        (string) ($it['payload_json'] ?? ''),
        (string) ($it['created_at'] ?? ''),
    ], ';');
}
fclose($out);
exit;
