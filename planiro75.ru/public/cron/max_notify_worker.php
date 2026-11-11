<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
if (!is_dir($projectRoot) || !@chdir($projectRoot)) {
    fwrite(STDERR, "max_notify_worker: cannot chdir to project root: {$projectRoot}\n");
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
    require_once $projectRoot . '/includes/max_notify.php';
} catch (Throwable $e) {
    $logDir = $projectRoot . '/var/log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $line = date('c') . ' bootstrap_error: ' . $e->getMessage() . "\n";
    @file_put_contents($logDir . '/max-cron.log', $line, FILE_APPEND);
    fwrite(STDERR, $line);
    exit(1);
}

$limit = isset($argv[1]) ? (int) $argv[1] : 100;
$result = planner_max_process_queue($limit, 12);
planner_max_mark_worker_heartbeat('cron_done');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
