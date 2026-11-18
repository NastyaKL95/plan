<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$method = effective_request_method();
if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
if (!user_may_manage_users($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

$body = read_json_body();
$rawIds = $body['user_ids'] ?? [];
if (!is_array($rawIds)) {
    json_response(['ok' => false, 'error' => 'bad_ids'], 422);
}
$ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static function ($x) {
    return $x > 0;
})));
if ($ids === []) {
    json_response(['ok' => false, 'error' => 'no_users'], 422);
}
if (count($ids) > 100) {
    json_response(['ok' => false, 'error' => 'too_many'], 422);
}

$action = (string) ($body['action'] ?? '');
$allowedActions = ['block', 'unblock', 'delete', 'approve'];
if (!in_array($action, $allowedActions, true)) {
    json_response(['ok' => false, 'error' => 'bad_action'], 422);
}

$hasAccountStatus = planner_table_has_column('users', 'account_status');
$hasEmailVerified = planner_table_has_column('users', 'email_verified_at');
$regSel = '';
if ($hasAccountStatus) {
    $regSel .= ', account_status';
}
if ($hasEmailVerified) {
    $regSel .= ', email_verified_at';
}

$ph = implode(',', array_fill(0, count($ids), '?'));
$st = db()->prepare(
    'SELECT id, organization_id, email, name, role, department_id' . $regSel . '
     FROM users WHERE organization_id = ? AND id IN (' . $ph . ')'
);
$st->execute(array_merge([$orgId], $ids));
/** @var array<int, array<string, mixed>> $usersById */
$usersById = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $usersById[(int) $row['id']] = $row;
}

/** @var list<int> $updatedIds */
$updatedIds = [];
/** @var list<array{user_id:int, error:string}> $failed */
$failed = [];

if ($action === 'block') {
    if (!$hasAccountStatus) {
        json_response(['ok' => false, 'error' => 'migration_required'], 422);
    }
    $upd = db()->prepare(
        "UPDATE users SET account_status = 'blocked' WHERE id = ? AND organization_id = ? AND account_status != 'blocked'"
    );
    foreach ($ids as $uid) {
        if ($uid === $userId) {
            $failed[] = ['user_id' => $uid, 'error' => 'cannot_block_self'];
            continue;
        }
        $row = $usersById[$uid] ?? null;
        if (!$row) {
            $failed[] = ['user_id' => $uid, 'error' => 'not_found'];
            continue;
        }
        $curSt = (string) ($row['account_status'] ?? 'active');
        if ($curSt === 'blocked') {
            $failed[] = ['user_id' => $uid, 'error' => 'already_blocked'];
            continue;
        }
        $upd->execute([$uid, $orgId]);
        log_activity($orgId, $userId, 'update', 'user', $uid, 'Массово: заблокирован пользователь');
        $updatedIds[] = $uid;
    }
    json_response([
        'ok' => true,
        'updated' => $updatedIds,
        'updated_count' => count($updatedIds),
        'failed' => $failed,
    ]);
}

if ($action === 'unblock') {
    if (!$hasAccountStatus) {
        json_response(['ok' => false, 'error' => 'migration_required'], 422);
    }
    foreach ($ids as $uid) {
        $row = $usersById[$uid] ?? null;
        if (!$row) {
            $failed[] = ['user_id' => $uid, 'error' => 'not_found'];
            continue;
        }
        $curSt = (string) ($row['account_status'] ?? 'active');
        if ($curSt !== 'blocked') {
            $failed[] = ['user_id' => $uid, 'error' => 'not_blocked'];
            continue;
        }
        $verified = !empty($row['email_verified_at']);
        $nextSt = $verified ? 'active' : 'pending_email';
        db()->prepare(
            'UPDATE users SET account_status = ? WHERE id = ? AND organization_id = ? AND account_status = ?'
        )->execute([$nextSt, $uid, $orgId, 'blocked']);
        log_activity($orgId, $userId, 'update', 'user', $uid, 'Массово: разблокирован пользователь');
        $updatedIds[] = $uid;
    }
    json_response([
        'ok' => true,
        'updated' => $updatedIds,
        'updated_count' => count($updatedIds),
        'failed' => $failed,
    ]);
}

if ($action === 'delete') {
    $taskCounts = planner_user_task_involvement_counts_for_org($orgId);
    $orgAdminIds = planner_org_admin_ids_for_org($orgId);
    $del = db()->prepare('DELETE FROM users WHERE id = ? AND organization_id = ?');
    foreach ($ids as $uid) {
        $row = $usersById[$uid] ?? null;
        if (!$row) {
            $failed[] = ['user_id' => $uid, 'error' => 'not_found'];
            continue;
        }
        $blockReason = planner_user_delete_block_reason(
            $orgId,
            $userId,
            $uid,
            (int) ($taskCounts[$uid] ?? 0),
            $orgAdminIds
        );
        if ($blockReason !== null) {
            $failed[] = ['user_id' => $uid, 'error' => $blockReason];
            continue;
        }
        $name = (string) ($row['name'] ?? '');
        $del->execute([$uid, $orgId]);
        log_activity($orgId, $userId, 'delete', 'user', $uid, 'Массово: удалён пользователь: ' . $name);
        $updatedIds[] = $uid;
    }
    json_response([
        'ok' => true,
        'updated' => $updatedIds,
        'updated_count' => count($updatedIds),
        'failed' => $failed,
    ]);
}

if ($action === 'approve') {
    if (!planner_registration_schema_ready()) {
        json_response(['ok' => false, 'error' => 'migration_required'], 422);
    }
    foreach ($ids as $uid) {
        $row = $usersById[$uid] ?? null;
        if (!$row) {
            $failed[] = ['user_id' => $uid, 'error' => 'not_found'];
            continue;
        }
        $deptRolesForApprove = null;
        if (planner_table_exists('user_departments')) {
            if (planner_table_has_column('user_departments', 'role')) {
                $stDr = db()->prepare('SELECT department_id, role FROM user_departments WHERE user_id = ?');
            } else {
                $stDr = db()->prepare("SELECT department_id, 'member' AS role FROM user_departments WHERE user_id = ?");
            }
            $stDr->execute([$uid]);
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
            $uid,
            (string) ($row['role'] ?? 'member'),
            (int) ($row['department_id'] ?? 0),
            $deptRolesForApprove
        );
        if (empty($approve['ok'])) {
            $failed[] = ['user_id' => $uid, 'error' => (string) ($approve['error'] ?? 'approve_failed')];
            continue;
        }
        if (empty($approve['already'])) {
            log_activity($orgId, $userId, 'update', 'user', $uid, 'Массово: активирован пользователь');
        }
        $updatedIds[] = $uid;
    }
    json_response([
        'ok' => true,
        'updated' => $updatedIds,
        'updated_count' => count($updatedIds),
        'failed' => $failed,
    ]);
}

json_response(['ok' => false, 'error' => 'bad_action'], 422);
