<?php
declare(strict_types=1);

/**
 * Заменяет набор тегов задачи (только теги организации).
 *
 * @param int[] $tagIds
 */
function planner_sync_task_tags(int $orgId, int $taskId, array $tagIds): void
{
    if (!planner_table_exists('task_tag_map') || !planner_table_exists('tags')) {
        return;
    }
    $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
    $tagIds = array_filter($tagIds, static fn (int $id) => $id > 0);
    if ($tagIds === []) {
        db()->prepare('DELETE FROM task_tag_map WHERE task_id = ?')->execute([$taskId]);
        return;
    }
    $ph = implode(',', array_fill(0, count($tagIds), '?'));
    $params = array_merge([$orgId], $tagIds);
    $st = db()->prepare("SELECT id FROM tags WHERE organization_id = ? AND id IN ($ph)");
    $st->execute($params);
    $allowed = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
    db()->prepare('DELETE FROM task_tag_map WHERE task_id = ?')->execute([$taskId]);
    $ins = db()->prepare('INSERT INTO task_tag_map (task_id, tag_id) VALUES (?,?)');
    foreach ($allowed as $tid) {
        $ins->execute([$taskId, $tid]);
    }
}

/** @return array{id:int,title:string,status:string,priority:string}|null */
function planner_task_brief(int $orgId, int $taskId): ?array
{
    $st = db()->prepare('SELECT id, title, status, priority FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$taskId, $orgId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function planner_clone_recurring_followup(int $orgId, int $userId, array $doneTask): int
{
    $rec = (string) ($doneTask['recurrence'] ?? 'none');
    $due = $doneTask['due_date'] ?? null;
    $remind = $doneTask['remind_at'] ?? null;
    $newDue = null;
    $newRemind = null;
    if (is_string($due) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        $sql = $rec === 'monthly' ? 'SELECT DATE_ADD(?, INTERVAL 1 MONTH)' : 'SELECT DATE_ADD(?, INTERVAL 1 WEEK)';
        $st = db()->prepare($sql);
        $st->execute([$due]);
        $newDue = $st->fetchColumn() ?: null;
    }
    if (is_string($remind) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $remind)) {
        $sql = $rec === 'monthly' ? 'SELECT DATE_ADD(?, INTERVAL 1 MONTH)' : 'SELECT DATE_ADD(?, INTERVAL 1 WEEK)';
        $st = db()->prepare($sql);
        $st->execute([$remind]);
        $newRemind = $st->fetchColumn() ?: null;
    }
    $hasExtra = planner_table_has_column('tasks', 'remind_at');
    $hasWorkYear = planner_table_has_column('tasks', 'work_year');
    $taskYear = $hasWorkYear
        ? (int) ($doneTask['work_year'] ?? planner_org_work_year_for_org($orgId))
        : 0;
    if ($hasExtra) {
        if ($hasWorkYear) {
            $st = db()->prepare(
                'INSERT INTO tasks (organization_id, work_year, project_id, title, description, status, priority, author_id, assignee_id, due_date, remind_at, recurrence, transition_note)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULL)'
            );
            $st->execute([
                $orgId,
                $taskYear,
                (int) $doneTask['project_id'],
                (string) $doneTask['title'],
                $doneTask['description'] !== null && $doneTask['description'] !== '' ? (string) $doneTask['description'] : null,
                'new',
                (string) $doneTask['priority'],
                (int) $doneTask['author_id'],
                $doneTask['assignee_id'] !== null && $doneTask['assignee_id'] !== '' ? (int) $doneTask['assignee_id'] : null,
                $newDue,
                $newRemind,
                $rec,
            ]);
        } else {
            $st = db()->prepare(
                'INSERT INTO tasks (organization_id, project_id, title, description, status, priority, author_id, assignee_id, due_date, remind_at, recurrence, transition_note)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL)'
            );
            $st->execute([
                $orgId,
                (int) $doneTask['project_id'],
                (string) $doneTask['title'],
                $doneTask['description'] !== null && $doneTask['description'] !== '' ? (string) $doneTask['description'] : null,
                'new',
                (string) $doneTask['priority'],
                (int) $doneTask['author_id'],
                $doneTask['assignee_id'] !== null && $doneTask['assignee_id'] !== '' ? (int) $doneTask['assignee_id'] : null,
                $newDue,
                $newRemind,
                $rec,
            ]);
        }
    } elseif ($hasWorkYear) {
        $st = db()->prepare(
            'INSERT INTO tasks (organization_id, work_year, project_id, title, description, status, priority, author_id, assignee_id, due_date)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $orgId,
            $taskYear,
            (int) $doneTask['project_id'],
            (string) $doneTask['title'],
            $doneTask['description'] !== null && $doneTask['description'] !== '' ? (string) $doneTask['description'] : null,
            'new',
            (string) $doneTask['priority'],
            (int) $doneTask['author_id'],
            $doneTask['assignee_id'] !== null && $doneTask['assignee_id'] !== '' ? (int) $doneTask['assignee_id'] : null,
            $newDue,
        ]);
    } else {
        $st = db()->prepare(
            'INSERT INTO tasks (organization_id, project_id, title, description, status, priority, author_id, assignee_id, due_date)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $orgId,
            (int) $doneTask['project_id'],
            (string) $doneTask['title'],
            $doneTask['description'] !== null && $doneTask['description'] !== '' ? (string) $doneTask['description'] : null,
            'new',
            (string) $doneTask['priority'],
            (int) $doneTask['author_id'],
            $doneTask['assignee_id'] !== null && $doneTask['assignee_id'] !== '' ? (int) $doneTask['assignee_id'] : null,
            $newDue,
        ]);
    }
    $newId = (int) db()->lastInsertId();
    log_activity($orgId, $userId, 'create', 'task', $newId, 'Повтор задачи после выполнения #' . (int) $doneTask['id']);
    if (planner_table_exists('task_tag_map') && planner_table_exists('tags')) {
        $st = db()->prepare('SELECT tag_id FROM task_tag_map WHERE task_id = ?');
        $st->execute([(int) $doneTask['id']]);
        $tids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'tag_id'));
        if ($tids !== []) {
            planner_sync_task_tags($orgId, $newId, $tids);
        }
    }
    planner_copy_task_participants((int) $doneTask['id'], $newId);
    return $newId;
}

