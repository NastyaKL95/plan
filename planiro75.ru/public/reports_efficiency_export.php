<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/api/_guard.php';
require_once dirname(__DIR__) . '/includes/task_extras.php';

$hasProjectDept = planner_table_has_column('projects', 'department_id');
$scope = planner_reports_tasks_scope($user, $hasProjectDept, 'p');
$tw = planner_scope_org_work_year_task($orgId, 't');

$mode = (string) ($_GET['mode'] ?? 'employees');
if ($mode === 'departments' && planner_reports_visibility_level($user) === 'self') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Экспорт по подразделениям недоступен: отчёт только по вашим задачам.';
    exit;
}
if (!in_array($mode, ['employees', 'departments'], true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bad mode';
    exit;
}
$period = (string) ($_GET['period'] ?? '30d');
$daysMap = ['7d' => 7, '30d' => 30, '90d' => 90];
$days = $daysMap[$period] ?? 30;
$rangeExpr = "DATE_SUB(NOW(), INTERVAL $days DAY)";

header('Content-Type: text/csv; charset=UTF-8');
header('Cache-Control: no-store');
header('Content-Disposition: attachment; filename="efficiency_' . $mode . '_' . $period . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'wb');
if (!$out) {
    exit;
}

if (!$hasProjectDept) {
    fputcsv($out, ['Недоступно: проекты не привязаны к подразделениям (нет department_id).'], ';');
    fclose($out);
    exit;
}

$params = array_merge([$orgId], $scope['params'], $tw['params']);
$normRate = static function (int $num, int $den): float {
    return $den > 0 ? round(($num / $den) * 100, 1) : 0.0;
};
$score = static function (array $r) use ($normRate): float {
    $done = (int) ($r['done_total'] ?? 0);
    $doneIn = (int) ($r['done_in_time'] ?? 0);
    $created = (int) ($r['created_total'] ?? 0);
    $active = (int) ($r['active_total'] ?? 0);
    $over = (int) ($r['overdue_active'] ?? 0);
    $onTime = $normRate($doneIn, $done);
    $completion = $normRate($done, $created);
    $backlog = max(0.0, 100.0 - $normRate($over, max(1, $active)));
    return round(0.45 * $onTime + 0.25 * $completion + 0.30 * $backlog, 1);
};

if ($mode === 'employees') {
    fputcsv($out, ['Сотрудник', 'Отдел', 'Создано', 'Закрыто', 'В срок, %', 'Активные', 'Просроченные', 'Сред. закрытие, ч', 'Индекс'], ';');
    $sql = "SELECT
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
    WHERE t.organization_id = ?" . $scope['sql'] . $tw['sql'] . "
    GROUP BY u.id, u.name, d.name
    ORDER BY overdue_active DESC, active_total DESC, user_name ASC";
    $st = db()->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $onTime = $normRate((int) $r['done_in_time'], (int) $r['done_total']);
        fputcsv($out, [
            (string) ($r['user_name'] ?? 'Без исполнителя'),
            (string) ($r['department_name'] ?? ''),
            (int) ($r['created_total'] ?? 0),
            (int) ($r['done_total'] ?? 0),
            $onTime,
            (int) ($r['active_total'] ?? 0),
            (int) ($r['overdue_active'] ?? 0),
            $r['avg_close_hours'] !== null ? round((float) $r['avg_close_hours'], 1) : '',
            $score($r),
        ], ';');
    }
} else {
    fputcsv($out, ['Подразделение', 'Создано', 'Закрыто', 'В срок, %', 'Активные', 'Просроченные', 'Без исполнителя', 'Без срока', 'Индекс'], ';');
    $sql = "SELECT
        d.name AS department_name,
        SUM(CASE WHEN t.created_at >= $rangeExpr THEN 1 ELSE 0 END) AS created_total,
        SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN 1 ELSE 0 END) AS done_total,
        SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr
            AND (t.due_date IS NULL OR t.updated_at <= t.due_date) THEN 1 ELSE 0 END) AS done_in_time,
        SUM(CASE WHEN t.status NOT IN ('done','cancelled') THEN 1 ELSE 0 END) AS active_total,
        SUM(CASE WHEN t.status NOT IN ('done','cancelled') AND t.due_date IS NOT NULL AND t.due_date < NOW() THEN 1 ELSE 0 END) AS overdue_active,
        SUM(CASE WHEN t.assignee_id IS NULL THEN 1 ELSE 0 END) AS without_assignee,
        SUM(CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END) AS without_due
    FROM tasks t
    INNER JOIN projects p ON p.id = t.project_id
    INNER JOIN departments d ON d.id = p.department_id
    WHERE t.organization_id = ?" . $scope['sql'] . $tw['sql'] . "
    GROUP BY d.id, d.name
    ORDER BY overdue_active DESC, active_total DESC, d.name ASC";
    $st = db()->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $onTime = $normRate((int) $r['done_in_time'], (int) $r['done_total']);
        fputcsv($out, [
            (string) ($r['department_name'] ?? ''),
            (int) ($r['created_total'] ?? 0),
            (int) ($r['done_total'] ?? 0),
            $onTime,
            (int) ($r['active_total'] ?? 0),
            (int) ($r['overdue_active'] ?? 0),
            (int) ($r['without_assignee'] ?? 0),
            (int) ($r['without_due'] ?? 0),
            $score($r),
        ], ';');
    }
}
fclose($out);

