<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$hasProjectDept = planner_table_has_column('projects', 'department_id');
$tw = planner_scope_org_work_year_task($orgId, 't');
$pw = planner_scope_org_work_year_project($orgId, 'p');
/** @param array<int,mixed> $params */
function planner_stats_exec(PDOStatement $st, array $params, string $ctx): void
{
    planner_timed_execute($st, $params, $st->queryString ?? '', $ctx);
}

/** Одна строка: счётчики по статусам, всего задач, сегодня создано/закрыто. */
function planner_stats_parse_task_summary_row(?array $row): array
{
    $r = $row ?? [];
    return [
        'by_status' => [
            'new' => (int) ($r['n_new'] ?? 0),
            'in_progress' => (int) ($r['n_in_progress'] ?? 0),
            'review' => (int) ($r['n_review'] ?? 0),
            'done' => (int) ($r['n_done'] ?? 0),
            'cancelled' => (int) ($r['n_cancelled'] ?? 0),
        ],
        'total_tasks' => (int) ($r['total_tasks'] ?? 0),
        'today_created' => (int) ($r['today_created'] ?? 0),
        'today_done' => (int) ($r['today_done'] ?? 0),
    ];
}

if (!$hasProjectDept) {
    $summarySql = 'SELECT
            SUM(CASE WHEN t.status = \'new\' THEN 1 ELSE 0 END) AS n_new,
            SUM(CASE WHEN t.status = \'in_progress\' THEN 1 ELSE 0 END) AS n_in_progress,
            SUM(CASE WHEN t.status = \'review\' THEN 1 ELSE 0 END) AS n_review,
            SUM(CASE WHEN t.status = \'done\' THEN 1 ELSE 0 END) AS n_done,
            SUM(CASE WHEN t.status = \'cancelled\' THEN 1 ELSE 0 END) AS n_cancelled,
            COUNT(*) AS total_tasks,
            SUM(CASE WHEN t.created_at >= CURDATE() AND t.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS today_created,
            SUM(CASE WHEN t.status = \'done\' AND t.updated_at >= CURDATE() AND t.updated_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS today_done
        FROM tasks t
        WHERE t.organization_id = ?' . $tw['sql'];
    $st = db()->prepare($summarySql);
    planner_stats_exec($st, array_merge([$orgId], $tw['params']), 'stats.task_summary');
    $summary = planner_stats_parse_task_summary_row($st->fetch(PDO::FETCH_ASSOC) ?: null);
    $byStatus = $summary['by_status'];
    $totalTasks = $summary['total_tasks'];
    $todayCreated = $summary['today_created'];
    $todayDone = $summary['today_done'];
    $st = db()->prepare('SELECT COUNT(*) FROM projects p WHERE p.organization_id = ?' . $pw['sql']);
    planner_stats_exec($st, array_merge([$orgId], $pw['params']), 'stats.projects');
    $projects = (int) $st->fetchColumn();
    $st = db()->prepare('SELECT COUNT(*) FROM users WHERE organization_id = ?');
    planner_stats_exec($st, [$orgId], 'stats.members');
    $members = (int) $st->fetchColumn();
    $st = db()->prepare(
        'SELECT p.name AS name, COUNT(t.id) AS c FROM projects p
         LEFT JOIN tasks t ON t.project_id = p.id AND t.organization_id = ?' . $tw['sql'] . '
         WHERE p.organization_id = ?' . $pw['sql'] . '
         GROUP BY p.id, p.name ORDER BY p.name'
    );
    planner_stats_exec($st, array_merge([$orgId], $tw['params'], [$orgId], $pw['params']), 'stats.by_project');
    $byProject = $st->fetchAll(PDO::FETCH_ASSOC);
    json_response([
        'ok' => true,
        'by_status' => $byStatus,
        'totals' => [
            'tasks' => $totalTasks,
            'today_created' => $todayCreated,
            'today_done' => $todayDone,
            'projects' => $projects,
            'members' => $members,
        ],
        'by_project' => $byProject,
    ]);
}

$scope = planner_department_scope_for_projects($user, 'p');
$baseParams = array_merge([$orgId], $scope['params'], $tw['params']);

$summarySqlScoped = 'SELECT
        SUM(CASE WHEN t.status = \'new\' THEN 1 ELSE 0 END) AS n_new,
        SUM(CASE WHEN t.status = \'in_progress\' THEN 1 ELSE 0 END) AS n_in_progress,
        SUM(CASE WHEN t.status = \'review\' THEN 1 ELSE 0 END) AS n_review,
        SUM(CASE WHEN t.status = \'done\' THEN 1 ELSE 0 END) AS n_done,
        SUM(CASE WHEN t.status = \'cancelled\' THEN 1 ELSE 0 END) AS n_cancelled,
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN t.created_at >= CURDATE() AND t.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS today_created,
        SUM(CASE WHEN t.status = \'done\' AND t.updated_at >= CURDATE() AND t.updated_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS today_done
    FROM tasks t
    INNER JOIN projects p ON p.id = t.project_id
    WHERE t.organization_id = ?' . $scope['sql'] . $tw['sql'];
$st = db()->prepare($summarySqlScoped);
planner_stats_exec($st, $baseParams, 'stats.task_summary');
$summaryScoped = planner_stats_parse_task_summary_row($st->fetch(PDO::FETCH_ASSOC) ?: null);
$byStatus = $summaryScoped['by_status'];
$totalTasks = $summaryScoped['total_tasks'];
$todayCreated = $summaryScoped['today_created'];
$todayDone = $summaryScoped['today_done'];

$st = db()->prepare(
    'SELECT COUNT(*) FROM projects p WHERE p.organization_id = ?' . $scope['sql'] . $pw['sql']
);
$projListParams = array_merge([$orgId], $scope['params'], $pw['params']);
planner_stats_exec($st, $projListParams, 'stats.projects');
$projects = (int) $st->fetchColumn();

$st = db()->prepare('SELECT COUNT(*) FROM users WHERE organization_id = ?');
planner_stats_exec($st, [$orgId], 'stats.members');
$members = (int) $st->fetchColumn();

$st = db()->prepare(
    'SELECT CONCAT(d.name, \' — \', p.name) AS name, COUNT(t.id) AS c
     FROM projects p
     INNER JOIN departments d ON d.id = p.department_id
     LEFT JOIN tasks t ON t.project_id = p.id AND t.organization_id = ?' . $tw['sql'] . '
     WHERE p.organization_id = ?' . $scope['sql'] . $pw['sql'] . '
     GROUP BY p.id, d.name, p.name, d.sort_order
     ORDER BY d.sort_order, p.name'
);
planner_stats_exec(
    $st,
    array_merge([$orgId], $tw['params'], [$orgId], $scope['params'], $pw['params']),
    'stats.by_project'
);
$byProject = $st->fetchAll(PDO::FETCH_ASSOC);

json_response([
    'ok' => true,
    'by_status' => $byStatus,
    'totals' => [
        'tasks' => $totalTasks,
        'today_created' => $todayCreated,
        'today_done' => $todayDone,
        'projects' => $projects,
        'members' => $members,
    ],
    'by_project' => $byProject,
]);