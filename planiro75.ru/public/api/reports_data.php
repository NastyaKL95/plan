<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';

$hasProjectDept = planner_table_has_column('projects', 'department_id');
$scope = planner_reports_tasks_scope($user, $hasProjectDept, 'p');
$reportsScopeLevel = planner_reports_visibility_level($user);
$tw = planner_scope_org_work_year_task($orgId, 't');
$baseParams = array_merge([$orgId], $scope['params'], $tw['params']);
/** @param array<int,mixed> $params */
function planner_reports_exec(PDOStatement $st, array $params, string $ctx): void
{
    planner_timed_execute($st, $params, $st->queryString ?? '', $ctx);
}

if (!$hasProjectDept) {
    $st = db()->prepare(
        'SELECT t.status, COUNT(*) AS c FROM tasks t
         WHERE t.organization_id = ?' . $tw['sql'] . ' GROUP BY t.status'
    );
    planner_reports_exec($st, array_merge([$orgId], $tw['params']), 'reports.by_status');
    $byStatusRows = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = db()->prepare(
        "SELECT ua.id AS assignee_id, ua.name AS assignee_name, COUNT(*) AS c
         FROM tasks t
         LEFT JOIN users ua ON ua.id = t.assignee_id
         WHERE t.organization_id = ? AND t.status NOT IN ('done','cancelled')" . $tw['sql'] . '
         GROUP BY ua.id, ua.name ORDER BY c DESC, assignee_name'
    );
    planner_reports_exec($st, array_merge([$orgId], $tw['params']), 'reports.by_assignee');
    $byAssignee = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = db()->prepare(
        "SELECT COUNT(*) AS c FROM tasks t
         WHERE t.organization_id = ? AND t.due_date IS NOT NULL AND t.due_date < NOW()
           AND t.status NOT IN ('done','cancelled')" . $tw['sql']
    );
    planner_reports_exec($st, array_merge([$orgId], $tw['params']), 'reports.overdue_total');
    $overdueTotal = (int) $st->fetchColumn();
} else {
    $st = db()->prepare(
        'SELECT t.status, COUNT(*) AS c FROM tasks t
         INNER JOIN projects p ON p.id = t.project_id
         WHERE t.organization_id = ?' . $scope['sql'] . $tw['sql'] . ' GROUP BY t.status'
    );
    planner_reports_exec($st, $baseParams, 'reports.by_status');
    $byStatusRows = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = db()->prepare(
        "SELECT ua.id AS assignee_id, ua.name AS assignee_name, COUNT(*) AS c
         FROM tasks t
         INNER JOIN projects p ON p.id = t.project_id
         LEFT JOIN users ua ON ua.id = t.assignee_id
         WHERE t.organization_id = ? AND t.status NOT IN ('done','cancelled')" . $scope['sql'] . $tw['sql'] . '
         GROUP BY ua.id, ua.name ORDER BY c DESC, assignee_name'
    );
    planner_reports_exec($st, $baseParams, 'reports.by_assignee');
    $byAssignee = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = db()->prepare(
        "SELECT COUNT(*) AS c FROM tasks t
         INNER JOIN projects p ON p.id = t.project_id
         WHERE t.organization_id = ? AND t.due_date IS NOT NULL AND t.due_date < NOW()
           AND t.status NOT IN ('done','cancelled')" . $scope['sql'] . $tw['sql']
    );
    planner_reports_exec($st, $baseParams, 'reports.overdue_total');
    $overdueTotal = (int) $st->fetchColumn();
}

$byStatus = [
    'new' => 0,
    'in_progress' => 0,
    'review' => 0,
    'done' => 0,
    'cancelled' => 0,
];
foreach ($byStatusRows as $r) {
    $byStatus[$r['status']] = (int) $r['c'];
}

$byDepartment = [];
if ($hasProjectDept) {
    $st = db()->prepare(
        "SELECT d.id AS department_id, d.name AS department_name, COUNT(*) AS c
         FROM tasks t
         INNER JOIN projects p ON p.id = t.project_id
         INNER JOIN departments d ON d.id = p.department_id
         WHERE t.organization_id = ? AND t.status NOT IN ('done','cancelled')" . $scope['sql'] . $tw['sql'] . '
         GROUP BY d.id, d.name
         ORDER BY d.sort_order, d.name'
    );
    planner_reports_exec($st, $baseParams, 'reports.by_department');
    $byDepartment = $st->fetchAll(PDO::FETCH_ASSOC);
}

