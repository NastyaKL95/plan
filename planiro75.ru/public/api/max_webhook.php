<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

function planner_max_webhook_log_enabled(): bool
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }
    $env = strtolower(trim((string) getenv('PLANNER_MAX_WEBHOOK_LOG')));
    if ($env !== '') {
        $enabled = in_array($env, ['1', 'true', 'yes', 'on'], true);

        return $enabled;
    }
    $cfg = app_config();
    $fromCfg = $cfg['max']['webhook_log'] ?? true;

    $enabled = !in_array(strtolower((string) $fromCfg), ['0', 'false', 'no', 'off'], true);

    return $enabled;
}

function planner_max_webhook_debug_log(array $data): void
{
    $verbose = in_array(strtolower((string) getenv('PLANNER_MAX_WEBHOOK_DEBUG')), ['1', 'true', 'yes', 'on'], true);
    $stage = (string) ($data['stage'] ?? '');
    $basicStages = ['request', 'parsed', 'reject', 'ignored', 'error', 'handled'];
    $shouldLog = $verbose
        || in_array($stage, ['reject', 'ignored', 'error'], true)
        || (planner_max_webhook_log_enabled() && in_array($stage, $basicStages, true));
    if (!$shouldLog) {
        return;
    }
    $line = json_encode(['ts' => date('c')] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line) || $line === '') {
        return;
    }
    error_log('[planner-max-webhook] ' . $line);
    $dir = dirname(__DIR__, 2) . '/var/log';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log('[planner-max-webhook] var/log недоступен для записи: ' . $dir);

        return;
    }
    $file = $dir . '/max-webhook.log';
    if (@file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        error_log('[planner-max-webhook] не удалось записать ' . $file);
    }
}

/** @param array<string, mixed> $logExtra поля только для лога, не для ответа MAX */
function planner_max_webhook_ok(string $result, array $logExtra = []): void
{
    planner_max_webhook_debug_log(array_merge([
        'stage' => 'handled',
        'result' => $result,
    ], $logExtra));
    json_response(['ok' => true, 'handled' => $result]);
}

function planner_max_webhook_first_non_empty(array $candidates): string
{
    foreach ($candidates as $value) {
        if ($value === null) {
            continue;
        }
        if (is_array($value)) {
            $nested = planner_max_webhook_scalar_id($value);
            if ($nested !== '') {
                return $nested;
            }
            continue;
        }
        if (is_int($value) || is_float($value)) {
            $n = (int) $value;
            if ($n !== 0) {
                return (string) $n;
            }
            continue;
        }
        $s = trim((string) $value);
        if ($s !== '') {
            return $s;
        }
    }
    return '';
}

function planner_max_webhook_scalar_id(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_int($value) || is_float($value)) {
        $n = (int) $value;

        return $n !== 0 ? (string) $n : '';
    }
    if (is_string($value)) {
        return trim($value);
    }
    if (is_array($value)) {
        return planner_max_webhook_first_non_empty([
            $value['chat_id'] ?? null,
            $value['chatId'] ?? null,
            $value['user_id'] ?? null,
            $value['id'] ?? null,
        ]);
    }

    return '';
}

/**
 * MAX в личке: recipient.chat_id — id диалога (куда слать ответы), sender.user_id — id аккаунта (другой номер).
 *
 * @return array{
 *   reply_chat_id: string,
 *   lookup_ids: list<string>,
 *   sender_user_id: string,
 *   chat_type: string,
 *   recipient_chat_id: string
 * }
 */
function planner_max_webhook_resolve_context(
    string $updateType,
    array $body,
    array $message,
    array $messageRecipient,
    string $senderUserId,
    string $callbackUserId = ''
): array {
    $chatType = strtolower(trim((string) ($messageRecipient['chat_type'] ?? '')));
    $recipientChatId = planner_max_webhook_scalar_id($messageRecipient['chat_id'] ?? $messageRecipient['chatId'] ?? null);
    $bodyChatId = planner_max_webhook_first_non_empty([
        $body['chat_id'] ?? null,
        $body['chatId'] ?? null,
        $body['chat']['id'] ?? null,
    ]);
    $messageChatId = planner_max_webhook_first_non_empty([
        $message['chat_id'] ?? null,
        $message['chat']['id'] ?? null,
    ]);

    $lookupIds = [];
    foreach ([
        $recipientChatId,
        $bodyChatId,
        $messageChatId,
        $senderUserId,
        $callbackUserId,
        planner_max_webhook_scalar_id($messageRecipient['user_id'] ?? null),
    ] as $id) {
        $id = trim((string) $id);
        if ($id !== '' && !in_array($id, $lookupIds, true)) {
            $lookupIds[] = $id;
        }
    }

    $isDialog = $chatType === 'dialog' || $chatType === '';
    if ($isDialog || in_array($updateType, ['message_created', 'message_callback', 'bot_started'], true)) {
        $replyChatId = planner_max_webhook_first_non_empty([
            $recipientChatId,
            $bodyChatId,
            $messageChatId,
            $senderUserId,
            $callbackUserId,
        ]);
    } else {
        $replyChatId = planner_max_webhook_first_non_empty([
            $bodyChatId,
            $recipientChatId,
            $messageChatId,
            $senderUserId,
        ]);
    }

    return [
        'reply_chat_id' => $replyChatId,
        'lookup_ids' => $lookupIds,
        'sender_user_id' => $senderUserId,
        'chat_type' => $chatType,
        'recipient_chat_id' => $recipientChatId,
    ];
}

