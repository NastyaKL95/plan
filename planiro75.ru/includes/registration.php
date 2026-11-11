<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

function planner_registration_schema_ready(): bool
{
    return planner_table_has_column('users', 'account_status')
        && planner_table_has_column('users', 'email_verified_at')
        && planner_table_exists('user_email_verification_tokens');
}

function planner_registration_email_allowed(string $email): bool
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $domain = substr(strrchr($email, '@'), 1);
    if ($domain === false || $domain === '') {
        return false;
    }
    $allowed = app_config()['registration']['allowed_domains'] ?? ['iro75.ru'];
    if (!is_array($allowed) || $allowed === []) {
        return true;
    }

    return in_array(strtolower($domain), array_map('strtolower', $allowed), true);
}

function planner_registration_enabled(): bool
{
    $cfg = app_config()['registration'] ?? [];
    if (array_key_exists('enabled', $cfg)) {
        return !empty($cfg['enabled']);
    }

    return true;
}

/** Публичная регистрация и автосоздание через OAuth (кроме первого admin при пустой БД). */
function planner_registration_public_open(): bool
{
    if (planner_registration_enabled()) {
        return true;
    }
    $st = db()->query('SELECT COUNT(*) FROM users');

    return (int) $st->fetchColumn() === 0;
}

function planner_registration_purge_pending_days(): int
{
    $cfg = app_config()['registration'] ?? [];
    $days = (int) ($cfg['purge_pending_email_after_days'] ?? 14);

    return $days > 0 ? $days : 0;
}

/**
 * Удаляет заявки pending_email без подтверждения старше N дней (без задач).
 *
 * @return array{ok: bool, deleted: int, skipped: int, error?: string, user_ids?: list<int>}
 */
function planner_registration_purge_stale_pending_email(int $orgId = 0, ?int $days = null): array
{
    if (!planner_registration_schema_ready()) {
        return ['ok' => false, 'deleted' => 0, 'skipped' => 0, 'error' => 'schema'];
    }
    if (!planner_table_has_column('users', 'created_at')) {
        return ['ok' => false, 'deleted' => 0, 'skipped' => 0, 'error' => 'no_created_at'];
    }
    $days = $days ?? planner_registration_purge_pending_days();
    if ($days <= 0) {
        return ['ok' => true, 'deleted' => 0, 'skipped' => 0];
    }

    $sql = "SELECT id, organization_id, name, email FROM users
            WHERE account_status = 'pending_email'
              AND email_verified_at IS NULL
              AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params = [$days];
    if ($orgId > 0) {
        $sql .= ' AND organization_id = ?';
        $params[] = $orgId;
    }
    $sql .= ' ORDER BY id';
    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $deleted = 0;
    $skipped = 0;
    /** @var list<int> $deletedIds */
    $deletedIds = [];
    $taskCountsCache = [];

    foreach ($rows as $row) {
        $uid = (int) ($row['id'] ?? 0);
        $uOrg = (int) ($row['organization_id'] ?? 0);
        if ($uid <= 0 || $uOrg <= 0) {
            continue;
        }
        if (!isset($taskCountsCache[$uOrg])) {
            $taskCountsCache[$uOrg] = planner_user_task_involvement_counts_for_org($uOrg);
        }
        if (($taskCountsCache[$uOrg][$uid] ?? 0) > 0) {
            $skipped++;
            continue;
        }
        if (planner_table_exists('user_email_verification_tokens')) {
            db()->prepare('DELETE FROM user_email_verification_tokens WHERE user_id = ?')->execute([$uid]);
        }
        if (planner_table_exists('user_departments')) {
            db()->prepare('DELETE FROM user_departments WHERE user_id = ?')->execute([$uid]);
        }
        db()->prepare('DELETE FROM users WHERE id = ? AND organization_id = ?')->execute([$uid, $uOrg]);
        $name = (string) ($row['name'] ?? '');
        log_activity(
            $uOrg,
            0,
            'delete',
            'user',
            $uid,
            'Автоудаление заявки pending_email (старше ' . $days . ' дн.): ' . $name
        );
        $deleted++;
        $deletedIds[] = $uid;
    }

    return [
        'ok' => true,
        'deleted' => $deleted,
        'skipped' => $skipped,
        'user_ids' => $deletedIds,
    ];
}

