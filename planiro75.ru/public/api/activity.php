<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

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

if (!user_sees_all_departments($user) && planner_table_has_column('projects', 'department_id')) {
    if ($scopeParams === []) {
        $sql .= ' AND 1=0';
    } else {
        $ph = implode(',', array_fill(0, count($scopeParams), '?'));
        $sql .= " AND (
            a.entity_type NOT IN ('task','project')
            OR (a.entity_type = 'task' AND EXISTS (
                SELECT 1 FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                WHERE t.id = a.entity_id AND t.organization_id = ?
                AND p.department_id IN ($ph)" . $tw['sql'] . "
            ))
            OR (a.entity_type = 'project' AND EXISTS (
                SELECT 1 FROM projects p
                WHERE p.id = a.entity_id AND p.organization_id = ?
                AND p.department_id IN ($ph)" . $pw['sql'] . '
            ))
        )';
        $params = array_merge(
            $params,
            [$orgId],
            $scopeParams,
            $tw['params'],
            [$orgId],
            $scopeParams,
            $pw['params']
        );
    }
}

$sql .= ' ORDER BY a.id DESC LIMIT ' . $limit;

$st = db()->prepare($sql);
$st->execute($params);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

json_response(['ok' => true, 'items' => $items]);
