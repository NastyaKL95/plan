<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$hasDeptTable = planner_table_exists('departments');
$hasProjectDept = planner_table_has_column('projects', 'department_id');
if (!$hasDeptTable || !$hasProjectDept) {
    json_response([
        'ok' => true,
        'items' => [],
        'pagination' => ['total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1],
    ]);
}

$scope = planner_department_scope_for_projects($user, 'p');
$tw = planner_scope_org_work_year_task($orgId, 't');
$pw = planner_scope_org_work_year_project($orgId, 'p');

$st = db()->prepare(
    "SELECT d.id AS department_id,
            d.name AS department_name,
            MAX(COALESCE(t.updated_at, t.created_at)) AS last_activity_at,
            SUM(CASE WHEN t.status IN ('new','in_progress','review') THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_count,
            SUM(CASE WHEN t.id IS NOT NULL THEN 1 ELSE 0 END) AS total_count,
            SUM(
                CASE
                    WHEN t.due_date IS NOT NULL
                         AND DATE(t.due_date) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                         AND DATE(t.due_date) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                         AND t.status <> 'cancelled'
                    THEN 1
                    ELSE 0
                END
            ) AS planned_window_count,
            SUM(
                CASE
                    WHEN t.due_date IS NOT NULL
                         AND DATE(t.due_date) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                         AND DATE(t.due_date) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                         AND t.status = 'done'
                    THEN 1
                    ELSE 0
                END
            ) AS done_window_count
     FROM projects p
     INNER JOIN departments d ON d.id = p.department_id
     LEFT JOIN tasks t ON t.project_id = p.id AND t.organization_id = ?" . $tw['sql'] . "
     WHERE p.organization_id = ?" . $pw['sql'] . $scope['sql'] . "
     GROUP BY d.id, d.name, d.sort_order
     ORDER BY d.sort_order, d.name"
);
planner_timed_execute(
    $st,
    array_merge([$orgId], $tw['params'], [$orgId], $pw['params'], $scope['params']),
    $st->queryString ?? '',
    'dashboard.departments'
);

$rows = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $active = (int) ($r['active_count'] ?? 0);
    $done = (int) ($r['done_count'] ?? 0);
    $total = (int) ($r['total_count'] ?? 0);
    $plannedWindow = (int) ($r['planned_window_count'] ?? 0);
    $doneWindow = (int) ($r['done_window_count'] ?? 0);
    $eff = $plannedWindow > 0 ? (int) round(($doneWindow * 100) / $plannedWindow) : 0;
    if ($plannedWindow === 0) {
        $analytics = 'Нет данных';
    } elseif ($eff > 70) {
        $analytics = 'Отлично';
    } elseif ($eff > 40) {
        $analytics = 'Нормально';
    } else {
        $analytics = 'Ниже нормы';
    }
    $rows[] = [
        'department_id' => (int) $r['department_id'],
        'department_name' => (string) ($r['department_name'] ?? ''),
        'last_activity_at' => (string) ($r['last_activity_at'] ?? ''),
        'active_count' => $active,
        'done_count' => $done,
        'total_count' => $total,
        'planned_window_count' => $plannedWindow,
        'done_window_count' => $doneWindow,
        'effectiveness' => $eff,
        'analytics' => $analytics,
    ];
}

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
if ($page < 1) {
    $page = 1;
}
if ($perPage < 5) {
    $perPage = 5;
}
if ($perPage > 100) {
    $perPage = 100;
}

$total = count($rows);
$pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;
$items = array_slice($rows, $offset, $perPage);

json_response([
    'ok' => true,
    'items' => $items,
    'pagination' => [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
    ],
]);