/** @return 'pending_email'|'pending_approval'|'active'|'blocked' */
function planner_user_account_status(array $user): string
{
    if (!planner_table_has_column('users', 'account_status')) {
        return 'active';
    }
    $s = (string) ($user['account_status'] ?? 'active');
    if (!in_array($s, ['pending_email', 'pending_approval', 'active', 'blocked'], true)) {
        return 'active';
    }

    return $s;
}

function planner_user_is_active(array $user): bool
{
    return planner_user_account_status($user) === 'active';
}

function planner_user_is_blocked(array $user): bool
{
    return planner_user_account_status($user) === 'blocked';
}

/**
 * Счётчики задач по всем пользователям организации (один запрос вместо N).
 *
 * @return array<int, int> user_id => число уникальных задач
 */
function planner_user_task_involvement_counts_for_org(int $orgId): array
{
    $parts = [
        'SELECT author_id AS user_id, id AS task_id FROM tasks WHERE organization_id = ?',
        'SELECT assignee_id AS user_id, id AS task_id FROM tasks WHERE organization_id = ? AND assignee_id IS NOT NULL',
    ];
    $params = [$orgId, $orgId];
    if (planner_table_exists('task_participants')) {
        $parts[] = 'SELECT tp.user_id, tp.task_id FROM task_participants tp
            INNER JOIN tasks t ON t.id = tp.task_id
            WHERE t.organization_id = ?';
        $params[] = $orgId;
    }
    if (planner_table_exists('task_my_plan_placement')) {
        $parts[] = 'SELECT tmp.user_id, tmp.task_id FROM task_my_plan_placement tmp
            INNER JOIN tasks t ON t.id = tmp.task_id
            WHERE t.organization_id = ?';
        $params[] = $orgId;
    }
    $sql = 'SELECT user_id, COUNT(DISTINCT task_id) AS task_count
            FROM (' . implode(' UNION ALL ', $parts) . ') task_refs
            WHERE user_id IS NOT NULL AND user_id > 0
            GROUP BY user_id';
    $st = db()->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid > 0) {
            $out[$uid] = (int) ($row['task_count'] ?? 0);
        }
    }

    return $out;
}

/** Число задач организации, где пользователь — автор, исполнитель или участник. */
function planner_user_task_involvement_count(int $orgId, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $parts = ['SELECT id AS task_id FROM tasks WHERE organization_id = ? AND (author_id = ? OR assignee_id = ?)'];
    $params = [$orgId, $userId, $userId];
    if (planner_table_exists('task_participants')) {
        $parts[] = 'SELECT tp.task_id FROM task_participants tp
            INNER JOIN tasks t ON t.id = tp.task_id
            WHERE t.organization_id = ? AND tp.user_id = ?';
        $params[] = $orgId;
        $params[] = $userId;
    }
    if (planner_table_exists('task_my_plan_placement')) {
        $parts[] = 'SELECT tmp.task_id FROM task_my_plan_placement tmp
            INNER JOIN tasks t ON t.id = tmp.task_id
            WHERE t.organization_id = ? AND tmp.user_id = ?';
        $params[] = $orgId;
        $params[] = $userId;
    }
    $sql = 'SELECT COUNT(DISTINCT task_id) FROM (' . implode(' UNION ', $parts) . ') task_refs';
    $st = db()->prepare($sql);
    $st->execute($params);

    return (int) $st->fetchColumn();
}

