<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('department_heads') || !planner_table_exists('departments')) {
    json_response(['ok' => false, 'error' => 'not_configured'], 501);
}

if (!user_may_manage_departments($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

$method = effective_request_method();

if ($method === 'POST') {
    $body = read_json_body();
    $departmentId = (int) ($body['department_id'] ?? 0);
    $headUserId = (int) ($body['user_id'] ?? 0);
    if ($departmentId <= 0 || $headUserId <= 0) {
        json_response(['ok' => false, 'error' => 'bad_params'], 422);
    }
    $st = db()->prepare('SELECT id FROM departments WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$departmentId, $orgId]);
    if (!$st->fetch()) {
        json_response(['ok' => false, 'error' => 'bad_department'], 422);
    }
    $st = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$headUserId, $orgId]);
    if (!$st->fetch()) {
        json_response(['ok' => false, 'error' => 'bad_user'], 422);
    }
    try {
        $ins = db()->prepare('INSERT INTO department_heads (user_id, department_id) VALUES (?,?)');
        $ins->execute([$headUserId, $departmentId]);
    } catch (Throwable) {
        json_response(['ok' => false, 'error' => 'duplicate'], 422);
    }
    log_activity($orgId, $userId, 'assign', 'department_head', $departmentId, 'Назначен руководитель подразделения');
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $departmentId = (int) ($_GET['department_id'] ?? 0);
    $headUserId = (int) ($_GET['user_id'] ?? 0);
    if ($departmentId <= 0 || $headUserId <= 0) {
        json_response(['ok' => false, 'error' => 'bad_params'], 422);
    }
    $st = db()->prepare(
        'DELETE FROM department_heads WHERE user_id = ? AND department_id = ?'
    );
    $st->execute([$headUserId, $departmentId]);
    $st = db()->prepare('SELECT id FROM departments WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$departmentId, $orgId]);
    if (!$st->fetch()) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    log_activity($orgId, $userId, 'unassign', 'department_head', $departmentId, 'Снят руководитель подразделения');
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
