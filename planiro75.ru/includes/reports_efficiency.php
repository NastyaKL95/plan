<?php
declare(strict_types=1);

/**
 * Сотрудники отчёта эффективности: все люди подразделения, а не только исполнители задач.
 *
 * @param array{sql: string, params: array} $taskScope
 * @param array{sql: string, params: array} $workYear
 * @return list<array<string, mixed>>
 */
function planner_reports_efficiency_employees(
    int $orgId,
    array $user,
    string $rangeExpr,
    array $taskScope,
    array $workYear
): array {
    $level = planner_reports_visibility_level($user);
    if ($level === 'self') {
        return planner_reports_efficiency_employees_from_tasks($orgId, $rangeExpr, $taskScope, $workYear);
    }

    $userScope = planner_reports_efficiency_user_scope($user);
    $people = $userScope['sql'] !== ''
        ? '(' . preg_replace('/^\s*AND\s+/', '', $userScope['sql']) . ')'
        : '1=1';
    $peopleParams = $userScope['params'];
    if ($level === 'department') {
        $people = '(' . $people . " OR u.id IN (
            SELECT t.assignee_id
            FROM tasks t
            INNER JOIN projects p ON p.id = t.project_id
            WHERE t.organization_id = ? AND t.assignee_id IS NOT NULL" . $taskScope['sql'] . $workYear['sql'] . '
        ))';
        $peopleParams = array_merge($peopleParams, [$orgId], $taskScope['params'], $workYear['params']);
    }
    $statusSql = '';
    if (planner_table_has_column('users', 'account_status')) {
        $statusSql = " AND (u.account_status IS NULL OR u.account_status NOT IN ('pending_email', 'pending_approval'))";
    }
    $deptName = 'dep.name';
    if (planner_table_exists('user_departments')) {
        $deptName = "COALESCE(dep.name, (
            SELECT d2.name
            FROM user_departments udm
            INNER JOIN departments d2 ON d2.id = udm.department_id AND d2.organization_id = u.organization_id
            WHERE udm.user_id = u.id
            ORDER BY d2.name
            LIMIT 1
        ))";
    }
    $sql = "SELECT
        u.id AS user_id,
        u.name AS user_name,
        {$deptName} AS department_name,
        COALESCE(stats.created_total, 0) AS created_total,
        COALESCE(stats.done_total, 0) AS done_total,
        COALESCE(stats.done_in_time, 0) AS done_in_time,
        COALESCE(stats.active_total, 0) AS active_total,
        COALESCE(stats.overdue_active, 0) AS overdue_active,
        stats.avg_close_hours AS avg_close_hours
    FROM users u
    LEFT JOIN departments dep ON dep.id = u.department_id AND dep.organization_id = u.organization_id
    LEFT JOIN (
        SELECT t.assignee_id AS user_id,
            SUM(CASE WHEN t.created_at >= $rangeExpr THEN 1 ELSE 0 END) AS created_total,
            SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN 1 ELSE 0 END) AS done_total,
            SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr
                AND (t.due_date IS NULL OR t.updated_at <= t.due_date) THEN 1 ELSE 0 END) AS done_in_time,
            SUM(CASE WHEN t.status NOT IN ('done','cancelled') THEN 1 ELSE 0 END) AS active_total,
            SUM(CASE WHEN t.status NOT IN ('done','cancelled') AND t.due_date IS NOT NULL AND t.due_date < NOW() THEN 1 ELSE 0 END) AS overdue_active,
            AVG(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at) END) AS avg_close_hours
        FROM tasks t
        INNER JOIN projects p ON p.id = t.project_id
        WHERE t.organization_id = ? AND t.assignee_id IS NOT NULL" . $taskScope['sql'] . $workYear['sql'] . "
        GROUP BY t.assignee_id
    ) stats ON stats.user_id = u.id
    WHERE u.organization_id = ? AND " . $people . $statusSql . "
    ORDER BY overdue_active DESC, active_total DESC, user_name ASC";
    $params = array_merge([$orgId], $taskScope['params'], $workYear['params'], [$orgId], $peopleParams);
    $st = db()->prepare($sql);
    if (function_exists('planner_timed_execute')) {
        planner_timed_execute($st, $params, $sql, 'eff.employees');
    } else {
        $st->execute($params);
    }
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $unassigned = planner_reports_efficiency_unassigned_row($orgId, $rangeExpr, $taskScope, $workYear);
    if ($unassigned !== null) {
        $rows[] = $unassigned;
    }

    return $rows;
}

/**
 * @return array{sql: string, params: array}
 */
