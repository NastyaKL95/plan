<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

if (effective_request_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!planner_max_enabled()) {
    json_response(['ok' => false, 'error' => 'not_configured'], 422);
}

$body = read_json_body();
$text = trim((string) ($body['text'] ?? ''));
if ($text === '') {
    $text = 'Тест MAX: канал уведомлений настроен и доступен.';
}
$msg = "🧪 Проверка MAX\n"
    . $text
    . "\nПользователь: " . trim((string) ($user['name'] ?? ''))
    . "\nВремя: " . date('d.m.Y H:i:s');

planner_max_queue_user_message($orgId, $userId, $msg);

json_response(['ok' => true, 'queued' => true]);
