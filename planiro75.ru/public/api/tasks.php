<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';
require_once dirname(__DIR__, 2) . '/includes/my_plan_stages.php';

$scope = planner_department_scope_for_projects($user, 'p');
$hasProjectDept = planner_table_has_column('projects', 'department_id');
$taskExtraCols = '';
if (planner_table_has_column('tasks', 'remind_at')) {
    $taskExtraCols .= ', t.remind_at, t.recurrence';
}
if (planner_table_has_column('tasks', 'visible_department')) {
    $taskExtraCols .= ', t.visible_department';
}
if (planner_table_has_column('tasks', 'visible_institute')) {
    $taskExtraCols .= ', t.visible_institute';
}
$subtasksJoinSql = '
                LEFT JOIN (
                    SELECT task_id,
                        COUNT(*) AS subtasks_total,
                        SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) AS subtasks_done
                    FROM subtasks
                    GROUP BY task_id
                ) sub_agg ON sub_agg.task_id = t.id';

$tagJoinSql = '';
$tagLineSelect = 'NULL AS tag_line';
if (planner_table_exists('task_tag_map') && planner_table_exists('tags')) {
    $tagJoinSql = '
                LEFT JOIN (
                    SELECT m.task_id,
                        GROUP_CONCAT(tg.name ORDER BY tg.name SEPARATOR \' · \') AS tag_line
                    FROM task_tag_map m
                    INNER JOIN tags tg ON tg.id = m.tag_id
                    GROUP BY m.task_id
                ) tag_agg ON tag_agg.task_id = t.id';
    $tagLineSelect = 'tag_agg.tag_line';
}

$participantJoinSql = '';
$assistantsSelect = 'NULL AS assistants_line';
$observersSelect = 'NULL AS observers_line';
if (planner_table_exists('task_participants')) {
    $participantJoinSql = '
                LEFT JOIN (
                    SELECT tp.task_id,
                        GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR \', \') AS assistants_line
                    FROM task_participants tp
                    INNER JOIN users u ON u.id = tp.user_id
                    WHERE tp.role = \'assistant\'
                    GROUP BY tp.task_id
                ) asst_agg ON asst_agg.task_id = t.id
                LEFT JOIN (
                    SELECT tp.task_id,
                        GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR \', \') AS observers_line
                    FROM task_participants tp
                    INNER JOIN users u ON u.id = tp.user_id
                    WHERE tp.role = \'observer\'
                    GROUP BY tp.task_id
                ) obs_agg ON obs_agg.task_id = t.id';
    $assistantsSelect = 'asst_agg.assistants_line';
    $observersSelect = 'obs_agg.observers_line';
}

$ganttPredJoinSql = '';
$ganttPredSelectCol = 'NULL AS gantt_predecessor_ids';
if (planner_table_exists('task_links')) {
    $ganttOrgSql = (string) (int) $orgId;
    $ganttPredJoinSql = '
                LEFT JOIN (
                    SELECT tl.linked_task_id AS task_id,
                        GROUP_CONCAT(tl.task_id ORDER BY tl.task_id SEPARATOR \',\') AS gantt_predecessor_ids
                    FROM task_links tl
                    WHERE tl.link_type = \'blocks\'
                      AND tl.organization_id = ' . $ganttOrgSql . '
                    GROUP BY tl.linked_task_id
                ) pred_agg ON pred_agg.task_id = t.id';
    $ganttPredSelectCol = 'pred_agg.gantt_predecessor_ids';
}

$listExtraSelect = ',
                       ' . $tagLineSelect . ',
                       ' . $assistantsSelect . ',
                       ' . $observersSelect . ',
                       ' . $ganttPredSelectCol;

$method = effective_request_method();

if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
    $q = trim((string) ($_GET['q'] ?? ''));
    $roleScope = trim((string) ($_GET['role_scope'] ?? ''));
    $visibilityScope = trim((string) ($_GET['visibility_scope'] ?? ''));

    $filterPayload = [
        'status' => $status,
        'project_id' => $projectId ?: 0,
        'q' => $q,
        'role_scope' => $roleScope,
        'visibility_scope' => $visibilityScope,
    ];
    if (isset($_GET['assignee_id']) && (int) $_GET['assignee_id'] > 0) {
        $filterPayload['assignee_id'] = (int) $_GET['assignee_id'];
    }
    if (isset($_GET['assignee_empty']) && (string) $_GET['assignee_empty'] === '1') {
        $filterPayload['assignee_empty'] = 1;
    }
    if (isset($_GET['participant_user_id']) && (int) $_GET['participant_user_id'] > 0) {
        $filterPayload['participant_user_id'] = (int) $_GET['participant_user_id'];
    }
    $tagCsv = trim((string) ($_GET['tag_ids'] ?? ''));
    if ($tagCsv !== '') {
        $filterPayload['tag_ids'] = array_values(array_filter(array_map('intval', explode(',', $tagCsv)), static function (int $x): bool {
            return $x > 0;
        }));
    }
    if (isset($_GET['due_from']) && trim((string) $_GET['due_from']) !== '') {
        $filterPayload['due_from'] = (string) $_GET['due_from'];
    }
    if (isset($_GET['due_to']) && trim((string) $_GET['due_to']) !== '') {
        $filterPayload['due_to'] = (string) $_GET['due_to'];
    }
    if (array_key_exists('has_comments', $_GET)) {
        $filterPayload['has_comments'] = $_GET['has_comments'];
    }
    if (array_key_exists('has_attachments', $_GET)) {
        $filterPayload['has_attachments'] = $_GET['has_attachments'];
    }
    if (isset($_GET['department_id']) && (int) $_GET['department_id'] > 0) {
        $filterPayload['department_id'] = (int) $_GET['department_id'];
    }
    if (isset($_GET['report_active']) && (string) $_GET['report_active'] === '1') {
        $filterPayload['report_active'] = 1;
    }
    if (isset($_GET['report_overdue']) && (string) $_GET['report_overdue'] === '1') {
        $filterPayload['report_overdue'] = 1;
    }
    if (isset($_GET['report_stuck']) && (string) $_GET['report_stuck'] === '1') {
        $filterPayload['report_stuck'] = 1;
    }
    if (isset($_GET['task_kind']) && trim((string) $_GET['task_kind']) !== '') {
        $filterPayload['task_kind'] = trim((string) $_GET['task_kind']);
    }
    if (isset($_GET['include_archived_done']) && (string) $_GET['include_archived_done'] === '1') {
        $filterPayload['include_archived_done'] = 1;
    }
    if (isset($_GET['is_quick_task']) && (string) $_GET['is_quick_task'] === '1') {
        $filterPayload['is_quick_task'] = 1;
    }
    if (isset($_GET['quick_visible_managers']) && (string) $_GET['quick_visible_managers'] === '1') {
        $filterPayload['quick_visible_managers'] = 1;
    }
    $listScope = trim((string) ($_GET['list_scope'] ?? 'my'));
    $vis = $listScope === 'department'
        ? planner_tasks_visibility_fragment($user, $scope, $hasProjectDept)
        : planner_my_tasks_visibility_fragment($user, $hasProjectDept);
    $fil = planner_tasks_filters_fragment($user, $filterPayload);
    $qp = planner_tasks_quick_privacy_fragment($user);

    $planLaneSql = planner_table_has_column('tasks', 'my_plan_lane') ? ', t.my_plan_lane' : '';
    $planStageSql = planner_my_plan_tables_ready()
        ? ', (SELECT p.stage_id FROM task_my_plan_placement p WHERE p.task_id = t.id AND p.user_id = ' . (int) $userId . ' LIMIT 1) AS my_plan_stage_id'
        : '';
    $quickColsSql = '';
    if (planner_table_has_column('tasks', 'is_quick_task')) {
        $quickColsSql .= ', t.is_quick_task';
    }
    if (planner_table_has_column('tasks', 'quick_visible_managers')) {
        $quickColsSql .= ', t.quick_visible_managers';
    }

    if ($hasProjectDept) {
        $fromSql = '
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                INNER JOIN departments d ON d.id = p.department_id
                LEFT JOIN users ua ON ua.id = t.assignee_id
                LEFT JOIN users au ON au.id = t.author_id'
            . $subtasksJoinSql . $tagJoinSql . $participantJoinSql . $ganttPredJoinSql;
        $selectCols = 'SELECT t.id, t.title, t.status, t.priority, t.due_date, t.created_at, t.updated_at,
                       t.project_id, p.name AS project_name, d.name AS department_name,
                       t.assignee_id, ua.name AS assignee_name,
                       t.author_id, au.name AS author_name,
                       COALESCE(sub_agg.subtasks_total, 0) AS subtasks_total,
                       COALESCE(sub_agg.subtasks_done, 0) AS subtasks_done'
            . $planLaneSql . $planStageSql . $quickColsSql . $listExtraSelect;
    } else {
        $fromSql = '
                FROM tasks t
                LEFT JOIN projects p ON p.id = t.project_id
                LEFT JOIN users ua ON ua.id = t.assignee_id
                LEFT JOIN users au ON au.id = t.author_id'
            . $subtasksJoinSql . $tagJoinSql . $participantJoinSql . $ganttPredJoinSql;
        $selectCols = 'SELECT t.id, t.title, t.status, t.priority, t.due_date, t.created_at, t.updated_at,
                       t.project_id, p.name AS project_name, NULL AS department_name,
                       t.assignee_id, ua.name AS assignee_name,
                       t.author_id, au.name AS author_name,
                       COALESCE(sub_agg.subtasks_total, 0) AS subtasks_total,
                       COALESCE(sub_agg.subtasks_done, 0) AS subtasks_done'
            . $planLaneSql . $planStageSql . $quickColsSql . $listExtraSelect;
    }

    $whereSql = ' WHERE t.organization_id = ?' . $vis['sql'] . $fil['sql'] . $qp['sql'];
    $params = array_merge([$orgId], $vis['params'], $fil['params'], $qp['params']);

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 80;
    $limit = max(1, min(200, $limit));
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $offset = max(0, $offset);

    $countSql = 'SELECT COUNT(*) AS c' . $fromSql . $whereSql;
    $stCount = db()->prepare($countSql);
    planner_timed_execute($stCount, $params, $countSql, 'tasks.count');
    $totalRow = $stCount->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalCount = (int) ($totalRow['c'] ?? 0);

    $hasCommentsTbl = planner_table_exists('task_comments');
    $statsCommentJoin = '';
    if ($hasCommentsTbl) {
        $statsCommentJoin = '
                LEFT JOIN (
                    SELECT task_id,
                        COUNT(*) AS comment_rows,
                        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS comment_rows_week
                    FROM task_comments
                    GROUP BY task_id
                ) tc_stats ON tc_stats.task_id = t.id';
        $statsSelect = 'SELECT
            SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < NOW() AND t.status NOT IN (\'done\',\'cancelled\') THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN COALESCE(tc_stats.comment_rows, 0) > 0 THEN 1 ELSE 0 END) AS tasks_with_comments,
            SUM(CASE WHEN COALESCE(tc_stats.comment_rows_week, 0) > 0 THEN 1 ELSE 0 END) AS chats_week_count';
    } else {
        $statsSelect = 'SELECT
            SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < NOW() AND t.status NOT IN (\'done\',\'cancelled\') THEN 1 ELSE 0 END) AS overdue_count,
            0 AS tasks_with_comments,
            0 AS chats_week_count';
    }

    $statsSql = $statsSelect . $fromSql . $statsCommentJoin . $whereSql;
    $stStats = db()->prepare($statsSql);
    planner_timed_execute($stStats, $params, $statsSql, 'tasks.stats');
    $statsRow = $stStats->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats = [
        'overdue' => (int) ($statsRow['overdue_count'] ?? 0),
        'tasks_with_comments' => (int) ($statsRow['tasks_with_comments'] ?? 0),
        'chats_week' => (int) ($statsRow['chats_week_count'] ?? 0),
    ];

    $sql = $selectCols . $taskExtraCols . $fromSql . $whereSql
        . ' ORDER BY t.updated_at DESC, t.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $st = db()->prepare($sql);
    planner_timed_execute($st, $params, $sql, 'tasks.list');
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$row) {
        $raw = $row['gantt_predecessor_ids'] ?? null;
        if ($raw === null || $raw === '') {
            $row['gantt_predecessor_ids'] = [];
        } else {
            $row['gantt_predecessor_ids'] = array_values(
                array_filter(array_map('intval', explode(',', (string) $raw)), static fn ($v) => $v > 0)
            );
        }
    }
    unset($row);
    $hasMore = $offset + count($items) < $totalCount;
    json_response([
        'ok' => true,
        'items' => $items,
        'stats' => $stats,
        'total' => $totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => $hasMore,
    ]);
}

if ($method === 'POST') {
    $body = read_json_body();
    $templateId = isset($body['template_id']) ? (int) $body['template_id'] : 0;
    $tpl = null;
    if ($templateId > 0 && planner_table_exists('task_templates')) {
        $st = db()->prepare('SELECT * FROM task_templates WHERE id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$templateId, $orgId]);
        $tpl = $st->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) {
            json_response(['ok' => false, 'error' => 'bad_template'], 422);
        }
    }

    $title = trim((string) ($body['title'] ?? ''));
    if ($title === '' && $tpl) {
        $title = trim((string) $tpl['title']);
    }
    if ($title === '') {
        json_response(['ok' => false, 'error' => 'title_required'], 422);
    }
    $description = isset($body['description']) ? (string) $body['description'] : null;
    if ($description === null && $tpl && isset($tpl['description'])) {
        $description = (string) $tpl['description'];
    }
    if ($description === '') {
        $description = null;
    }
    $status = $body['status'] ?? ($tpl ? (string) $tpl['status'] : 'new');
    if (!in_array($status, ['new', 'in_progress', 'review', 'done', 'cancelled'], true)) {
        $status = 'new';
    }
    $priority = $body['priority'] ?? ($tpl ? (string) $tpl['priority'] : 'normal');
    if (!in_array($priority, ['low', 'normal', 'high', 'critical'], true)) {
        $priority = 'normal';
    }
    $projectId = isset($body['project_id']) ? (int) $body['project_id'] : 0;
    if ($projectId <= 0 && $tpl) {
        $projectId = (int) $tpl['project_id'];
    }
    if ($projectId <= 0) {
        json_response(['ok' => false, 'error' => 'project_id_required'], 422);
    }
    if (!project_visible_to_user($user, $projectId)) {
        json_response(['ok' => false, 'error' => 'forbidden_project'], 403);
    }
    if (planner_table_has_column('projects', 'work_year')) {
        $st = db()->prepare(
            'SELECT id FROM projects WHERE id = ? AND organization_id = ? AND work_year = ? LIMIT 1'
        );
        $st->execute([$projectId, $orgId, planner_org_work_year_for_org($orgId)]);
    } else {
        $st = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$projectId, $orgId]);
    }
    if (!$st->fetch()) {
        json_response(['ok' => false, 'error' => 'bad_project'], 422);
    }
    $assigneeId = isset($body['assignee_id']) ? (int) $body['assignee_id'] : null;
    if (!$assigneeId && $tpl && !empty($tpl['assignee_id'])) {
        $assigneeId = (int) $tpl['assignee_id'];
    }
    if ($assigneeId) {
        $st = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$assigneeId, $orgId]);
        if (!$st->fetch()) {
            $assigneeId = null;
        }
    }
    $isQuickCreate = !empty($body['is_quick_task']);
    if ($isQuickCreate && !$assigneeId) {
        $assigneeId = $userId;
    }
    if ($isQuickCreate && $status === 'new') {
        $status = 'in_progress';
    }
    $due = $body['due_date'] ?? null;
    $dueDate = planner_parse_due_datetime_input($due, planner_org_default_due_time_hms($orgId));
    $remindDate = null;
    if (planner_table_has_column('tasks', 'remind_at')) {
        $r = $body['remind_at'] ?? null;
        if (is_string($r) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $r)) {
            $remindDate = $r;
        }
    }
    $recurrence = 'none';
    if (planner_table_has_column('tasks', 'recurrence')) {
        $rec = (string) ($body['recurrence'] ?? 'none');
        $recurrence = in_array($rec, ['none', 'weekly', 'monthly'], true) ? $rec : 'none';
    }
    $visibleDepartment = planner_table_has_column('tasks', 'visible_department')
        ? (!empty($body['visible_department']) ? 1 : 0)
        : 0;
    $visibleInstitute = planner_table_has_column('tasks', 'visible_institute')
        ? (!empty($body['visible_institute']) ? 1 : 0)
        : 0;
    $id = 0;
    try {
        db()->beginTransaction();
        $insWorkYear = planner_org_work_year_for_org($orgId);
        if (planner_table_has_column('tasks', 'remind_at')) {
            if (planner_table_has_column('tasks', 'work_year')) {
                $insCols = ['organization_id', 'work_year', 'project_id', 'title', 'description', 'status', 'priority', 'author_id', 'assignee_id', 'due_date', 'remind_at', 'recurrence'];
                $insVals = [$orgId, $insWorkYear, $projectId, $title, $description !== '' ? $description : null, $status, $priority, $userId, $assigneeId, $dueDate, $remindDate, $recurrence];
            } else {
                $insCols = ['organization_id', 'project_id', 'title', 'description', 'status', 'priority', 'author_id', 'assignee_id', 'due_date', 'remind_at', 'recurrence'];
                $insVals = [$orgId, $projectId, $title, $description !== '' ? $description : null, $status, $priority, $userId, $assigneeId, $dueDate, $remindDate, $recurrence];
            }
            if (planner_table_has_column('tasks', 'visible_department')) {
                $insCols[] = 'visible_department';
                $insVals[] = $visibleDepartment;
            }
            if (planner_table_has_column('tasks', 'visible_institute')) {
                $insCols[] = 'visible_institute';
                $insVals[] = $visibleInstitute;
            }
            $ph = implode(',', array_fill(0, count($insCols), '?'));
            $st = db()->prepare('INSERT INTO tasks (' . implode(', ', $insCols) . ') VALUES (' . $ph . ')');
            $st->execute($insVals);
        } else {
            if (planner_table_has_column('tasks', 'work_year')) {
                $insCols = ['organization_id', 'work_year', 'project_id', 'title', 'description', 'status', 'priority', 'author_id', 'assignee_id', 'due_date'];
                $insVals = [$orgId, $insWorkYear, $projectId, $title, $description !== '' ? $description : null, $status, $priority, $userId, $assigneeId, $dueDate];
            } else {
                $insCols = ['organization_id', 'project_id', 'title', 'description', 'status', 'priority', 'author_id', 'assignee_id', 'due_date'];
                $insVals = [$orgId, $projectId, $title, $description !== '' ? $description : null, $status, $priority, $userId, $assigneeId, $dueDate];
            }
            if (planner_table_has_column('tasks', 'visible_department')) {
                $insCols[] = 'visible_department';
                $insVals[] = $visibleDepartment;
            }
            if (planner_table_has_column('tasks', 'visible_institute')) {
                $insCols[] = 'visible_institute';
                $insVals[] = $visibleInstitute;
            }
            $ph = implode(',', array_fill(0, count($insCols), '?'));
            $st = db()->prepare('INSERT INTO tasks (' . implode(', ', $insCols) . ') VALUES (' . $ph . ')');
            $st->execute($insVals);
        }
        $id = (int) db()->lastInsertId();
        if (
            $id > 0
            && planner_table_has_column('tasks', 'is_quick_task')
            && planner_table_has_column('tasks', 'quick_visible_managers')
        ) {
            if ($isQuickCreate) {
                $qVis = array_key_exists('quick_visible_managers', $body)
                    ? (!empty($body['quick_visible_managers']) ? 1 : 0)
                    : planner_quick_visible_default_from_project($orgId, $projectId);
                $stQ = db()->prepare(
                    'UPDATE tasks SET is_quick_task = 1, quick_visible_managers = ? WHERE id = ? AND organization_id = ?'
                );
                $stQ->execute([$qVis, $id, $orgId]);
            }
        }
        if (planner_table_has_column('tasks', 'status_changed_at') && $id > 0) {
            db()->prepare(
                'UPDATE tasks SET status_changed_at = COALESCE(created_at, CURRENT_TIMESTAMP) WHERE id = ? AND organization_id = ?'
            )->execute([$id, $orgId]);
        }
        if (planner_table_has_column('tasks', 'my_plan_lane') && array_key_exists('my_plan_lane', $body)) {
            $lane = (string) ($body['my_plan_lane'] ?? '');
            if (in_array($lane, ['unplanned', 'active', 'week'], true)) {
                $stLane = db()->prepare('UPDATE tasks SET my_plan_lane = ? WHERE id = ? AND organization_id = ?');
                $stLane->execute([$lane, $id, $orgId]);
            }
        }
        if (planner_my_plan_tables_ready() && $id > 0 && array_key_exists('my_plan_stage_id', $body)) {
            planner_my_plan_ensure_user_stages($orgId, $userId);
            $sid = (int) ($body['my_plan_stage_id'] ?? 0);
            if ($sid > 0 && planner_my_plan_stage_owned_by_user($orgId, $userId, $sid)) {
                planner_my_plan_upsert_placement($orgId, $userId, $id, $sid);
            }
        }
        log_activity(
            $orgId,
            $userId,
            'create',
            'task',
            $id,
            ($isQuickCreate ? 'Быстрая задача: ' : 'Создана задача: ') . $title
        );
        if ($tpl && planner_table_exists('task_tag_map')) {
            $tagArr = [];
            if (!empty($tpl['tag_ids_json'])) {
                $d = json_decode((string) $tpl['tag_ids_json'], true);
                $tagArr = is_array($d) ? $d : [];
            }
            planner_sync_task_tags($orgId, $id, $tagArr);
        } elseif (array_key_exists('tag_ids', $body) && planner_table_exists('task_tag_map')) {
            $rawTags = $body['tag_ids'];
            planner_sync_task_tags($orgId, $id, is_array($rawTags) ? $rawTags : []);
        }
        if ($tpl && planner_table_exists('task_participants')) {
            $a = [];
            $o = [];
            if (!empty($tpl['assistant_ids_json'])) {
                $d = json_decode((string) $tpl['assistant_ids_json'], true);
                $a = is_array($d) ? array_values(array_filter(array_map('intval', $d))) : [];
            }
            if (!empty($tpl['observer_ids_json'])) {
                $d = json_decode((string) $tpl['observer_ids_json'], true);
                $o = is_array($d) ? array_values(array_filter(array_map('intval', $d))) : [];
            }
            planner_sync_task_participants($orgId, $id, $a, $o);
        } elseif (
            planner_table_exists('task_participants')
            && (array_key_exists('assistant_ids', $body) || array_key_exists('observer_ids', $body))
        ) {
            $a = isset($body['assistant_ids']) && is_array($body['assistant_ids']) ? $body['assistant_ids'] : [];
            $o = isset($body['observer_ids']) && is_array($body['observer_ids']) ? $body['observer_ids'] : [];
            planner_sync_task_participants($orgId, $id, $a, $o);
        }
        if ($tpl && planner_table_exists('subtasks') && !empty($tpl['subtasks_json'])) {
            $titles = json_decode((string) $tpl['subtasks_json'], true);
            if (is_array($titles)) {
                $hasSo = planner_table_has_column('subtasks', 'sort_order');
                $ins = db()->prepare(
                    $hasSo
                        ? 'INSERT INTO subtasks (task_id, title, is_done, sort_order) VALUES (?,?,0,?)'
                        : 'INSERT INTO subtasks (task_id, title, is_done) VALUES (?,?,0)'
                );
                $so = 0;
                foreach ($titles as $line) {
                    $line = trim((string) $line);
                    if ($line === '') {
                        continue;
                    }
                    if ($hasSo) {
                        $ins->execute([$id, $line, $so++]);
                    } else {
                        $ins->execute([$id, $line]);
                    }
                }
            }
        }
        planner_run_task_automation_rules($orgId, $userId, $id, $status, $projectId);
        db()->commit();
        if ($id > 0 && $assigneeId) {
            planner_notify_task_assignee_email($orgId, $id, (int) $assigneeId, $userId);
        }
        if ($id > 0) {
            planner_notify_max_task_created($orgId, $id);
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
    json_response(['ok' => true, 'id' => $id]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