/**
 * @param int[] $assistantIds
 * @param int[] $observerIds
 */
function planner_sync_task_participants(int $orgId, int $taskId, array $assistantIds, array $observerIds): void
{
    if (!planner_table_exists('task_participants')) {
        return;
    }
    $assistantIds = array_values(array_unique(array_filter(array_map('intval', $assistantIds), static fn (int $id) => $id > 0)));
    $observerIds = array_values(array_unique(array_filter(array_map('intval', $observerIds), static fn (int $id) => $id > 0)));
    $observerIds = array_values(array_diff($observerIds, $assistantIds));
    $ph = implode(',', array_fill(0, count($assistantIds) + count($observerIds), '?'));
    if ($ph !== '') {
        $params = array_merge([$orgId], $assistantIds, $observerIds);
        $st = db()->prepare("SELECT id FROM users WHERE organization_id = ? AND id IN ($ph)");
        $st->execute($params);
        $allowed = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
        $assistantIds = array_values(array_intersect($assistantIds, $allowed));
        $observerIds = array_values(array_intersect($observerIds, $allowed));
    } else {
        $assistantIds = [];
        $observerIds = [];
    }
    db()->prepare('DELETE FROM task_participants WHERE task_id = ?')->execute([$taskId]);
    $ins = db()->prepare('INSERT INTO task_participants (task_id, user_id, role) VALUES (?,?,?)');
    foreach ($assistantIds as $uid) {
        $ins->execute([$taskId, $uid, 'assistant']);
    }
    foreach ($observerIds as $uid) {
        $ins->execute([$taskId, $uid, 'observer']);
    }
}

