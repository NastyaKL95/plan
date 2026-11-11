<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!user_may_manage_users($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['ok' => false, 'error' => 'bad_id'], 422);
}

$regSel = '';
if (planner_table_has_column('users', 'account_status')) {
    $regSel .= ', account_status';
}
if (planner_table_has_column('users', 'email_verified_at')) {
    $regSel .= ', email_verified_at';
}
$st = db()->prepare(
    'SELECT id, organization_id, email, name, role, department_id' . $regSel . ' FROM users WHERE id = ? AND organization_id = ? LIMIT 1'
);
$st->execute([$id, $orgId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$method = effective_request_method();

if ($method === 'DELETE') {
    $blockReason = planner_user_delete_block_reason($orgId, $userId, $id);
    if ($blockReason === 'cannot_delete_self') {
        json_response(['ok' => false, 'error' => 'cannot_delete_self'], 422);
    }
    if ($blockReason === 'last_admin') {
        json_response(['ok' => false, 'error' => 'last_admin'], 422);
    }
    if ($blockReason === 'has_tasks') {
        $taskCount = planner_user_task_involvement_count($orgId, $id);
        json_response([
            'ok' => false,
            'error' => 'has_tasks',
            'task_count' => $taskCount,
            'message' => 'Нельзя удалить пользователя: у него '
                . $taskCount
                . ' '
                . planner_ru_tasks_word($taskCount)
                . ' (как автор, исполнитель или участник). Сначала передайте задачи другим или удалите их.',
        ], 422);
    }
    $name = (string) ($row['name'] ?? '');
    db()->prepare('DELETE FROM users WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
    log_activity($orgId, $userId, 'delete', 'user', $id, 'Удалён пользователь: ' . $name);
    json_response(['ok' => true]);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $body = read_json_body();
    $fields = [];
    $params = [];
    $newDepartmentIds = null;
    $newDepartmentRoles = null;
    if (array_key_exists('name', $body)) {
        $n = trim((string) $body['name']);
        if ($n === '' || strlen($n) > 120) {
            json_response(['ok' => false, 'error' => 'bad_name'], 422);
        }
        $fields[] = 'name = ?';
        $params[] = $n;
    }
    if (array_key_exists('role', $body)) {
        $r = (string) $body['role'];
        if (!in_array($r, ['member', 'admin', 'owner'], true)) {
            json_response(['ok' => false, 'error' => 'bad_role'], 422);
        }
        $fields[] = 'role = ?';
        $params[] = $r;
    }
    if (array_key_exists('department_ids', $body) && is_array($body['department_ids'])) {
        $rawIds = $body['department_ids'];
        $newDepartmentIds = [];
        foreach ($rawIds as $v) {
            $did = (int) $v;
            if ($did > 0) {
                $newDepartmentIds[$did] = $did;
            }
        }
        $newDepartmentIds = array_values($newDepartmentIds);
        if ($newDepartmentIds !== []) {
            $ph = implode(',', array_fill(0, count($newDepartmentIds), '?'));
            $chk = db()->prepare(
                "SELECT id FROM departments WHERE organization_id = ? AND id IN ($ph)"
            );
            $chk->execute(array_merge([$orgId], $newDepartmentIds));
            $ok = array_map('intval', array_column($chk->fetchAll(PDO::FETCH_ASSOC), 'id'));
            sort($ok);
            $req = $newDepartmentIds;
            sort($req);
            if ($ok !== $req) {
                json_response(['ok' => false, 'error' => 'bad_department'], 422);
            }
        }
        if (planner_table_has_column('users', 'department_id')) {
            if ($newDepartmentIds !== []) {
                $fields[] = 'department_id = ?';
                $params[] = (int) $newDepartmentIds[0];
            } else {
                $fields[] = 'department_id = NULL';
            }
        }
    }
    if (array_key_exists('department_roles', $body) && is_array($body['department_roles'])) {
        $newDepartmentRoles = [];
        foreach ($body['department_roles'] as $rowX) {
            if (!is_array($rowX)) {
                continue;
            }
            $did = (int) ($rowX['department_id'] ?? 0);
            if ($did <= 0) {
                continue;
            }
            $rl = planner_validate_department_role((string) ($rowX['role'] ?? 'user'));
            if ($rl === null) {
                json_response(['ok' => false, 'error' => 'bad_role'], 422);
            }
            if (in_array($rl, ['manager', 'administrator'], true) && !user_may_assign_department_roles($user)) {
                json_response(['ok' => false, 'error' => 'forbidden'], 403);
            }
            $newDepartmentRoles[$did] = ['department_id' => $did, 'role' => $rl];
        }
        $newDepartmentRoles = array_values($newDepartmentRoles);
        $newDepartmentIds = array_values(array_map(static function (array $x): int {
            return (int) $x['department_id'];
        }, $newDepartmentRoles));
        if ($newDepartmentIds !== []) {
            $ph = implode(',', array_fill(0, count($newDepartmentIds), '?'));
            $chk = db()->prepare("SELECT id FROM departments WHERE organization_id = ? AND id IN ($ph)");
            $chk->execute(array_merge([$orgId], $newDepartmentIds));
            $ok = array_map('intval', array_column($chk->fetchAll(PDO::FETCH_ASSOC), 'id'));
            sort($ok);
            $req = $newDepartmentIds;
            sort($req);
            if ($ok !== $req) {
                json_response(['ok' => false, 'error' => 'bad_department'], 422);
            }
        }
        if (planner_table_has_column('users', 'department_id')) {
            if ($newDepartmentIds !== []) {
                $primary = 0;
                foreach ($newDepartmentRoles as $rr) {
                    if (department_role_is_leadership((string) ($rr['role'] ?? ''))) {
                        $primary = (int) $rr['department_id'];
                        break;
                    }
                }
                if ($primary <= 0) {
                    $primary = (int) $newDepartmentIds[0];
                }
                $fields[] = 'department_id = ?';
                $params[] = $primary;
            } else {
                $fields[] = 'department_id = NULL';
            }
        }
    }
    if (array_key_exists('department_id', $body) && planner_table_has_column('users', 'department_id')) {
        $did = $body['department_id'];
        if ($did === null || $did === '') {
            $fields[] = 'department_id = NULL';
        } else {
            $did = (int) $did;
            if ($did > 0) {
                $chk = db()->prepare('SELECT id FROM departments WHERE id = ? AND organization_id = ? LIMIT 1');
                $chk->execute([$did, $orgId]);
                if (!$chk->fetch()) {
                    json_response(['ok' => false, 'error' => 'bad_department'], 422);
                }
                $fields[] = 'department_id = ?';
                $params[] = $did;
            } else {
                $fields[] = 'department_id = NULL';
            }
        }
    }
    $wantsApprove = !empty($body['approve']);
    $blockTouched = false;
    $wantsBlocked = null;
    if (array_key_exists('blocked', $body)) {
        if (!planner_table_has_column('users', 'account_status')) {
            json_response(['ok' => false, 'error' => 'migration_required'], 422);
        }
        $wantsBlocked = !empty($body['blocked']);
        if ($wantsBlocked && $id === $userId) {
            json_response(['ok' => false, 'error' => 'cannot_block_self'], 422);
        }
        if ($wantsBlocked) {
            $curSt = (string) ($row['account_status'] ?? 'active');
            if ($curSt === 'blocked') {
                json_response(['ok' => false, 'error' => 'already_blocked'], 422);
            }
            $fields[] = 'account_status = ?';
            $params[] = 'blocked';
        } else {
            $curSt = (string) ($row['account_status'] ?? 'active');
            if ($curSt !== 'blocked') {
                json_response(['ok' => false, 'error' => 'not_blocked'], 422);
            }
            $verified = !empty($row['email_verified_at']);
            $fields[] = 'account_status = ?';
            $params[] = $verified ? 'active' : 'pending_email';
        }
        $blockTouched = true;
    }
    if ($fields === [] && $newDepartmentIds === null && $newDepartmentRoles === null && !$wantsApprove) {
        json_response(['ok' => false, 'error' => 'no_fields'], 422);
    }
    if ($fields !== []) {
        $params[] = $id;
        $params[] = $orgId;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?';
        db()->prepare($sql)->execute($params);
    }
    if (($newDepartmentIds !== null || $newDepartmentRoles !== null) && planner_table_exists('user_departments')) {
        db()->prepare('DELETE FROM user_departments WHERE user_id = ?')->execute([$id]);
        if ($newDepartmentRoles !== null) {
            if ($newDepartmentRoles !== []) {
                if (planner_table_has_column('user_departments', 'role')) {
                    $ins = db()->prepare('INSERT INTO user_departments (user_id, department_id, role) VALUES (?, ?, ?)');
                    foreach ($newDepartmentRoles as $rr) {
                        $ins->execute([$id, (int) $rr['department_id'], (string) $rr['role']]);
                    }
                } else {
                    $ins = db()->prepare('INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)');
                    foreach ($newDepartmentRoles as $rr) {
                        $ins->execute([$id, (int) $rr['department_id']]);
                    }
                }
            }
        } elseif ($newDepartmentIds !== []) {
            if (planner_table_has_column('user_departments', 'role')) {
                $ins = db()->prepare('INSERT INTO user_departments (user_id, department_id, role) VALUES (?, ?, ?)');
                foreach ($newDepartmentIds as $didX) {
                    $ins->execute([$id, (int) $didX, 'user']);
                }
            } else {
                $ins = db()->prepare('INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)');
                foreach ($newDepartmentIds as $didX) {
                    $ins->execute([$id, (int) $didX]);
                }
            }
        }
    }
    if ($wantsApprove) {
        $stFresh = db()->prepare(
            'SELECT role, department_id' . $regSel . ' FROM users WHERE id = ? AND organization_id = ? LIMIT 1'
        );
        $stFresh->execute([$id, $orgId]);
        $fresh = $stFresh->fetch(PDO::FETCH_ASSOC);
        if (!$fresh) {
            json_response(['ok' => false, 'error' => 'not_found'], 404);
        }
        $deptRolesForApprove = null;
        if (planner_table_exists('user_departments')) {
            if (planner_table_has_column('user_departments', 'role')) {
                $stDr = db()->prepare('SELECT department_id, role FROM user_departments WHERE user_id = ?');
            } else {
                $stDr = db()->prepare("SELECT department_id, 'member' AS role FROM user_departments WHERE user_id = ?");
            }
            $stDr->execute([$id]);
            $deptRolesForApprove = [];
            foreach ($stDr->fetchAll(PDO::FETCH_ASSOC) as $dr) {
                $deptRolesForApprove[] = [
                    'department_id' => (int) ($dr['department_id'] ?? 0),
                    'role' => (string) ($dr['role'] ?? 'member'),
                ];
            }
        }
        $approve = planner_registration_approve_user(
            $orgId,
            $userId,
            $id,
            (string) ($fresh['role'] ?? 'member'),
            (int) ($fresh['department_id'] ?? 0),
            $deptRolesForApprove
        );
        if (empty($approve['ok'])) {
            $errMap = [
                'department_required' => 'Укажите хотя бы одно подразделение (для ролей сотрудник и руководитель).',
                'email_not_verified' => 'Пользователь ещё не подтвердил email.',
                'invalid_state' => 'Пользователь не ожидает активации.',
                'bad_role' => 'Некорректная роль.',
            ];
            $code = (string) ($approve['error'] ?? 'approve_failed');
            json_response(['ok' => false, 'error' => $code, 'message' => $errMap[$code] ?? 'Не удалось активировать'], 422);
        }
    }
    $logMsg = 'Изменён пользователь';
    if ($wantsApprove) {
        $logMsg = 'Активирован пользователь';
    } elseif ($blockTouched) {
        $logMsg = $wantsBlocked ? 'Заблокирован пользователь' : 'Разблокирован пользователь';
    }
    log_activity($orgId, $userId, 'update', 'user', $id, $logMsg);
    json_response([
        'ok' => true,
        'approved' => $wantsApprove,
        'blocked' => $blockTouched ? $wantsBlocked : null,
    ]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
