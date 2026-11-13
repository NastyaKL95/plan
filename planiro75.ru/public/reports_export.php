<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/xlsx_simple.php';

$user = require_login();
$orgPhaseYear = isset($user['organization_work_year']) ? (int) $user['organization_work_year'] : (int) date('Y');

$hasProjectDept = planner_table_has_column('projects', 'department_id');
$scope = planner_reports_tasks_scope($user, $hasProjectDept, 'p');
$orgId = (int) $user['organization_id'];
$tw = planner_scope_org_work_year_task($orgId, 't');
$baseParams = array_merge([$orgId], $scope['params'], $tw['params']);

$mode = $_GET['mode'] ?? 'departments';
$reportsScopeLevel = planner_reports_visibility_level($user);

if ($mode === 'departments' && $reportsScopeLevel === 'self') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Экспорт по подразделениям недоступен: отчёт только по вашим задачам.';
    exit;
}

if ($mode !== 'departments' && $mode !== 'tasks') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bad mode';
    exit;
}

$generated = (new DateTimeImmutable('now'))->format('d.m.Y H:i');

if ($mode === 'departments') {
    $reportTitle = 'Активные задачи по подразделениям · год ' . $orgPhaseYear . ' г. · сформировано ' . $generated;
    $sheetName = 'По подразделениям';
    $fileSlug = 'tasks_by_department.xlsx';
    $headers = ['Подразделение', 'Активных задач'];
    $rows = [];
    if (!$hasProjectDept) {
        $rows[] = ['Проекты не привязаны к подразделениям (нет поля department_id).', '—'];
    } else {
        $st = db()->prepare(
            "SELECT d.name AS department_name, COUNT(*) AS c
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             INNER JOIN departments d ON d.id = p.department_id
             WHERE t.organization_id = ? AND t.status NOT IN ('done','cancelled')" . $scope['sql'] . $tw['sql'] . '
             GROUP BY d.id, d.name
             ORDER BY d.sort_order, d.name'
        );
        $st->execute($baseParams);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [(string) $row['department_name'], (int) $row['c']];
        }
    }
} else {
    $reportTitle = 'Список задач (до 5000 записей) · год ' . $orgPhaseYear . ' г. · сформировано ' . $generated;
    $sheetName = 'Список задач';
    $fileSlug = 'tasks_list.xlsx';
    $headers = ['Задача', 'Статус', 'Подразделение', 'Проект', 'Исполнитель', 'Срок'];
    $labels = [
        'new' => 'Новая',
        'in_progress' => 'В работе',
        'review' => 'На проверке',
        'done' => 'Выполнена',
        'cancelled' => 'Отменена',
    ];
    if (!$hasProjectDept) {
        $sql = 'SELECT t.title, t.status, NULL AS department_name, p.name AS project_name,
                       ua.name AS assignee_name, t.due_date
                FROM tasks t
                LEFT JOIN projects p ON p.id = t.project_id
                LEFT JOIN users ua ON ua.id = t.assignee_id
                WHERE t.organization_id = ?' . $tw['sql'] . ' ORDER BY t.updated_at DESC LIMIT 5000';
        $st = db()->prepare($sql);
        $st->execute(array_merge([$orgId], $tw['params']));
    } else {
        $sql =
            'SELECT t.title, t.status, d.name AS department_name, p.name AS project_name,
                    ua.name AS assignee_name, t.due_date
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             INNER JOIN departments d ON d.id = p.department_id
             LEFT JOIN users ua ON ua.id = t.assignee_id
             WHERE t.organization_id = ?' . $scope['sql'] . $tw['sql'] . '
             ORDER BY t.updated_at DESC LIMIT 5000';
        $st = db()->prepare($sql);
        $st->execute($baseParams);
    }
    $rows = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stLab = $labels[$row['status']] ?? (string) $row['status'];
        $due = $row['due_date'] ? (string) $row['due_date'] : '';
        $rows[] = [
            (string) $row['title'],
            $stLab,
            (string) ($row['department_name'] ?? ''),
            (string) ($row['project_name'] ?? ''),
            (string) ($row['assignee_name'] ?? ''),
            $due,
        ];
    }
}

try {
    $xlsx = planner_xlsx_build($sheetName, $reportTitle, $headers, $rows);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Не удалось сформировать Excel: ' . $e->getMessage();
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: no-store');
header('Content-Disposition: attachment; filename="' . $fileSlug . '"');

echo $xlsx;
