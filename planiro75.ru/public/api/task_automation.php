<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

function planner_automation_manage_allowed(array $user): bool
{
    return in_array($user['role'] ?? '', ['admin', 'owner'], true);
}

$method = effective_request_method();

if ($method === 'GET') {
    if (!planner_automation_manage_allowed($user)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    if (!planner_table_exists('task_automation_rules')) {
        json_response(['ok' => true, 'items' => []]);
    }
    $st = db()->prepare(
        'SELECT r.id, r.project_id, r.trigger_status, r.action_assignee_id, r.action_comment,
                r.sort_order, r.enabled, p.name AS project_name,
                ua.name AS action_assignee_name
         FROM task_automation_rules r
         LEFT JOIN projects p ON p.id = r.project_id AND p.organization_id = r.organization_id
         LEFT JOIN users ua ON ua.id = r.action_assignee_id
         WHERE r.organization_id = ?
         ORDER BY r.sort_order ASC, r.id ASC'
    );
    $st->execute([$orgId]);
    json_response(['ok' => true, 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if (!planner_automation_manage_allowed($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

if (!planner_table_exists('task_automation_rules')) {
    json_response(['ok' => false, 'error' => 'not_configured'], 503);
}

if ($method === 'POST') {
    $body = read_json_body();
    $trigger = (string) ($body['trigger_status'] ?? '');
    if (!in_array($trigger, ['new', 'in_progress', 'review', 'done', 'cancelled'], true)) {
        json_response(['ok' => false, 'error' => 'bad_trigger_status'], 422);
    }
    $projectId = null;
    if (isset($body['project_id']) && $body['project_id'] !== '' && $body['project_id'] !== null) {
        $projectId = (int) $body['project_id'];
        if ($projectId <= 0) {
            json_response(['ok' => false, 'error' => 'bad_project'], 422);
        }
        if (planner_table_has_column('projects', 'work_year')) {
            $chk = db()->prepare(
                'SELECT id FROM projects WHERE id = ? AND organization_id = ? AND work_year = ? LIMIT 1'
            );
            $chk->execute([$projectId, $orgId, planner_org_work_year_for_org($orgId)]);
        } else {
            $chk = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
            $chk->execute([$projectId, $orgId]);
        }
        if (!$chk->fetch()) {
            json_response(['ok' => false, 'error' => 'bad_project'], 422);
        }
    }
    $aid = null;
    if (isset($body['action_assignee_id']) && $body['action_assignee_id'] !== '' && $body['action_assignee_id'] !== null) {
        $aid = (int) $body['action_assignee_id'];
        if ($aid <= 0) {
            json_response(['ok' => false, 'error' => 'bad_assignee'], 422);
        }
        $chk = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
        $chk->execute([$aid, $orgId]);
        if (!$chk->fetch()) {
            json_response(['ok' => false, 'error' => 'bad_assignee'], 422);
        }
        if (planner_user_is_blocked_id($orgId, $aid)) {
            json_response(['ok' => false, 'error' => 'user_blocked'], 422);
        }
    }
    $comment = trim((string) ($body['action_comment'] ?? ''));
    $comment = $comment !== ''
        ? (function_exists('mb_substr') ? mb_substr($comment, 0, 8000, 'UTF-8') : substr($comment, 0, 8000))
        : null;
    if ($aid === null && ($comment === null || $comment === '')) {
        json_response(['ok' => false, 'error' => 'need_action'], 422);
    }
    $sortOrder = isset($body['sort_order']) ? max(0, (int) $body['sort_order']) : 0;
    $enabled = isset($body['enabled']) ? ((bool) $body['enabled']) : true;

    $st = db()->prepare(
        'INSERT INTO task_automation_rules
         (organization_id, project_id, trigger_status, action_assignee_id, action_comment, sort_order, enabled)
         VALUES (?,?,?,?,?,?,?)'
    );
    $st->execute([
        $orgId,
        $projectId,
        $trigger,
        $aid,
        $comment,
        $sortOrder,
        $enabled ? 1 : 0,
    ]);
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($method === 'PATCH' || $method === 'PUT') {
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $chk = db()->prepare('SELECT id FROM task_automation_rules WHERE id = ? AND organization_id = ? LIMIT 1');
    $chk->execute([$id, $orgId]);
    if (!$chk->fetch()) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    $body = read_json_body();
    $parts = [];
    $params = [];
    if (array_key_exists('trigger_status', $body)) {
        $t = (string) $body['trigger_status'];
        if (!in_array($t, ['new', 'in_progress', 'review', 'done', 'cancelled'], true)) {
            json_response(['ok' => false, 'error' => 'bad_trigger_status'], 422);
        }
        $parts[] = 'trigger_status = ?';
        $params[] = $t;
    }
    if (array_key_exists('project_id', $body)) {
        $pv = $body['project_id'];
        if ($pv === null || $pv === '') {
            $parts[] = 'project_id = NULL';
        } else {
            $pid = (int) $pv;
            if ($pid <= 0) {
                json_response(['ok' => false, 'error' => 'bad_project'], 422);
            }
            if (planner_table_has_column('projects', 'work_year')) {
                $pchk = db()->prepare(
                    'SELECT id FROM projects WHERE id = ? AND organization_id = ? AND work_year = ? LIMIT 1'
                );
                $pchk->execute([$pid, $orgId, planner_org_work_year_for_org($orgId)]);
            } else {
                $pchk = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
                $pchk->execute([$pid, $orgId]);
            }
            if (!$pchk->fetch()) {
                json_response(['ok' => false, 'error' => 'bad_project'], 422);
            }
            $parts[] = 'project_id = ?';
            $params[] = $pid;
        }
    }
    if (array_key_exists('action_assignee_id', $body)) {
        $pv = $body['action_assignee_id'];
        if ($pv === null || $pv === '') {
            $parts[] = 'action_assignee_id = NULL';
        } else {
            $aid = (int) $pv;
            if ($aid <= 0) {
                json_response(['ok' => false, 'error' => 'bad_assignee'], 422);
            }
            $uchk = db()->prepare('SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1');
            $uchk->execute([$aid, $orgId]);
            if (!$uchk->fetch()) {
                json_response(['ok' => false, 'error' => 'bad_assignee'], 422);
            }
            if (planner_user_is_blocked_id($orgId, $aid)) {
                json_response(['ok' => false, 'error' => 'user_blocked'], 422);
            }
            $parts[] = 'action_assignee_id = ?';
            $params[] = $aid;
        }
    }
    if (array_key_exists('action_comment', $body)) {
        $comment = trim((string) $body['action_comment']);
        $comment = $comment !== ''
            ? (function_exists('mb_substr') ? mb_substr($comment, 0, 8000, 'UTF-8') : substr($comment, 0, 8000))
            : null;
        $parts[] = 'action_comment = ?';
        $params[] = $comment;
    }
    if (array_key_exists('sort_order', $body)) {
        $parts[] = 'sort_order = ?';
        $params[] = max(0, (int) $body['sort_order']);
    }
    if (array_key_exists('enabled', $body)) {
        $parts[] = 'enabled = ?';
        $params[] = $body['enabled'] ? 1 : 0;
    }
    if ($parts === []) {
        json_response(['ok' => false, 'error' => 'no_fields'], 422);
    }
    $params[] = $id;
    $params[] = $orgId;
    $sql = 'UPDATE task_automation_rules SET ' . implode(', ', $parts) . ' WHERE id = ? AND organization_id = ?';
    db()->prepare($sql)->execute($params);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    $st = db()->prepare('DELETE FROM task_automation_rules WHERE id = ? AND organization_id = ?');
    $st->execute([$id, $orgId]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
