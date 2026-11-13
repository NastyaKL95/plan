<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$taskId = (int) ($_GET['task_id'] ?? 0);
if ($taskId <= 0 || !planner_table_exists('task_links')) {
    json_response(['ok' => false, 'error' => 'bad_request'], $taskId <= 0 ? 422 : 501);
}

$st = db()->prepare('SELECT id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
$st->execute([$taskId, $orgId]);
if (!$st->fetch() || !task_visible_to_user($user, $taskId)) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$method = effective_request_method();

if ($method === 'GET') {
    $st = db()->prepare(
        'SELECT tl.id, tl.link_type, tl.linked_task_id, t.title AS linked_title, t.status AS linked_status
         FROM task_links tl
         INNER JOIN tasks t ON t.id = tl.linked_task_id AND t.organization_id = ?
         WHERE tl.task_id = ? AND tl.organization_id = ?'
    );
    $st->execute([$orgId, $taskId, $orgId]);
    $outgoing = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = db()->prepare(
        'SELECT tl.id, tl.link_type, tl.task_id AS from_task_id, t.title AS from_title, t.status AS from_status
         FROM task_links tl
         INNER JOIN tasks t ON t.id = tl.task_id AND t.organization_id = ?
         WHERE tl.linked_task_id = ? AND tl.organization_id = ?'
    );
    $st->execute([$orgId, $taskId, $orgId]);
    $incoming = $st->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'outgoing' => $outgoing, 'incoming' => $incoming]);
}

if ($method === 'POST') {
    if (!user_may_edit_task($user, $taskId)) {
        json_response(['ok' => false, 'error' => 'forbidden_edit'], 403);
    }
    $body = read_json_body();
    $linkedId = (int) ($body['linked_task_id'] ?? 0);
    $linkType = (string) ($body['link_type'] ?? '');
    if ($linkedId <= 0 || $linkedId === $taskId) {
        json_response(['ok' => false, 'error' => 'bad_linked_task'], 422);
    }
    if (!in_array($linkType, ['blocks', 'duplicates', 'relates'], true)) {
        json_response(['ok' => false, 'error' => 'bad_link_type'], 422);
    }
    if (!task_visible_to_user($user, $linkedId)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $chk = db()->prepare('SELECT id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
    $chk->execute([$linkedId, $orgId]);
    if (!$chk->fetch()) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    try {
        $st = db()->prepare(
            'INSERT INTO task_links (organization_id, task_id, linked_task_id, link_type) VALUES (?,?,?,?)'
        );
        $st->execute([$orgId, $taskId, $linkedId, $linkType]);
    } catch (Throwable) {
        json_response(['ok' => false, 'error' => 'duplicate_link'], 422);
    }
    $lid = (int) db()->lastInsertId();
    log_activity($orgId, $userId, 'create', 'task_link', $lid, 'Связь задачи #' . $taskId);
    json_response(['ok' => true, 'id' => $lid]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
