<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['ok' => false, 'error' => 'bad_id'], 422);
}

if (!planner_table_exists('task_comments')) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$st = db()->prepare(
    'SELECT c.id, c.task_id, c.user_id FROM task_comments c
     JOIN tasks t ON t.id = c.task_id
     WHERE c.id = ? AND t.organization_id = ? LIMIT 1'
);
$st->execute([$id, $orgId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$taskId = (int) $row['task_id'];
if (!task_visible_to_user($user, $taskId)) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$authorId = (int) $row['user_id'];
$role = $user['role'] ?? '';
$mayManage = $authorId === $userId
    || in_array($role, ['admin', 'owner'], true)
    || user_is_institute_manager($user);

if (!$mayManage) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

$method = effective_request_method();
if ($method === 'PATCH') {
    $body = read_json_body();
    $text = trim((string) ($body['body'] ?? ''));
    if ($text === '') {
        json_response(['ok' => false, 'error' => 'body_required'], 422);
    }
    $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($len > 16000) {
        json_response(['ok' => false, 'error' => 'body_too_long'], 422);
    }
    db()->prepare('UPDATE task_comments SET body = ? WHERE id = ?')->execute([$text, $id]);
    $mentionIds = planner_parse_comment_mention_user_ids($text, $orgId, $taskId, $authorId);
    planner_sync_task_comment_mentions($id, $mentionIds);
    log_activity($orgId, $userId, 'edit_comment', 'task', $taskId, 'Изменён комментарий к задаче');
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    db()->prepare('DELETE FROM task_comments WHERE id = ?')->execute([$id]);
    log_activity($orgId, $userId, 'delete_comment', 'task', $taskId, 'Удалён комментарий к задаче');
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
