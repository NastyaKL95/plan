<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';
require_once dirname(__DIR__, 2) . '/includes/teacher_report.php';
planner_teacher_report_ensure_schema();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['ok' => false, 'error' => 'bad_id'], 422);
}

$st = db()->prepare(
    'SELECT s.id, s.task_id FROM subtasks s
     JOIN tasks t ON t.id = s.task_id
     WHERE s.id = ? AND t.organization_id = ? LIMIT 1'
);
$st->execute([$id, $orgId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}
if (!task_visible_to_user($user, (int) $row['task_id'])) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$method = effective_request_method();

if (($method === 'PATCH' || $method === 'PUT' || $method === 'DELETE') && !user_may_edit_task($user, (int) $row['task_id'])) {
    json_response(['ok' => false, 'error' => 'forbidden_edit'], 403);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $body = read_json_body();
    $parts = [];
    $params = [];
    $assistantTouched = false;
    if (array_key_exists('title', $body)) {
        $t = trim((string) $body['title']);
        if ($t === '') {
            json_response(['ok' => false, 'error' => 'title_empty'], 422);
        }
        $parts[] = 'title = ?';
        $params[] = $t;
    }
    if (array_key_exists('is_done', $body)) {
        $parts[] = 'is_done = ?';
        $params[] = !empty($body['is_done']) ? 1 : 0;
    }
    $workTouched = planner_subtask_work_append_fields($body, $parts, $params);
    if (array_key_exists('assistant_ids', $body) && is_array($body['assistant_ids'])) {
        $assistantTouched = true;
    }
    if ($parts === [] && !$assistantTouched) {
        json_response(['ok' => false, 'error' => 'no_fields'], 422);
    }
    if ($parts !== []) {
        $params[] = $id;
        $sql = 'UPDATE subtasks SET ' . implode(', ', $parts) . ' WHERE id = ?';
        db()->prepare($sql)->execute($params);
    }
    if ($assistantTouched) {
        planner_sync_subtask_assistants($orgId, $id, $body['assistant_ids']);
    }
    if ($workTouched) {
        planner_task_sync_hours_from_subtasks((int) $row['task_id'], $orgId);
    }
    log_activity($orgId, $userId, 'update', 'subtask', $id, 'Обновлён пункт чек-листа');
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    db()->prepare('DELETE FROM subtasks WHERE id = ?')->execute([$id]);
    planner_task_sync_hours_from_subtasks((int) $row['task_id'], $orgId);
    log_activity($orgId, $userId, 'delete', 'subtask', $id, 'Удалён пункт чек-листа');
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
