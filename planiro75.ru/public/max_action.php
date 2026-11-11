<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = trim((string) ($_REQUEST['token'] ?? ''));
if ($token === '' || !planner_table_exists('max_action_tokens')) {
    planner_max_action_page('Некорректная ссылка', 'Ссылка не содержит кода действия.', 400);
}

$st = db()->prepare(
    'SELECT token, organization_id, user_id, task_id, action, expires_at, used_at
     FROM max_action_tokens
     WHERE token = ?
     LIMIT 1'
);
$st->execute([$token]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    planner_max_action_page('Ссылка не найдена', 'Такого действия нет или ссылка уже недействительна.', 404);
}
if (!empty($row['used_at'])) {
    planner_max_action_page('Ссылка уже использована', 'Это действие уже выполнено.');
}
if (strtotime((string) $row['expires_at']) < time()) {
    planner_max_action_page('Срок ссылки истёк', 'Откройте задачу в планировщике и измените статус там.');
}

$orgId = (int) ($row['organization_id'] ?? 0);
$taskId = (int) ($row['task_id'] ?? 0);
$userId = (int) ($row['user_id'] ?? 0);
$action = trim((string) ($row['action'] ?? ''));

if ($action === 'open') {
    db()->prepare('UPDATE max_action_tokens SET used_at = NOW() WHERE token = ? AND used_at IS NULL')->execute([$token]);
    header('Location: ' . planner_url('/tasks.php?open=' . $taskId), true, 302);
    exit;
}

if ($action !== 'take' && $action !== 'done') {
    planner_max_action_page('Неизвестное действие', 'Эта ссылка не поддерживается.');
}

$taskSt = db()->prepare('SELECT title, status FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
$taskSt->execute([$taskId, $orgId]);
$task = $taskSt->fetch(PDO::FETCH_ASSOC);
if (!$task) {
    db()->prepare('UPDATE max_action_tokens SET used_at = NOW() WHERE token = ? AND used_at IS NULL')->execute([$token]);
    planner_max_action_page('Задача не найдена', 'Задача удалена или относится к другой организации.');
}

$title = trim((string) ($task['title'] ?? ''));
if ($title === '') {
    $title = 'Задача #' . $taskId;
}
$question = $action === 'take'
    ? 'Взять задачу в работу?'
    : 'Отметить задачу выполненной?';
$doneText = $action === 'take'
    ? 'Статус задачи обновлён: в работе.'
    : 'Статус задачи обновлён: выполнена.';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST' && (string) ($_POST['confirm'] ?? '') === '1') {
    $status = $action === 'take' ? 'in_progress' : 'done';
    db()->prepare(
        'UPDATE tasks SET status = ?, assignee_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?'
    )->execute([$status, $userId, $taskId, $orgId]);
    db()->prepare('UPDATE max_action_tokens SET used_at = NOW() WHERE token = ? AND used_at IS NULL')->execute([$token]);
    planner_max_action_page('Готово', $doneText . ' «' . $title . '».');
}

$safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
$safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
$form = '<p class="muted">«' . $safeTitle . '»</p>'
    . '<form method="post" action="' . htmlspecialchars(planner_url('/max_action.php'), ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="token" value="' . $safeToken . '">'
    . '<input type="hidden" name="confirm" value="1">'
    . '<button type="submit" class="btn btn-primary">Подтвердить</button>'
    . '</form>'
    . '<p class="muted">Пока кнопка не нажата, статус задачи не меняется.</p>';
planner_max_action_page($question, $form, 200, true);

/**
 * @param bool $messageIsHtml
 */
function planner_max_action_page(string $title, string $message, int $status = 200, bool $messageIsHtml = false): never
{
    http_response_code($status);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $body = $messageIsHtml ? $message : '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    $css = htmlspecialchars(planner_url('/assets/css/app.css'), ENT_QUOTES, 'UTF-8');
    $app = htmlspecialchars((string) (app_config()['app']['name'] ?? 'Planner'), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . $safeTitle . ' — ' . $app . '</title>'
        . '<link rel="stylesheet" href="' . $css . '">'
        . '</head><body class="app-body">'
        . '<main style="max-width:520px;margin:48px auto;padding:0 16px">'
        . '<section class="panel"><h1 class="panel-title">' . $safeTitle . '</h1>'
        . $body
        . '</section></main></body></html>';
    exit;
}