function planner_copy_task_participants(int $fromTaskId, int $toTaskId): void
{
    if (!planner_table_exists('task_participants')) {
        return;
    }
    $st = db()->prepare('SELECT user_id, role FROM task_participants WHERE task_id = ?');
    $st->execute([$fromTaskId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return;
    }
    $ins = db()->prepare('INSERT INTO task_participants (task_id, user_id, role) VALUES (?,?,?)');
    foreach ($rows as $r) {
        $ins->execute([$toTaskId, (int) $r['user_id'], (string) $r['role']]);
    }
}

/** @return array{assistants: list<array{id:int,name:string}>, observers: list<array{id:int,name:string}>} */
function planner_task_participants_payload(int $taskId): array
{
    $out = ['assistants' => [], 'observers' => []];
    if (!planner_table_exists('task_participants')) {
        return $out;
    }
    $st = db()->prepare(
        'SELECT tp.user_id AS id, tp.role, u.name FROM task_participants tp
         INNER JOIN users u ON u.id = tp.user_id
         WHERE tp.task_id = ? ORDER BY tp.role, u.name'
    );
    $st->execute([$taskId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $row = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
        if (($r['role'] ?? '') === 'observer') {
            $out['observers'][] = $row;
        } else {
            $out['assistants'][] = $row;
        }
    }
    return $out;
}

/**
 * @param int[] $assistantIds
 */
function planner_sync_subtask_assistants(int $orgId, int $subtaskId, array $assistantIds): void
{
    if (!planner_table_exists('subtask_participants')) {
        return;
    }
    $assistantIds = array_values(array_unique(array_filter(array_map('intval', $assistantIds), static fn (int $id) => $id > 0)));
    if ($assistantIds !== []) {
        $stTask = db()->prepare(
            'SELECT t.id AS task_id, t.author_id, t.assignee_id
             FROM subtasks s
             INNER JOIN tasks t ON t.id = s.task_id
             WHERE s.id = ? AND t.organization_id = ? LIMIT 1'
        );
        $stTask->execute([$subtaskId, $orgId]);
        $taskRow = $stTask->fetch(PDO::FETCH_ASSOC);
        if (!$taskRow) {
            $assistantIds = [];
        } else {
            $allowedSet = [];
            $authorId = (int) ($taskRow['author_id'] ?? 0);
            $assigneeId = (int) ($taskRow['assignee_id'] ?? 0);
            if ($authorId > 0) {
                $allowedSet[$authorId] = true;
            }
            if ($assigneeId > 0) {
                $allowedSet[$assigneeId] = true;
            }
            $taskId = (int) ($taskRow['task_id'] ?? 0);
            if ($taskId > 0 && planner_table_exists('task_participants')) {
                $stParts = db()->prepare('SELECT user_id FROM task_participants WHERE task_id = ?');
                $stParts->execute([$taskId]);
                foreach ($stParts->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $uid = (int) ($row['user_id'] ?? 0);
                    if ($uid > 0) {
                        $allowedSet[$uid] = true;
                    }
                }
            }
            $assistantIds = array_values(array_filter(
                $assistantIds,
                static function (int $uid) use ($allowedSet): bool {
                    return isset($allowedSet[$uid]);
                }
            ));
        }
    }
    db()->prepare('DELETE FROM subtask_participants WHERE subtask_id = ?')->execute([$subtaskId]);
    if ($assistantIds === []) {
        return;
    }
    $ins = db()->prepare('INSERT INTO subtask_participants (subtask_id, user_id, role) VALUES (?,?,?)');
    foreach ($assistantIds as $uid) {
        $ins->execute([$subtaskId, $uid, 'assistant']);
    }
}

/**
 * @return array<int, list<array{id:int,name:string}>>
 */
function planner_subtask_assistants_payload_map(int $taskId): array
{
    if (!planner_table_exists('subtask_participants')) {
        return [];
    }
    $st = db()->prepare(
        'SELECT sp.subtask_id, sp.user_id AS id, u.name
         FROM subtask_participants sp
         INNER JOIN subtasks s ON s.id = sp.subtask_id
         INNER JOIN users u ON u.id = sp.user_id
         WHERE s.task_id = ? AND sp.role = \'assistant\'
         ORDER BY sp.subtask_id, u.name'
    );
    $st->execute([$taskId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sid = (int) $r['subtask_id'];
        if (!isset($out[$sid])) {
            $out[$sid] = [];
        }
        $out[$sid][] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
    }
    return $out;
}

function planner_run_task_automation_rules(int $orgId, int $actorUserId, int $taskId, string $triggerStatus, ?int $projectId): void
{
    if (!planner_table_exists('task_automation_rules')) {
        return;
    }
    $valid = ['new', 'in_progress', 'review', 'done', 'cancelled'];
    if (!in_array($triggerStatus, $valid, true)) {
        return;
    }
    $st = db()->prepare(
        'SELECT id, action_assignee_id, action_comment FROM task_automation_rules
         WHERE organization_id = ? AND enabled = 1 AND trigger_status = ?
           AND (project_id IS NULL OR project_id <=> ?)
         ORDER BY sort_order ASC, id ASC'
    );
    $st->execute([$orgId, $triggerStatus, $projectId]);
    $rules = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rules as $rule) {
        $aid = isset($rule['action_assignee_id']) && $rule['action_assignee_id'] !== null && $rule['action_assignee_id'] !== ''
            ? (int) $rule['action_assignee_id']
            : 0;
        if ($aid > 0) {
            $chk = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
            $chk->execute([$aid, $orgId]);
            if ($chk->fetchColumn()) {
                db()->prepare('UPDATE tasks SET assignee_id = ? WHERE id = ? AND organization_id = ?')->execute([$aid, $taskId, $orgId]);
                log_activity($orgId, $actorUserId, 'update', 'task', $taskId, 'Автоматизация: назначен исполнитель');
            }
        }
        $comment = trim((string) ($rule['action_comment'] ?? ''));
        if ($comment !== '') {
            $ins = db()->prepare(
                'INSERT INTO task_comments (task_id, user_id, body) VALUES (?,?,?)'
            );
            $body = '[Автоматизация] ' . (function_exists('mb_substr')
                ? mb_substr($comment, 0, 16000, 'UTF-8')
                : substr($comment, 0, 16000));
            $ins->execute([$taskId, $actorUserId, $body]);
            log_activity($orgId, $actorUserId, 'comment', 'task', $taskId, 'Автоматизация: добавлен комментарий');
        }
    }
}

/** Парсинг срока: дата YYYY-MM-DD (+ время организации по умолчанию) или дата-время из datetime-local / ISO. */
function planner_parse_due_datetime_input(mixed $due, ?string $dateOnlyDefaultHms = null): ?string
{
    if ($due === null || $due === '') {
        return null;
    }
    if (!is_string($due)) {
        return null;
    }
    $due = trim(str_replace('T', ' ', $due));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        $t = $dateOnlyDefaultHms !== null && $dateOnlyDefaultHms !== ''
            ? planner_normalize_due_time_hm($dateOnlyDefaultHms)
            : '23:59:59';

        return $due . ' ' . $t;
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{2}):(\d{2})(?::(\d{2}))?/', $due, $m)) {
        $sec = isset($m[4]) && $m[4] !== '' ? $m[4] : '00';

        return $m[1] . ' ' . $m[2] . ':' . $m[3] . ':' . $sec;
    }

    return null;
}

