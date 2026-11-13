<?php
declare(strict_types=1);

/** Создание, изменение и удаление подразделений — администратор организации. */
function user_may_manage_departments(array $user): bool
{
    return in_array($user['role'] ?? '', ['admin', 'owner'], true);
}

/** Управление пользователями организации. */
function user_may_manage_users(array $user): bool
{
    return in_array($user['role'] ?? '', ['admin', 'owner'], true);
}

/** Раздел «План года» в боковой панели и импорт Excel: только администратор организации. */
function user_may_access_org_plan_console(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

/** Создание меток организации — только администратор организации. */
function user_may_manage_tags(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

/** Подразделение считается ректоратом по названию (например «Ректорат»). */
function planner_department_name_is_rectorate(string $name): bool
{
    $n = mb_strtolower(trim($name), 'UTF-8');

    return $n === 'ректорат' || str_starts_with($n, 'ректорат ');
}

/**
 * id подразделений «Ректорат» в организации.
 *
 * @return int[]
 */
function planner_rectorate_department_ids(int $orgId): array
{
    static $cache = [];
    if (isset($cache[$orgId])) {
        return $cache[$orgId];
    }
    if ($orgId <= 0) {
        return $cache[$orgId] = [];
    }
    $st = db()->prepare('SELECT id, name FROM departments WHERE organization_id = ?');
    $st->execute([$orgId]);
    $ids = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (planner_department_name_is_rectorate((string) ($row['name'] ?? ''))) {
            $ids[] = (int) $row['id'];
        }
    }
    $cache[$orgId] = $ids;

    return $ids;
}

/** Состоит в подразделении «Ректорат» (любая роль в отделе). */
function user_is_rectorate_department_member(array $user): bool
{
    $rectIds = planner_rectorate_department_ids((int) $user['organization_id']);
    if ($rectIds === []) {
        return false;
    }
    foreach (user_department_membership_ids($user) as $deptId) {
        if (in_array($deptId, $rectIds, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Роль «руководитель организации» в ректорате — обзор по всему институту.
 * Устар. users.role = rectorate трактуется так же.
 */
function user_is_institute_manager(array $user): bool
{
    if (in_array($user['role'] ?? '', ['rectorate'], true)) {
        return true;
    }
    if (!planner_table_exists('user_departments') || !planner_table_has_column('user_departments', 'role')) {
        return false;
    }
    $rectIds = planner_rectorate_department_ids((int) $user['organization_id']);
    if ($rectIds === []) {
        return false;
    }
    $ph = implode(',', array_fill(0, count($rectIds), '?'));
    $st = db()->prepare(
        "SELECT 1 FROM user_departments ud
         WHERE ud.user_id = ? AND ud.role = 'manager' AND ud.department_id IN ($ph)
         LIMIT 1"
    );
    $st->execute(array_merge([(int) $user['id']], $rectIds));

    return (bool) $st->fetchColumn();
}

/** Администратор, руководитель организации или сотрудник ректората — все подразделения и проекты. */
function user_sees_all_departments(array $user): bool
{
    $r = $user['role'] ?? '';
    if (in_array($r, ['admin', 'owner'], true)) {
        return true;
    }
    if (user_is_institute_manager($user) || user_is_rectorate_department_member($user)) {
        return true;
    }
    if (!planner_table_has_column('projects', 'department_id')) {
        return true;
    }

    return false;
}

/**
 * @return int[]|null null — без ограничения по подразделениям; [] — нет доступа
 */
function user_department_membership_ids(array $user): array
{
    $ids = [];
    if (planner_table_exists('user_departments')) {
        $st = db()->prepare(
            'SELECT ud.department_id
             FROM user_departments ud
             INNER JOIN departments d ON d.id = ud.department_id
             WHERE ud.user_id = ? AND d.organization_id = ?'
        );
        $st->execute([(int) $user['id'], (int) $user['organization_id']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $x = (int) ($r['department_id'] ?? 0);
            if ($x > 0) {
                $ids[$x] = $x;
            }
        }
    }
    $did = isset($user['department_id']) && $user['department_id'] !== null && $user['department_id'] !== ''
        ? (int) $user['department_id'] : 0;
    if ($did > 0) {
        $ids[$did] = $did;
    }
    return array_values($ids);
}

/**
 * Подразделения, где пользователь руководитель подразделения (не организации).
 *
 * @return int[]
 */
function user_managed_department_ids(array $user): array
{
    $ids = [];
    if (planner_table_exists('department_heads')) {
        $st = db()->prepare('SELECT department_id FROM department_heads WHERE user_id = ?');
        $st->execute([(int) $user['id']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $x = (int) ($r['department_id'] ?? 0);
            if ($x > 0) {
                $ids[$x] = $x;
            }
        }
    }
    if (planner_table_exists('user_departments') && planner_table_has_column('user_departments', 'role')) {
        $headRoles = planner_sql_in_department_head_roles();
        $ph = implode(',', array_fill(0, count($headRoles), '?'));
        $st = db()->prepare(
            "SELECT ud.department_id
             FROM user_departments ud
             INNER JOIN departments d ON d.id = ud.department_id
             WHERE ud.user_id = ? AND d.organization_id = ? AND ud.role IN ($ph)"
        );
        $st->execute(array_merge([(int) $user['id'], (int) $user['organization_id']], $headRoles));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $x = (int) ($r['department_id'] ?? 0);
            if ($x > 0) {
                $ids[$x] = $x;
            }
        }
    }
    return array_values($ids);
}

/** @return int[] то же, что user_managed_department_ids (руководитель подразделения). */
function user_administered_department_ids(array $user): array
{
    return user_managed_department_ids($user);
}

/** Руководитель организации, подразделения или устар. users.role=head. */
function user_has_manager_visibility_privileges(array $user): bool
{
    if (user_is_institute_manager($user)) {
        return true;
    }
    if (($user['role'] ?? '') === 'head') {
        return true;
    }
    if (user_managed_department_ids($user) !== []) {
        return true;
    }

    return user_administered_department_ids($user) !== [];
}

/**
 * @return int[]|null null — без ограничения по подразделениям; [] — нет доступа
 */
function user_visible_department_ids(array $user): ?array
{
    if (user_sees_all_departments($user)) {
        return null;
    }
    if (user_has_manager_visibility_privileges($user)) {
        $ids = array_merge(user_managed_department_ids($user), user_administered_department_ids($user));
        $member = user_department_membership_ids($user);
        foreach ($member as $x) {
            $ids[] = (int) $x;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $x): bool {
            return $x > 0;
        })));
        return $ids;
    }
    return user_department_membership_ids($user);
}

function user_may_assign_department(array $user, int $departmentId): bool
{
    if (user_sees_all_departments($user)) {
        return true;
    }
    $managed = user_managed_department_ids($user);
    if ($managed !== [] && in_array($departmentId, $managed, true)) {
        return true;
    }
    $administered = user_administered_department_ids($user);
    if ($administered !== [] && in_array($departmentId, $administered, true)) {
        return true;
    }
    $ids = user_department_membership_ids($user);
    return in_array($departmentId, $ids, true);
}

function project_visible_to_user(array $user, int $projectId): bool
{
    $orgId = (int) $user['organization_id'];
    if (planner_table_has_column('projects', 'work_year')) {
        $stY = db()->prepare('SELECT work_year FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
        $stY->execute([$projectId, $orgId]);
        $py = $stY->fetchColumn();
        if ($py === false) {
            return false;
        }
        if ((int) $py !== planner_org_work_year_for_org($orgId)) {
            return false;
        }
    }
    if (user_sees_all_departments($user)) {
        return true;
    }
    if (!planner_table_has_column('projects', 'department_id')) {
        $st = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$projectId, $orgId]);
        return (bool) $st->fetch(PDO::FETCH_ASSOC);
    }
    $scope = user_visible_department_ids($user);
    $st = db()->prepare(
        'SELECT department_id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $st->execute([$projectId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    if ($scope !== null && $scope !== [] && in_array((int) $row['department_id'], $scope, true)) {
        return true;
    }
    if (!function_exists('planner_user_has_visible_task_in_project')) {
        require_once __DIR__ . '/task_extras.php';
    }

    return planner_user_has_visible_task_in_project($user, $projectId);
}

function task_visible_to_user(array $user, int $taskId): bool
{
    if ($taskId <= 0) {
        return false;
    }
    $orgId = (int) ($user['organization_id'] ?? 0);
    if ($orgId <= 0) {
        return false;
    }
    if (!function_exists('planner_tasks_visibility_fragment')) {
        require_once __DIR__ . '/task_extras.php';
    }
    $hasProjectDept = planner_table_has_column('projects', 'department_id');
    $vis = planner_tasks_visibility_fragment($user, ['sql' => '', 'params' => []], $hasProjectDept);
    $join = $hasProjectDept
        ? ' LEFT JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id '
        : '';
    $sql = 'SELECT 1 FROM tasks t' . $join
        . ' WHERE t.organization_id = ? AND t.id = ?' . $vis['sql'] . ' LIMIT 1';
    $st = db()->prepare($sql);
    $st->execute(array_merge([$orgId, $taskId], $vis['params']));

    return (bool) $st->fetchColumn();
}

/**
 * Может ли пользователь менять поля задачи.
 * Админ организации — всё; автор/исполнитель/соисполнитель — свои;
 * менеджер/администратор подразделения — задачи проектов своих управляемых отделов.
 */
function user_may_edit_task(array $user, int $taskId): bool
{
    $role = (string) ($user['role'] ?? '');
    if (in_array($role, ['admin', 'owner'], true)) {
        return true;
    }
    if (user_is_institute_manager($user)) {
        $orgId = (int) ($user['organization_id'] ?? 0);
        $st = db()->prepare('SELECT 1 FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$taskId, $orgId]);

        return (bool) $st->fetchColumn();
    }
    $orgId = (int) ($user['organization_id'] ?? 0);
    $uid = (int) ($user['id'] ?? 0);
    if ($orgId <= 0 || $uid <= 0) {
        return false;
    }
    $st = db()->prepare(
        'SELECT 1
         FROM tasks t
         WHERE t.id = ? AND t.organization_id = ?
           AND (t.author_id = ? OR t.assignee_id = ?)
         LIMIT 1'
    );
    $st->execute([$taskId, $orgId, $uid, $uid]);
    if ($st->fetchColumn()) {
        return true;
    }
    if (planner_table_exists('task_participants')) {
        $st = db()->prepare(
            "SELECT 1
             FROM task_participants tp
             INNER JOIN tasks t ON t.id = tp.task_id
             WHERE tp.task_id = ? AND tp.user_id = ? AND tp.role = 'assistant'
               AND t.organization_id = ?
             LIMIT 1"
        );
        $st->execute([$taskId, $uid, $orgId]);
        if ($st->fetchColumn()) {
            return true;
        }
    }
    if (planner_table_has_column('projects', 'department_id')) {
        $leadDepts = array_values(array_unique(array_merge(
            user_managed_department_ids($user),
            user_administered_department_ids($user)
        )));
        if ($leadDepts !== []) {
            $ph = implode(',', array_fill(0, count($leadDepts), '?'));
            $st = db()->prepare(
                "SELECT 1 FROM tasks t
                 INNER JOIN projects p ON p.id = t.project_id
                 WHERE t.id = ? AND t.organization_id = ?
                   AND p.department_id IN ($ph)
                 LIMIT 1"
            );
            $st->execute(array_merge([$taskId, $orgId], $leadDepts));
            if ($st->fetchColumn()) {
                return true;
            }
        }
        if ($role === 'head') {
            $scope = user_visible_department_ids($user);
            if ($scope !== null && $scope !== []) {
                $ph = implode(',', array_fill(0, count($scope), '?'));
                $st = db()->prepare(
                    "SELECT 1 FROM tasks t
                     INNER JOIN projects p ON p.id = t.project_id
                     WHERE t.id = ? AND t.organization_id = ?
                       AND p.department_id IN ($ph)
                     LIMIT 1"
                );
                $st->execute(array_merge([$taskId, $orgId], $scope));
                if ($st->fetchColumn()) {
                    return true;
                }
            }
        }
    }

    return false;
}

/** Удаление задачи: автор или администратор организации. */
function user_may_delete_task(array $user, int $taskId): bool
{
    $role = (string) ($user['role'] ?? '');
    if (in_array($role, ['admin', 'owner'], true)) {
        return true;
    }
    $orgId = (int) ($user['organization_id'] ?? 0);
    $uid = (int) ($user['id'] ?? 0);
    $st = db()->prepare(
        'SELECT 1 FROM tasks WHERE id = ? AND organization_id = ? AND author_id = ? LIMIT 1'
    );
    $st->execute([$taskId, $orgId, $uid]);
    return (bool) $st->fetchColumn();
}

/** Массовые операции: админ организации, менеджер (ректорат) или админ подразделения. */
function user_may_bulk_update_tasks(array $user): bool
{
    $role = (string) ($user['role'] ?? '');
    if (in_array($role, ['admin', 'owner'], true)) {
        return true;
    }
    if (user_is_institute_manager($user)) {
        return true;
    }

    return user_administered_department_ids($user) !== [];
}

/**
 * @return array{sql: string, params: array}
 */
function planner_department_scope_for_projects(array $user, string $alias = 'p'): array
{
    if (!planner_table_has_column('projects', 'department_id')) {
        return ['sql' => '', 'params' => []];
    }
    if (user_sees_all_departments($user)) {
        return ['sql' => '', 'params' => []];
    }

    if (!function_exists('planner_project_ids_visible_via_tasks_subquery')) {
        require_once __DIR__ . '/task_extras.php';
    }
    $viaTasks = planner_project_ids_visible_via_tasks_subquery($user);

    $ids = user_visible_department_ids($user);
    $parts = [];
    $params = [];
    if ($ids !== null && $ids !== []) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $parts[] = "{$alias}.department_id IN ($ph)";
        $params = array_merge($params, $ids);
    }
    if ($viaTasks['sql'] !== '') {
        $parts[] = "{$alias}.id IN ({$viaTasks['sql']})";
        $params = array_merge($params, $viaTasks['params']);
    }
    if ($parts === []) {
        return ['sql' => ' AND 1=0 ', 'params' => []];
    }

    return ['sql' => ' AND (' . implode(' OR ', $parts) . ') ', 'params' => $params];
}

/**
 * Уровень видимости отчётов: org — вся организация, department — подразделение, self — только свои задачи.
 *
 * @return 'org'|'department'|'self'
 */
function planner_reports_visibility_level(array $user): string
{
    $role = (string) ($user['role'] ?? '');
    if (
        in_array($role, ['admin', 'owner'], true)
        || user_is_institute_manager($user)
        || user_is_rectorate_department_member($user)
    ) {
        return 'org';
    }
    if (user_managed_department_ids($user) !== [] || $role === 'head') {
        return 'department';
    }

    return 'self';
}

/**
 * Подразделения, по которым пользователь видит отчёт «своего отдела».
 *
 * @return list<int>
 */
function planner_reports_department_ids(array $user): array
{
    $ids = array_values(array_unique(array_merge(
        user_managed_department_ids($user),
        user_administered_department_ids($user)
    )));
    if ($ids === [] && (($user['role'] ?? '') === 'head')) {
        $fallback = user_visible_department_ids($user);
        $ids = ($fallback !== null && $fallback !== []) ? $fallback : user_department_membership_ids($user);
    }

    return array_values(array_unique(array_filter(
        array_map('intval', $ids),
        static fn (int $id): bool => $id > 0
    )));
}

/**
 * Ограничение задач в отчётах (таблица tasks t; при department/org — join projects p).
 *
 * @return array{sql: string, params: array}
 */
function planner_reports_tasks_scope(array $user, bool $hasProjectDept, string $projectAlias = 'p'): array
{
    $level = planner_reports_visibility_level($user);
    if ($level === 'org') {
        return ['sql' => '', 'params' => []];
    }
    if ($level === 'department') {
        if (!$hasProjectDept) {
            return ['sql' => '', 'params' => []];
        }
        $ids = planner_reports_department_ids($user);
        if ($ids === []) {
            return ['sql' => ' AND 1=0 ', 'params' => []];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));

        return ['sql' => " AND {$projectAlias}.department_id IN ($ph) ", 'params' => $ids];
    }

    $uid = (int) $user['id'];
    $parts = ['t.author_id = ?', 't.assignee_id = ?'];
    $params = [$uid, $uid];
    if (planner_table_exists('task_participants')) {
        $parts[] = 'EXISTS (SELECT 1 FROM task_participants tp WHERE tp.task_id = t.id AND tp.user_id = ?)';
        $params[] = $uid;
    }

    return ['sql' => ' AND (' . implode(' OR ', $parts) . ') ', 'params' => $params];
}

/** Может ли пользователь назначать роли менеджера/администратора в подразделениях. */
function user_may_assign_department_roles(array $user): bool
{
    return user_may_manage_users($user);
}

/**
 * Раздел «Задачи подразделения»: руководители отделов — свой отдел; ректорат — все подразделения.
 */
function user_may_view_department_tasks_page(array $user): bool
{
    if (in_array($user['role'] ?? '', ['admin', 'owner'], true)) {
        return true;
    }
    if (user_is_institute_manager($user) || user_is_rectorate_department_member($user)) {
        return true;
    }

    return user_has_manager_visibility_privileges($user);
}

/** В разделе «Задачи подразделения» видны задачи всех отделов организации. */
function user_sees_all_department_tasks_in_nav(array $user): bool
{
    return user_sees_all_departments($user);
}

function planner_validate_department_role(string $role): ?string
{
    $n = planner_normalize_department_role($role);
    if (!in_array($n, ['user', 'manager', 'administrator'], true)) {
        return null;
    }

    return $n;
}

function planner_project_task_count(int $orgId, int $projectId): int
{
    if ($orgId <= 0 || $projectId <= 0) {
        return 0;
    }
    $st = db()->prepare(
        'SELECT COUNT(*) FROM tasks WHERE organization_id = ? AND project_id = ?'
    );
    $st->execute([$orgId, $projectId]);

    return (int) $st->fetchColumn();
}

/**
 * @param list<int> $projectIds
 * @return array<int, int> project_id => task_count
 */
function planner_project_task_counts_map(int $orgId, array $projectIds): array
{
    $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn(int $x): bool => $x > 0)));
    if ($orgId <= 0 || $projectIds === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($projectIds), '?'));
    $st = db()->prepare(
        "SELECT project_id, COUNT(*) AS c
         FROM tasks
         WHERE organization_id = ? AND project_id IN ($ph)
         GROUP BY project_id"
    );
    $st->execute(array_merge([$orgId], $projectIds));
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['project_id']] = (int) $row['c'];
    }

    return $out;
}

