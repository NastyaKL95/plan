<?php
declare(strict_types=1);

/** Нормализованные роли в подразделении (user_departments.role). */
function planner_normalize_department_role(string $role): string
{
    return match ($role) {
        'head' => 'administrator',
        'member' => 'user',
        'user', 'manager', 'administrator' => $role,
        default => 'user',
    };
}

/** @return list<string> */
function planner_sql_in_department_head_roles(): array
{
    return ['administrator', 'head'];
}

/** @return list<string> Руководитель организации (обзор по институту). */
function planner_sql_in_institute_manager_roles(): array
{
    return ['manager'];
}

/** @return list<string> */
function planner_department_roles_leadership(): array
{
    return ['manager', 'administrator', 'head'];
}

function department_role_is_leadership(string $role): bool
{
    return in_array(planner_normalize_department_role($role), ['manager', 'administrator'], true);
}

function department_role_is_department_head(string $role): bool
{
    return planner_normalize_department_role($role) === 'administrator';
}

function department_role_is_institute_manager(string $role): bool
{
    return planner_normalize_department_role($role) === 'manager';
}

/** @return list<array{department_id: int, role: string}> */
function planner_fetch_user_department_roles(int $userId): array
{
    if ($userId <= 0 || !planner_table_exists('user_departments')) {
        return [];
    }
    if (planner_table_has_column('user_departments', 'role')) {
        $st = db()->prepare('SELECT department_id, role FROM user_departments WHERE user_id = ?');
    } else {
        $st = db()->prepare("SELECT department_id, 'user' AS role FROM user_departments WHERE user_id = ?");
    }
    $st->execute([$userId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $dr) {
        $did = (int) ($dr['department_id'] ?? 0);
        if ($did > 0) {
            $out[] = [
                'department_id' => $did,
                'role' => planner_normalize_department_role((string) ($dr['role'] ?? 'user')),
            ];
        }
    }

    return $out;
}

/**
 * Сохраняет руководящие роли при самообслуживании профиля (users.role = member и т.п.).
 *
 * @param list<array{department_id: int, role: string}> $incoming
 * @param list<array{department_id: int, role: string}> $existing
 * @return list<array{department_id: int, role: string}>
 */
function planner_merge_department_roles_preserving_leadership(array $incoming, array $existing): array
{
    $existingByDept = [];
    foreach ($existing as $row) {
        $did = (int) ($row['department_id'] ?? 0);
        if ($did > 0) {
            $existingByDept[$did] = planner_normalize_department_role((string) ($row['role'] ?? 'user'));
        }
    }
    $merged = [];
    foreach ($incoming as $row) {
        $did = (int) ($row['department_id'] ?? 0);
        if ($did <= 0) {
            continue;
        }
        $rl = planner_normalize_department_role((string) ($row['role'] ?? 'user'));
        if (isset($existingByDept[$did]) && department_role_is_leadership($existingByDept[$did])) {
            $rl = $existingByDept[$did];
        }
        $merged[$did] = ['department_id' => $did, 'role' => $rl];
    }
    foreach ($existingByDept as $did => $rl) {
        if (department_role_is_leadership($rl) && !isset($merged[$did])) {
            $merged[$did] = ['department_id' => $did, 'role' => $rl];
        }
    }

    return array_values($merged);
}

function department_role_label(string $role): string
{
    return match (planner_normalize_department_role($role)) {
        'manager' => 'Руководитель организации',
        'administrator' => 'Руководитель подразделения',
        default => 'Сотрудник',
    };
}

function department_role_short_label(string $role): string
{
    return match (planner_normalize_department_role($role)) {
        'manager' => 'рук. орг.',
        'administrator' => 'рук. отд.',
        default => 'сотр.',
    };
}

function org_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Администратор',
        'owner' => 'Администратор',
        'head' => 'Пользователь',
        'rectorate' => 'Пользователь',
        'member' => 'Пользователь',
        default => 'Пользователь',
    };
}

/** @deprecated use planner_sql_in_department_head_roles */
function planner_sql_in_department_manager_roles(): array
{
    return planner_sql_in_department_head_roles();
}

/** @return list<string> */
function planner_sql_in_department_leadership_roles(): array
{
    return planner_department_roles_leadership();
}
