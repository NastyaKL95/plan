<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$method = effective_request_method();
if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!planner_mail_enabled()) {
    json_response(['ok' => false, 'error' => 'mail_disabled'], 422);
}

$body = read_json_body();
$to = trim((string) ($body['email'] ?? ''));
if ($to === '') {
    $to = trim((string) ($user['email'] ?? ''));
}
if ($to === '' || strpos($to, '@') === false) {
    json_response(['ok' => false, 'error' => 'bad_email'], 422);
}

$subject = 'Тест SMTP из Planner';
$message = "Это тестовое письмо.\n\n"
    . "Организация ID: {$orgId}\n"
    . "Пользователь ID: {$userId}\n"
    . "Время: " . date('Y-m-d H:i:s') . "\n";

$sent = planner_send_mail($to, $subject, $message);
if (!$sent) {
    json_response(['ok' => false, 'error' => 'mail_send_failed'], 500);
}

json_response(['ok' => true, 'sent_to' => $to]);

