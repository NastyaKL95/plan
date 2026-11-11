<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';

$hasProjectDept = planner_table_has_column('projects', 'department_id');
$scope = planner_reports_tasks_scope($user, $hasProjectDept, 'p');
$reportsScopeLevel = planner_reports_visibility_level($user);
$tw = planner_scope_org_work_year_task($orgId, 't');

$period = (string) ($_GET['period'] ?? '30d');
$daysMap = ['7d' => 7, '30d' => 30, '90d' => 90];
$days = $daysMap[$period] ?? 30;
$rangeExpr = "DATE_SUB(NOW(), INTERVAL $days DAY)";

if (!$hasProjectDept) {
    json_response([
        'ok' => true,
        'period' => $period,
        'days' => $days,
        'kpi' => [
            'created_total' => 0,
            'done_total' => 0,
            'on_time_rate' => 0,
            'overdue_active' => 0,
            'avg_close_hours' => 0,
            'active_total' => 0,
        ],
        'employees' => [],
        'departments' => [],
        'trend' => [],
    ]);
}

$params = array_merge([$orgId], $scope['params'], $tw['params']);
$fromWhere = ' FROM tasks t INNER JOIN projects p ON p.id = t.project_id WHERE t.organization_id = ?' . $scope['sql'] . $tw['sql'];

$cacheDir = dirname(__DIR__, 2) . '/var/cache/reports-eff';
$cacheKey = 'e' . $orgId . '_u' . (int) $user['id'] . '_d' . $days . '_' . md5((string) json_encode($params));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
$cacheTtl = 90;
if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $cacheTtl) {
    $cached = @file_get_contents($cacheFile);
    if (is_string($cached) && $cached !== '') {
        try {
            $decoded = json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $decoded = null;
        }
        if (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
            header('X-Planner-Cache: hit', false);
            json_response($decoded);
        }
    }
}
@mkdir($cacheDir, 0775, true);

$kpiSql = "SELECT
    SUM(CASE WHEN t.created_at >= $rangeExpr THEN 1 ELSE 0 END) AS created_total,
    SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN 1 ELSE 0 END) AS done_total,
    SUM(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr
        AND (t.due_date IS NULL OR t.updated_at <= t.due_date) THEN 1 ELSE 0 END) AS done_in_time,
    SUM(CASE WHEN t.status NOT IN ('done','cancelled') AND t.due_date IS NOT NULL AND t.due_date < NOW() THEN 1 ELSE 0 END) AS overdue_active,
    SUM(CASE WHEN t.status NOT IN ('done','cancelled') THEN 1 ELSE 0 END) AS active_total,
    AVG(CASE WHEN t.status = 'done' AND t.updated_at >= $rangeExpr THEN TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at) END) AS avg_close_hours
" . $fromWhere;
$st = db()->prepare($kpiSql);
planner_timed_execute($st, $params, $kpiSql, 'eff.kpi');
$kpi = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$empSql = "SELECT
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
WHERE t.organization_id = ?" . $scope['sql'] . $tw['sql'] . "
GROUP BY u.id, u.name, d.name
ORDER BY overdue_active DESC, active_total DESC, user_name ASC";
$stE = db()->prepare($empSql);
planner_timed_execute($stE, $params, $empSql, 'eff.employees');
$employees = $stE->fetchAll(PDO::FETCH_ASSOC);

$depSql = "SELECT
    d.id AS department_id,
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
$stD = db()->prepare($depSql);
planner_timed_execute($stD, $params, $depSql, 'eff.departments');
$departments = $stD->fetchAll(PDO::FETCH_ASSOC);

$trendSql = "SELECT
    DATE_FORMAT(x.day, '%x-W%v') AS week_label,
    SUM(x.created_c) AS created_total,
    SUM(x.done_c) AS done_total
FROM (
    SELECT DATE(t.created_at) AS day, COUNT(*) AS created_c, 0 AS done_c
    FROM tasks t
    INNER JOIN projects p ON p.id = t.project_id
    WHERE t.organization_id = ? AND t.created_at >= $rangeExpr" . $scope['sql'] . $tw['sql'] . "
    GROUP BY DATE(t.created_at)
    UNION ALL
    SELECT DATE(t.updated_at) AS day, 0 AS created_c, COUNT(*) AS done_c
    FROM tasks t
    INNER JOIN projects p ON p.id = t.project_id
    WHERE t.organization_id = ? AND t.status = 'done' AND t.updated_at >= $rangeExpr" . $scope['sql'] . $tw['sql'] . "
    GROUP BY DATE(t.updated_at)
) x
GROUP BY week_label
ORDER BY week_label ASC";
$trendParams = array_merge([$orgId], $scope['params'], $tw['params'], [$orgId], $scope['params'], $tw['params']);
$stT = db()->prepare($trendSql);
planner_timed_execute($stT, $trendParams, $trendSql, 'eff.trend');
$trend = $stT->fetchAll(PDO::FETCH_ASSOC);

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

foreach ($employees as &$r) {
    $r['on_time_rate'] = $normRate((int) $r['done_in_time'], (int) $r['done_total']);
    $r['score'] = $score($r);
    $r['avg_close_hours'] = $r['avg_close_hours'] !== null ? round((float) $r['avg_close_hours'], 1) : null;
}
unset($r);
foreach ($departments as &$r) {
    $r['on_time_rate'] = $normRate((int) $r['done_in_time'], (int) $r['done_total']);
    $r['score'] = $score($r);
}
unset($r);

$kCreated = (int) ($kpi['created_total'] ?? 0);
$kDone = (int) ($kpi['done_total'] ?? 0);
$kDoneIn = (int) ($kpi['done_in_time'] ?? 0);
$payload = [
    'ok' => true,
    'scope_level' => $reportsScopeLevel,
    'period' => $period,
    'days' => $days,
    'kpi' => [
        'created_total' => $kCreated,
        'done_total' => $kDone,
        'on_time_rate' => $normRate($kDoneIn, $kDone),
        'overdue_active' => (int) ($kpi['overdue_active'] ?? 0),
        'avg_close_hours' => $kpi['avg_close_hours'] !== null ? round((float) $kpi['avg_close_hours'], 1) : 0,
        'active_total' => (int) ($kpi['active_total'] ?? 0),
    ],
    'employees' => $employees,
    'departments' => $departments,
    'trend' => $trend,
];
@file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
header('X-Planner-Cache: miss', false);
json_response($payload);

