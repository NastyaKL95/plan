<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
if (!is_dir($projectRoot) || !@chdir($projectRoot)) {
    fwrite(STDERR, "purge_pending_registrations: cannot chdir to project root: {$projectRoot}\n");
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "CLI only\n";
    exit;
}

try {
    require_once $projectRoot . '/includes/bootstrap.php';
} catch (Throwable $e) {
    $logDir = $projectRoot . '/var/log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $line = date('c') . ' bootstrap_error: ' . $e->getMessage() . "\n";
    @file_put_contents($logDir . '/reg-purge-cron.log', $line, FILE_APPEND);
    fwrite(STDERR, $line);
    exit(1);
}

$days = null;
$orgId = 0;
foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if (str_starts_with($arg, '--days=')) {
        $days = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--org=')) {
        $orgId = (int) substr($arg, 6);
    }
}

$result = planner_registration_purge_stale_pending_email($orgId, $days);

$logDir = $projectRoot . '/var/log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
@file_put_contents(
    $logDir . '/reg-purge-cron.log',
    date('c') . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND
);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit(empty($result['ok']) ? 1 : 0);
