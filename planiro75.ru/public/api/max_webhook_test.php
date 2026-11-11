<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

if (!user_may_manage_users($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}
if (effective_request_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$body = read_json_body();
$chatId = trim((string) ($body['chat_id'] ?? ''));
$cmd = trim((string) ($body['command'] ?? '/help'));
$secret = trim((string) ($body['secret'] ?? ''));

if ($chatId === '') {
    json_response(['ok' => false, 'error' => 'chat_id_required'], 422);
}

if (planner_table_exists('max_settings') && planner_table_has_column('max_settings', 'webhook_secret')) {
    $st = db()->prepare('SELECT webhook_secret FROM max_settings WHERE organization_id = ? LIMIT 1');
    $st->execute([$orgId]);
    $stored = trim((string) ($st->fetchColumn() ?: ''));
    if ($stored !== '' && !hash_equals($stored, $secret)) {
        json_response(['ok' => false, 'error' => 'bad_secret'], 403);
    }
}

$known = planner_max_find_user_by_chat_id($chatId);
$preview = [
    'chat_id' => $chatId,
    'text' => $cmd,
];

if (!$known) {
    json_response([
        'ok' => true,
        'result' => 'unbound_chat',
        'preview_payload' => $preview,
        'note' => 'chat_id не привязан ни к одному пользователю',
    ]);
}

json_response([
    'ok' => true,
    'result' => 'ready',
    'preview_payload' => $preview,
    'note' => 'chat_id привязан, секрет корректный, webhook может обрабатывать команды',
    'user' => [
        'id' => (int) ($known['id'] ?? 0),
        'name' => (string) ($known['name'] ?? ''),
    ],
]);