function planner_reports_efficiency_user_scope(array $user): array
{
    $level = planner_reports_visibility_level($user);
    if ($level === 'org') {
        return ['sql' => '', 'params' => []];
    }
    if ($level !== 'department') {
        return ['sql' => ' AND u.id = ? ', 'params' => [(int) ($user['id'] ?? 0)]];
    }
    $ids = planner_reports_department_ids($user);
    if ($ids === []) {
        return ['sql' => ' AND 1=0 ', 'params' => []];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $parts = ["u.department_id IN ($ph)"];
    $params = $ids;
    if (planner_table_exists('user_departments')) {
        $parts[] = "u.id IN (SELECT ud.user_id FROM user_departments ud WHERE ud.department_id IN ($ph))";
        $params = array_merge($params, $ids);
    }

    return ['sql' => ' AND (' . implode(' OR ', $parts) . ') ', 'params' => $params];
}

/**
 * @param array{sql: string, params: array} $taskScope
 * @param array{sql: string, params: array} $workYear
 * @return list<array<string, mixed>>
 */
function planner_reports_efficiency_employees_from_tasks(
    int $orgId,
    string $rangeExpr,
    array $taskScope,
    array $workYear
): array {
    $sql = "SELECT
        u.id AS user_id,
        u.name AS user_name,
        d.name AS department_name,
        SUM(CASE WHEN t.created_at >= $rangeExpr THEN 1 ELSE 0 END) AS created_total,
        SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN 1 ELSE 0 END) AS done_total,
        SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr
            AND (t.due_date IS NULL OR t.updated_at <= t.due_date) THEN 1 ELSE 0 END) AS done_in_time,
        SUM(CASE WHEN t.status NOT IN ('done','cancelled') THEN 1 ELSE 0 END) AS active_total,
        SUM(CASE WHEN t.status NOT IN ('done','cancelled') AND t.due_date IS NOT NULL AND t.due_date < NOW() THEN 1 ELSE 0 END) AS overdue_active,
        AVG(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at) END) AS avg_close_hours
    FROM tasks t
    INNER JOIN projects p ON p.id = t.project_id
    LEFT JOIN users u ON u.id = t.assignee_id
    LEFT JOIN departments d ON d.id = p.department_id
    WHERE t.organization_id = ?" . $taskScope['sql'] . $workYear['sql'] . "
    GROUP BY u.id, u.name, d.name
    ORDER BY overdue_active DESC, active_total DESC, user_name ASC";
    $params = array_merge([$orgId], $taskScope['params'], $workYear['params']);
    $st = db()->prepare($sql);
    if (function_exists('planner_timed_execute')) {
        planner_timed_execute($st, $params, $sql, 'eff.employees');
    } else {
        $st->execute($params);
    }

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param array{sql: string, params: array} $taskScope
 * @param array{sql: string, params: array} $workYear
 * @return array<string, mixed>|null
 */
function planner_reports_efficiency_unassigned_row(
    int $orgId,
    string $rangeExpr,
    array $taskScope,
    array $workYear
): ?array {
    $sql = "SELECT
        SUM(CASE WHEN t.created_at >= $rangeExpr THEN 1 ELSE 0 END) AS created_total,
        SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN 1 ELSE 0 END) AS done_total,
        SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr
            AND (t.due_date IS NULL OR t.updated_at <= t.due_date) THEN 1 ELSE 0 END) AS done_in_time,
        SUM(CASE WHEN t.status NOT IN ('done','cancelled') THEN 1 ELSE 0 END) AS active_total,
        SUM(CASE WHEN t.status NOT IN ('done','cancelled') AND t.due_date IS NOT NULL AND t.due_date < NOW() THEN 1 ELSE 0 END) AS overdue_active,
        AVG(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at) END) AS avg_close_hours
    FROM tasks t
    INNER JOIN projects p ON p.id = t.project_id
    WHERE t.organization_id = ? AND t.assignee_id IS NULL" . $taskScope['sql'] . $workYear['sql'];
    $params = array_merge([$orgId], $taskScope['params'], $workYear['params']);
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $created = (int) ($row['created_total'] ?? 0);
    $done = (int) ($row['done_total'] ?? 0);
    $active = (int) ($row['active_total'] ?? 0);
    $over = (int) ($row['overdue_active'] ?? 0);
    if ($created === 0 && $done === 0 && $active === 0 && $over === 0) {
        return null;
    }
    $row['user_id'] = null;
    $row['user_name'] = null;
    $row['department_name'] = null;

    return $row;
}
