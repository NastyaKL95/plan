<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('task_comment_reads') || !planner_table_exists('task_comments')) {
    json_response(['ok' => true, 'marked' => 0]);
}

$method = effective_request_method();

if ($method === 'POST') {
    $body = read_json_body();
    $taskId = isset($body['task_id']) ? (int) $body['task_id'] : 0;
    if ($taskId <= 0) {
        json_response(['ok' => false, 'error' => 'task_id_required'], 422);
    }
    if (!task_visible_to_user($user, $taskId)) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    $st = db()->prepare(
        'INSERT INTO task_comment_reads (user_id, comment_id, read_at)
         SELECT ?, c.id, CURRENT_TIMESTAMP FROM task_comments c
         WHERE c.task_id = ? AND c.user_id <> ?
         ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP'
    );
    $st->execute([$userId, $taskId, $userId]);
    json_response(['ok' => true, 'marked' => $st->rowCount()]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
