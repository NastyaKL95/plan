<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$scope = planner_department_scope_for_projects($user, 'p');
$hasProjectDept = planner_table_has_column('projects', 'department_id');
$hasPortfolioMap = planner_table_exists('portfolio_projects');
$method = effective_request_method();
$pw = planner_scope_org_work_year_project($orgId, 'p');

if ($method === 'GET') {
    if ($hasProjectDept) {
        $sql = 'SELECT p.id, p.name, p.description, p.color, p.created_at, p.department_id, d.name AS department_name
                FROM projects p
                INNER JOIN departments d ON d.id = p.department_id
                WHERE p.organization_id = ?' . $pw['sql'] . $scope['sql'] . ' ORDER BY d.sort_order, d.name, p.name';
        $params = array_merge([$orgId], $pw['params'], $scope['params']);
    } else {
        $sql = 'SELECT p.id, p.name, p.description, p.color, p.created_at, NULL AS department_id, NULL AS department_name
                FROM projects p
                WHERE p.organization_id = ?' . $pw['sql'] . ' ORDER BY p.name';
        $params = array_merge([$orgId], $pw['params']);
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($hasPortfolioMap && $items) {
        $ids = array_values(array_map(static fn(array $x): int => (int) $x['id'], $items));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stMap = db()->prepare(
            "SELECT pp.project_id, pp.portfolio_id
             FROM portfolio_projects pp
             INNER JOIN project_portfolios pf ON pf.id = pp.portfolio_id
             WHERE pf.organization_id = ? AND pp.project_id IN ($ph)"
        );
        $stMap->execute(array_merge([$orgId], $ids));
        $map = [];
        foreach ($stMap->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pid = (int) $row['project_id'];
            if (!isset($map[$pid])) {
                $map[$pid] = [];
            }
            $map[$pid][] = (int) $row['portfolio_id'];
        }
        foreach ($items as &$it) {
            $it['portfolio_ids'] = $map[(int) $it['id']] ?? [];
        }
        unset($it);
    } else {
        foreach ($items as &$it) {
            $it['portfolio_ids'] = [];
        }
        unset($it);
    }
    if ($items !== []) {
        $pids = array_values(array_map(static fn(array $x): int => (int) $x['id'], $items));
        $taskCounts = planner_project_task_counts_map($orgId, $pids);
        foreach ($items as &$it) {
            $pid = (int) $it['id'];
            $tc = (int) ($taskCounts[$pid] ?? 0);
            $it['task_count'] = $tc;
            $deleteBlock = planner_project_delete_block_reason($user, $pid, $tc);
            $it['can_delete'] = $deleteBlock === null;
            $it['delete_block'] = $deleteBlock;
        }
        unset($it);
    }
    json_response([
        'ok' => true,
        'items' => $items,
        'can_create' => user_may_create_projects_and_portfolios($user),
    ]);
} elseif ($method === 'POST') {
    if (!user_may_create_projects_and_portfolios($user)) {
        json_response([
            'ok' => false,
            'error' => 'create_forbidden',
            'message' => 'Создавать проекты и портфели могут администратор, ректорат и руководитель подразделения.',
        ], 403);
    }
    $body = read_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'name_required'], 422);
    }
    $desc = trim((string) ($body['description'] ?? ''));
    $color = trim((string) ($body['color'] ?? '#2563eb'));
    $portfolioIds = isset($body['portfolio_ids']) && is_array($body['portfolio_ids'])
        ? array_values(array_unique(array_map('intval', $body['portfolio_ids'])))
        : [];
    $portfolioIds = array_values(array_filter($portfolioIds, static fn(int $x): bool => $x > 0));
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $color = '#2563eb';
    }

    if ($hasProjectDept) {
        $departmentId = (int) ($body['department_id'] ?? 0);
        if ($departmentId <= 0) {
            json_response(['ok' => false, 'error' => 'department_id_required'], 422);
        }
        $st = db()->prepare('SELECT id FROM departments WHERE id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$departmentId, $orgId]);
        if (!$st->fetch()) {
            json_response(['ok' => false, 'error' => 'bad_department'], 422);
        }
        if (!user_may_assign_department($user, $departmentId)) {
            json_response(['ok' => false, 'error' => 'forbidden_department'], 403);
        }
        if (planner_table_has_column('projects', 'work_year')) {
            $wy = planner_org_work_year_for_org($orgId);
            $st = db()->prepare(
                'INSERT INTO projects (organization_id, work_year, department_id, name, description, color) VALUES (?,?,?,?,?,?)'
            );
            $st->execute([$orgId, $wy, $departmentId, $name, $desc !== '' ? $desc : null, $color]);
        } else {
            $st = db()->prepare(
                'INSERT INTO projects (organization_id, department_id, name, description, color) VALUES (?,?,?,?,?)'
            );
            $st->execute([$orgId, $departmentId, $name, $desc !== '' ? $desc : null, $color]);
        }
    } else {
        if (planner_table_has_column('projects', 'work_year')) {
            $wy = planner_org_work_year_for_org($orgId);
            $st = db()->prepare(
                'INSERT INTO projects (organization_id, work_year, name, description, color) VALUES (?,?,?,?,?)'
            );
            $st->execute([$orgId, $wy, $name, $desc !== '' ? $desc : null, $color]);
        } else {
            $st = db()->prepare(
                'INSERT INTO projects (organization_id, name, description, color) VALUES (?,?,?,?)'
            );
            $st->execute([$orgId, $name, $desc !== '' ? $desc : null, $color]);
        }
    }
    $id = (int) db()->lastInsertId();
    if ($hasPortfolioMap && $portfolioIds !== []) {
        $ph = implode(',', array_fill(0, count($portfolioIds), '?'));
        $stValid = db()->prepare("SELECT id FROM project_portfolios WHERE organization_id = ? AND id IN ($ph)");
        $stValid->execute(array_merge([$orgId], $portfolioIds));
        $allowed = array_map('intval', array_column($stValid->fetchAll(PDO::FETCH_ASSOC), 'id'));
        if ($allowed !== []) {
            $ins = db()->prepare('INSERT INTO portfolio_projects (portfolio_id, project_id) VALUES (?,?)');
            foreach ($allowed as $pfid) {
                $ins->execute([$pfid, $id]);
            }
        }
    }
    log_activity($orgId, $userId, 'create', 'project', $id, 'Создан проект: ' . $name);
    json_response(['ok' => true, 'id' => $id]);
} elseif ($method === 'PATCH') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $chk = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
    $chk->execute([$id, $orgId]);
    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }

    $body = read_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'name_required'], 422);
    }
    $desc = trim((string) ($body['description'] ?? ''));
    $color = trim((string) ($body['color'] ?? '#2563eb'));
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $color = '#2563eb';
    }
    $portfolioIds = isset($body['portfolio_ids']) && is_array($body['portfolio_ids'])
        ? array_values(array_unique(array_map('intval', $body['portfolio_ids'])))
        : [];
    $portfolioIds = array_values(array_filter($portfolioIds, static fn(int $x): bool => $x > 0));

    if ($hasProjectDept) {
        $departmentId = (int) ($body['department_id'] ?? 0);
        if ($departmentId <= 0) {
            json_response(['ok' => false, 'error' => 'department_id_required'], 422);
        }
        $st = db()->prepare('SELECT id FROM departments WHERE id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$departmentId, $orgId]);
        if (!$st->fetch(PDO::FETCH_ASSOC)) {
            json_response(['ok' => false, 'error' => 'bad_department'], 422);
        }
        if (!user_may_assign_department($user, $departmentId)) {
            json_response(['ok' => false, 'error' => 'forbidden_department'], 403);
        }
        db()->prepare('UPDATE projects SET department_id = ?, name = ?, description = ?, color = ? WHERE id = ? AND organization_id = ?')
            ->execute([$departmentId, $name, $desc !== '' ? $desc : null, $color, $id, $orgId]);
    } else {
        db()->prepare('UPDATE projects SET name = ?, description = ?, color = ? WHERE id = ? AND organization_id = ?')
            ->execute([$name, $desc !== '' ? $desc : null, $color, $id, $orgId]);
    }

    if ($hasPortfolioMap) {
        db()->prepare('DELETE FROM portfolio_projects WHERE project_id = ?')->execute([$id]);
        if ($portfolioIds !== []) {
            $ph = implode(',', array_fill(0, count($portfolioIds), '?'));
            $stValid = db()->prepare("SELECT id FROM project_portfolios WHERE organization_id = ? AND id IN ($ph)");
            $stValid->execute(array_merge([$orgId], $portfolioIds));
            $allowed = array_map('intval', array_column($stValid->fetchAll(PDO::FETCH_ASSOC), 'id'));
            if ($allowed !== []) {
                $ins = db()->prepare('INSERT INTO portfolio_projects (portfolio_id, project_id) VALUES (?,?)');
                foreach ($allowed as $pfid) {
                    $ins->execute([$pfid, $id]);
                }
            }
        }
    }
    log_activity($orgId, $userId, 'update', 'project', $id, 'Обновлён проект: ' . $name);
    json_response(['ok' => true]);
} elseif ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $st = db()->prepare(
        'SELECT id, name FROM projects WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $st->execute([$id, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    $block = planner_project_delete_block_reason($user, $id);
    if ($block === 'forbidden') {
        json_response([
            'ok' => false,
            'error' => 'forbidden',
            'message' => user_is_organization_admin($user)
                ? 'Недостаточно прав для удаления этого проекта.'
                : 'Удалять проекты может только администратор организации.',
        ], 403);
    }
    if ($block === 'has_tasks') {
        $taskCount = planner_project_task_count($orgId, $id);
        json_response([
            'ok' => false,
            'error' => 'has_tasks',
            'task_count' => $taskCount,
            'message' => 'Нельзя удалить проект: в нём '
                . $taskCount
                . ' '
                . planner_ru_tasks_word($taskCount)
                . '. Сначала удалите или перенесите задачи в другой проект.',
        ], 422);
    }
    $name = (string) ($row['name'] ?? '');
    if ($hasPortfolioMap) {
        db()->prepare('DELETE FROM portfolio_projects WHERE project_id = ?')->execute([$id]);
    }
    db()->prepare('DELETE FROM projects WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
    log_activity($orgId, $userId, 'delete', 'project', $id, 'Удалён проект: ' . $name);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
