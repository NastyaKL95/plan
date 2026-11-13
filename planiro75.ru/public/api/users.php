<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$method = effective_request_method();
if ($method !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$regCols = '';
if (planner_table_has_column('users', 'account_status')) {
    $regCols .= ', u.account_status';
}
if (planner_table_has_column('users', 'email_verified_at')) {
    $regCols .= ', u.email_verified_at';
}
if (planner_table_has_column('users', 'department_id')) {
    $st = db()->prepare(
        'SELECT u.id, u.name, u.email, u.role, u.department_id, d.name AS department_name'
        . $regCols . '
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.organization_id = ?
         ORDER BY u.name'
    );
} else {
    $st = db()->prepare(
        'SELECT id, name, email, role, NULL AS department_id, NULL AS department_name'
        . str_replace('u.', '', $regCols) . '
         FROM users WHERE organization_id = ? ORDER BY name'
    );
}
$st->execute([$orgId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);
$byUserRoles = [];
if (planner_table_exists('user_departments')) {
    if (planner_table_has_column('user_departments', 'role')) {
        $stR = db()->prepare(
            "SELECT ud.user_id, ud.department_id, ud.role, d.name AS department_name
             FROM user_departments ud
             INNER JOIN departments d ON d.id = ud.department_id
             INNER JOIN users u ON u.id = ud.user_id
             WHERE u.organization_id = ?
             ORDER BY d.sort_order, d.name"
        );
    } else {
        $stR = db()->prepare(
            "SELECT ud.user_id, ud.department_id, 'user' AS role, d.name AS department_name
             FROM user_departments ud
             INNER JOIN departments d ON d.id = ud.department_id
             INNER JOIN users u ON u.id = ud.user_id
             WHERE u.organization_id = ?
             ORDER BY d.sort_order, d.name"
        );
    }
    $stR->execute([$orgId]);
    foreach ($stR->fetchAll(PDO::FETCH_ASSOC) as $rr) {
        $uid = (int) ($rr['user_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        if (!isset($byUserRoles[$uid])) {
            $byUserRoles[$uid] = [];
        }
        $byUserRoles[$uid][] = [
            'department_id' => (int) ($rr['department_id'] ?? 0),
            'role' => planner_normalize_department_role((string) ($rr['role'] ?? 'user')),
            'department_name' => (string) ($rr['department_name'] ?? ''),
        ];
    }
}
$taskCountsByUser = planner_user_task_involvement_counts_for_org($orgId);
$orgAdminIds = planner_org_admin_ids_for_org($orgId);

foreach ($items as &$it) {
    $uid = (int) ($it['id'] ?? 0);
    $roles = $byUserRoles[$uid] ?? [];
    if ($roles === [] && isset($it['department_id']) && (int) $it['department_id'] > 0) {
        $roles[] = [
            'department_id' => (int) $it['department_id'],
            'role' => ((string) ($it['role'] ?? '') === 'head') ? 'administrator' : 'user',
            'department_name' => (string) ($it['department_name'] ?? ''),
        ];
    }
    $ids = [];
    $labels = [];
    foreach ($roles as $r) {
        $did = (int) ($r['department_id'] ?? 0);
        if ($did <= 0) {
            continue;
        }
        $ids[] = $did;
        $nm = (string) ($r['department_name'] ?? '');
        $rl = department_role_short_label((string) ($r['role'] ?? 'user'));
        $labels[] = trim($nm) !== '' ? ($nm . ' (' . $rl . ')') : ('ID ' . $did . ' (' . $rl . ')');
    }
    $it['department_roles'] = $roles;
    $it['department_ids'] = $ids;
    $it['department_names'] = implode(', ', $labels);
    $stAcc = (string) ($it['account_status'] ?? 'active');
    $it['account_status_label'] = match ($stAcc) {
        'pending_email' => 'Ожидает email',
        'pending_approval' => 'Ожидает активации',
        'blocked' => 'Заблокирован',
        default => 'Активен',
    };
    $it['is_blocked'] = $stAcc === 'blocked';
    $it['can_login'] = $stAcc === 'active';
    $taskCount = $taskCountsByUser[$uid] ?? 0;
    $it['task_count'] = $taskCount;
    $deleteBlock = planner_user_delete_block_reason($orgId, $userId, $uid, $taskCount, $orgAdminIds);
    $it['can_delete'] = $deleteBlock === null;
    $it['delete_block_reason'] = $deleteBlock;
}
unset($it);
json_response(['ok' => true, 'items' => $items, 'registration_flow' => planner_registration_schema_ready()]);
