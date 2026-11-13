<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !planner_table_exists('task_attachments')) {
    json_response(['ok' => false, 'error' => 'bad_id'], 422);
}

$method = effective_request_method();
if ($method !== 'DELETE') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$st = db()->prepare(
    'SELECT a.id, a.stored_name, a.organization_id, a.task_id FROM task_attachments a
     WHERE a.id = ? AND a.organization_id = ? LIMIT 1'
);
$st->execute([$id, $orgId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row || !task_visible_to_user($user, (int) $row['task_id'])) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

if (!user_may_edit_task($user, (int) $row['task_id'])) {
    json_response(['ok' => false, 'error' => 'forbidden_edit'], 403);
}

$path = planner_upload_root() . '/' . (int) $row['organization_id'] . '/' . $row['stored_name'];
if (is_file($path)) {
    @unlink($path);
}
db()->prepare('DELETE FROM task_attachments WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
log_activity($orgId, $userId, 'delete', 'attachment', $id, 'Удалено вложение');
json_response(['ok' => true]);
