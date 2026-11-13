<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('project_portfolios')) {
    json_response([
        'ok' => true,
        'items' => [],
        'can_delete' => user_may_delete_portfolio($user),
        'can_create' => user_may_create_projects_and_portfolios($user),
    ]);
}

$pw = planner_scope_org_work_year_project($orgId, 'p');

/**
 * Проверяет, что $candidateParentId не находится внутри поддерева $portfolioId.
 */
function portfolio_parent_creates_cycle(int $orgId, int $portfolioId, int $candidateParentId): bool
{
    if ($portfolioId <= 0 || $candidateParentId <= 0) {
        return false;
    }
    if ($portfolioId === $candidateParentId) {
        return true;
    }
    $cur = $candidateParentId;
    $seen = [];
    $st = db()->prepare(
        'SELECT parent_portfolio_id FROM project_portfolios WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    while ($cur > 0 && !isset($seen[$cur])) {
        $seen[$cur] = true;
        if ($cur === $portfolioId) {
            return true;
        }
        $st->execute([$cur, $orgId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            break;
        }
        $cur = (int) ($row['parent_portfolio_id'] ?? 0);
    }
    return false;
}

$method = effective_request_method();

if ($method === 'GET') {
    $st = db()->prepare(
        'SELECT id, organization_id, parent_portfolio_id, name, description, color, created_at
         FROM project_portfolios
         WHERE organization_id = ?
         ORDER BY name'
    );
    $st->execute([$orgId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$it) {
        $it['project_ids'] = [];
    }
    unset($it);
    if (planner_table_exists('portfolio_projects') && $items !== []) {
        $ids = array_values(array_map(static fn(array $x): int => (int) $x['id'], $items));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $mapSt = db()->prepare("SELECT portfolio_id, project_id FROM portfolio_projects WHERE portfolio_id IN ($ph)");
        $mapSt->execute($ids);
        $m = [];
        foreach ($mapSt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $k = (int) $row['portfolio_id'];
            if (!isset($m[$k])) {
                $m[$k] = [];
            }
            $m[$k][] = (int) $row['project_id'];
        }
        foreach ($items as &$it2) {
            $it2['project_ids'] = $m[(int) $it2['id']] ?? [];
        }
        unset($it2);
    }
    json_response([
        'ok' => true,
        'items' => $items,
        'can_delete' => user_may_delete_portfolio($user),
        'can_create' => user_may_create_projects_and_portfolios($user),
    ]);
}

if ($method === 'POST') {
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
    $color = trim((string) ($body['color'] ?? '#0ea5e9'));
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $color = '#0ea5e9';
    }
    $parentId = (int) ($body['parent_portfolio_id'] ?? 0);
    if ($parentId > 0) {
        $stPar = db()->prepare('SELECT id FROM project_portfolios WHERE id = ? AND organization_id = ? LIMIT 1');
        $stPar->execute([$parentId, $orgId]);
        if (!$stPar->fetch(PDO::FETCH_ASSOC)) {
            json_response(['ok' => false, 'error' => 'bad_parent_portfolio'], 422);
        }
    } else {
        $parentId = null;
    }

    $projectIds = isset($body['project_ids']) && is_array($body['project_ids'])
        ? array_values(array_unique(array_map('intval', $body['project_ids'])))
        : [];
    $projectIds = array_values(array_filter($projectIds, static fn(int $x): bool => $x > 0));

    $st = db()->prepare(
        'INSERT INTO project_portfolios (organization_id, parent_portfolio_id, name, description, color)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$orgId, $parentId, $name, $desc !== '' ? $desc : null, $color]);
    $id = (int) db()->lastInsertId();

    if (planner_table_exists('portfolio_projects') && $projectIds !== []) {
        $ph = implode(',', array_fill(0, count($projectIds), '?'));
        $stValid = db()->prepare("SELECT p.id FROM projects p WHERE p.organization_id = ? AND p.id IN ($ph)" . $pw['sql']);
        $stValid->execute(array_merge([$orgId], $projectIds, $pw['params']));
        $allowed = array_map('intval', array_column($stValid->fetchAll(PDO::FETCH_ASSOC), 'id'));
        if ($allowed !== []) {
            $ins = db()->prepare('INSERT INTO portfolio_projects (portfolio_id, project_id) VALUES (?,?)');
            foreach ($allowed as $pid) {
                $ins->execute([$id, $pid]);
            }
        }
    }

    log_activity($orgId, $userId, 'create', 'portfolio', $id, 'Создан портфель: ' . $name);
    json_response(['ok' => true, 'id' => $id]);
} elseif ($method === 'PATCH') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $chk = db()->prepare('SELECT id FROM project_portfolios WHERE id = ? AND organization_id = ? LIMIT 1');
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
    $color = trim((string) ($body['color'] ?? '#0ea5e9'));
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $color = '#0ea5e9';
    }
    $parentId = (int) ($body['parent_portfolio_id'] ?? 0);
    if ($parentId > 0) {
        $stPar = db()->prepare('SELECT id FROM project_portfolios WHERE id = ? AND organization_id = ? LIMIT 1');
        $stPar->execute([$parentId, $orgId]);
        if (!$stPar->fetch(PDO::FETCH_ASSOC)) {
            json_response(['ok' => false, 'error' => 'bad_parent_portfolio'], 422);
        }
        if (portfolio_parent_creates_cycle($orgId, $id, $parentId)) {
            json_response(['ok' => false, 'error' => 'portfolio_cycle'], 422);
        }
    } else {
        $parentId = null;
    }

    $projectIds = isset($body['project_ids']) && is_array($body['project_ids'])
        ? array_values(array_unique(array_map('intval', $body['project_ids'])))
        : [];
    $projectIds = array_values(array_filter($projectIds, static fn(int $x): bool => $x > 0));

    db()->prepare(
        'UPDATE project_portfolios
         SET parent_portfolio_id = ?, name = ?, description = ?, color = ?
         WHERE id = ? AND organization_id = ?'
    )->execute([$parentId, $name, $desc !== '' ? $desc : null, $color, $id, $orgId]);

    if (planner_table_exists('portfolio_projects')) {
        db()->prepare('DELETE FROM portfolio_projects WHERE portfolio_id = ?')->execute([$id]);
        if ($projectIds !== []) {
            $ph = implode(',', array_fill(0, count($projectIds), '?'));
            $stValid = db()->prepare("SELECT p.id FROM projects p WHERE p.organization_id = ? AND p.id IN ($ph)" . $pw['sql']);
            $stValid->execute(array_merge([$orgId], $projectIds, $pw['params']));
            $allowed = array_map('intval', array_column($stValid->fetchAll(PDO::FETCH_ASSOC), 'id'));
            if ($allowed !== []) {
                $ins = db()->prepare('INSERT INTO portfolio_projects (portfolio_id, project_id) VALUES (?,?)');
                foreach ($allowed as $pid) {
                    $ins->execute([$id, $pid]);
                }
            }
        }
    }

    log_activity($orgId, $userId, 'update', 'portfolio', $id, 'Обновлён портфель: ' . $name);
    json_response(['ok' => true]);
} elseif ($method === 'DELETE') {
    if (!user_may_delete_portfolio($user)) {
        json_response([
            'ok' => false,
            'error' => 'forbidden',
            'message' => 'Удалять портфели может только администратор организации.',
        ], 403);
    }
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $st = db()->prepare('SELECT name FROM project_portfolios WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$id, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    db()->prepare('DELETE FROM project_portfolios WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
    log_activity($orgId, $userId, 'delete', 'portfolio', $id, 'Удалён портфель: ' . (string) ($row['name'] ?? ''));
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);