$stuckRef = planner_table_has_column('tasks', 'status_changed_at')
    ? 'COALESCE(t.status_changed_at, t.updated_at)'
    : 't.updated_at';

$stuckTasks = [];
if (!$hasProjectDept) {
    $st = db()->prepare(
        "SELECT t.id, t.title, t.status, t.assignee_id, ua.name AS assignee_name, t.updated_at,
                DATEDIFF(NOW(), $stuckRef) AS days_in_state
         FROM tasks t
         LEFT JOIN users ua ON ua.id = t.assignee_id
         WHERE t.organization_id = ?
           AND t.status NOT IN ('done','cancelled')
           AND $stuckRef < DATE_SUB(NOW(), INTERVAL 14 DAY)" . $tw['sql'] . "
         ORDER BY $stuckRef ASC
         LIMIT 30"
    );
    planner_reports_exec($st, array_merge([$orgId], $tw['params']), 'reports.stuck');
} else {
    $st = db()->prepare(
        "SELECT t.id, t.title, t.status, t.assignee_id, ua.name AS assignee_name, t.updated_at,
                DATEDIFF(NOW(), $stuckRef) AS days_in_state
         FROM tasks t
         INNER JOIN projects p ON p.id = t.project_id
         LEFT JOIN users ua ON ua.id = t.assignee_id
         WHERE t.organization_id = ?
           AND t.status NOT IN ('done','cancelled')
           AND $stuckRef < DATE_SUB(NOW(), INTERVAL 14 DAY)" . $scope['sql'] . $tw['sql'] . "
         ORDER BY $stuckRef ASC
         LIMIT 30"
    );
    planner_reports_exec($st, $baseParams, 'reports.stuck');
}
$stuckTasks = $st->fetchAll(PDO::FETCH_ASSOC);

$quickTasksByDay = [];
$role = (string) ($user['role'] ?? '');
if (
    planner_table_has_column('tasks', 'is_quick_task')
    && (in_array($role, ['admin', 'owner'], true) || user_has_manager_visibility_privileges($user))
) {
    $qp = (!in_array($role, ['admin', 'owner'], true) && user_has_manager_visibility_privileges($user))
        ? planner_tasks_quick_privacy_fragment($user)
        : ['sql' => '', 'params' => []];
    if ($hasProjectDept) {
        $qSql =
            "SELECT t.id, t.title, t.status, t.created_at, t.assignee_id, ua.name AS assignee_name, p.name AS project_name,
                    DATE(t.created_at) AS day_key
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             LEFT JOIN users ua ON ua.id = t.assignee_id
             WHERE t.organization_id = ? AND t.is_quick_task = 1
               AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
            . $scope['sql']
            . $tw['sql']
            . $qp['sql']
            . ' ORDER BY t.created_at DESC LIMIT 200';
        $qParams = array_merge([$orgId], $scope['params'], $tw['params'], $qp['params']);
    } else {
        $qSql =
            "SELECT t.id, t.title, t.status, t.created_at, t.assignee_id, ua.name AS assignee_name, p.name AS project_name,
                    DATE(t.created_at) AS day_key
             FROM tasks t
             LEFT JOIN projects p ON p.id = t.project_id
             LEFT JOIN users ua ON ua.id = t.assignee_id
             WHERE t.organization_id = ? AND t.is_quick_task = 1
               AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
            . $tw['sql']
            . $qp['sql']
            . ' ORDER BY t.created_at DESC LIMIT 200';
        $qParams = array_merge([$orgId], $tw['params'], $qp['params']);
    }
    $stQ = db()->prepare($qSql);
    planner_reports_exec($stQ, $qParams, 'reports.quick_tasks');
    $qRows = $stQ->fetchAll(PDO::FETCH_ASSOC);
    $bucket = [];
    foreach ($qRows as $row) {
        $dk = (string) ($row['day_key'] ?? '');
        if ($dk === '') {
            continue;
        }
        if (!isset($bucket[$dk])) {
            $bucket[$dk] = [];
        }
        unset($row['day_key']);
        $bucket[$dk][] = $row;
    }
    krsort($bucket);
    foreach ($bucket as $date => $items) {
        $quickTasksByDay[] = ['date' => $date, 'items' => $items];
    }
}

json_response([
    'ok' => true,
    'scope_level' => $reportsScopeLevel,
    'by_status' => $byStatus,
    'by_assignee' => $byAssignee,
    'by_department' => $byDepartment,
    'overdue_total' => $overdueTotal,
    'stuck_tasks' => $stuckTasks,
    'quick_tasks_by_day' => $quickTasksByDay,
]);