/** @return list<int> */
function planner_org_admin_ids_for_org(int $orgId): array
{
    $st = db()->prepare(
        "SELECT id FROM users WHERE organization_id = ? AND role IN ('admin', 'owner') ORDER BY id"
    );
    $st->execute([$orgId]);

    return array_values(array_filter(array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'))));
}

/** true, если после удаления $userId в организации не останется администратора. */
function planner_user_is_last_org_admin(int $orgId, int $userId): bool
{
    $st = db()->prepare('SELECT role FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$userId, $orgId]);
    $role = (string) ($st->fetchColumn() ?: '');
    if (!in_array($role, ['admin', 'owner'], true)) {
        return false;
    }
    $st = db()->prepare(
        "SELECT COUNT(*) FROM users WHERE organization_id = ? AND role IN ('admin', 'owner') AND id <> ?"
    );
    $st->execute([$orgId, $userId]);

    return (int) $st->fetchColumn() === 0;
}

/** Склонение «задача» для русских сообщений (1 / 2–4 / 5+). */
function planner_ru_tasks_word(int $n): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return 'задач';
    }
    if ($n1 > 1 && $n1 < 5) {
        return 'задачи';
    }
    if ($n1 === 1) {
        return 'задача';
    }

    return 'задач';
}

/**
 * Код ошибки удаления или null, если удаление разрешено.
 *
 * @return null|'cannot_delete_self'|'has_tasks'|'last_admin'
 */
function planner_user_delete_block_reason(
    int $orgId,
    int $actorUserId,
    int $targetUserId,
    ?int $taskCount = null,
    ?array $orgAdminIds = null
): ?string {
    if ($targetUserId <= 0) {
        return null;
    }
    if ($targetUserId === $actorUserId) {
        return 'cannot_delete_self';
    }
    if ($taskCount === null) {
        $taskCount = planner_user_task_involvement_count($orgId, $targetUserId);
    }
    if ($taskCount > 0) {
        return 'has_tasks';
    }
    if ($orgAdminIds === null) {
        $orgAdminIds = planner_org_admin_ids_for_org($orgId);
    }
    if (in_array($targetUserId, $orgAdminIds, true) && count($orgAdminIds) <= 1) {
        return 'last_admin';
    }

    return null;
}

/** Сообщение для формы входа или null, если вход разрешён. */
function planner_login_block_message(array $user): ?string
{
    if (!planner_registration_schema_ready()) {
        return null;
    }
    $status = planner_user_account_status($user);
    if ($status === 'pending_email') {
        return 'Подтвердите email: перейдите по ссылке из письма, отправленного при регистрации.';
    }
    if ($status === 'pending_approval') {
        return 'Аккаунт ожидает активации администратором (назначение роли и подразделения).';
    }
    if ($status === 'blocked') {
        return 'Доступ к системе заблокирован администратором. Обратитесь к администратору организации.';
    }

    return null;
}

/** Префикс URL до public/ (например /public). Пустая строка — если document root уже указывает на public/. */
function planner_public_path_prefix(): string
{
    $cfg = app_config();
    if (array_key_exists('public_base_path', $cfg['app'] ?? [])) {
        $raw = (string) ($cfg['app']['public_base_path'] ?? '');
    } else {
        $env = getenv('PLANNER_PUBLIC_BASE_PATH');
        $raw = $env !== false ? (string) $env : '/public';
    }
    $raw = trim($raw);
    if ($raw === '' || $raw === '/') {
        return '';
    }

    return '/' . trim($raw, '/');
}

function planner_public_site_url(string $path = ''): string
{
    $cfg = app_config();
    $origin = trim((string) ($cfg['app']['public_origin'] ?? ''));
    if ($path !== '' && $path[0] !== '/') {
        $path = '/' . $path;
    }
    if ($origin !== '') {
        $base = rtrim($origin, '/');
        $prefix = planner_public_path_prefix();

        return $path === '' ? $base . $prefix : $base . $prefix . $path;
    }
    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $base = ($https ? 'https' : 'http') . '://' . $host . planner_app_root();
    $base = rtrim($base, '/');
    if ($path === '') {
        return $base;
    }

    return $base . planner_url($path);
}

function planner_registration_token_ttl_hours(): int
{
    $cfg = app_config();
    $h = (int) (($cfg['registration']['verify_token_hours'] ?? 48));

    return max(1, min(168, $h));
}

function planner_registration_create_verification_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('now'))
        ->modify('+' . planner_registration_token_ttl_hours() . ' hours')
        ->format('Y-m-d H:i:s');
    db()->prepare('DELETE FROM user_email_verification_tokens WHERE user_id = ?')->execute([$userId]);
    db()->prepare(
        'INSERT INTO user_email_verification_tokens (user_id, token, expires_at) VALUES (?,?,?)'
    )->execute([$userId, $token, $expires]);

    return $token;
}

