<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

$method = effective_request_method();
$withDept = planner_table_has_column('users', 'department_id');
$withMaxChat = planner_table_has_column('users', 'max_chat_id');
$maxChatSelect = $withMaxChat ? 'u.max_chat_id' : 'NULL AS max_chat_id';
$maxChatGroupBy = $withMaxChat ? ', u.max_chat_id' : '';
$mayAssignDeptRoles = user_may_assign_department_roles($user);

if ($method === 'GET') {
    if ($withDept && planner_table_exists('user_departments')) {
        $selectRole = planner_table_has_column('user_departments', 'role')
            ? 'ud.role'
            : "'user' AS role";
        $st = db()->prepare(
            "SELECT u.id, u.email, u.name, u.role, u.department_id, " . $maxChatSelect . ", d0.name AS department_name,
                    GROUP_CONCAT(DISTINCT ud.department_id ORDER BY ud.department_id SEPARATOR ',') AS department_ids_csv,
                    GROUP_CONCAT(DISTINCT d.name ORDER BY d.sort_order, d.name SEPARATOR ', ') AS department_names,
                    GROUP_CONCAT(DISTINCT CONCAT(ud.department_id, ':', " . $selectRole . ") ORDER BY ud.department_id SEPARATOR '|') AS department_roles_csv
             FROM users u
             LEFT JOIN departments d0 ON d0.id = u.department_id
             LEFT JOIN user_departments ud ON ud.user_id = u.id
             LEFT JOIN departments d ON d.id = ud.department_id
             WHERE u.id = ? AND u.organization_id = ?
             GROUP BY u.id, u.email, u.name, u.role, u.department_id" . $maxChatGroupBy . ", d0.name
             LIMIT 1"
        );
    } elseif ($withDept) {
        $st = db()->prepare(
            'SELECT u.id, u.email, u.name, u.role, u.department_id, ' . $maxChatSelect . ', d.name AS department_name, NULL AS department_roles_csv
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = ? AND u.organization_id = ?
             LIMIT 1'
        );
    } else {
        $st = db()->prepare(
            'SELECT u.id, u.email, u.name, u.role, NULL AS department_id, ' . $maxChatSelect . ', NULL AS department_name, NULL AS department_ids_csv, NULL AS department_names, NULL AS department_roles_csv
             FROM users u
             WHERE u.id = ? AND u.organization_id = ?
             LIMIT 1'
        );
    }
    $st->execute([$userId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    $ids = [];
    $csv = trim((string) ($row['department_ids_csv'] ?? ''));
    if ($csv !== '') {
        $ids = array_values(array_filter(array_map('intval', explode(',', $csv)), static function (int $x): bool {
            return $x > 0;
        }));
    } elseif (isset($row['department_id']) && (int) $row['department_id'] > 0) {
        $ids = [(int) $row['department_id']];
    }
    $row['department_ids'] = $ids;
    $roles = [];
    $rcsv = trim((string) ($row['department_roles_csv'] ?? ''));
    if ($rcsv !== '') {
        foreach (explode('|', $rcsv) as $chunk) {
            $parts = explode(':', $chunk, 2);
            $did = isset($parts[0]) ? (int) $parts[0] : 0;
            $rl = planner_normalize_department_role(isset($parts[1]) ? (string) $parts[1] : 'user');
            if ($did > 0) {
                $roles[] = ['department_id' => $did, 'role' => $rl];
            }
        }
    } elseif ($ids !== []) {
        foreach ($ids as $did) {
            $roles[] = ['department_id' => (int) $did, 'role' => 'user'];
        }
    }
    $row['department_roles'] = $roles;
    $deptOptions = [];
    if (planner_table_exists('departments')) {
        $stDep = db()->prepare(
            'SELECT id, name FROM departments WHERE organization_id = ? ORDER BY sort_order, name'
        );
        $stDep->execute([$orgId]);
        $deptOptions = $stDep->fetchAll(PDO::FETCH_ASSOC);
    }
    $orgWorkYear = (int) date('Y');
    $orgDefaultDueTime = '18:00';
    $mayEditOrg = user_may_manage_departments($user);
    $mayEditOrgWorkYear = $mayEditOrg && planner_table_has_column('organizations', 'work_year');
    $mayEditOrgDueTime = $mayEditOrg && planner_table_has_column('organizations', 'default_due_time');
    if (planner_table_has_column('organizations', 'work_year')) {
        $cols = 'work_year';
        if (planner_table_has_column('organizations', 'default_due_time')) {
            $cols .= ', default_due_time';
        }
        $stOy = db()->prepare("SELECT {$cols} FROM organizations WHERE id = ? LIMIT 1");
        $stOy->execute([$orgId]);
        $oy = $stOy->fetch(PDO::FETCH_ASSOC);
        if ($oy) {
            if (isset($oy['work_year'])) {
                $orgWorkYear = (int) $oy['work_year'];
            }
            if (isset($oy['default_due_time'])) {
                $dt = trim((string) $oy['default_due_time']);
                if ($dt !== '' && planner_validate_org_due_time_hm($dt)) {
                    $orgDefaultDueTime = $dt;
                }
            }
        }
    }
    json_response([
        'ok' => true,
        'item' => $row,
        'max_prefs' => planner_max_user_prefs($orgId, $userId),
        'department_options' => $deptOptions,
        'may_assign_department_roles' => $mayAssignDeptRoles,
        'may_assign_head_role' => $mayAssignDeptRoles,
        'is_institute_manager' => user_is_institute_manager($user),
        'organization_work_year' => $orgWorkYear,
        'may_edit_organization_work_year' => $mayEditOrgWorkYear,
        'organization_default_due_time' => $orgDefaultDueTime,
        'may_edit_organization_default_due_time' => $mayEditOrgDueTime,
    ]);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $body = read_json_body();
    $fields = [];
    $params = [];
    $newDepartmentRoles = null;
    $newDepartmentIds = null;
    $newMaxPrefs = null;

    if (array_key_exists('name', $body)) {
        $name = trim((string) $body['name']);
        $nameLen = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($name === '' || $nameLen > 120) {
            json_response(['ok' => false, 'error' => 'bad_name'], 422);
        }
        $fields[] = 'name = ?';
        $params[] = $name;
    }

    if (array_key_exists('new_password', $body)) {
        $password = (string) $body['new_password'];
        if ($password !== '') {
            if (strlen($password) < 6 || strlen($password) > 255) {
                json_response(['ok' => false, 'error' => 'bad_password'], 422);
            }
            $fields[] = 'password_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    if (!$withMaxChat && array_key_exists('max_chat_id', $body)) {
        json_response(['ok' => false, 'error' => 'schema_max_chat_id_missing'], 422);
    }
    if ($withMaxChat && array_key_exists('max_chat_id', $body)) {
        $chatIdRaw = trim((string) ($body['max_chat_id'] ?? ''));
        if ($chatIdRaw === '') {
            $fields[] = 'max_chat_id = NULL';
        } else {
            if (!preg_match('/^\d{5,20}$/', $chatIdRaw)) {
                json_response(['ok' => false, 'error' => 'bad_max_chat_id'], 422);
            }
            $fields[] = 'max_chat_id = ?';
            $params[] = $chatIdRaw;
        }
    }
    if (array_key_exists('max_prefs', $body)) {
        if (!planner_table_exists('user_max_prefs')) {
            json_response(['ok' => false, 'error' => 'schema_max_prefs_missing'], 422);
        }
        if (!is_array($body['max_prefs'])) {
            json_response(['ok' => false, 'error' => 'bad_params'], 422);
        }
        $defaults = planner_max_default_user_prefs();
        $allowed = array_keys($defaults);
        $src = $body['max_prefs'];
        $newMaxPrefs = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $src)) {
                if ($k === 'digest_interval_minutes') {
                    $newMaxPrefs[$k] = max(15, min(1440, (int) $src[$k]));
                } elseif ($k === 'quiet_start' || $k === 'quiet_end') {
                    $v = trim((string) $src[$k]);
                    if (!preg_match('/^\d{2}:\d{2}$/', $v)) {
                        json_response(['ok' => false, 'error' => 'bad_params'], 422);
                    }
                    $newMaxPrefs[$k] = $v;
                } else {
                    $newMaxPrefs[$k] = !empty($src[$k]);
                }
            } else {
                $newMaxPrefs[$k] = $defaults[$k];
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
            if (in_array($rl, ['manager', 'administrator'], true) && !$mayAssignDeptRoles) {
                json_response(['ok' => false, 'error' => 'forbidden'], 403);
            }
            $newDepartmentRoles[$did] = ['department_id' => $did, 'role' => $rl];
        }
        $newDepartmentRoles = array_values($newDepartmentRoles);
        if (!$mayAssignDeptRoles) {
            $existingDeptRoles = planner_fetch_user_department_roles($userId);
            $newDepartmentRoles = planner_merge_department_roles_preserving_leadership(
                $newDepartmentRoles,
                $existingDeptRoles
            );
        }
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
        if ($withDept) {
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

    if ($fields === [] && $newDepartmentRoles === null && $newMaxPrefs === null) {
        json_response(['ok' => false, 'error' => 'no_fields'], 422);
    }

    if ($fields !== []) {
        $params[] = $userId;
        $params[] = $orgId;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?';
        db()->prepare($sql)->execute($params);
    }
    if ($newDepartmentRoles !== null && planner_table_exists('user_departments')) {
        db()->prepare('DELETE FROM user_departments WHERE user_id = ?')->execute([$userId]);
        if ($newDepartmentRoles !== []) {
            if (planner_table_has_column('user_departments', 'role')) {
                $ins = db()->prepare('INSERT INTO user_departments (user_id, department_id, role) VALUES (?, ?, ?)');
                foreach ($newDepartmentRoles as $rr) {
                    $ins->execute([$userId, (int) $rr['department_id'], (string) $rr['role']]);
                }
            } else {
                $ins = db()->prepare('INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)');
                foreach ($newDepartmentRoles as $rr) {
                    $ins->execute([$userId, (int) $rr['department_id']]);
                }
            }
        }
    }
    if ($newMaxPrefs !== null && planner_table_exists('user_max_prefs')) {
        $cols = [];
        foreach (array_keys($newMaxPrefs) as $col) {
            if (planner_table_has_column('user_max_prefs', $col)) {
                $cols[] = $col;
            }
        }
        if ($cols !== []) {
            $insertCols = 'organization_id, user_id, ' . implode(', ', $cols);
            $placeholders = implode(', ', array_fill(0, 2 + count($cols), '?'));
            $updates = [];
            foreach ($cols as $col) {
                $updates[] = $col . ' = VALUES(' . $col . ')';
            }
            $sqlPref = 'INSERT INTO user_max_prefs (' . $insertCols . ') VALUES (' . $placeholders . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
            $paramsPref = [$orgId, $userId];
            foreach ($cols as $col) {
                if (is_bool($newMaxPrefs[$col])) {
                    $paramsPref[] = $newMaxPrefs[$col] ? 1 : 0;
                } else {
                    $paramsPref[] = $newMaxPrefs[$col];
                }
            }
            db()->prepare($sqlPref)->execute($paramsPref);
        }
    }
    log_activity($orgId, $userId, 'update', 'user_profile', $userId, 'Пользователь обновил профиль');
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);

