<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

function oauth_base_url(string $path): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/auth/yandex_start.php'));
    $base = rtrim(str_replace('\\', '/', dirname($scriptName, 2)), '/');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

if (current_user()) {
    header('Location: ' . oauth_base_url('/index.php'), true, 302);
    exit;
}

$oauthCfg = app_config()['oauth']['yandex'] ?? [];
$enabled = !empty($oauthCfg['enabled']);
$clientId = trim((string) ($oauthCfg['client_id'] ?? ''));
$redirectUri = trim((string) ($oauthCfg['redirect_uri'] ?? ''));
$scope = trim((string) ($oauthCfg['scope'] ?? 'login:email login:info'));

if (!$enabled || $clientId === '' || $redirectUri === '') {
    header('Location: ' . oauth_base_url('/login.php?error=yandex_not_configured'), true, 302);
    exit;
}

try {
    $state = bin2hex(random_bytes(16));
} catch (Throwable) {
    $state = sha1(uniqid('yx_', true));
}
$_SESSION['oauth_yandex_state'] = $state;
$_SESSION['oauth_yandex_state_ts'] = time();

$query = http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => $scope,
    'state' => $state,
], '', '&', PHP_QUERY_RFC3986);

header('Location: https://oauth.yandex.ru/authorize?' . $query, true, 302);
exit;