function planner_registration_send_verification_email(int $orgId, int $userId, string $email, string $name): bool
{
    if (!planner_registration_schema_ready() || !planner_mail_enabled()) {
        return false;
    }
    $token = planner_registration_create_verification_token($userId);
    $link = planner_public_site_url('/confirm_email.php?token=' . rawurlencode($token));
    $appName = trim((string) (app_config()['app']['name'] ?? 'Вектор'));
    if ($appName === '') {
        $appName = 'Вектор';
    }
    $subject = $appName . ': подтверждение регистрации';
    $body = "Здравствуйте, {$name}!\n\n"
        . "Вы зарегистрировались в {$appName}.\n"
        . "Подтвердите адрес email, перейдя по ссылке (действует "
        . planner_registration_token_ttl_hours() . " ч.):\n\n"
        . $link . "\n\n"
        . "После подтверждения администратор назначит вам роль и подразделение.\n"
        . "Если вы не регистрировались — проигнорируйте это письмо.\n";

    $queued = planner_mail_queue_push($orgId, $email, $subject, $body, ['kind' => 'registration_verify']);
    if ($queued) {
        planner_mail_queue_kick_async(3);
        return true;
    }

    return planner_send_mail($email, $subject, $body);
}

function planner_registration_send_activated_email(int $orgId, string $email, string $name): void
{
    if (!planner_mail_enabled()) {
        return;
    }
    $appName = trim((string) (app_config()['app']['name'] ?? 'Вектор'));
    if ($appName === '') {
        $appName = 'Вектор';
    }
    $link = planner_public_site_url('/login.php');
    $subject = $appName . ': доступ к системе открыт';
    $body = "Здравствуйте, {$name}!\n\n"
        . "Администратор активировал ваш аккаунт в {$appName}.\n"
        . "Теперь вы можете войти:\n\n"
        . $link . "\n";

    if (planner_mail_queue_push($orgId, $email, $subject, $body, ['kind' => 'registration_activated'])) {
        planner_mail_queue_kick_async(2);
    } else {
        planner_send_mail_deferred($email, $subject, $body);
    }
}

/**
 * @return array{ok: bool, error?: string, user_id?: int}
 */
