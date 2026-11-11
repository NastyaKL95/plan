<?php
declare(strict_types=1);

/**
 * Пользователи, которым отправляем уведомление о новом комментарии (автор задачи, исполнитель, участники).
 *
 * @return int[]
 */
function planner_task_notification_recipients(int $orgId, int $taskId, int $excludeUserId): array
{
    $ids = [];
    $st = db()->prepare('SELECT author_id, assignee_id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$taskId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [];
    }
    foreach (['author_id', 'assignee_id'] as $col) {
        $v = isset($row[$col]) ? (int) $row[$col] : 0;
        if ($v > 0 && $v !== $excludeUserId) {
            $ids[$v] = true;
        }
    }
    if (planner_table_exists('task_participants')) {
        $st = db()->prepare(
            'SELECT user_id FROM task_participants WHERE task_id = ? AND user_id <> ?'
        );
        $st->execute([$taskId, $excludeUserId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $uid = (int) $r['user_id'];
            if ($uid > 0) {
                $ids[$uid] = true;
            }
        }
    }
    unset($ids[$excludeUserId]);

    return array_keys($ids);
}

function planner_notify_new_comment(
    int $orgId,
    int $actorUserId,
    int $taskId,
    int $commentId,
    string $taskTitle,
    string $bodyPreview
): void {
    if (!planner_table_exists('notifications')) {
        return;
    }
    $recipients = planner_task_notification_recipients($orgId, $taskId, $actorUserId);
    if ($recipients === []) {
        return;
    }
    $t = trim($taskTitle);
    if ($t === '') {
        $t = 'Задача #' . $taskId;
    }
    if (function_exists('mb_strlen') && mb_strlen($t, 'UTF-8') > 160) {
        $t = mb_substr($t, 0, 160, 'UTF-8') . '…';
    } elseif (strlen($t) > 160) {
        $t = substr($t, 0, 160) . '…';
    }
    $titleSingle = 'Комментарий к задаче: ' . $t;
    $ins = db()->prepare(
        'INSERT INTO notifications (organization_id, user_id, kind, task_id, comment_id, title, body)
         VALUES (?,?,?,?,?,?,?)'
    );
    $findBatch = db()->prepare(
        'SELECT id, title FROM notifications
         WHERE user_id = ? AND organization_id = ? AND kind = ? AND task_id = ?
           AND read_at IS NULL
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         ORDER BY id DESC LIMIT 1'
    );
    $updBatch = db()->prepare(
        'UPDATE notifications SET title = ?, body = ?, comment_id = ?, created_at = CURRENT_TIMESTAMP
         WHERE id = ? AND organization_id = ?'
    );
    foreach ($recipients as $uid) {
        $chk = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
        $chk->execute([$uid, $orgId]);
        if (!$chk->fetch()) {
            continue;
        }
        $findBatch->execute([$uid, $orgId, 'comment', $taskId]);
        $existing = $findBatch->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $n = 2;
            $prevTitle = (string) ($existing['title'] ?? '');
            if (preg_match('/Комментарии\s*\((\d+)\)\s*к\s*задаче\s*:/u', $prevTitle, $m)) {
                $n = (int) $m[1] + 1;
            }
            $mergedTitle = 'Комментарии (' . $n . ') к задаче: ' . $t;
            $updBatch->execute([
                $mergedTitle,
                $bodyPreview !== '' ? $bodyPreview : null,
                $commentId,
                (int) $existing['id'],
                $orgId,
            ]);
            continue;
        }
        $ins->execute([$orgId, $uid, 'comment', $taskId, $commentId, $titleSingle, $bodyPreview !== '' ? $bodyPreview : null]);
    }
}

function planner_notify_task_assignee_email(int $orgId, int $taskId, int $assigneeId, int $actorUserId): void
{
    if ($assigneeId <= 0 || $assigneeId === $actorUserId) {
        return;
    }
    if (!planner_mail_enabled()) {
        return;
    }
    $stUser = db()->prepare('SELECT email, name FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $stUser->execute([$assigneeId, $orgId]);
    $assignee = $stUser->fetch(PDO::FETCH_ASSOC);
    if (!$assignee) {
        return;
    }
    $to = trim((string) ($assignee['email'] ?? ''));
    if ($to === '' || strpos($to, '@') === false) {
        return;
    }

    $stTask = db()->prepare(
        'SELECT t.title, p.name AS project_name
         FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.id = ? AND t.organization_id = ? LIMIT 1'
    );
    $stTask->execute([$taskId, $orgId]);
    $task = $stTask->fetch(PDO::FETCH_ASSOC) ?: [];
    $taskTitle = trim((string) ($task['title'] ?? ''));
    if ($taskTitle === '') {
        $taskTitle = 'Задача #' . $taskId;
    }
    $projectName = trim((string) ($task['project_name'] ?? ''));

    $stActor = db()->prepare('SELECT name FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
    $stActor->execute([$actorUserId, $orgId]);
    $actorName = trim((string) ($stActor->fetchColumn() ?: 'Пользователь'));

    $subject = 'Вам назначена задача: ' . $taskTitle;
    $body = "Здравствуйте, " . trim((string) ($assignee['name'] ?? '')) . "!\n\n"
        . "Вам назначена задача.\n"
        . "Название: " . $taskTitle . "\n"
        . ($projectName !== '' ? ("Проект: " . $projectName . "\n") : '')
        . "Назначил: " . $actorName . "\n"
        . "Ссылка: " . planner_public_site_url('/tasks.php?open=' . $taskId) . "\n\n"
        . "Это автоматическое уведомление Planner.";

    if (planner_mail_queue_push($orgId, $to, $subject, $body, [
        'kind' => 'task_assignee',
        'task_id' => $taskId,
        'assignee_id' => $assigneeId,
        'actor_user_id' => $actorUserId,
    ])) {
        planner_mail_queue_kick_async(2);
        return;
    }
    // Фолбэк: если очередь недоступна/ошибка вставки, оставляем отложенную отправку.
    planner_send_mail_deferred($to, $subject, $body);
}