/**
 * Видимость раздела «Мои задачи»: участник, метка «Институт», метка «Структура» (свои подразделения).
 *
 * @return array{sql: string, params: array}
 */
function planner_my_tasks_visibility_fragment(array $user, bool $hasProjectDept): array
{
    if (in_array($user['role'] ?? '', ['admin', 'owner'], true)) {
        return ['sql' => '', 'params' => []];
    }

    $params = [];
    $clauses = [];
    $currentUid = (int) $user['id'];

    $participantClause = '(t.author_id = ? OR t.assignee_id = ?';
    $participantParams = [$currentUid, $currentUid];
    if (planner_table_exists('task_participants')) {
        $participantClause .= ' OR t.id IN (SELECT tp.task_id FROM task_participants tp WHERE tp.user_id = ?)';
        $participantParams[] = $currentUid;
    }
    $participantClause .= ')';
    $clauses[] = $participantClause;
    $params = array_merge($params, $participantParams);

    if (planner_table_has_column('tasks', 'visible_institute')) {
        $clauses[] = 't.visible_institute = 1';
    }

    if ($hasProjectDept && planner_table_has_column('tasks', 'visible_department')) {
        $deptIds = user_department_membership_ids($user);
        if ($deptIds !== []) {
            $ph = implode(',', array_fill(0, count($deptIds), '?'));
            $clauses[] = "(t.visible_department = 1 AND p.department_id IN ($ph))";
            $params = array_merge($params, $deptIds);
        }
    }

    if ($clauses === []) {
        return ['sql' => ' AND 1=0 ', 'params' => []];
    }

    return ['sql' => ' AND (' . implode(' OR ', $clauses) . ')', 'params' => $params];
}

/**
 * Фрагменты WHERE для списка задач (видимость по подразделениям / участникам).
 *
 * @return array{sql: string, params: array}
 */
function planner_tasks_visibility_fragment(array $user, array $scope, bool $hasProjectDept): array
{
    if (user_sees_all_departments($user)) {
        return ['sql' => '', 'params' => []];
    }

    $params = [];
    $clauses = [];
    $currentUid = (int) $user['id'];

    // Участники задачи видят её всегда: постановщик, исполнитель, соисполнитель, наблюдатель.
    $participantClause = '(t.author_id = ? OR t.assignee_id = ?';
    $participantParams = [$currentUid, $currentUid];
    if (planner_table_exists('task_participants')) {
        $participantClause .= ' OR t.id IN (SELECT tp.task_id FROM task_participants tp WHERE tp.user_id = ?)';
        $participantParams[] = $currentUid;
    }
    $participantClause .= ')';
    $clauses[] = $participantClause;
    $params = array_merge($params, $participantParams);

    // Метка «Институт» (visible_institute): видят все сотрудники организации с ограниченной видимостью.
    if (planner_table_has_column('tasks', 'visible_institute')) {
        $clauses[] = 't.visible_institute = 1';
    }

    if ($hasProjectDept) {
        $deptScope = user_visible_department_ids($user);
        $managedDeptIds = array_values(array_unique(array_merge(
            user_managed_department_ids($user),
            user_administered_department_ids($user)
        )));
        $legacyHead = (($user['role'] ?? '') === 'head');

        // Руководитель подразделения видит задачи своих отделов.
        if ($managedDeptIds !== []) {
            $ph = implode(',', array_fill(0, count($managedDeptIds), '?'));
            $clauses[] = "p.department_id IN ($ph)";
            $params = array_merge($params, $managedDeptIds);
        } elseif ($legacyHead && $deptScope !== null && $deptScope !== []) {
            // Совместимость: устаревшая глобальная роль head без записей в user_departments.
            $ph = implode(',', array_fill(0, count($deptScope), '?'));
            $clauses[] = "p.department_id IN ($ph)";
            $params = array_merge($params, $deptScope);
        }

        // Метка «Структура» (visible_department): задачи подразделения при расширенной видимости на структуру.
        if (planner_table_has_column('tasks', 'visible_department') && $deptScope !== null && $deptScope !== []) {
            $ph = implode(',', array_fill(0, count($deptScope), '?'));
            $clauses[] = "(t.visible_department = 1 AND p.department_id IN ($ph))";
            $params = array_merge($params, $deptScope);
        }
    }

    if ($clauses === []) {
        return ['sql' => ' AND 1=0 ', 'params' => []];
    }

    return ['sql' => ' AND (' . implode(' OR ', $clauses) . ')', 'params' => $params];
}

