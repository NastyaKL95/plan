<?php
declare(strict_types=1);

// Запуск по расписанию: php vitrina/cron/refresh.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../lib/events.php';

$t = microtime(true);
try {
    $data = vitrina_refresh(true);
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
if ($data === null) {
    fwrite(STDERR, "Обновление уже выполняется другим процессом\n");
    exit(0);
}
printf(
    "Событий: %d, категорий: %d, за %.1f с\n",
    count($data['events']),
    count($data['categories']),
    microtime(true) - $t
);
