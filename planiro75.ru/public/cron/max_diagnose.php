<?php
declare(strict_types=1);

/**
 * Диагностика MAX: токен, URL webhook, secret в БД, подписки в MAX API.
 *
 * php public/cron/max_diagnose.php
 * php public/cron/max_diagnose.php --org=1
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$orgId = 0;
foreach ($argv as $arg) {
    if (preg_match('/^--org=(\d+)$/', (string) $arg, $m)) {
        $orgId = (int) $m[1];
    }
}
if ($orgId <= 0) {
    $st = db()->query('SELECT id FROM organizations ORDER BY id LIMIT 1');
    $orgId = (int) ($st->fetchColumn() ?: 0);
}

$cfg = app_config();
$cfgToken = planner_max_normalize_token((string) (($cfg['max']['token'] ?? '') ?: ''));
$runtime = planner_max_runtime_settings($orgId > 0 ? $orgId : null);
$rtToken = planner_max_normalize_token((string) ($runtime['token'] ?? ''));
$origin = trim((string) (($cfg['app']['public_origin'] ?? '') ?: ''));
$webhookUrl = planner_max_webhook_endpoint_url();

$dbSecret = '';
$dbToken = '';
if (planner_table_exists('max_settings')) {
    if ($orgId > 0) {
        $st = db()->prepare(
            'SELECT token, webhook_secret, fallback_chat_id, updated_at
             FROM max_settings WHERE organization_id = ? LIMIT 1'
        );
        $st->execute([$orgId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $dbToken = planner_max_normalize_token((string) ($row['token'] ?? ''));
        if (planner_table_has_column('max_settings', 'webhook_secret')) {
            $dbSecret = trim((string) ($row['webhook_secret'] ?? ''));
        }
    }
}

$mask = static function (string $s): string {
    $s = trim($s);
    if ($s === '') {
        return '(пусто)';
    }
    if (strlen($s) <= 8) {
        return str_repeat('*', strlen($s)) . ' (len=' . strlen($s) . ')';
    }

    return substr($s, 0, 4) . '…' . substr($s, -4) . ' (len=' . strlen($s) . ')';
};

$out = [
    'organization_id' => $orgId,
    'planner_max_enabled' => planner_max_enabled(),
    'config' => [
        'public_origin' => $origin !== '' ? $origin : '(не задан — нужен PLANNER_PUBLIC_ORIGIN)',
        'public_base_path' => function_exists('planner_public_path_prefix') ? (planner_public_path_prefix() ?: '(пусто — docroot = public)') : null,
        'webhook_url_computed' => $webhookUrl !== '' ? $webhookUrl : '(не задан — задайте public_origin)',
        'max_webhook_url_override' => trim((string) (($cfg['max']['webhook_url'] ?? '') ?: '')) ?: null,
        'config_token' => $mask($cfgToken),
        'runtime_token_used' => $mask($rtToken),
        'db_max_settings_token' => $mask($dbToken),
        'token_mismatch_hint' => ($cfgToken !== '' && $dbToken !== '' && $cfgToken !== $dbToken)
            ? 'Токен в config и max_settings РАЗНЫЕ — для API используется БД (max_settings).'
            : null,
    ],
    'webhook' => [
        'db_webhook_secret' => $dbSecret !== '' ? $mask($dbSecret) : '(пусто)',
        'secret_valid_for_max' => $dbSecret !== '' && planner_max_webhook_secret_is_valid($dbSecret),
    ],
    'env' => [
        'PLANNER_PUBLIC_ORIGIN' => trim((string) (getenv('PLANNER_PUBLIC_ORIGIN') ?: '')) ?: null,
        'PLANNER_MAX_TOKEN_set' => getenv('PLANNER_MAX_TOKEN') !== false && trim((string) getenv('PLANNER_MAX_TOKEN')) !== '',
        'PLANNER_MAX_WEBHOOK_LOG' => getenv('PLANNER_MAX_WEBHOOK_LOG') !== false ? getenv('PLANNER_MAX_WEBHOOK_LOG') : null,
    ],
    'log_file' => dirname(__DIR__, 2) . '/var/log/max-webhook.log',
    'log_file_exists' => is_file(dirname(__DIR__, 2) . '/var/log/max-webhook.log'),
    'log_file_writable_dir' => is_writable(dirname(__DIR__, 2) . '/var/log')
        || (is_dir(dirname(__DIR__, 2) . '/var/log') ? is_writable(dirname(__DIR__, 2) . '/var/log') : is_writable(dirname(__DIR__, 2))),
];

if ($rtToken !== '') {
    $listed = planner_max_subscriptions_list($orgId > 0 ? $orgId : null);
    $out['max_api'] = [
        'subscriptions_ok' => !empty($listed['ok']),
        'subscriptions_error' => $listed['error'] ?? null,
        'subscriptions' => $listed['items'] ?? [],
    ];
} else {
    $out['max_api'] = ['error' => 'Нет токена — GET /subscriptions не вызывался'];
}

$issues = [];
if ($rtToken === '') {
    $issues[] = 'Не задан токен MAX (config.local.php, env PLANNER_MAX_TOKEN или max_settings.token).';
}
if ($webhookUrl === '' || !str_starts_with(strtolower($webhookUrl), 'https://')) {
    $issues[] = 'Некорректный webhook URL — задайте PLANNER_PUBLIC_ORIGIN=https://ваш-домен';
}
if ($dbSecret === '') {
    $issues[] = 'В БД нет webhook_secret — выполните: php public/cron/max_webhook_register.php --org=' . $orgId;
} elseif (!planner_max_webhook_secret_is_valid($dbSecret)) {
    $issues[] = 'webhook_secret в БД не проходит правила MAX (5–256, [a-zA-Z0-9_-]).';
}
if ($rtToken !== '' && !empty($out['max_api']['subscriptions_ok']) && ($out['max_api']['subscriptions'] ?? []) === []) {
    $issues[] = 'В MAX нет подписок webhook — зарегистрируйте (PowerShell max-setup.ps1 или кнопка в админке).';
}
$out['issues'] = $issues;
$out['fix_powershell_example'] = '.\scripts\max-setup.ps1 -Token "<TOKEN>" -WebhookUrl "' . ($webhookUrl ?: 'https://planiro75.ru/public/api/max_webhook.php') . '" -PhpExe "C:\\path\\php.exe" -ProjectRoot "' . dirname(__DIR__, 2) . '"';

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($issues === [] ? 0 : 1);
