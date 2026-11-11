<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';
require_once dirname(__DIR__, 2) . '/includes/my_plan_stages.php';

/**
 * Валидные переходы статусов задачи.
 * Небольшая гибкость сохранена для "переоткрытия" done/cancelled.
 */
function planner_task_status_transition_allowed(string $from, string $to): bool
{
    if ($from === $to) {
        return true;
    }
    $allowed = [
        'new' => ['in_progress', 'cancelled'],
        'in_progress' => ['new', 'review', 'done', 'cancelled'],
        'review' => ['in_progress', 'done', 'cancelled'],
        'done' => ['in_progress', 'cancelled'],
        'cancelled' => ['new', 'in_progress'],
    ];
    return in_array($to, $allowed[$from] ?? [], true);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['ok' => false, 'error' => 'bad_id'], 422);
}

$hasProjectDept = planner_table_has_column('projects', 'department_id');
if ($hasProjectDept) {
    $st = db()->prepare(
        'SELECT t.*, p.name AS project_name, p.department_id AS project_department_id,
                d.name AS department_name, ua.name AS assignee_name, au.name AS author_name
         FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN departments d ON d.id = p.department_id
         LEFT JOIN users ua ON ua.id = t.assignee_id
         LEFT JOIN users au ON au.id = t.author_id
         WHERE t.id = ? AND t.organization_id = ? LIMIT 1'
    );
} else {
    $st = db()->prepare(
        'SELECT t.*, p.name AS project_name, NULL AS project_department_id,
                NULL AS department_name, ua.name AS assignee_name, au.name AS author_name
         FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN users ua ON ua.id = t.assignee_id
         LEFT JOIN users au ON au.id = t.author_id
         WHERE t.id = ? AND t.organization_id = ? LIMIT 1'
    );
}
$st->execute([$id, $orgId]);
$task = $st->fetch(PDO::FETCH_ASSOC);
if (!$task) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}
if (!task_visible_to_user($user, $id)) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$method = effective_request_method();
$beforeAssigneeId = isset($task['assignee_id']) ? (int) $task['assignee_id'] : 0;

