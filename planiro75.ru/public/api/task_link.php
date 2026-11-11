<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !planner_table_exists('task_links')) {
    json_response(['ok' => false, 'error' => 'bad_id'], 422);
}

$method = effective_request_method();
if ($method !== 'DELETE') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$st = db()->prepare('SELECT id, task_id FROM task_links WHERE id = ? AND organization_id = ? LIMIT 1');
$st->execute([$id, $orgId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row || !task_visible_to_user($user, (int) $row['task_id'])) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

if (!user_may_edit_task($user, (int) $row['task_id'])) {
    json_response(['ok' => false, 'error' => 'forbidden_edit'], 403);
}

db()->prepare('DELETE FROM task_links WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
log_activity($orgId, $userId, 'delete', 'task_link', $id, 'Удалена связь');
json_response(['ok' => true]);
