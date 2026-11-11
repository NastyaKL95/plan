<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$hasNotif = planner_table_exists('notifications');
$scope = planner_department_scope_for_projects($user, 'p');
$hasProjectDept = planner_table_has_column('projects', 'department_id');
/** @param array<int,mixed> $params */
function planner_notifications_exec(PDOStatement $st, array $params, string $ctx): void
{
    planner_timed_execute($st, $params, $st->queryString ?? '', $ctx);
}

function planner_due_alert_rows(PDO $pdo, int $orgId, int $userId, array $scope, bool $hasProjectDept): array
{
    $tw = planner_scope_org_work_year_task($orgId, 't');
    if (!$hasProjectDept) {
        $sql = "SELECT t.id, t.title, t.due_date
                FROM tasks t
                WHERE t.organization_id = ?
                  AND t.assignee_id = ?
                  AND t.status NOT IN ('done','cancelled')
                  AND t.due_date IS NOT NULL
                  AND (t.due_date < NOW() OR t.due_date <= DATE_ADD(NOW(), INTERVAL 72 HOUR))"
            . $tw['sql'] . '
                ORDER BY t.due_date ASC
                LIMIT 40';
        $st = $pdo->prepare($sql);
        planner_notifications_exec($st, array_merge([$orgId, $userId], $tw['params']), 'notifications.due_alerts');
    } else {
        $sql = "SELECT t.id, t.title, t.due_date
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                WHERE t.organization_id = ?
                  AND t.assignee_id = ?
                  AND t.status NOT IN ('done','cancelled')
                  AND t.due_date IS NOT NULL
                  AND (t.due_date < NOW() OR t.due_date <= DATE_ADD(NOW(), INTERVAL 72 HOUR))"
            . $scope['sql'] . $tw['sql'] . '
                ORDER BY t.due_date ASC
                LIMIT 40';
        $st = $pdo->prepare($sql);
        planner_notifications_exec($st, array_merge([$orgId, $userId], $scope['params'], $tw['params']), 'notifications.due_alerts');
    }
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    $now = time();
    foreach ($rows as $r) {
        $dueMs = strtotime((string) ($r['due_date'] ?? ''));
        if ($dueMs === false) {
            continue;
        }
        $level = $dueMs < $now ? 'overdue' : 'soon';
        $out[] = [
            'task_id' => (int) $r['id'],
            'title' => (string) $r['title'],
            'due_date' => (string) $r['due_date'],
            'level' => $level,
        ];
    }

    return $out;
}

$method = effective_request_method();

if ($method === 'GET' && isset($_GET['badge_only']) && $_GET['badge_only'] === '1') {
    $dueAlerts = planner_due_alert_rows(db(), $orgId, $userId, $scope, $hasProjectDept);
    $overdueN = 0;
    $soonN = 0;
    foreach ($dueAlerts as $a) {
        if ($a['level'] === 'overdue') {
            $overdueN++;
        } else {
            $soonN++;
        }
    }
    $unread = 0;
    if ($hasNotif) {
        $st = db()->prepare(
            'SELECT COUNT(*) AS c FROM notifications
             WHERE user_id = ? AND organization_id = ? AND read_at IS NULL'
        );
        planner_notifications_exec($st, [$userId, $orgId], 'notifications.unread_count');
        $unread = (int) (($st->fetch(PDO::FETCH_ASSOC) ?: [])['c'] ?? 0);
    }
    json_response([
        'ok' => true,
        'badge_only' => true,
        'unread_comment_count' => $unread,
        'overdue_assignee_count' => $overdueN,
        'due_soon_assignee_count' => $soonN,
        'badge_total' => $unread + $overdueN,
    ]);
}

if ($method === 'GET') {
    $dueAlerts = planner_due_alert_rows(db(), $orgId, $userId, $scope, $hasProjectDept);
    $overdueN = 0;
    $soonN = 0;
    foreach ($dueAlerts as $a) {
        if ($a['level'] === 'overdue') {
            $overdueN++;
        } else {
            $soonN++;
        }
    }

    $items = [];
    $unread = 0;
    if ($hasNotif) {
        $st = db()->prepare(
            'SELECT id, kind, task_id, comment_id, title, body, read_at, created_at
             FROM notifications
             WHERE user_id = ? AND organization_id = ?
             ORDER BY created_at DESC
             LIMIT 80'
        );
        planner_notifications_exec($st, [$userId, $orgId], 'notifications.list');
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$it) {
            $it['id'] = (int) $it['id'];
            $it['task_id'] = isset($it['task_id']) ? (int) $it['task_id'] : null;
            $read = $it['read_at'] !== null && $it['read_at'] !== '';
            $it['read'] = $read;
            if (!$read) {
                $unread++;
            }
        }
        unset($it);
    }

    json_response([
        'ok' => true,
        'items' => $items,
        'due_alerts' => $dueAlerts,
        'unread_comment_count' => $unread,
        'overdue_assignee_count' => $overdueN,
        'due_soon_assignee_count' => $soonN,
        'badge_total' => $unread + $overdueN,
    ]);
}

if ($method === 'PATCH' || $method === 'POST') {
    if (!$hasNotif) {
        json_response(['ok' => false, 'error' => 'not_configured'], 501);
    }
    $body = read_json_body();
    if (!empty($body['mark_all'])) {
        db()->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND organization_id = ? AND read_at IS NULL'
        )->execute([$userId, $orgId]);
        json_response(['ok' => true]);
    }
    $nid = isset($body['id']) ? (int) $body['id'] : 0;
    if ($nid <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $st = db()->prepare(
        'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND organization_id = ?'
    );
    $st->execute([$nid, $userId, $orgId]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