if ($method === 'GET') {
    $subOrder = planner_table_has_column('subtasks', 'sort_order') ? 'sort_order, id' : 'id';
    $st = db()->prepare(
        "SELECT id, title, is_done, created_at" .
        (planner_table_has_column('subtasks', 'sort_order') ? ', sort_order' : ', 0 AS sort_order') .
        " FROM subtasks WHERE task_id = ? ORDER BY $subOrder"
    );
    $st->execute([$id]);
    $task['subtasks'] = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($task['subtasks'] !== []) {
        $subAssistants = planner_subtask_assistants_payload_map($id);
        foreach ($task['subtasks'] as &$sub) {
            $sid = (int) ($sub['id'] ?? 0);
            $sub['assistants'] = $sid > 0 && isset($subAssistants[$sid]) ? $subAssistants[$sid] : [];
        }
        unset($sub);
    }
    $st = db()->prepare(
        'SELECT a.id, a.action, a.message, a.created_at, u.name AS user_name
         FROM activity_log a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.organization_id = ? AND a.entity_type = ? AND a.entity_id = ?
         ORDER BY a.id DESC
         LIMIT 50'
    );
    $st->execute([$orgId, 'task', $id]);
    $task['history'] = $st->fetchAll(PDO::FETCH_ASSOC);
    $task['tags'] = [];
    if (planner_table_exists('task_tag_map') && planner_table_exists('tags')) {
        $st = db()->prepare(
            'SELECT tg.id, tg.name, tg.color FROM tags tg
             INNER JOIN task_tag_map m ON m.tag_id = tg.id
             WHERE m.task_id = ? AND tg.organization_id = ?
             ORDER BY tg.name'
        );
        $st->execute([$id, $orgId]);
        $task['tags'] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $task['links'] = ['outgoing' => [], 'incoming' => []];
    if (planner_table_exists('task_links')) {
        $st = db()->prepare(
            'SELECT u.link_dir, u.id, u.link_type, u.peer_id, u.peer_title, u.peer_status FROM (
                SELECT \'out\' AS link_dir, tl.id, tl.link_type, tl.linked_task_id AS peer_id,
                    t.title AS peer_title, t.status AS peer_status
                FROM task_links tl
                INNER JOIN tasks t ON t.id = tl.linked_task_id AND t.organization_id = ?
                WHERE tl.task_id = ? AND tl.organization_id = ?
                UNION ALL
                SELECT \'in\', tl.id, tl.link_type, tl.task_id,
                    t.title, t.status
                FROM task_links tl
                INNER JOIN tasks t ON t.id = tl.task_id AND t.organization_id = ?
                WHERE tl.linked_task_id = ? AND tl.organization_id = ?
            ) u ORDER BY u.link_dir, u.id'
        );
        $st->execute([$orgId, $id, $orgId, $orgId, $id, $orgId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $lr) {
            $dir = (string) ($lr['link_dir'] ?? '');
            if ($dir === 'out') {
                $task['links']['outgoing'][] = [
                    'id' => (int) ($lr['id'] ?? 0),
                    'link_type' => (string) ($lr['link_type'] ?? ''),
                    'linked_task_id' => (int) ($lr['peer_id'] ?? 0),
                    'linked_title' => (string) ($lr['peer_title'] ?? ''),
                    'linked_status' => (string) ($lr['peer_status'] ?? ''),
                ];
            } elseif ($dir === 'in') {
                $task['links']['incoming'][] = [
                    'id' => (int) ($lr['id'] ?? 0),
                    'link_type' => (string) ($lr['link_type'] ?? ''),
                    'from_task_id' => (int) ($lr['peer_id'] ?? 0),
                    'from_title' => (string) ($lr['peer_title'] ?? ''),
                    'from_status' => (string) ($lr['peer_status'] ?? ''),
                ];
            }
        }
    }
    $task['attachments'] = [];
    if (planner_table_exists('task_attachments')) {
        $st = db()->prepare(
            'SELECT a.id, a.original_name, a.mime, a.size_bytes, a.comment_id, a.created_at, u.name AS uploaded_by_name
             FROM task_attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.task_id = ? AND a.organization_id = ?
             ORDER BY a.id DESC'
        );
        $st->execute([$id, $orgId]);
        $task['attachments'] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $task['participants'] = planner_task_participants_payload($id);
    $task['my_plan_stage_id'] = null;
    if (planner_my_plan_tables_ready()) {
        $st = db()->prepare(
            'SELECT stage_id FROM task_my_plan_placement WHERE task_id = ? AND user_id = ? LIMIT 1'
        );
        $st->execute([$id, $userId]);
        $sid = $st->fetchColumn();
        $task['my_plan_stage_id'] = $sid !== false && $sid !== null ? (int) $sid : null;
    }
    $role = (string) ($user['role'] ?? '');
    $uid = (int) ($user['id'] ?? 0);
    if (in_array($role, ['admin', 'owner'], true) || user_is_institute_manager($user)) {
        $task['may_edit'] = true;
    } elseif (
        $uid > 0
        && (
            (int) ($task['author_id'] ?? 0) === $uid
            || (int) ($task['assignee_id'] ?? 0) === $uid
        )
    ) {
        $task['may_edit'] = true;
    } else {
        $task['may_edit'] = user_may_edit_task($user, $id);
    }
    json_response(['ok' => true, 'task' => $task]);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $body = read_json_body();
    if (planner_my_plan_tables_ready() && planner_my_plan_body_is_only_stage_move($body)) {
        if (!task_visible_to_user($user, $id)) {
            json_response(['ok' => false, 'error' => 'not_found'], 404);
        }
        $raw = $body['my_plan_stage_id'];
        $sidInt = null;
        if ($raw !== null && $raw !== '') {
            $sidInt = (int) $raw;
            if ($sidInt <= 0) {
                json_response(['ok' => false, 'error' => 'bad_plan_stage'], 422);
            }
        }
        planner_my_plan_ensure_user_stages($orgId, $userId);
        planner_my_plan_upsert_placement($orgId, $userId, $id, $sidInt);
        json_response(['ok' => true]);
    }
    if (!user_may_edit_task($user, $id)) {
        json_response(['ok' => false, 'error' => 'forbidden_edit'], 403);
    }
    $beforeStatus = (string) $task['status'];
    $beforeDueDate = isset($task['due_date']) && $task['due_date'] !== null ? (string) $task['due_date'] : null;
    $beforeRecurrence = planner_table_has_column('tasks', 'recurrence')
        ? (string) ($task['recurrence'] ?? 'none')
        : 'none';
    $fields = [];
    $params = [];
    if (array_key_exists('title', $body)) {
        $t = trim((string) $body['title']);
        if ($t === '') {
            json_response(['ok' => false, 'error' => 'title_empty'], 422);
        }
        $fields[] = 'title = ?';
        $params[] = $t;
    }
    if (array_key_exists('description', $body)) {
        $fields[] = 'description = ?';
        $params[] = (string) $body['description'] !== '' ? (string) $body['description'] : null;
    }
    if (array_key_exists('status', $body)) {
        $s = (string) $body['status'];
        if (!in_array($s, ['new', 'in_progress', 'review', 'done', 'cancelled'], true)) {
            json_response(['ok' => false, 'error' => 'bad_status'], 422);
        }
        if (!planner_task_status_transition_allowed($beforeStatus, $s)) {
            json_response(['ok' => false, 'error' => 'bad_status_transition'], 422);
        }
        if (
            $s === 'review'
            && $beforeStatus !== 'review'
            && planner_table_has_column('tasks', 'transition_note')
        ) {
            // Технические поля не должны провоцировать обязательную transition_note.
            $keysOther = array_diff(array_keys($body), ['status', 'my_plan_lane', 'my_plan_stage_id', 'transition_note']);
            $minimalLaneChange = $keysOther === [];
            if (!$minimalLaneChange) {
                $tn = trim((string) ($body['transition_note'] ?? ''));
                if ($tn === '') {
                    json_response(['ok' => false, 'error' => 'transition_note_required'], 422);
                }
            }
        }
        $fields[] = 'status = ?';
        $params[] = $s;
        if (planner_table_has_column('tasks', 'status_changed_at') && $s !== $beforeStatus) {
            $fields[] = 'status_changed_at = NOW()';
        }
    }
    if (array_key_exists('priority', $body)) {
        $p = (string) $body['priority'];
        if (!in_array($p, ['low', 'normal', 'high', 'critical'], true)) {
            json_response(['ok' => false, 'error' => 'bad_priority'], 422);
        }
        $fields[] = 'priority = ?';
        $params[] = $p;
    }
    if (array_key_exists('project_id', $body)) {
        $pid = $body['project_id'] !== null && $body['project_id'] !== '' ? (int) $body['project_id'] : 0;
        if ($pid <= 0) {
            json_response(['ok' => false, 'error' => 'project_required'], 422);
        }
        $prevProjectId = isset($task['project_id']) ? (int) $task['project_id'] : 0;
        if ($pid !== $prevProjectId && (int) ($task['author_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            json_response(['ok' => false, 'error' => 'project_change_forbidden'], 403);
        }
        if (!project_visible_to_user($user, $pid)) {
            json_response(['ok' => false, 'error' => 'forbidden_project'], 403);
        }
        if (planner_table_has_column('tasks', 'work_year') && planner_table_has_column('projects', 'work_year')) {
            $chk = db()->prepare('SELECT work_year FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
            $chk->execute([$pid, $orgId]);
            $pwy = $chk->fetchColumn();
            if ($pwy === false) {
                json_response(['ok' => false, 'error' => 'bad_project'], 422);
            }
            $twy = (int) ($task['work_year'] ?? planner_org_work_year_for_org($orgId));
            if ((int) $pwy !== $twy) {
                json_response(['ok' => false, 'error' => 'project_year_mismatch'], 422);
            }
        } else {
            $chk = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ?');
            $chk->execute([$pid, $orgId]);
            if (!$chk->fetch()) {
                json_response(['ok' => false, 'error' => 'bad_project'], 422);
            }
        }
        $fields[] = 'project_id = ?';
        $params[] = $pid;
    }
    if (array_key_exists('assignee_id', $body)) {
        $aid = $body['assignee_id'] !== null && $body['assignee_id'] !== '' ? (int) $body['assignee_id'] : null;
        if ($aid) {
            $chk = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ?');
            $chk->execute([$aid, $orgId]);
            if (!$chk->fetch()) {
                json_response(['ok' => false, 'error' => 'bad_assignee'], 422);
            }
        }
        $fields[] = 'assignee_id = ?';
        $params[] = $aid;
    }
    if (array_key_exists('due_date', $body)) {
        $due = planner_parse_due_datetime_input($body['due_date'], planner_org_default_due_time_hms($orgId));
        $fields[] = 'due_date = ?';
        $params[] = $due;
    }
    if (planner_table_has_column('tasks', 'remind_at') && array_key_exists('remind_at', $body)) {
        $r = $body['remind_at'];
        $rem = null;
        if (is_string($r) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $r)) {
            $rem = $r;
        }
        $fields[] = 'remind_at = ?';
        $params[] = $rem;
    }
    if (planner_table_has_column('tasks', 'recurrence') && array_key_exists('recurrence', $body)) {
        $rec = (string) $body['recurrence'];
        if (!in_array($rec, ['none', 'weekly', 'monthly'], true)) {
            json_response(['ok' => false, 'error' => 'bad_recurrence'], 422);
        }
        $fields[] = 'recurrence = ?';
        $params[] = $rec;
    }
    if (planner_table_has_column('tasks', 'my_plan_lane') && array_key_exists('my_plan_lane', $body)) {
        $rawLane = $body['my_plan_lane'];
        if ($rawLane === null || $rawLane === '') {
            $fields[] = 'my_plan_lane = ?';
            $params[] = null;
        } else {
            $lane = (string) $rawLane;
            if (!in_array($lane, ['unplanned', 'active', 'week'], true)) {
                json_response(['ok' => false, 'error' => 'bad_plan_lane'], 422);
            }
            $fields[] = 'my_plan_lane = ?';
            $params[] = $lane;
        }
    }
    if (planner_table_has_column('tasks', 'transition_note') && array_key_exists('transition_note', $body)) {
        $tn = trim((string) $body['transition_note']);
        $fields[] = 'transition_note = ?';
        $params[] = $tn !== ''
            ? (function_exists('mb_substr') ? mb_substr($tn, 0, 500, 'UTF-8') : substr($tn, 0, 500))
            : null;
    }
    if (planner_table_has_column('tasks', 'visible_department') && array_key_exists('visible_department', $body)) {
        $fields[] = 'visible_department = ?';
        $params[] = !empty($body['visible_department']) ? 1 : 0;
    }
    if (planner_table_has_column('tasks', 'visible_institute') && array_key_exists('visible_institute', $body)) {
        $fields[] = 'visible_institute = ?';
        $params[] = !empty($body['visible_institute']) ? 1 : 0;
    }

    $newStatusForClone = array_key_exists('status', $body) ? (string) $body['status'] : $beforeStatus;
    $statusBecameDone = $newStatusForClone === 'done' && $beforeStatus !== 'done';

    $participantBody =
        planner_table_exists('task_participants')
        && (array_key_exists('assistant_ids', $body) || array_key_exists('observer_ids', $body));
    if ($fields === [] && !array_key_exists('tag_ids', $body) && !$participantBody) {
        json_response(['ok' => false, 'error' => 'no_fields'], 422);
    }
    $maxAddedAssistantIds = [];
    $afterAssigneeId = 0;
    $afterDueDate = $beforeDueDate;
    try {
        db()->beginTransaction();
        if ($fields !== []) {
            $params[] = $id;
            $params[] = $orgId;
            $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?';
            db()->prepare($sql)->execute($params);
            log_activity($orgId, $userId, 'update', 'task', $id, 'Обновлена задача');
        }
        if (array_key_exists('tag_ids', $body) && planner_table_exists('task_tag_map')) {
            $rawTags = $body['tag_ids'];
            $tagList = is_array($rawTags) ? $rawTags : [];
            planner_sync_task_tags($orgId, $id, $tagList);
            if ($fields === []) {
                log_activity($orgId, $userId, 'update', 'task', $id, 'Обновлены метки задачи');
            }
        }
        if ($participantBody) {
            $beforeAssistantIds = [];
            if (planner_table_exists('task_participants')) {
                $stBa = db()->prepare(
                    "SELECT user_id FROM task_participants WHERE task_id = ? AND role = 'assistant'"
                );
                $stBa->execute([$id]);
                $beforeAssistantIds = array_map(
                    static fn (array $r): int => (int) $r['user_id'],
                    $stBa->fetchAll(PDO::FETCH_ASSOC)
                );
            }
            $a = isset($body['assistant_ids']) && is_array($body['assistant_ids']) ? $body['assistant_ids'] : [];
            $o = isset($body['observer_ids']) && is_array($body['observer_ids']) ? $body['observer_ids'] : [];
            planner_sync_task_participants($orgId, $id, $a, $o);
            if (planner_table_exists('task_participants')) {
                $stAa = db()->prepare(
                    "SELECT user_id FROM task_participants WHERE task_id = ? AND role = 'assistant'"
                );
                $stAa->execute([$id]);
                $afterAssistantIds = array_map(
                    static fn (array $r): int => (int) $r['user_id'],
                    $stAa->fetchAll(PDO::FETCH_ASSOC)
                );
                $maxAddedAssistantIds = array_values(array_diff($afterAssistantIds, $beforeAssistantIds));
            }
            if ($fields === [] && !array_key_exists('tag_ids', $body)) {
                log_activity($orgId, $userId, 'update', 'task', $id, 'Обновлён состав участников');
            }
        }
        $stAfter = db()->prepare('SELECT status, project_id, assignee_id, due_date FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
        $stAfter->execute([$id, $orgId]);
        $rowAfter = $stAfter->fetch(PDO::FETCH_ASSOC);
        $newStatus = $rowAfter ? (string) $rowAfter['status'] : $beforeStatus;
        $projAfter = $rowAfter && isset($rowAfter['project_id']) ? (int) $rowAfter['project_id'] : null;
        $afterAssigneeId = $rowAfter && isset($rowAfter['assignee_id']) ? (int) $rowAfter['assignee_id'] : 0;
        $afterDueDate = $rowAfter && array_key_exists('due_date', $rowAfter) && $rowAfter['due_date'] !== null
            ? (string) $rowAfter['due_date']
            : null;
        if ($newStatus !== $beforeStatus) {
            planner_run_task_automation_rules($orgId, $userId, $id, $newStatus, $projAfter > 0 ? $projAfter : null);
        }
        if (
            $statusBecameDone
            && planner_table_has_column('tasks', 'recurrence')
            && in_array($beforeRecurrence, ['weekly', 'monthly'], true)
        ) {
            $st = db()->prepare('SELECT * FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
            $st->execute([$id, $orgId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                planner_clone_recurring_followup($orgId, $userId, $row);
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
    if ($afterAssigneeId > 0 && $afterAssigneeId !== $beforeAssigneeId) {
        planner_notify_task_assignee_email($orgId, $id, $afterAssigneeId, $userId);
        planner_notify_max_task_assigned($orgId, $id, $afterAssigneeId, $userId);
    }
    if ($newStatus !== $beforeStatus) {
        planner_notify_max_task_status_changed($orgId, $id, $beforeStatus, $newStatus, $userId);
    }
    planner_notify_max_task_due_changed($orgId, $id, $beforeDueDate, $afterDueDate ?? null, $userId);
    if ($maxAddedAssistantIds !== []) {
        $titleForMax = array_key_exists('title', $body)
            ? trim((string) $body['title'])
            : trim((string) ($task['title'] ?? ''));
        planner_notify_max_assistants_added(
            $orgId,
            $id,
            $maxAddedAssistantIds,
            $userId,
            $titleForMax
        );
    }
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    if (!user_may_delete_task($user, $id)) {
        json_response(['ok' => false, 'error' => 'forbidden_delete'], 403);
    }
    $st = db()->prepare('DELETE FROM tasks WHERE id = ? AND organization_id = ?');
    $st->execute([$id, $orgId]);
    log_activity($orgId, $userId, 'delete', 'task', $id, 'Удалена задача');
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
