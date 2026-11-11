<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !planner_table_exists('max_action_tokens')) {
    http_response_code(400);
    echo 'Некорректная ссылка';
    exit;
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
    http_response_code(404);
    echo 'Ссылка не найдена';
    exit;
}
if (!empty($row['used_at'])) {
    echo 'Ссылка уже использована.';
    exit;
}
if (strtotime((string) $row['expires_at']) < time()) {
    echo 'Срок действия ссылки истёк.';
    exit;
}
$orgId = (int) ($row['organization_id'] ?? 0);
$taskId = (int) ($row['task_id'] ?? 0);
$userId = (int) ($row['user_id'] ?? 0);
$action = trim((string) ($row['action'] ?? ''));

if ($action === 'open') {
    db()->prepare('UPDATE max_action_tokens SET used_at = NOW() WHERE token = ?')->execute([$token]);
    header('Location: ' . planner_url('/tasks.php?open=' . $taskId), true, 302);
    exit;
}
if ($action === 'take' || $action === 'done') {
    $status = $action === 'take' ? 'in_progress' : 'done';
    db()->prepare(
        'UPDATE tasks SET status = ?, assignee_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?'
    )->execute([$status, $userId, $taskId, $orgId]);
    db()->prepare('UPDATE max_action_tokens SET used_at = NOW() WHERE token = ?')->execute([$token]);
    echo $action === 'take' ? 'Статус задачи обновлён: В работе.' : 'Статус задачи обновлён: Выполнена.';
    exit;
}
echo 'Неизвестное действие.';
