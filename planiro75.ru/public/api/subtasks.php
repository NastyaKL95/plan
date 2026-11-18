<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';
require_once dirname(__DIR__, 2) . '/includes/teacher_report.php';
planner_teacher_report_ensure_schema();

$taskId = (int) ($_GET['task_id'] ?? 0);
if ($taskId <= 0) {
    json_response(['ok' => false, 'error' => 'task_id_required'], 422);
}

$st = db()->prepare('SELECT id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
$st->execute([$taskId, $orgId]);
if (!$st->fetch()) {
    json_response(['ok' => false, 'error' => 'task_not_found'], 404);
}
if (!task_visible_to_user($user, $taskId)) {
    json_response(['ok' => false, 'error' => 'task_not_found'], 404);
}

$method = effective_request_method();

if (($method === 'POST' || $method === 'PATCH') && !user_may_edit_task($user, $taskId)) {
    json_response(['ok' => false, 'error' => 'forbidden_edit'], 403);
}

if ($method === 'GET') {
    $st = db()->prepare(
        'SELECT id, title, is_done, sort_order, created_at' . planner_subtask_work_select_sql()
        . ' FROM subtasks WHERE task_id = ? ORDER BY sort_order, id'
    );
    $st->execute([$taskId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($items !== []) {
        $subAssistants = planner_subtask_assistants_payload_map($taskId);
        foreach ($items as &$sub) {
            $sid = (int) ($sub['id'] ?? 0);
            $sub['assistants'] = $sid > 0 && isset($subAssistants[$sid]) ? $subAssistants[$sid] : [];
        }
        unset($sub);
    }
    json_response(['ok' => true, 'items' => $items]);
}

if ($method === 'POST') {
    $body = read_json_body();
    $tplId = (int) ($body['template_id'] ?? 0);
    if ($tplId > 0 && planner_table_exists('checklist_template_items')) {
        $st = db()->prepare(
            'SELECT i.title, i.sort_order FROM checklist_template_items i
             INNER JOIN checklist_templates ct ON ct.id = i.template_id
             WHERE i.template_id = ? AND ct.organization_id = ?'
        );
        $st->execute([$tplId, $orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            json_response(['ok' => false, 'error' => 'template_not_found'], 404);
        }
        $st = db()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM subtasks WHERE task_id = ?');
        $st->execute([$taskId]);
        $base = (int) $st->fetchColumn();
        $ins = db()->prepare('INSERT INTO subtasks (task_id, title, is_done, sort_order) VALUES (?,?,0,?)');
        $n = 0;
        foreach ($rows as $r) {
            $base++;
            $ins->execute([$taskId, (string) $r['title'], $base]);
            $n++;
        }
        log_activity($orgId, $userId, 'create', 'subtask', $taskId, 'Чек-лист из шаблона, пунктов: ' . $n);
        json_response(['ok' => true, 'added' => $n]);
    }
    $title = trim((string) ($body['title'] ?? ''));
    if ($title === '') {
        json_response(['ok' => false, 'error' => 'title_required'], 422);
    }
    $st = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM subtasks WHERE task_id = ?');
    $st->execute([$taskId]);
    $ord = (int) $st->fetchColumn();
    $st = db()->prepare('INSERT INTO subtasks (task_id, title, is_done, sort_order) VALUES (?,?,0,?)');
    $st->execute([$taskId, $title, $ord]);
    $sid = (int) db()->lastInsertId();
    if (array_key_exists('assistant_ids', $body) && is_array($body['assistant_ids'])) {
        planner_sync_subtask_assistants($orgId, $sid, $body['assistant_ids']);
    }
    log_activity($orgId, $userId, 'create', 'subtask', $sid, 'Пункт чек-листа: ' . $title);
    json_response(['ok' => true, 'id' => $sid]);
}

if ($method === 'PATCH') {
    if (!planner_table_has_column('subtasks', 'sort_order')) {
        json_response(['ok' => false, 'error' => 'not_configured'], 501);
    }
    $body = read_json_body();
    $order = $body['order'] ?? null;
    if (!is_array($order) || $order === []) {
        json_response(['ok' => false, 'error' => 'order_required'], 422);
    }
    $ids = array_map('intval', $order);
    $ids = array_values(array_unique(array_filter($ids, static fn (int $i) => $i > 0)));
    if ($ids === []) {
        json_response(['ok' => false, 'error' => 'order_required'], 422);
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$taskId], $ids);
    $st = db()->prepare("SELECT id FROM subtasks WHERE task_id = ? AND id IN ($ph)");
    $st->execute($params);
    $found = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
    if (count($found) !== count($ids)) {
        json_response(['ok' => false, 'error' => 'bad_subtask_ids'], 422);
    }
    $upd = db()->prepare('UPDATE subtasks SET sort_order = ? WHERE id = ? AND task_id = ?');
    foreach ($ids as $i => $sid) {
        $upd->execute([$i, $sid, $taskId]);
    }
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