/**
 * Подзапрос: id проектов, в которых есть хотя бы одна задача, видимая пользователю.
 *
 * @return array{sql: string, params: array}
 */
function planner_project_ids_visible_via_tasks_subquery(array $user): array
{
    if (user_sees_all_departments($user)) {
        return ['sql' => '', 'params' => []];
    }

    $orgId = (int) $user['organization_id'];
    $hasProjectDept = planner_table_has_column('projects', 'department_id');
    $vis = planner_tasks_visibility_fragment($user, ['sql' => '', 'params' => []], $hasProjectDept);
    $pw = planner_scope_org_work_year_task($orgId, 't');
    $join = $hasProjectDept
        ? ' LEFT JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id '
        : '';
    $sql = 'SELECT DISTINCT t.project_id FROM tasks t' . $join
        . ' WHERE t.organization_id = ? AND t.project_id IS NOT NULL' . $pw['sql'] . $vis['sql'];
    $params = array_merge([$orgId], $pw['params'], $vis['params']);

    return ['sql' => $sql, 'params' => $params];
}

function planner_user_has_visible_task_in_project(array $user, int $projectId): bool
{
    if ($projectId <= 0) {
        return false;
    }
    if (user_sees_all_departments($user)) {
        return true;
    }

    $orgId = (int) $user['organization_id'];
    $hasProjectDept = planner_table_has_column('projects', 'department_id');
    $vis = planner_tasks_visibility_fragment($user, ['sql' => '', 'params' => []], $hasProjectDept);
    $pw = planner_scope_org_work_year_task($orgId, 't');
    $join = $hasProjectDept
        ? ' LEFT JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id '
        : '';
    $sql = 'SELECT 1 FROM tasks t' . $join
        . ' WHERE t.organization_id = ? AND t.project_id = ?' . $pw['sql'] . $vis['sql'] . ' LIMIT 1';
    $params = array_merge([$orgId, $projectId], $pw['params'], $vis['params']);
    $st = db()->prepare($sql);
    $st->execute($params);

    return (bool) $st->fetchColumn();
}

function planner_normalize_datetime_filter(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return $s;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $s)) {
        return str_replace('T', ' ', substr($s, 0, 16)) . ':00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s)) {
        return $s;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s . ' 00:00:00';
    }

    return $s;
}

/**
 * Фильтры списка задач (статус, проект, поиск, роль «я», расширенные).
 *
 * @param array<string, mixed> $f
 * @return array{sql: string, params: array}
 */
/** Сколько дней держать выполненные/отменённые на доске и в общем списке (0 = все). */
function planner_tasks_hide_done_cancelled_after_days(): int
{
    $cfg = app_config();
    $v = (int) (($cfg['tasks']['hide_done_cancelled_after_days'] ?? 90));

    return max(0, min(3650, $v));
}

