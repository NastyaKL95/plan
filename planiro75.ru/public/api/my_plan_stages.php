<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/my_plan_stages.php';

$method = effective_request_method();

if (!planner_my_plan_tables_ready()) {
    json_response(['ok' => true, 'items' => [], 'feature' => false]);
}

planner_my_plan_ensure_user_stages($orgId, $userId);
planner_my_plan_migrate_legacy_for_user($orgId, $userId);

if ($method === 'GET') {
    json_response(['ok' => true, 'items' => planner_my_plan_stages_for_user($orgId, $userId), 'feature' => true]);
}

if ($method === 'POST') {
    $body = read_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'name_required'], 422);
    }
    if (function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > 120) {
        $name = mb_substr($name, 0, 120, 'UTF-8');
    } elseif (strlen($name) > 120) {
        $name = substr($name, 0, 120);
    }
    $color = planner_my_plan_normalize_color($body['color'] ?? '');
    $st = db()->prepare(
        'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM user_my_plan_stages WHERE organization_id = ? AND user_id = ?'
    );
    $st->execute([$orgId, $userId]);
    $sort = (int) $st->fetchColumn();
    db()->prepare(
        'INSERT INTO user_my_plan_stages (organization_id, user_id, name, color, sort_order) VALUES (?,?,?,?,?)'
    )->execute([$orgId, $userId, $name, $color, $sort]);
    $newId = (int) db()->lastInsertId();
    json_response(['ok' => true, 'id' => $newId]);
}

if ($method === 'PATCH') {
    $body = read_json_body();
    if (isset($body['reorder']) && is_array($body['reorder'])) {
        $ids = array_values(array_filter(array_map('intval', $body['reorder']), static fn ($v) => $v > 0));
        if ($ids === []) {
            json_response(['ok' => false, 'error' => 'bad_reorder'], 422);
        }
        $st = db()->prepare(
            'SELECT id FROM user_my_plan_stages WHERE organization_id = ? AND user_id = ? AND id IN (' .
            implode(',', array_fill(0, count($ids), '?')) . ')'
        );
        $st->execute(array_merge([$orgId, $userId], $ids));
        $found = array_map(static fn ($r) => (int) $r['id'], $st->fetchAll(PDO::FETCH_ASSOC));
        if (count($found) !== count($ids)) {
            json_response(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $upd = db()->prepare('UPDATE user_my_plan_stages SET sort_order = ? WHERE id = ? AND organization_id = ? AND user_id = ?');
        $ord = 0;
        foreach ($ids as $sid) {
            $upd->execute([$ord++, $sid, $orgId, $userId]);
        }
        json_response(['ok' => true]);
    }
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    if (!planner_my_plan_stage_owned_by_user($orgId, $userId, $id)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $name = isset($body['name']) ? trim((string) $body['name']) : null;
    if ($name !== null) {
        if ($name === '') {
            json_response(['ok' => false, 'error' => 'name_required'], 422);
        }
        if (function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > 120) {
            $name = mb_substr($name, 0, 120, 'UTF-8');
        } elseif (strlen($name) > 120) {
            $name = substr($name, 0, 120);
        }
        db()->prepare(
            'UPDATE user_my_plan_stages SET name = ? WHERE id = ? AND organization_id = ? AND user_id = ?'
        )->execute([$name, $id, $orgId, $userId]);
    }
    if (array_key_exists('color', $body)) {
        $color = planner_my_plan_normalize_color($body['color']);
        db()->prepare(
            'UPDATE user_my_plan_stages SET color = ? WHERE id = ? AND organization_id = ? AND user_id = ?'
        )->execute([$color, $id, $orgId, $userId]);
    }
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'bad_id'], 422);
    }
    if (!planner_my_plan_stage_owned_by_user($orgId, $userId, $id)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $st = db()->prepare('SELECT COUNT(*) FROM user_my_plan_stages WHERE organization_id = ? AND user_id = ?');
    $st->execute([$orgId, $userId]);
    if ((int) $st->fetchColumn() <= 1) {
        json_response(['ok' => false, 'error' => 'last_stage'], 422);
    }
    $stages = planner_my_plan_stages_for_user($orgId, $userId);
    $fallback = 0;
    foreach ($stages as $s) {
        if ($s['id'] !== $id) {
            $fallback = $s['id'];
            break;
        }
    }
    if ($fallback <= 0) {
        json_response(['ok' => false, 'error' => 'internal'], 500);
    }
    db()->prepare(
        'UPDATE task_my_plan_placement SET stage_id = ? WHERE stage_id = ? AND organization_id = ? AND user_id = ?'
    )->execute([$fallback, $id, $orgId, $userId]);
    db()->prepare(
        'DELETE FROM user_my_plan_stages WHERE id = ? AND organization_id = ? AND user_id = ?'
    )->execute([$id, $orgId, $userId]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
