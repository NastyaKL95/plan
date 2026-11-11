<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('task_saved_views')) {
    json_response(['ok' => true, 'items' => []]);
}

$method = effective_request_method();
$userDeptId = isset($user['department_id']) ? (int) $user['department_id'] : 0;
$hasDefaultCol = planner_table_has_column('task_saved_views', 'is_default');

function planner_saved_views_visible_sql(): string
{
    return ' AND (
        v.user_id = ?
        OR (v.scope = \'team\' AND (v.department_id IS NULL OR v.department_id = ?))
    )';
}

if ($method === 'GET') {
    $defSel = $hasDefaultCol ? ', v.is_default' : ', 0 AS is_default';
    $orderDef = $hasDefaultCol ? 'v.is_default DESC, ' : '';
    $st = db()->prepare(
        'SELECT v.id, v.user_id, v.name, v.scope, v.department_id, v.filters_json, v.created_at, v.updated_at' . $defSel . '
         FROM task_saved_views v
         WHERE v.organization_id = ?' . planner_saved_views_visible_sql() . '
         ORDER BY ' . $orderDef . 'v.updated_at DESC'
    );
    $st->execute([$orgId, $userId, $userDeptId]);
    json_response(['ok' => true, 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'POST') {
    $body = read_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'name_required'], 422);
    }
    $scope = (string) ($body['scope'] ?? 'personal');
    if (!in_array($scope, ['personal', 'team'], true)) {
        $scope = 'personal';
    }
    $deptId = null;
    if ($scope === 'team') {
        $role = (string) ($user['role'] ?? 'member');
        if (in_array($role, ['admin', 'owner'], true) || user_has_manager_visibility_privileges($user)) {
            $deptId = isset($body['department_id']) && $body['department_id'] !== null && $body['department_id'] !== ''
                ? (int) $body['department_id']
                : null;
        } else {
            $deptId = $userDeptId > 0 ? $userDeptId : null;
        }
    }
    $filters = $body['filters'] ?? null;
    $fromViewId = isset($body['from_view_id']) ? (int) $body['from_view_id'] : 0;
    if (!is_array($filters) && $fromViewId > 0) {
        $stFrom = db()->prepare(
            'SELECT * FROM task_saved_views v WHERE v.id = ? AND v.organization_id = ?' . planner_saved_views_visible_sql() . ' LIMIT 1'
        );
        $stFrom->execute([$fromViewId, $orgId, $userId, $userDeptId]);
        $src = $stFrom->fetch(PDO::FETCH_ASSOC);
        if (!$src) {
            json_response(['ok' => false, 'error' => 'not_found'], 404);
        }
        $filters = json_decode((string) ($src['filters_json'] ?? '{}'), true);
        if (!is_array($filters)) {
            $filters = [];
        }
        if ($scope === 'personal' && (string) ($src['scope'] ?? '') === 'team') {
            // копия командного в личное — ок
        }
    }
    if (!is_array($filters)) {
        json_response(['ok' => false, 'error' => 'filters_required'], 422);
    }
    $json = json_encode($filters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if ($hasDefaultCol) {
        $st = db()->prepare(
            'INSERT INTO task_saved_views (organization_id, user_id, name, scope, department_id, filters_json, is_default)
             VALUES (?,?,?,?,?,?,0)'
        );
        $st->execute([$orgId, $userId, $name, $scope, $deptId, $json]);
    } else {
        $st = db()->prepare(
            'INSERT INTO task_saved_views (organization_id, user_id, name, scope, department_id, filters_json)
             VALUES (?,?,?,?,?,?)'
        );
        $st->execute([$orgId, $userId, $name, $scope, $deptId, $json]);
    }
    $id = (int) db()->lastInsertId();
    json_response(['ok' => true, 'id' => $id]);
}

if ($method === 'PATCH') {
    $body = read_json_body();
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $st = db()->prepare(
        'SELECT * FROM task_saved_views WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $st->execute([$id, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    if ((int) $row['user_id'] !== $userId && ($user['role'] ?? '') !== 'admin') {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $name = isset($body['name']) ? trim((string) $body['name']) : null;
    if ($name !== null && $name === '') {
        json_response(['ok' => false, 'error' => 'name_required'], 422);
    }
    if ($name !== null) {
        db()->prepare('UPDATE task_saved_views SET name = ? WHERE id = ? AND organization_id = ?')
            ->execute([$name, $id, $orgId]);
    }
    if (isset($body['filters']) && is_array($body['filters'])) {
        $json = json_encode($body['filters'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        db()->prepare('UPDATE task_saved_views SET filters_json = ? WHERE id = ? AND organization_id = ?')
            ->execute([$json, $id, $orgId]);
    }
    if (!empty($body['make_default'])) {
        if (!$hasDefaultCol) {
            json_response(['ok' => false, 'error' => 'migration_required'], 501);
        }
        if ((int) $row['user_id'] !== $userId) {
            json_response(['ok' => false, 'error' => 'forbidden_default'], 403);
        }
        db()->prepare('UPDATE task_saved_views SET is_default = 0 WHERE organization_id = ? AND user_id = ?')
            ->execute([$orgId, $userId]);
        db()->prepare('UPDATE task_saved_views SET is_default = 1 WHERE id = ? AND organization_id = ? AND user_id = ?')
            ->execute([$id, $orgId, $userId]);
    }
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $st = db()->prepare(
        'SELECT user_id FROM task_saved_views WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $st->execute([$id, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    if ((int) $row['user_id'] !== $userId && ($user['role'] ?? '') !== 'admin') {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    db()->prepare('DELETE FROM task_saved_views WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
