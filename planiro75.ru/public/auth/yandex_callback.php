<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

function oauth_base_url(string $path): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/auth/yandex_callback.php'));
    $base = rtrim(str_replace('\\', '/', dirname($scriptName, 2)), '/');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

if (current_user()) {
    header('Location: ' . oauth_base_url('/index.php'), true, 302);
    exit;
}

function yandex_callback_fail(string $error): never
{
    header('Location: ' . oauth_base_url('/login.php?error=' . rawurlencode($error)), true, 302);
    exit;
}

function yandex_http_post_form(string $url, array $fields): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        return ['status' => $code, 'body' => $raw];
    }

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    return ['status' => 200, 'body' => $raw];
}

function yandex_http_get_json(string $url, string $bearer): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . $bearer]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        return ['status' => $code, 'body' => $raw];
    }

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: OAuth {$bearer}\r\n",
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    return ['status' => 200, 'body' => $raw];
}

$oauthCfg = app_config()['oauth']['yandex'] ?? [];
$enabled = !empty($oauthCfg['enabled']);
$clientId = trim((string) ($oauthCfg['client_id'] ?? ''));
$clientSecret = trim((string) ($oauthCfg['client_secret'] ?? ''));
$redirectUri = trim((string) ($oauthCfg['redirect_uri'] ?? ''));

if (!$enabled || $clientId === '' || $clientSecret === '' || $redirectUri === '') {
    yandex_callback_fail('yandex_not_configured');
}

$state = (string) ($_GET['state'] ?? '');
$expectedState = (string) ($_SESSION['oauth_yandex_state'] ?? '');
$stateTs = (int) ($_SESSION['oauth_yandex_state_ts'] ?? 0);
unset($_SESSION['oauth_yandex_state'], $_SESSION['oauth_yandex_state_ts']);
if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state) || (time() - $stateTs) > 600) {
    yandex_callback_fail('yandex_bad_state');
}

if (!empty($_GET['error'])) {
    yandex_callback_fail('yandex_access_denied');
}

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    yandex_callback_fail('yandex_no_code');
}

$tokenResp = yandex_http_post_form('https://oauth.yandex.ru/token', [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
]);
if (!$tokenResp) {
    yandex_callback_fail('yandex_token_http');
}