/** Администратор организации (users.role admin или устар. owner). */
function user_is_organization_admin(array $user): bool
{
    return in_array($user['role'] ?? '', ['admin', 'owner'], true);
}

/**
 * Создание проекта и портфеля: администратор организации, сотрудник ректората
 * или руководитель подразделения. Обычный сотрудник подразделения — нет.
 */
function user_may_create_projects_and_portfolios(array $user): bool
{
    if (user_is_organization_admin($user)) {
        return true;
    }
    if (user_is_institute_manager($user) || user_is_rectorate_department_member($user)) {
        return true;
    }
    if (($user['role'] ?? '') === 'head') {
        return true;
    }

    return user_managed_department_ids($user) !== [];
}

/** Удаление портфеля — только администратор организации. */
function user_may_delete_portfolio(array $user): bool
{
    return user_is_organization_admin($user);
}

/**
 * Удаление проекта — только администратор организации.
 * Проект должен быть виден пользователю (организация и текущий рабочий год).
 */
function user_may_delete_project(array $user, int $projectId): bool
{
    if (!user_is_organization_admin($user)) {
        return false;
    }

    return project_visible_to_user($user, $projectId);
}

/**
 * @return null|'not_found'|'forbidden'|'has_tasks'
 */
function planner_project_delete_block_reason(array $user, int $projectId, ?int $taskCount = null): ?string
{
    $orgId = (int) $user['organization_id'];
    $st = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$projectId, $orgId]);
    if (!$st->fetch(PDO::FETCH_ASSOC)) {
        return 'not_found';
    }
    if (!user_may_delete_project($user, $projectId)) {
        return 'forbidden';
    }
    if ($taskCount === null) {
        $taskCount = planner_project_task_count($orgId, $projectId);
    }
    if ($taskCount > 0) {
        return 'has_tasks';
    }

    return null;
}
