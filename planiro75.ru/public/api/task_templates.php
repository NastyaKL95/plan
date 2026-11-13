<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('task_templates')) {
    json_response(['ok' => false, 'error' => 'not_configured'], 503);
}

$method = effective_request_method();

if ($method === 'GET') {
    $st = db()->prepare(
        'SELECT tt.id, tt.name, tt.project_id, tt.title, tt.status, tt.priority,
                p.name AS project_name, tt.created_at
         FROM task_templates tt
         INNER JOIN projects p ON p.id = tt.project_id AND p.organization_id = tt.organization_id
         WHERE tt.organization_id = ?
         ORDER BY tt.name ASC'
    );
    $st->execute([$orgId]);
    json_response(['ok' => true, 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'POST') {
    $body = read_json_body();
    $fromTaskId = isset($body['from_task_id']) ? (int) $body['from_task_id'] : 0;
    $name = trim((string) ($body['name'] ?? ''));

    if ($fromTaskId > 0) {
        if ($name === '') {
            json_response(['ok' => false, 'error' => 'name_required'], 422);
        }
        if (!task_visible_to_user($user, $fromTaskId)) {
            json_response(['ok' => false, 'error' => 'forbidden_task'], 403);
        }
        $st = db()->prepare(
            'SELECT * FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1'
        );
        $st->execute([$fromTaskId, $orgId]);
        $task = $st->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            json_response(['ok' => false, 'error' => 'not_found'], 404);
        }
        $tagIds = [];
        if (planner_table_exists('task_tag_map')) {
            $st = db()->prepare('SELECT tag_id FROM task_tag_map WHERE task_id = ?');
            $st->execute([$fromTaskId]);
            $tagIds = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'tag_id'));
        }
        $assistants = [];
        $observers = [];
        if (planner_table_exists('task_participants')) {
            $st = db()->prepare(
                'SELECT user_id, role FROM task_participants WHERE task_id = ?'
            );
            $st->execute([$fromTaskId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (($row['role'] ?? '') === 'observer') {
                    $observers[] = (int) $row['user_id'];
                } else {
                    $assistants[] = (int) $row['user_id'];
                }
            }
        }
        $subtasks = [];
        $subOrder = planner_table_has_column('subtasks', 'sort_order') ? 'sort_order, id' : 'id';
        $st = db()->prepare("SELECT title FROM subtasks WHERE task_id = ? ORDER BY $subOrder");
        $st->execute([$fromTaskId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $subtasks[] = (string) $row['title'];
        }

        $ins = db()->prepare(
            'INSERT INTO task_templates (organization_id, name, project_id, title, description, status, priority, assignee_id, tag_ids_json, subtasks_json, assistant_ids_json, observer_ids_json)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            $orgId,
            function_exists('mb_substr') ? mb_substr($name, 0, 120, 'UTF-8') : substr($name, 0, 120),
            (int) $task['project_id'],
            (string) $task['title'],
            $task['description'] !== null && $task['description'] !== '' ? (string) $task['description'] : null,
            (string) $task['status'],
            (string) $task['priority'],
            $task['assignee_id'] !== null && $task['assignee_id'] !== '' ? (int) $task['assignee_id'] : null,
            json_encode($tagIds, JSON_THROW_ON_ERROR),
            json_encode($subtasks, JSON_THROW_ON_ERROR),
            json_encode($assistants, JSON_THROW_ON_ERROR),
            json_encode($observers, JSON_THROW_ON_ERROR),
        ]);
        json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
    }

    if ($name === '') {
        json_response(['ok' => false, 'error' => 'name_required'], 422);
    }
    $projectId = isset($body['project_id']) ? (int) $body['project_id'] : 0;
    $title = trim((string) ($body['title'] ?? ''));
    if ($projectId <= 0 || $title === '') {
        json_response(['ok' => false, 'error' => 'project_title_required'], 422);
    }
    if (!project_visible_to_user($user, $projectId)) {
        json_response(['ok' => false, 'error' => 'forbidden_project'], 403);
    }
    $chk = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
    $chk->execute([$projectId, $orgId]);
    if (!$chk->fetch()) {
        json_response(['ok' => false, 'error' => 'bad_project'], 422);
    }
    $description = isset($body['description']) ? (string) $body['description'] : null;
    $status = (string) ($body['status'] ?? 'new');
    if (!in_array($status, ['new', 'in_progress', 'review', 'done', 'cancelled'], true)) {
        $status = 'new';
    }
    $priority = (string) ($body['priority'] ?? 'normal');
    if (!in_array($priority, ['low', 'normal', 'high', 'critical'], true)) {
        $priority = 'normal';
    }
    $assigneeId = isset($body['assignee_id']) ? (int) $body['assignee_id'] : null;
    if ($assigneeId) {
        $uchk = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
        $uchk->execute([$assigneeId, $orgId]);
        if (!$uchk->fetch()) {
            $assigneeId = null;
        }
    }
    $tagIds = isset($body['tag_ids']) && is_array($body['tag_ids']) ? $body['tag_ids'] : [];
    $subtasksRaw = isset($body['subtasks']) && is_array($body['subtasks']) ? $body['subtasks'] : [];
    $subtasksClean = [];
    foreach ($subtasksRaw as $s) {
        $t = trim((string) $s);
        if ($t !== '') {
            $subtasksClean[] = $t;
        }
    }
    $assistants = isset($body['assistant_ids']) && is_array($body['assistant_ids']) ? $body['assistant_ids'] : [];
    $observers = isset($body['observer_ids']) && is_array($body['observer_ids']) ? $body['observer_ids'] : [];

    $ins = db()->prepare(
        'INSERT INTO task_templates (organization_id, name, project_id, title, description, status, priority, assignee_id, tag_ids_json, subtasks_json, assistant_ids_json, observer_ids_json)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $ins->execute([
        $orgId,
        function_exists('mb_substr') ? mb_substr($name, 0, 120, 'UTF-8') : substr($name, 0, 120),
        $projectId,
        $title,
        $description !== '' ? $description : null,
        $status,
        $priority,
        $assigneeId,
        json_encode(array_values(array_map('intval', $tagIds)), JSON_THROW_ON_ERROR),
        json_encode($subtasksClean, JSON_THROW_ON_ERROR),
        json_encode(array_values(array_map('intval', $assistants)), JSON_THROW_ON_ERROR),
        json_encode(array_values(array_map('intval', $observers)), JSON_THROW_ON_ERROR),
    ]);
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($method === 'DELETE') {
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $st = db()->prepare('DELETE FROM task_templates WHERE id = ? AND organization_id = ?');
    $st->execute([$id, $orgId]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
