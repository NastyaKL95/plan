<?php
declare(strict_types=1);

/**
 * Регистрация webhook в MAX после смены токена (CLI).
 *
 * php public/cron/max_webhook_register.php
 * php public/cron/max_webhook_register.php --org=1
 * php public/cron/max_webhook_register.php --secret=my_secret_123
 * php public/cron/max_webhook_register.php --regenerate-secret
 * php public/cron/max_webhook_register.php --url=https://planiro75.ru/public/api/max_webhook.php
 * php public/cron/max_webhook_register.php --dry-run --url=https://planiro75.ru/public/api/max_webhook.php
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "CLI only\n";
    exit(1);
}

$orgId = 0;
$customSecret = null;
$regenerateSecret = false;
$customUrl = null;
$dryRun = false;
foreach ($argv as $arg) {
    if (preg_match('/^--org=(\d+)$/', (string) $arg, $m)) {
        $orgId = (int) $m[1];
    }
    if (preg_match('/^--secret=(.+)$/', (string) $arg, $m)) {
        $customSecret = trim((string) $m[1]);
    }
    if (preg_match('/^--url=(.+)$/', (string) $arg, $m)) {
        $customUrl = trim((string) $m[1]);
    }
    if ($arg === '--regenerate-secret') {
        $regenerateSecret = true;
    }
    if ($arg === '--dry-run') {
        $dryRun = true;
    }
}
if ($orgId <= 0) {
    $st = db()->query('SELECT id FROM organizations ORDER BY id LIMIT 1');
    $orgId = (int) ($st->fetchColumn() ?: 0);
}
if ($orgId <= 0) {
    fwrite(STDERR, "Organization not found. Use --org=ID\n");
    exit(1);
}

$resolvedUrl = $customUrl !== null && $customUrl !== '' ? $customUrl : planner_max_webhook_endpoint_url();
$preview = [
    'dry_run' => $dryRun,
    'config_local_exists' => is_file(dirname(__DIR__, 2) . '/config/config.local.php'),
    'resolved_webhook_url' => $resolvedUrl,
    'url_valid' => planner_max_webhook_url_is_valid($resolvedUrl),
    'env_PLANNER_MAX_WEBHOOK_URL' => trim((string) (getenv('PLANNER_MAX_WEBHOOK_URL') ?: '')) ?: null,
    'hint' => 'На сервере обязательно --url=... пока не задеплоены includes/registration.php и config.local.php с webhook_url.',
];
if ($dryRun) {
    echo json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($preview['url_valid'] ? 0 : 1);
}
if (!$preview['url_valid']) {
    $preview['ok'] = false;
    $preview['error'] = 'bad_webhook_url';
    echo json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

$result = planner_max_webhook_register($orgId, 0, $customUrl, $customSecret, $regenerateSecret);
echo json_encode($result + ['resolved_before_register' => $resolvedUrl], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit(!empty($result['ok']) ? 0 : 1);