function planner_tasks_filters_fragment(array $user, array $f): array
{
    $params = [];
    $sql = '';
    $orgIdF = (int) ($user['organization_id'] ?? 0);
    if ($orgIdF > 0 && planner_table_has_column('tasks', 'work_year')) {
        $sql .= ' AND t.work_year = ?';
        $params[] = planner_org_work_year_for_org($orgIdF);
    }
    $status = $f['status'] ?? null;
    $projectId = isset($f['project_id']) ? (int) $f['project_id'] : 0;
    $q = isset($f['q']) ? trim((string) $f['q']) : '';
    $roleScope = isset($f['role_scope']) ? trim((string) $f['role_scope']) : '';
    $visibilityScope = isset($f['visibility_scope']) ? trim((string) $f['visibility_scope']) : '';
    $currentUid = (int) $user['id'];

    if ($status && in_array($status, ['new', 'in_progress', 'review', 'done', 'cancelled'], true)) {
        $sql .= ' AND t.status = ?';
        $params[] = $status;
    }
    if ($projectId > 0) {
        $sql .= ' AND t.project_id = ?';
        $params[] = $projectId;
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= ' AND (t.title LIKE ? OR t.description LIKE ? OR p.name LIKE ? OR ua.name LIKE ? OR au.name LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (
        $roleScope !== ''
        && in_array($roleScope, ['responsible', 'author', 'assistant', 'observer'], true)
    ) {
        if ($roleScope === 'responsible') {
            $sql .= ' AND t.assignee_id = ?';
            $params[] = $currentUid;
        } elseif ($roleScope === 'author') {
            $sql .= ' AND t.author_id = ?';
            $params[] = $currentUid;
        } elseif ($roleScope === 'assistant' && planner_table_exists('task_participants')) {
            $sql .= ' AND t.id IN (SELECT tp.task_id FROM task_participants tp WHERE tp.user_id = ? AND tp.role = \'assistant\')';
            $params[] = $currentUid;
        } elseif ($roleScope === 'observer' && planner_table_exists('task_participants')) {
            $sql .= ' AND t.id IN (SELECT tp.task_id FROM task_participants tp WHERE tp.user_id = ? AND tp.role = \'observer\')';
            $params[] = $currentUid;
        }
    }
    if (
        $visibilityScope !== ''
        && in_array($visibilityScope, ['institute', 'department', 'personal'], true)
        && planner_table_has_column('tasks', 'visible_department')
        && planner_table_has_column('tasks', 'visible_institute')
    ) {
        if ($visibilityScope === 'institute') {
            $sql .= ' AND t.visible_institute = 1';
        } elseif ($visibilityScope === 'department') {
            $sql .= ' AND t.visible_institute = 0 AND t.visible_department = 1';
        } elseif ($visibilityScope === 'personal') {
            $sql .= ' AND t.visible_institute = 0 AND t.visible_department = 0';
        }
    }

    $assigneeEmpty = $f['assignee_empty'] ?? null;
    $assigneeIsEmpty = $assigneeEmpty === 1 || $assigneeEmpty === '1' || $assigneeEmpty === true;
    if ($assigneeIsEmpty) {
        $sql .= ' AND t.assignee_id IS NULL';
    }

    $assigneeFilterId = isset($f['assignee_id']) ? (int) $f['assignee_id'] : 0;
    if (!$assigneeIsEmpty && $assigneeFilterId > 0) {
        $sql .= ' AND t.assignee_id = ?';
        $params[] = $assigneeFilterId;
    }

    $participantUid = isset($f['participant_user_id']) ? (int) $f['participant_user_id'] : 0;
    if ($participantUid > 0) {
        $sql .= ' AND (t.assignee_id = ? OR t.author_id = ?';
        $params[] = $participantUid;
        $params[] = $participantUid;
        if (planner_table_exists('task_participants')) {
            $sql .= ' OR t.id IN (SELECT tp2.task_id FROM task_participants tp2 WHERE tp2.user_id = ?)';
            $params[] = $participantUid;
        }
        $sql .= ')';
    }

    $tagIds = $f['tag_ids'] ?? [];
    if (!is_array($tagIds)) {
        $tagIds = [];
    }
    $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds), static fn (int $id) => $id > 0)));
    if ($tagIds !== [] && planner_table_exists('task_tag_map')) {
        $ph = implode(',', array_fill(0, count($tagIds), '?'));
        $sql .= " AND t.id IN (SELECT ttm.task_id FROM task_tag_map ttm WHERE ttm.tag_id IN ($ph))";
        foreach ($tagIds as $tid) {
            $params[] = $tid;
        }
    }

    $dueFrom = isset($f['due_from']) ? trim((string) $f['due_from']) : '';
    $dueTo = isset($f['due_to']) ? trim((string) $f['due_to']) : '';
    if ($dueFrom !== '') {
        $sql .= ' AND t.due_date IS NOT NULL AND t.due_date >= ?';
        $params[] = planner_normalize_datetime_filter($dueFrom);
    }
    if ($dueTo !== '') {
        $sql .= ' AND t.due_date IS NOT NULL AND t.due_date <= ?';
        $params[] = planner_normalize_datetime_filter($dueTo);
    }

    $hc = $f['has_comments'] ?? null;
    if ($hc !== null && $hc !== '' && planner_table_exists('task_comments')) {
        $want = (string) $hc === '1' || $hc === 1 || $hc === true;
        if ($want) {
            $sql .= ' AND EXISTS (SELECT 1 FROM task_comments tc_f WHERE tc_f.task_id = t.id)';
        } else {
            $sql .= ' AND NOT EXISTS (SELECT 1 FROM task_comments tc_f WHERE tc_f.task_id = t.id)';
        }
    }

    $ha = $f['has_attachments'] ?? null;
    if ($ha !== null && $ha !== '' && planner_table_exists('task_attachments')) {
        $wantA = (string) $ha === '1' || $ha === 1 || $ha === true;
        if ($wantA) {
            $sql .= ' AND EXISTS (SELECT 1 FROM task_attachments ta_f WHERE ta_f.task_id = t.id)';
        } else {
            $sql .= ' AND NOT EXISTS (SELECT 1 FROM task_attachments ta_f WHERE ta_f.task_id = t.id)';
        }
    }

    $departmentId = isset($f['department_id']) ? (int) $f['department_id'] : 0;
    if ($departmentId > 0 && planner_table_has_column('projects', 'department_id')) {
        $sql .= ' AND p.department_id = ?';
        $params[] = $departmentId;
    }

    $reportActive = $f['report_active'] ?? null;
    if ($reportActive === 1 || $reportActive === '1' || $reportActive === true) {
        $sql .= " AND t.status NOT IN ('done','cancelled')";
    }

    $reportOverdue = $f['report_overdue'] ?? null;
    if ($reportOverdue === 1 || $reportOverdue === '1' || $reportOverdue === true) {
        $sql .= " AND t.due_date IS NOT NULL AND t.due_date < NOW() AND t.status NOT IN ('done','cancelled')";
    }

    $reportStuck = $f['report_stuck'] ?? null;
    if ($reportStuck === 1 || $reportStuck === '1' || $reportStuck === true) {
        $stuckRef = planner_table_has_column('tasks', 'status_changed_at')
            ? 'COALESCE(t.status_changed_at, t.updated_at)'
            : 't.updated_at';
        $sql .= " AND t.status NOT IN ('done','cancelled') AND $stuckRef < DATE_SUB(NOW(), INTERVAL 14 DAY)";
    }

    $taskKind = isset($f['task_kind']) ? trim((string) $f['task_kind']) : '';
    if ($taskKind !== '' && planner_table_has_column('tasks', 'is_quick_task')) {
        if ($taskKind === 'quick') {
            $sql .= ' AND t.is_quick_task = 1';
        } elseif ($taskKind === 'normal') {
            $sql .= ' AND COALESCE(t.is_quick_task, 0) = 0';
        }
    }

    $iq = $f['is_quick_task'] ?? null;
    if (($iq === 1 || $iq === '1' || $iq === true) && planner_table_has_column('tasks', 'is_quick_task')) {
        $sql .= ' AND t.is_quick_task = 1';
    }
    $qvm = $f['quick_visible_managers'] ?? null;
    if (($qvm === 1 || $qvm === '1' || $qvm === true) && planner_table_has_column('tasks', 'quick_visible_managers')) {
        $sql .= ' AND t.quick_visible_managers = 1';
    }

    $hideDoneDays = planner_tasks_hide_done_cancelled_after_days();
    $incArch = $f['include_archived_done'] ?? null;
    $includeArchivedDone = $incArch === 1 || $incArch === '1' || $incArch === true;
    if ($hideDoneDays > 0 && !$includeArchivedDone) {
        $statusFilter = $f['status'] ?? null;
        $onlyDoneOrCancelled = $statusFilter !== null
            && $statusFilter !== ''
            && in_array((string) $statusFilter, ['done', 'cancelled'], true);
        if (!$onlyDoneOrCancelled) {
            $doneRef = planner_table_has_column('tasks', 'status_changed_at')
                ? 'COALESCE(t.status_changed_at, t.updated_at)'
                : 't.updated_at';
            $sql .= " AND NOT (t.status IN ('done','cancelled') AND ($doneRef < DATE_SUB(NOW(), INTERVAL ? DAY)))";
            $params[] = $hideDoneDays;
        }
    }

    return ['sql' => $sql, 'params' => $params];
}

