<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';

$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

$scope = planner_department_scope_for_projects($user, 'p');
$scopeParams = $scope['params'];
$tw = planner_scope_org_work_year_task($orgId, 't');
$pw = planner_scope_org_work_year_project($orgId, 'p');

$sql = 'SELECT a.id, a.action, a.entity_type, a.entity_id, a.message, a.created_at, u.name AS user_name
     FROM activity_log a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE a.organization_id = ?';

$params = [$orgId];

if (!user_is_organization_admin($user)) {
    // Задачи — по тем же правилам видимости, что и список задач; проекты — по области подразделений.
    // Записи о пользователях, вложениях, настройках MAX и т. п. видит только администратор.
    $hasProjectDept = planner_table_has_column('projects', 'department_id');
    $vis = planner_tasks_visibility_fragment($user, $scope, $hasProjectDept);
    $qp = planner_tasks_quick_privacy_fragment($user);
    $taskJoin = $hasProjectDept ? ' LEFT JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id' : '';
    $sql .= " AND (
        a.entity_type IN ('document', 'portfolio', 'department', 'checklist_template')
        OR (a.entity_type = 'task' AND EXISTS (
            SELECT 1 FROM tasks t" . $taskJoin . '
            WHERE t.id = a.entity_id AND t.organization_id = ?' . $vis['sql'] . $tw['sql'] . $qp['sql'] . "
        ))
        OR (a.entity_type = 'project' AND EXISTS (
            SELECT 1 FROM projects p
            WHERE p.id = a.entity_id AND p.organization_id = ?" . $scope['sql'] . $pw['sql'] . '
        ))
    )';
    $params = array_merge(
        $params,
        [$orgId],
        $vis['params'],
        $tw['params'],
        $qp['params'],
        [$orgId],
        $scopeParams,
        $pw['params']
    );
}

$sql .= ' ORDER BY a.id DESC LIMIT ' . $limit;

$st = db()->prepare($sql);
$st->execute($params);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

json_response(['ok' => true, 'items' => $items]);
