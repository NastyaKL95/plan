<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('checklist_templates')) {
    json_response(['ok' => false, 'error' => 'not_configured'], 501);
}
if (!planner_table_exists('checklist_template_items')) {
    json_response(['ok' => false, 'error' => 'not_configured'], 501);
}

$method = effective_request_method();

if ($method === 'GET') {
    $st = db()->prepare('SELECT id, name FROM checklist_templates WHERE organization_id = ? ORDER BY name');
    $st->execute([$orgId]);
    $templates = $st->fetchAll(PDO::FETCH_ASSOC);
    $stItems = db()->prepare(
        'SELECT id, title, sort_order FROM checklist_template_items WHERE template_id = ? ORDER BY sort_order, id'
    );
    foreach ($templates as &$tpl) {
        $stItems->execute([(int) $tpl['id']]);
        $tpl['items'] = $stItems->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($tpl);

    json_response(['ok' => true, 'items' => $templates]);
}

if ($method === 'POST') {
    $body = read_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'name_required'], 422);
    }
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 120, 'UTF-8') : substr($name, 0, 120);

    $fromTaskId = (int) ($body['from_task_id'] ?? 0);
    if ($fromTaskId <= 0) {
        json_response(['ok' => false, 'error' => 'task_id_required'], 422);
    }
    $stTask = db()->prepare('SELECT id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
    $stTask->execute([$fromTaskId, $orgId]);
    if (!$stTask->fetch()) {
        json_response(['ok' => false, 'error' => 'task_not_found'], 404);
    }
    if (!task_visible_to_user($user, $fromTaskId)) {
        json_response(['ok' => false, 'error' => 'task_not_found'], 404);
    }

    $subOrder = planner_table_has_column('subtasks', 'sort_order') ? 'sort_order, id' : 'id';
    $stItems = db()->prepare("SELECT title FROM subtasks WHERE task_id = ? ORDER BY $subOrder");
    $stItems->execute([$fromTaskId]);
    $items = $stItems->fetchAll(PDO::FETCH_ASSOC);
    if ($items === []) {
        json_response(['ok' => false, 'error' => 'subtasks_empty'], 422);
    }

    $insTpl = db()->prepare('INSERT INTO checklist_templates (organization_id, name) VALUES (?,?)');
    $insTpl->execute([$orgId, $name]);
    $tplId = (int) db()->lastInsertId();

    $insItem = db()->prepare(
        'INSERT INTO checklist_template_items (template_id, title, sort_order) VALUES (?,?,?)'
    );
    $sort = 0;
    foreach ($items as $row) {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $title = function_exists('mb_substr') ? mb_substr($title, 0, 500, 'UTF-8') : substr($title, 0, 500);
        $insItem->execute([$tplId, $title, $sort]);
        $sort++;
    }
    if ($sort === 0) {
        db()->prepare('DELETE FROM checklist_templates WHERE id = ? AND organization_id = ?')->execute([$tplId, $orgId]);
        json_response(['ok' => false, 'error' => 'subtasks_empty'], 422);
    }
    log_activity($orgId, $userId, 'create', 'checklist_template', $tplId, 'Создан шаблон чек-листа: ' . $name);
    json_response(['ok' => true, 'id' => $tplId]);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $body = read_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'name_required'], 422);
    }
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 120, 'UTF-8') : substr($name, 0, 120);
    $st = db()->prepare('UPDATE checklist_templates SET name = ? WHERE id = ? AND organization_id = ?');
    $st->execute([$name, $id, $orgId]);
    if ($st->rowCount() <= 0) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    log_activity($orgId, $userId, 'update', 'checklist_template', $id, 'Переименован шаблон чек-листа: ' . $name);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $st = db()->prepare('DELETE FROM checklist_templates WHERE id = ? AND organization_id = ?');
    $st->execute([$id, $orgId]);
    if ($st->rowCount() <= 0) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    log_activity($orgId, $userId, 'delete', 'checklist_template', $id, 'Удалён шаблон чек-листа');
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