/**
 * Руководитель подразделения не видит чужие быстрые задачи с quick_visible_managers=0.
 *
 * @return array{sql: string, params: array<int, mixed>}
 */
function planner_tasks_quick_privacy_fragment(array $user): array
{
    if (!planner_table_has_column('tasks', 'is_quick_task') || !planner_table_has_column('tasks', 'quick_visible_managers')) {
        return ['sql' => '', 'params' => []];
    }
    if (!user_has_manager_visibility_privileges($user)) {
        return ['sql' => '', 'params' => []];
    }
    $uid = (int) $user['id'];
    if (planner_table_exists('task_participants')) {
        return [
            'sql' => ' AND (COALESCE(t.is_quick_task, 0) = 0 OR COALESCE(t.quick_visible_managers, 1) = 1 OR t.assignee_id = ? OR t.author_id = ? OR t.id IN (SELECT tpq.task_id FROM task_participants tpq WHERE tpq.user_id = ?))',
            'params' => [$uid, $uid, $uid],
        ];
    }

    return [
        'sql' => ' AND (COALESCE(t.is_quick_task, 0) = 0 OR COALESCE(t.quick_visible_managers, 1) = 1 OR t.assignee_id = ? OR t.author_id = ?)',
        'params' => [$uid, $uid],
    ];
}