function planner_registration_verify_token(string $token): array
{
    if (!planner_registration_schema_ready()) {
        return ['ok' => false, 'error' => 'schema'];
    }
    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return ['ok' => false, 'error' => 'bad_token'];
    }
    $st = db()->prepare(
        'SELECT t.user_id, t.expires_at, u.organization_id, u.account_status
         FROM user_email_verification_tokens t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.token = ?
         LIMIT 1'
    );
    $st->execute([$token]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'expired'];
    }
    $userId = (int) $row['user_id'];
    $orgId = (int) $row['organization_id'];
    $status = (string) $row['account_status'];
    if ($status === 'active') {
        db()->prepare('DELETE FROM user_email_verification_tokens WHERE token = ?')->execute([$token]);
        return ['ok' => true, 'user_id' => $userId, 'already' => true];
    }
    if ($status !== 'pending_email') {
        return ['ok' => false, 'error' => 'invalid_state'];
    }
    db()->beginTransaction();
    try {
        db()->prepare(
            'UPDATE users SET email_verified_at = NOW(), account_status = ? WHERE id = ?'
        )->execute(['pending_approval', $userId]);
        db()->prepare('DELETE FROM user_email_verification_tokens WHERE user_id = ?')->execute([$userId]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
    log_activity($orgId, $userId, 'email_verified', 'user', $userId, 'Подтверждён email при регистрации');
    planner_registration_notify_admins_pending($orgId, $userId);

    return ['ok' => true, 'user_id' => $userId];
}

function planner_registration_notify_admins_pending(int $orgId, int $newUserId): void
{
    if (!planner_table_exists('notifications')) {
        return;
    }
    $st = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $st->execute([$newUserId]);
    $nu = $st->fetch(PDO::FETCH_ASSOC);
    if (!$nu) {
        return;
    }
    $title = 'Новая регистрация: ' . (string) ($nu['name'] ?? '');
    $body = 'Пользователь ' . (string) ($nu['email'] ?? '') . ' подтвердил email. Назначьте роль и подразделение в разделе «Команда».';
    $sql = "SELECT DISTINCT u.id FROM users u";
    if (planner_table_exists('user_departments') && planner_table_has_column('user_departments', 'role')) {
        $sql .= " LEFT JOIN user_departments ud ON ud.user_id = u.id AND ud.role = 'manager'";
    }
    $sql .= " WHERE u.organization_id = ? AND (u.role IN ('admin', 'rectorate', 'owner')";
    if (planner_table_exists('user_departments') && planner_table_has_column('user_departments', 'role')) {
        $sql .= ' OR ud.role = \'manager\'';
    }
    $sql .= ')';
    if (planner_table_has_column('users', 'account_status')) {
        $sql .= " AND u.account_status = 'active'";
    }
    $sql .= ' ORDER BY u.id';
    $st = db()->prepare($sql);
    $st->execute([$orgId]);
    $ins = db()->prepare(
        'INSERT INTO notifications (organization_id, user_id, kind, task_id, comment_id, title, body)
         VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $adm) {
        $aid = (int) ($adm['id'] ?? 0);
        if ($aid <= 0 || $aid === $newUserId) {
            continue;
        }
        $ins->execute([$orgId, $aid, 'user_pending', null, null, $title, $body]);
    }
}

/**
 * @param list<array{department_id: int, role: string}>|null $departmentRoles
 */
function planner_registration_user_has_department_assignment(
    int $departmentId,
    ?array $departmentRoles
): bool {
    if ($departmentRoles !== null && $departmentRoles !== []) {
        foreach ($departmentRoles as $rr) {
            if ((int) ($rr['department_id'] ?? 0) > 0) {
                return true;
            }
        }
    }

    return $departmentId > 0;
}

/**
 * @param list<array{department_id: int, role: string}>|null $departmentRoles
 * @return array{ok: bool, error?: string}
 */
function planner_registration_approve_user(
    int $orgId,
    int $actorUserId,
    int $targetUserId,
    string $role,
    int $departmentId,
    ?array $departmentRoles
): array {
    if (!planner_registration_schema_ready()) {
        return ['ok' => false, 'error' => 'schema'];
    }
    if (!in_array($role, ['member', 'admin'], true)) {
        return ['ok' => false, 'error' => 'bad_role'];
    }
    $needsDept = !in_array($role, ['admin', 'owner'], true);
    if ($needsDept && !planner_registration_user_has_department_assignment($departmentId, $departmentRoles)) {
        return ['ok' => false, 'error' => 'department_required'];
    }
    $st = db()->prepare(
        'SELECT id, email, name, account_status, email_verified_at FROM users WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $st->execute([$targetUserId, $orgId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    $status = (string) ($u['account_status'] ?? '');
    if ($status === 'pending_email') {
        return ['ok' => false, 'error' => 'email_not_verified'];
    }
    if ($status === 'blocked') {
        return ['ok' => false, 'error' => 'invalid_state'];
    }
    if ($status === 'active') {
        return ['ok' => true, 'already' => true];
    }
    if ($status !== 'pending_approval') {
        return ['ok' => false, 'error' => 'invalid_state'];
    }
    if (empty($u['email_verified_at'])) {
        return ['ok' => false, 'error' => 'email_not_verified'];
    }
    db()->prepare(
        "UPDATE users SET account_status = 'active' WHERE id = ? AND organization_id = ?"
    )->execute([$targetUserId, $orgId]);
    log_activity($orgId, $actorUserId, 'approve', 'user', $targetUserId, 'Активирован доступ пользователя');
    planner_registration_send_activated_email($orgId, (string) $u['email'], (string) $u['name']);

    return ['ok' => true];
}

function planner_registration_resend_verification(int $userId): bool
{
    if (!planner_registration_schema_ready()) {
        return false;
    }
    $st = db()->prepare(
        'SELECT id, organization_id, email, name, account_status FROM users WHERE id = ? LIMIT 1'
    );
    $st->execute([$userId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u || (string) ($u['account_status'] ?? '') !== 'pending_email') {
        return false;
    }

    return planner_registration_send_verification_email(
        (int) $u['organization_id'],
        (int) $u['id'],
        (string) $u['email'],
        (string) $u['name']
    );
}
