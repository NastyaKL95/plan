<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$method = effective_request_method();
if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
if (!user_may_bulk_update_tasks($user)) {
    json_response(['ok' => false, 'error' => 'forbidden_bulk'], 403);
}

$body = read_json_body();
$rawIds = $body['task_ids'] ?? [];
if (!is_array($rawIds)) {
    json_response(['ok' => false, 'error' => 'bad_ids'], 422);
}
$ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static function ($x) {
    return $x > 0;
})));
if ($ids === []) {
    json_response(['ok' => false, 'error' => 'no_tasks'], 422);
}
if (count($ids) > 200) {
    json_response(['ok' => false, 'error' => 'too_many'], 422);
}

$action = (string) ($body['action'] ?? '');

/** @var list<int> $updatedIds */
$updatedIds = [];
/** @var list<array{task_id:int, error:string}> $failed */
$failed = [];

if ($action === 'set_status') {
    $status = (string) ($body['status'] ?? '');
    if (!in_array($status, ['new', 'in_progress', 'review', 'done', 'cancelled'], true)) {
        json_response(['ok' => false, 'error' => 'bad_status'], 422);
    }
    $setSql = 'status = ?';
    if (planner_table_has_column('tasks', 'status_changed_at')) {
        $setSql .= ', status_changed_at = NOW()';
    }
    $upd = db()->prepare('UPDATE tasks SET ' . $setSql . ' WHERE id = ? AND organization_id = ?');
    $exists = db()->prepare('SELECT id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
    foreach ($ids as $tid) {
        if (!task_visible_to_user($user, $tid)) {
            $failed[] = ['task_id' => $tid, 'error' => 'not_visible'];
            continue;
        }
        if (!user_may_edit_task($user, $tid)) {
            $failed[] = ['task_id' => $tid, 'error' => 'forbidden'];
            continue;
        }
        $exists->execute([$tid, $orgId]);
        if (!$exists->fetch()) {
            $failed[] = ['task_id' => $tid, 'error' => 'not_found'];
            continue;
        }
        $upd->execute([$status, $tid, $orgId]);
        $updatedIds[] = $tid;
        log_activity($orgId, $userId, 'update', 'task', $tid, 'Массово: статус «' . $status . '»');
    }
    json_response([
        'ok' => true,
        'updated' => $updatedIds,
        'updated_count' => count($updatedIds),
        'failed' => $failed,
    ]);
}

if ($action === 'set_assignee') {
    $aid = isset($body['assignee_id']) && $body['assignee_id'] !== '' && $body['assignee_id'] !== null
        ? (int) $body['assignee_id']
        : null;
    if ($aid) {
        $chk = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
        $chk->execute([$aid, $orgId]);
        if (!$chk->fetch()) {
            json_response(['ok' => false, 'error' => 'bad_assignee'], 422);
        }
        if (planner_user_is_blocked_id($orgId, $aid)) {
            json_response(['ok' => false, 'error' => 'user_blocked'], 422);
        }
    }
    $upd = db()->prepare('UPDATE tasks SET assignee_id = ? WHERE id = ? AND organization_id = ?');
    $exists = db()->prepare('SELECT id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
    foreach ($ids as $tid) {
        if (!task_visible_to_user($user, $tid)) {
            $failed[] = ['task_id' => $tid, 'error' => 'not_visible'];
            continue;
        }
        if (!user_may_edit_task($user, $tid)) {
            $failed[] = ['task_id' => $tid, 'error' => 'forbidden'];
            continue;
        }
        $exists->execute([$tid, $orgId]);
        if (!$exists->fetch()) {
            $failed[] = ['task_id' => $tid, 'error' => 'not_found'];
            continue;
        }
        $upd->execute([$aid, $tid, $orgId]);
        $updatedIds[] = $tid;
        log_activity($orgId, $userId, 'update', 'task', $tid, 'Массово: исполнитель');
    }
    json_response([
        'ok' => true,
        'updated' => $updatedIds,
        'updated_count' => count($updatedIds),
        'failed' => $failed,
    ]);
}

json_response(['ok' => false, 'error' => 'bad_action'], 422);