try {
    $tokenData = json_decode((string) $tokenResp['body'], true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    yandex_callback_fail('yandex_token_json');
}
$accessToken = trim((string) ($tokenData['access_token'] ?? ''));
if ($accessToken === '') {
    yandex_callback_fail('yandex_token_missing');
}

$infoResp = yandex_http_get_json('https://login.yandex.ru/info?format=json', $accessToken);
if (!$infoResp) {
    yandex_callback_fail('yandex_profile_http');
}
try {
    $profile = json_decode((string) $infoResp['body'], true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    yandex_callback_fail('yandex_profile_json');
}

$subject = trim((string) ($profile['id'] ?? $profile['sub'] ?? ''));
$email = trim((string) ($profile['default_email'] ?? ''));
$name = trim((string) ($profile['real_name'] ?? ''));
if ($name === '') {
    $name = trim((string) (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
}
if ($name === '') {
    $name = 'Пользователь Яндекс';
}
if ($subject === '') {
    yandex_callback_fail('yandex_subject_missing');
}

if (!planner_table_has_column('users', 'oauth_provider') || !planner_table_has_column('users', 'oauth_subject')) {
    yandex_callback_fail('yandex_schema_missing');
}

try {
    db()->beginTransaction();

    $st = db()->prepare(
        'SELECT id FROM users WHERE oauth_provider = ? AND oauth_subject = ? LIMIT 1'
    );
    $st->execute(['yandex', $subject]);
    $byOauth = $st->fetch(PDO::FETCH_ASSOC);
    if ($byOauth) {
        $userId = (int) $byOauth['id'];
        db()->commit();
        $stU = db()->prepare(
            'SELECT u.id, u.organization_id, u.email, u.name, u.role, u.department_id'
            . (planner_table_has_column('users', 'account_status') ? ', u.account_status' : '')
            . (planner_table_has_column('users', 'email_verified_at') ? ', u.email_verified_at' : '')
            . ' FROM users u WHERE u.id = ? LIMIT 1'
        );
        $stU->execute([$userId]);
        $uRow = $stU->fetch(PDO::FETCH_ASSOC);
        $block = $uRow ? planner_login_block_message($uRow) : null;
        if ($block !== null) {
            $_SESSION['login_notice'] = $block;
            header('Location: ' . oauth_base_url('/login.php'), true, 302);
            exit;
        }
        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        planner_csrf_rotate();
        header('Location: ' . oauth_base_url('/index.php'), true, 302);
        exit;
    }

    $existingByEmail = null;
    if ($email !== '') {
        $st = db()->prepare('SELECT id, oauth_provider, oauth_subject FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $existingByEmail = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($existingByEmail) {
        $userId = (int) $existingByEmail['id'];
        $currProvider = trim((string) ($existingByEmail['oauth_provider'] ?? ''));
        $currSubject = trim((string) ($existingByEmail['oauth_subject'] ?? ''));
        if ($currProvider !== '' && ($currProvider !== 'yandex' || ($currSubject !== '' && $currSubject !== $subject))) {
            throw new RuntimeException('oauth_conflict');
        }
        $st = db()->prepare(
            'UPDATE users SET oauth_provider = ?, oauth_subject = ? WHERE id = ?'
        );
        $st->execute(['yandex', $subject, $userId]);
        db()->commit();
        $stU = db()->prepare(
            'SELECT u.id, u.organization_id, u.email, u.name, u.role, u.department_id'
            . (planner_table_has_column('users', 'account_status') ? ', u.account_status' : '')
            . (planner_table_has_column('users', 'email_verified_at') ? ', u.email_verified_at' : '')
            . ' FROM users u WHERE u.id = ? LIMIT 1'
        );
        $stU->execute([$userId]);
        $uRow = $stU->fetch(PDO::FETCH_ASSOC);
        $block = $uRow ? planner_login_block_message($uRow) : null;
        if ($block !== null) {
            $_SESSION['login_notice'] = $block;
            header('Location: ' . oauth_base_url('/login.php'), true, 302);
            exit;
        }
        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        planner_csrf_rotate();
        header('Location: ' . oauth_base_url('/index.php'), true, 302);
        exit;
    }

    $st = db()->query('SELECT id FROM organizations ORDER BY id LIMIT 1');
    $orgRow = $st->fetch(PDO::FETCH_ASSOC);
    if (!$orgRow) {
        throw new RuntimeException('organization_missing');
    }
    $orgId = (int) $orgRow['id'];

    $role = 'member';
    $deptId = null;

    if ($email === '' || !planner_registration_email_allowed($email)) {
        throw new RuntimeException('email_domain_not_allowed');
    }
    if (!planner_registration_public_open()) {
        throw new RuntimeException('registration_closed');
    }
    if (planner_maintenance_is_enabled($orgId)) {
        throw new RuntimeException('maintenance');
    }

    $passwordStub = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    if ($passwordStub === false) {
        throw new RuntimeException('password_hash_fail');
    }

    $hasReg = planner_registration_schema_ready();
    if (planner_table_has_column('users', 'department_id')) {
        if ($hasReg) {
            $st = db()->prepare(
                'INSERT INTO users (organization_id, email, password_hash, email_verified_at, account_status, name, role, department_id, oauth_provider, oauth_subject)
                 VALUES (?,?,?,NOW(),\'pending_approval\',?,?,?,?,?)'
            );
            $st->execute([$orgId, $email, $passwordStub, $name, $role, $deptId, 'yandex', $subject]);
        } else {
            $st = db()->prepare(
                'INSERT INTO users (organization_id, email, password_hash, name, role, department_id, oauth_provider, oauth_subject)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $st->execute([$orgId, $email, $passwordStub, $name, $role, $deptId, 'yandex', $subject]);
        }
    } else {
        $st = db()->prepare(
            'INSERT INTO users (organization_id, email, password_hash, name, role, oauth_provider, oauth_subject)
             VALUES (?,?,?,?,?,?,?)'
        );
        $st->execute([$orgId, $email, $passwordStub, $name, $role, 'yandex', $subject]);
    }
    $userId = (int) db()->lastInsertId();

    log_activity($orgId, $userId, 'oauth_login', 'user', $userId, 'Вход через Яндекс: ожидает активации администратором');
    db()->commit();
    if ($hasReg) {
        planner_registration_notify_admins_pending($orgId, $userId);
        header('Location: ' . oauth_base_url('/login.php?error=yandex_pending_approval'), true, 302);
        exit;
    }
    if (function_exists('session_regenerate_id')) {
        @session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $userId;
    planner_csrf_rotate();
    header('Location: ' . oauth_base_url('/index.php'), true, 302);
    exit;
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    if ($e->getMessage() === 'oauth_conflict') {
        yandex_callback_fail('yandex_oauth_conflict');
    }
    if ($e->getMessage() === 'organization_missing') {
        yandex_callback_fail('organization_missing');
    }
    if ($e->getMessage() === 'email_domain_not_allowed') {
        yandex_callback_fail('yandex_email_domain_not_allowed');
    }
    if ($e->getMessage() === 'registration_closed') {
        yandex_callback_fail('yandex_registration_closed');
    }
    if ($e->getMessage() === 'maintenance') {
        yandex_callback_fail('maintenance');
    }
    yandex_callback_fail('yandex_login_failed');
}