function planner_max_webhook_is_callback_update(string $updateType, string $callbackCommand): bool
{
    if ($callbackCommand !== '') {
        return true;
    }
    $t = strtolower(trim($updateType));

    return in_array($t, ['message_callback', 'message_callback_query', 'callback_query', 'callback'], true);
}

/**
 * @return array{text: string, command: string}
 */
function planner_max_webhook_parse_command_text(string $text): array
{
    $text = trim($text);
    $t = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $command = '';
    if (preg_match('/^(\/[a-z_]+)(?:@\S+)?(?:\s|$)/u', $t, $m)) {
        $command = (string) ($m[1] ?? '');
    }

    return ['text' => $t, 'command' => $command];
}

/**
 * Извлекает команду из callback-полей MAX (строка, JSON-строка, вложенный объект).
 */
function planner_max_webhook_extract_callback_command(array $callback): string
{
    $candidates = [
        $callback['payload'] ?? null,
        $callback['data'] ?? null,
        $callback['text'] ?? null,
        $callback['command'] ?? null,
    ];
    foreach ($candidates as $raw) {
        if ($raw === null) {
            continue;
        }
        if (is_string($raw)) {
            $v = trim($raw);
            if ($v === '') {
                continue;
            }
            if ($v[0] === '{' || $v[0] === '[') {
                try {
                    $decoded = json_decode($v, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable $e) {
                    $decoded = null;
                }
                if (is_array($decoded)) {
                    $nested = planner_max_webhook_first_non_empty([
                        $decoded['command'] ?? null,
                        $decoded['payload'] ?? null,
                        $decoded['text'] ?? null,
                        $decoded['data'] ?? null,
                        $decoded['value'] ?? null,
                    ]);
                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }
            return $v;
        }
        if (is_array($raw)) {
            $nested = planner_max_webhook_first_non_empty([
                $raw['command'] ?? null,
                $raw['payload'] ?? null,
                $raw['text'] ?? null,
                $raw['data'] ?? null,
                $raw['value'] ?? null,
            ]);
            if ($nested !== '') {
                return $nested;
            }
        }
    }
    // Фолбэк: иногда MAX присылает только текст кнопки.
    $all = json_encode($callback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $src = is_string($all) ? mb_strtolower($all, 'UTF-8') : '';
    if ($src !== '') {
        if (str_contains($src, 'срок') && str_contains($src, 'выкл')) return '/settings due off';
        if (str_contains($src, 'срок') && str_contains($src, 'вкл')) return '/settings due on';
        if (str_contains($src, 'упоминан') && str_contains($src, 'выкл')) return '/settings mention off';
        if (str_contains($src, 'упоминан') && str_contains($src, 'вкл')) return '/settings mention on';
        if (str_contains($src, 'дайджест 60')) return '/settings digest 60';
        if (str_contains($src, 'дайджест') && str_contains($src, 'выкл')) return '/settings digest off';
        if (str_contains($src, 'тихий режим') && str_contains($src, 'выкл')) return '/settings quiet off';
        if (str_contains($src, 'тихий режим') && str_contains($src, 'вкл')) return '/settings quiet on';
    }
    return '';
}

/**
 * @param array<int, array<int, array{text:string,payload:string}>> $rows
 */
function planner_max_webhook_send_keyboard(string $chatId, string $text, array $rows, int $orgId = 0, string $messageId = ''): bool
{
    $chatId = trim($chatId);
    $text = trim($text);
    if ($chatId === '' || $text === '') {
        return false;
    }
    $runtime = planner_max_runtime_settings($orgId > 0 ? $orgId : null);
    $token = planner_max_normalize_token((string) ($runtime['token'] ?? ''));
    if ($token === '') {
        return false;
    }
    $buttons = [];
    foreach ($rows as $row) {
        $r = [];
        foreach ($row as $btn) {
            $label = trim((string) ($btn['text'] ?? ''));
            $payload = trim((string) ($btn['payload'] ?? ''));
            if ($label === '' || $payload === '') {
                continue;
            }
            $r[] = [
                'type' => 'callback',
                'text' => $label,
                'payload' => $payload,
            ];
        }
        if ($r !== []) {
            $buttons[] = $r;
        }
    }
    if ($buttons === []) {
        return false;
    }
    $url = 'https://platform-api.max.ru/messages?chat_id=' . rawurlencode($chatId);
    $payload = json_encode([
        'text' => $text,
        'notify' => true,
        'attachments' => [[
            'type' => 'inline_keyboard',
            'payload' => ['buttons' => $buttons],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $headers = [
        'Authorization: ' . $token,
        'Content-Type: application/json; charset=utf-8',
    ];
    if ($messageId !== '') {
        $headers[] = 'X-Idempotency-Key: ' . $messageId;
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) (($runtime['timeout'] ?? 12) ?: 12),
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            return true;
        }
        planner_max_log_failure($code, $err !== '' ? $err : (string) $raw);
        return false;
    }
    return false;
}

$secretFromQuery = trim((string) ($_GET['secret'] ?? ''));
$secretFromHeader = trim((string) ($_SERVER['HTTP_X_MAX_BOT_API_SECRET'] ?? ''));

if (effective_request_method() === 'GET') {
    json_response([
        'ok' => true,
        'status' => 'alive',
        'endpoint' => 'max_webhook',
        'hint' => 'Команды из чата обрабатываются только запросом POST с JSON (событие сообщения). GET — только проверка URL.',
    ]);
}

if (effective_request_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$secretProvided = $secretFromHeader !== '' ? $secretFromHeader : $secretFromQuery;
if (!planner_table_exists('max_settings') || !planner_table_has_column('max_settings', 'webhook_secret')) {
    planner_max_webhook_debug_log(['stage' => 'reject', 'reason' => 'webhook_schema_missing']);
    json_response(['ok' => false, 'error' => 'not_configured'], 503);
}
$stSec = db()->query(
    'SELECT organization_id, webhook_secret FROM max_settings
     WHERE webhook_secret IS NOT NULL AND TRIM(webhook_secret) <> \'\''
);
$secretRows = $stSec ? $stSec->fetchAll(PDO::FETCH_ASSOC) : [];
if ($secretRows === []) {
    planner_max_webhook_debug_log(['stage' => 'reject', 'reason' => 'webhook_secret_empty']);
    json_response(['ok' => false, 'error' => 'not_configured'], 503);
}
$secretMatched = false;
if ($secretProvided !== '') {
    foreach ($secretRows as $row) {
        $stored = trim((string) ($row['webhook_secret'] ?? ''));
        if ($stored !== '' && hash_equals($stored, $secretProvided)) {
            $secretMatched = true;
            break;
        }
    }
}
if (!$secretMatched) {
    $storedLens = [];
    foreach ($secretRows as $row) {
        $storedLens[] = strlen(trim((string) ($row['webhook_secret'] ?? '')));
    }
    planner_max_webhook_debug_log([
        'stage' => 'reject',
        'reason' => 'bad_secret',
        'has_secret_header' => $secretFromHeader !== '',
        'has_secret_query' => $secretFromQuery !== '',
        'stored_secrets' => count($secretRows),
        'stored_lens' => $storedLens,
        'provided_len' => strlen($secretProvided),
        'hint' => 'MAX и Planner должны использовать один secret — «Зарегистрировать webhook» в MAX мониторинге.',
    ]);
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

planner_max_webhook_debug_log([
    'stage' => 'request',
    'method' => 'POST',
    'has_secret_header' => $secretFromHeader !== '',
    'has_secret_query' => $secretFromQuery !== '',
]);

$body = read_json_body();
$message = is_array($body['message'] ?? null) ? $body['message'] : [];
$messageBody = is_array($message['body'] ?? null) ? $message['body'] : [];
$messageSender = is_array($message['sender'] ?? null) ? $message['sender'] : [];
$messageRecipient = is_array($message['recipient'] ?? null) ? $message['recipient'] : [];
$callback = is_array($body['callback'] ?? null) ? $body['callback'] : [];
$callbackUser = is_array($callback['user'] ?? null) ? $callback['user'] : [];
$callbackCommand = planner_max_webhook_extract_callback_command($callback);
if ($callbackCommand === '' && isset($body['callback']) && !is_array($body['callback'])) {
    $callbackCommand = trim((string) $body['callback']);
}
if ($callbackCommand === '') {
    $callbackCommand = trim((string) ($body['payload'] ?? ''));
}
$updateType = (string) ($body['update_type'] ?? '');
$isCallbackUpdate = planner_max_webhook_is_callback_update($updateType, $callbackCommand);
$senderUserId = planner_max_webhook_first_non_empty([
    $messageSender['user_id'] ?? null,
    $messageSender['id'] ?? null,
    $body['user_id'] ?? null,
    is_array($body['user'] ?? null) ? ($body['user']['user_id'] ?? $body['user']['id'] ?? null) : null,
]);
$callbackUserId = planner_max_webhook_first_non_empty([
    $callbackUser['user_id'] ?? null,
    $callbackUser['id'] ?? null,
]);
if ($isCallbackUpdate && $callbackUserId !== '') {
    $senderUserId = $callbackUserId;
}
$ctx = planner_max_webhook_resolve_context(
    $updateType,
    $body,
    $message,
    $messageRecipient,
    $senderUserId,
    $callbackUserId
);
$chatId = (string) $ctx['reply_chat_id'];
$webhookLookupIds = (array) $ctx['lookup_ids'];
$text = planner_max_webhook_first_non_empty([
    $isCallbackUpdate && $callbackCommand !== '' ? $callbackCommand : null,
    $callbackCommand !== '' ? $callbackCommand : null,
    $body['text'] ?? null,
    $body['command'] ?? null,
    $message['text'] ?? null,
    is_string($message['body'] ?? null) ? $message['body'] : null,
    $messageBody['text'] ?? null,
    $messageBody['markdown'] ?? null,
    $messageBody['plain'] ?? null,
    $messageBody['value'] ?? null,
    $body['update']['message']['text'] ?? null,
    $body['event']['message']['text'] ?? null,
]);
if ($text === '' && in_array($updateType, ['bot_started', 'user_added', 'bot_added'], true) && $chatId !== '') {
    $text = '/start';
}
if ($chatId === '' || $text === '') {
    planner_max_webhook_debug_log([
        'stage' => 'ignored',
        'reason' => 'empty',
        'body_keys' => array_keys($body),
        'update_type' => (string) ($body['update_type'] ?? ''),
        'message_keys' => array_keys($message),
        'message_body_keys' => array_keys($messageBody),
        'message_sender_keys' => array_keys($messageSender),
        'message_recipient_keys' => array_keys($messageRecipient),
        'parsed_chat_id' => $chatId,
        'parsed_text' => $text,
    ]);
    json_response(['ok' => true, 'ignored' => 'empty']);
}

$parsedPreview = planner_max_webhook_parse_command_text($text);
planner_max_webhook_debug_log([
    'stage' => 'parsed',
    'update_type' => $updateType,
    'chat_id' => $chatId,
    'recipient_chat_id' => (string) ($ctx['recipient_chat_id'] ?? ''),
    'chat_type' => (string) ($ctx['chat_type'] ?? ''),
    'sender_user_id' => $senderUserId,
    'lookup_ids' => $webhookLookupIds,
    'text' => function_exists('mb_substr') ? mb_substr($text, 0, 120, 'UTF-8') : substr($text, 0, 120),
    'cmd' => (string) ($parsedPreview['command'] ?? ''),
]);

$reply = static function (string $chatId, string $message, int $orgId = 0, string $messageId = ''): bool {
    try {
        planner_max_send_chat_message($chatId, $message, $orgId, $messageId);
        return true;
    } catch (Throwable $e) {
        planner_max_log_failure(0, 'max_webhook reply failed: ' . $e->getMessage());
        return false;
    }
};

$bound = planner_max_find_user_for_webhook($chatId, $senderUserId, $webhookLookupIds);
if (!$bound) {
    $hintId = $recipientChatId = (string) ($ctx['recipient_chat_id'] ?? '');
    if ($hintId === '') {
        $hintId = $chatId !== '' ? $chatId : $senderUserId;
    }
    $replyChat = $chatId !== '' ? $chatId : ($recipientChatId !== '' ? $recipientChatId : $senderUserId);
    $replyOrgId = 0;
    if (planner_table_exists('organizations')) {
        $replyOrgId = (int) (db()->query('SELECT id FROM organizations ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    }
    $unboundMsg = "👋 Привет! Я бот Planner.\n"
        . "Для личного чата в профиле укажите MAX chat_id диалога"
        . ($recipientChatId !== '' ? " (сейчас в webhook: {$recipientChatId})" : '')
        . ".\n"
        . ($senderUserId !== '' && $senderUserId !== $recipientChatId
            ? "user_id аккаунта ({$senderUserId}) — это не chat_id, для команд не подходит.\n"
            : '')
        . "Затем снова /help.";
    $replySent = $reply($replyChat, $unboundMsg, $replyOrgId, 'max-webhook-unbound');
    planner_max_webhook_ok('unbound', [
        'chat_id' => $replyChat,
        'reply_sent' => $replySent,
        'hint' => 'Привяжите max_chat_id=' . $hintId . ' в профиле Planner',
    ]);
}
$u = $bound['user'];
$chatId = (string) $bound['reply_chat_id'];
$orgId = (int) ($u['organization_id'] ?? 0);
$userId = (int) ($u['id'] ?? 0);
$name = trim((string) ($u['name'] ?? ''));
if ($callbackCommand !== '') {
    $text = $callbackCommand;
}
$parsedCmd = planner_max_webhook_parse_command_text($text);
$t = (string) $parsedCmd['text'];
$command = (string) $parsedCmd['command'];
$partsRaw = preg_split('/\s+/', trim($t)) ?: [];

if ($command === '/start') {
    $prefs = planner_max_user_prefs($orgId, $userId);
    $boundChat = trim((string) ($u['max_chat_id'] ?? ''));
    $msg = "👋 Привет, {$name}!\n"
        . "Связь с Planner активна.\n"
        . "Чат для команд: {$chatId}\n"
        . ($boundChat !== '' && $boundChat !== $chatId ? "В профиле указан другой chat_id ({$boundChat}) — для команд лучше указать {$chatId}.\n" : '')
        . "MAX уведомления: " . (!empty($prefs['enabled']) ? 'включены' : 'выключены') . "\n"
        . "Команды: /help, /my, /task <id>, /done <id>, /settings";
    $reply($chatId, $msg, $orgId, 'max-webhook-start');
    planner_max_webhook_ok('start', ['chat_id' => $chatId]);
}
if ($command === '/help') {
    $msg = "ℹ Команды Planner-бота\n"
        . "/start — статус привязки\n"
        . "/my — мои активные задачи\n"
        . "/task <id> — карточка задачи\n"
        . "/done <id> — отметить задачу выполненной\n"
        . "/settings — текущие настройки\n"
        . "/settings assigned|status|due|mention|created|participant|sla_due|sla_overdue|sla_stale on|off\n"
        . "/settings digest <минуты|off>\n"
        . "/settings quiet on|off\n"
        . "Пример: /settings due off";
    $reply($chatId, $msg, $orgId, 'max-webhook-help');
    planner_max_webhook_ok('help', ['chat_id' => $chatId]);
}

$statusLabel = static function (string $status): string {
    return match ($status) {
        'new' => 'Новая',
        'in_progress' => 'В работе',
        'review' => 'На проверке',
        'done' => 'Выполнена',
        'cancelled' => 'Отменена',
        default => $status,
    };
};

$shortDate = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 16, 'UTF-8');
    }
    return substr($value, 0, 16);
};

if ($command === '/my') {
    $st = db()->prepare(
        "SELECT id, title, status, due_date
         FROM tasks
         WHERE organization_id = ? AND assignee_id = ? AND status IN ('new','in_progress','review')
         ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, id DESC
         LIMIT 7"
    );
    $st->execute([$orgId, $userId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($items === []) {
        $reply($chatId, 'У вас нет активных задач 🎉', $orgId, 'max-webhook-my-empty');
        planner_max_webhook_ok('my_empty', ['chat_id' => $chatId]);
    }
    $lines = ["🗂 Ваши активные задачи:"];
    foreach ($items as $it) {
        $tid = (int) ($it['id'] ?? 0);
        $title = trim((string) ($it['title'] ?? ''));
        $status = $statusLabel((string) ($it['status'] ?? ''));
        $due = trim((string) ($it['due_date'] ?? ''));
        $dueText = $due !== '' ? (' · срок: ' . $shortDate($due)) : '';
        $lines[] = "#{$tid} {$title} · {$status}{$dueText}";
    }
    $lines[] = "Открыть: " . planner_url('/tasks.php');
    $reply($chatId, implode("\n", $lines), $orgId, 'max-webhook-my');
    planner_max_webhook_ok('my', ['chat_id' => $chatId]);
}

if ($command === '/task') {
    if (!preg_match('/^\/task(?:@\S+)?\s+(\d+)$/u', trim($t), $m)) {
        $reply($chatId, 'Формат: /task <id>', $orgId, 'max-webhook-task-bad');
        planner_max_webhook_ok('task_bad', ['chat_id' => $chatId]);
    }
    $taskId = (int) $m[1];
    if ($taskId <= 0 || !task_visible_to_user($u, $taskId)) {
        $reply($chatId, 'Задача не найдена или нет доступа.', $orgId, 'max-webhook-task-forbidden');
        planner_max_webhook_ok('task_forbidden', ['chat_id' => $chatId]);
    }
    $st = db()->prepare(
        'SELECT t.id, t.title, t.status, t.due_date,
                au.name AS author_name, su.name AS assignee_name
         FROM tasks t
         LEFT JOIN users au ON au.id = t.author_id AND au.organization_id = t.organization_id
         LEFT JOIN users su ON su.id = t.assignee_id AND su.organization_id = t.organization_id
         WHERE t.id = ? AND t.organization_id = ?
         LIMIT 1'
    );
    $st->execute([$taskId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($row === []) {
        $reply($chatId, 'Задача не найдена.', $orgId, 'max-webhook-task-not-found');
        planner_max_webhook_ok('task_not_found', ['chat_id' => $chatId]);
    }
    $title = trim((string) ($row['title'] ?? ''));
    $status = $statusLabel((string) ($row['status'] ?? ''));
    $due = trim((string) ($row['due_date'] ?? ''));
    $author = trim((string) ($row['author_name'] ?? '—'));
    $assignee = trim((string) ($row['assignee_name'] ?? '—'));
    $msg = "📌 Задача #{$taskId}\n"
        . "{$title}\n"
        . "Статус: {$status}\n"
        . "Постановщик: {$author}\n"
        . "Исполнитель: {$assignee}\n"
        . "Срок: " . ($due !== '' ? $shortDate($due) : 'не указан') . "\n"
        . "Открыть: " . planner_public_task_link($taskId);
    $reply($chatId, $msg, $orgId, 'max-webhook-task');
    planner_max_webhook_ok('task', ['chat_id' => $chatId]);
}

if ($command === '/done') {
    if (!preg_match('/^\/done(?:@\S+)?\s+(\d+)$/u', trim($t), $m)) {
        $reply($chatId, 'Формат: /done <id>', $orgId, 'max-webhook-done-bad');
        planner_max_webhook_ok('done_bad', ['chat_id' => $chatId]);
    }
    $taskId = (int) $m[1];
    if ($taskId <= 0 || !task_visible_to_user($u, $taskId)) {
        $reply($chatId, 'Задача не найдена или нет доступа.', $orgId, 'max-webhook-done-forbidden');
        planner_max_webhook_ok('done_forbidden', ['chat_id' => $chatId]);
    }
    if (!user_may_edit_task($u, $taskId)) {
        $reply($chatId, 'Недостаточно прав для изменения задачи.', $orgId, 'max-webhook-done-no-rights');
        planner_max_webhook_ok('done_no_rights', ['chat_id' => $chatId]);
    }
    $stTask = db()->prepare('SELECT title, status FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
    $stTask->execute([$taskId, $orgId]);
    $task = $stTask->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$task) {
        $reply($chatId, 'Задача не найдена.', $orgId, 'max-webhook-done-not-found');
        planner_max_webhook_ok('done_not_found', ['chat_id' => $chatId]);
    }
    $title = trim((string) ($task['title'] ?? ('Задача #' . $taskId)));
    $before = (string) ($task['status'] ?? '');
    if ($before === 'done') {
        $reply($chatId, "Задача #{$taskId} уже выполнена ✅", $orgId, 'max-webhook-done-already');
        planner_max_webhook_ok('done_already', ['chat_id' => $chatId]);
    }
    $fields = ['status = ?', 'updated_at = NOW()'];
    $params = ['done'];
    if (planner_table_has_column('tasks', 'status_changed_at')) {
        $fields[] = 'status_changed_at = NOW()';
    }
    $params[] = $taskId;
    $params[] = $orgId;
    $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ? LIMIT 1';
    db()->prepare($sql)->execute($params);
    $reply($chatId, "✅ Отметила выполненной: #{$taskId} {$title}", $orgId, 'max-webhook-done');
    planner_max_webhook_ok('done', ['chat_id' => $chatId]);
}

if ($command === '/settings') {
    $prefs = planner_max_user_prefs($orgId, $userId);
    $parts = preg_split('/\s+/', $t) ?: [];
    $orgDueHm = '18:00';
    if (planner_table_has_column('organizations', 'default_due_time')) {
        $stDt = db()->prepare('SELECT default_due_time FROM organizations WHERE id = ? LIMIT 1');
        $stDt->execute([$orgId]);
        $v = trim((string) ($stDt->fetchColumn() ?: ''));
        if ($v !== '' && planner_validate_org_due_time_hm($v)) {
            $orgDueHm = $v;
        }
    }
    $save = function (array $p) use ($orgId, $userId): void {
        if (!planner_table_exists('user_max_prefs')) {
            return;
        }
        $cols = [];
        foreach (array_keys($p) as $c) {
            if (planner_table_has_column('user_max_prefs', $c)) {
                $cols[] = $c;
            }
        }
        if ($cols === []) return;
        $insertCols = 'organization_id, user_id, ' . implode(', ', $cols);
        $ph = implode(', ', array_fill(0, 2 + count($cols), '?'));
        $upd = implode(', ', array_map(static fn ($c) => $c . '=VALUES(' . $c . ')', $cols));
        $params = [$orgId, $userId];
        foreach ($cols as $c) {
            $params[] = is_bool($p[$c]) ? ($p[$c] ? 1 : 0) : $p[$c];
        }
        db()->prepare("INSERT INTO user_max_prefs ($insertCols) VALUES ($ph) ON DUPLICATE KEY UPDATE $upd")->execute($params);
    };
    $sendSettingsMenu = static function (array $prefsState, ?string $notice = null) use ($chatId, $orgId, $reply, $u, $orgDueHm): void {
        $isOrgAdmin = user_may_manage_users($u);
        $eventsState = [];
        $eventsState[] = 'назначения:' . (!empty($prefsState['notify_task_assigned']) ? 'вкл' : 'выкл');
        $eventsState[] = 'статус:' . (!empty($prefsState['notify_task_status_changed']) ? 'вкл' : 'выкл');
        $eventsState[] = 'срок:' . (!empty($prefsState['notify_task_due_changed']) ? 'вкл' : 'выкл');
        $eventsState[] = 'упоминания:' . (!empty($prefsState['notify_task_comment_mention']) ? 'вкл' : 'выкл');
        $eventsState[] = 'создание:' . (!empty($prefsState['notify_task_created']) ? 'вкл' : 'выкл');
        $eventsState[] = 'участники:' . (!empty($prefsState['notify_task_participant_added']) ? 'вкл' : 'выкл');
        $eventsState[] = 'sla_due:' . (!empty($prefsState['notify_sla_due_2h']) ? 'вкл' : 'выкл');
        $eventsState[] = 'sla_overdue:' . (!empty($prefsState['notify_sla_overdue_24h']) ? 'вкл' : 'выкл');
        $eventsState[] = 'sla_stale:' . (!empty($prefsState['notify_sla_stale_days']) ? 'вкл' : 'выкл');

        $prefix = $notice ? ($notice . "\n\n") : '';
        $msg = $prefix
            . "⚙ Настройки уведомлений\n"
            . "┌ Базовые\n"
            . "├ Общие: " . (!empty($prefsState['enabled']) ? 'вкл' : 'выкл') . "\n"
            . "├ Дайджест: " . (!empty($prefsState['digest_enabled']) ? ((int) $prefsState['digest_interval_minutes'] . ' мин') : 'выкл') . "\n"
            . "└ Тихий режим: " . (!empty($prefsState['quiet_mode_enabled']) ? 'вкл' : 'выкл') . "\n\n"
            . "🔔 События\n"
            . implode(' · ', $eventsState) . "\n\n"
            . "⚡ Быстрые команды\n"
            . "• /settings due off\n"
            . "• /settings mention on\n"
            . "• /settings digest 60\n"
            . "• /settings digest off\n"
            . "• /settings quiet on\n"
            . ($isOrgAdmin
                ? "\n🏢 Организация (админ)\n"
                    . "├ Срок по умолчанию: {$orgDueHm}\n"
                    . "└ /settings due_time 18:00\n"
                : '')
            . "\nℹ Все команды: /help";

        $settingsKeyboardInner = static function () use ($prefsState): array {
            $onOff = static function (string $key) use ($prefsState): array {
                $isOn = !empty($prefsState[$key]);
                return [$isOn ? '✅ вкл' : 'вкл', !$isOn ? '✅ выкл' : 'выкл'];
            };
            [$dueOn, $dueOff] = $onOff('notify_task_due_changed');
            [$mentionOn, $mentionOff] = $onOff('notify_task_comment_mention');
            [$quietOn, $quietOff] = $onOff('quiet_mode_enabled');
            [$digOn, $digOff] = $onOff('digest_enabled');
            return [
                [
                    ['text' => "Срок {$dueOn}", 'payload' => '/settings due on'],
                    ['text' => "Срок {$dueOff}", 'payload' => '/settings due off'],
                ],
                [
                    ['text' => "Упоминания {$mentionOn}", 'payload' => '/settings mention on'],
                    ['text' => "Упоминания {$mentionOff}", 'payload' => '/settings mention off'],
                ],
                [
                    ['text' => "Дайджест 60", 'payload' => '/settings digest 60'],
                    ['text' => "Дайджест {$digOff}", 'payload' => '/settings digest off'],
                ],
                [
                    ['text' => "Тихий режим {$quietOn}", 'payload' => '/settings quiet on'],
                    ['text' => "Тихий режим {$quietOff}", 'payload' => '/settings quiet off'],
                ],
            ];
        };
        $sent = planner_max_webhook_send_keyboard($chatId, $msg, $settingsKeyboardInner(), $orgId, 'max-webhook-settings');
        if (!$sent) {
            $reply($chatId, $msg, $orgId, 'max-webhook-settings');
        }
    };

    if (count($parts) === 1) {
        $sendSettingsMenu($prefs);
        planner_max_webhook_ok('settings_show', ['chat_id' => $chatId]);
    }
    if (count($parts) >= 3 && $parts[1] === 'digest') {
        if ($parts[2] === 'off') {
            $prefs['digest_enabled'] = false;
        } else {
            $m = (int) $parts[2];
            $prefs['digest_enabled'] = $m > 0;
            $prefs['digest_interval_minutes'] = max(15, min(1440, $m > 0 ? $m : 60));
        }
        $save($prefs);
        $sendSettingsMenu($prefs, '✅ Дайджест обновлён');
        planner_max_webhook_ok('settings_digest', ['chat_id' => $chatId]);
    }
    if (count($parts) >= 3 && $parts[1] === 'quiet') {
        $prefs['quiet_mode_enabled'] = $parts[2] === 'on';
        $save($prefs);
        $sendSettingsMenu($prefs, '✅ Тихий режим обновлён');
        planner_max_webhook_ok('settings_quiet', ['chat_id' => $chatId]);
    }
    if (count($parts) >= 3 && $parts[1] === 'due_time') {
        if (!user_may_manage_users($u)) {
            $reply(
                $chatId,
                "⛔ Менять время срока организации может только администратор.\nТекущее значение: {$orgDueHm} (только просмотр).",
                $orgId,
                'max-webhook-due-time-forbidden'
            );
            planner_max_webhook_ok('settings_due_time_forbidden', ['chat_id' => $chatId]);
        }
        if (!planner_table_has_column('organizations', 'default_due_time')) {
            $reply($chatId, 'Нужна миграция БД: sql/migration_organization_default_due_time.sql', $orgId, 'max-webhook-due-time-schema');
            planner_max_webhook_ok('settings_due_time_schema', ['chat_id' => $chatId]);
        }
        $hm = $parts[2];
        if (!planner_validate_org_due_time_hm($hm)) {
            $reply(
                $chatId,
                "Неверное время. Пример: /settings due_time 18:00\nТекущее: {$orgDueHm}",
                $orgId,
                'max-webhook-due-time-bad'
            );
            planner_max_webhook_ok('settings_due_time_bad', ['chat_id' => $chatId]);
        }
        db()->prepare('UPDATE organizations SET default_due_time = ? WHERE id = ?')->execute([$hm, $orgId]);
        log_activity($orgId, $userId, 'update', 'organization', $orgId, 'MAX: время срока по умолчанию ' . $hm);
        $reply(
            $chatId,
            "✅ Время срока по умолчанию: {$hm}\n(импорт Excel и дата без времени в задаче)",
            $orgId,
            'max-webhook-due-time-ok'
        );
        planner_max_webhook_ok('settings_due_time', ['chat_id' => $chatId, 'due_time' => $hm]);
    }
    if (count($parts) >= 3) {
        $map = [
            'assigned' => 'notify_task_assigned',
            'status' => 'notify_task_status_changed',
            'due' => 'notify_task_due_changed',
            'mention' => 'notify_task_comment_mention',
            'created' => 'notify_task_created',
            'participant' => 'notify_task_participant_added',
            'sla_due' => 'notify_sla_due_2h',
            'sla_overdue' => 'notify_sla_overdue_24h',
            'sla_stale' => 'notify_sla_stale_days',
        ];
        $key = $map[$parts[1]] ?? '';
        if ($key !== '') {
            $prefs[$key] = $parts[2] === 'on';
            $save($prefs);
            $sendSettingsMenu($prefs, '✅ Настройка обновлена: ' . $parts[1]);
            planner_max_webhook_ok('settings_toggle', ['chat_id' => $chatId]);
        }
    }
    $reply(
        $chatId,
        "Неверная команда /settings.\nПримеры: /settings due off · /settings digest 60 · /settings quiet on · /settings due_time 18:00",
        $orgId,
        'max-webhook-settings-bad'
    );
    planner_max_webhook_ok('settings_bad', ['chat_id' => $chatId]);
}

$reply($chatId, "Не понял команду. Напишите /help", $orgId, 'max-webhook-unknown');
planner_max_webhook_ok('unknown', [
    'chat_id' => $chatId,
    'command' => $command,
    'text' => $text,
]);