/** Политика отдела: видимость быстрых задач для руководителя по умолчанию (0/1). */
function planner_quick_visible_default_from_project(int $orgId, int $projectId): int
{
    if (!planner_table_has_column('departments', 'quick_tasks_managers_visible_default')) {
        return 1;
    }
    if (!planner_table_has_column('projects', 'department_id')) {
        return 1;
    }
    $st = db()->prepare(
        'SELECT COALESCE(d.quick_tasks_managers_visible_default, 1) AS v
         FROM projects p
         LEFT JOIN departments d ON d.id = p.department_id
         WHERE p.id = ? AND p.organization_id = ?
         LIMIT 1'
    );
    $st->execute([$projectId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return 1;
    }

    return (int) ($row['v'] ?? 1) ? 1 : 0;
}

require_once __DIR__ . '/access.php';

/** @return array<string, mixed>|null */
function planner_user_row_by_id(int $orgId, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$userId, $orgId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    return $r ?: null;
}

/**
 * Извлекает @[user:ID] из текста; только пользователи организации, которым видна задача.
 *
 * @return int[]
 */
function planner_parse_comment_mention_user_ids(string $body, int $orgId, int $taskId, int $authorCommentUserId): array
{
    if (!preg_match_all('/@\[user:(\d+)\]/', $body, $m)) {
        return [];
    }
    $out = [];
    foreach ($m[1] as $raw) {
        $id = (int) $raw;
        if ($id <= 0 || $id === $authorCommentUserId) {
            continue;
        }
        $row = planner_user_row_by_id($orgId, $id);
        if (!$row) {
            continue;
        }
        if (!task_visible_to_user($row, $taskId)) {
            continue;
        }
        $out[$id] = true;
    }

    return array_map('intval', array_keys($out));
}

function planner_sync_task_comment_mentions(int $commentId, array $userIds): void
{
    if (!planner_table_exists('task_comment_mentions')) {
        return;
    }
    db()->prepare('DELETE FROM task_comment_mentions WHERE comment_id = ?')->execute([$commentId]);
    if ($userIds === []) {
        return;
    }
    $ins = db()->prepare('INSERT IGNORE INTO task_comment_mentions (comment_id, user_id) VALUES (?,?)');
    foreach (array_unique(array_map('intval', $userIds)) as $uid) {
        if ($uid <= 0) {
            continue;
        }
        $ins->execute([$commentId, $uid]);
    }
}

/**
 * @param list<array<string, mixed>> $items
 */
function planner_task_comments_attach_mentions_to_items(array &$items, int $orgId): void
{
    if ($items === []) {
        return;
    }
    if (!planner_table_exists('task_comment_mentions')) {
        foreach ($items as &$it0) {
            $it0['mentions'] = [];
        }
        unset($it0);

        return;
    }
    $ids = [];
    foreach ($items as $row) {
        $cid = (int) ($row['id'] ?? 0);
        if ($cid > 0) {
            $ids[] = $cid;
        }
    }
    if ($ids === []) {
        foreach ($items as &$itEmpty) {
            $itEmpty['mentions'] = [];
        }
        unset($itEmpty);

        return;
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT comment_id, user_id FROM task_comment_mentions WHERE comment_id IN ($ph)");
    $st->execute($ids);
    $byC = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $mr) {
        $c = (int) ($mr['comment_id'] ?? 0);
        $u = (int) ($mr['user_id'] ?? 0);
        if ($c <= 0 || $u <= 0) {
            continue;
        }
        if (!isset($byC[$c])) {
            $byC[$c] = [];
        }
        $byC[$c][] = $u;
    }
    $allU = [];
    foreach ($byC as $uids) {
        foreach ($uids as $uu) {
            $allU[$uu] = true;
        }
    }
    $names = [];
    if ($allU !== []) {
        $ul = array_map('intval', array_keys($allU));
        $phu = implode(',', array_fill(0, count($ul), '?'));
        $stn = db()->prepare("SELECT id, name FROM users WHERE organization_id = ? AND id IN ($phu)");
        $stn->execute(array_merge([$orgId], $ul));
        foreach ($stn->fetchAll(PDO::FETCH_ASSOC) as $ur) {
            $names[(int) $ur['id']] = (string) ($ur['name'] ?? '');
        }
    }
    foreach ($items as &$it) {
        $cid = (int) ($it['id'] ?? 0);
        $it['mentions'] = [];
        foreach ($byC[$cid] ?? [] as $mu) {
            $it['mentions'][] = ['user_id' => $mu, 'name' => $names[$mu] ?? ''];
        }
    }
    unset($it);
}
