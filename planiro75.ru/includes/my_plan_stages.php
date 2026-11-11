<?php
declare(strict_types=1);

/**
 * Персональные стадии «Мой план» и размещение задач (по пользователю).
 * @see sql/migration_my_plan_stages_v1.sql
 */
function planner_my_plan_tables_ready(): bool
{
    return planner_table_exists('user_my_plan_stages') && planner_table_exists('task_my_plan_placement');
}

function planner_my_plan_normalize_color(mixed $raw): string
{
    $s = trim((string) $raw);
    if ($s === '') {
        return '#64748b';
    }
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $s) === 1) {
        return $s;
    }
    return '#64748b';
}

/** @return list<array{id:int,name:string,color:string,sort_order:int}> */
function planner_my_plan_stages_for_user(int $orgId, int $userId): array
{
    $st = db()->prepare(
        'SELECT id, name, color, sort_order FROM user_my_plan_stages
         WHERE organization_id = ? AND user_id = ?
         ORDER BY sort_order ASC, id ASC'
    );
    $st->execute([$orgId, $userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int) ($r['id'] ?? 0),
            'name' => (string) ($r['name'] ?? ''),
            'color' => planner_my_plan_normalize_color($r['color'] ?? ''),
            'sort_order' => (int) ($r['sort_order'] ?? 0),
        ];
    }
    return $out;
}

function planner_my_plan_ensure_user_stages(int $orgId, int $userId): void
{
    $st = db()->prepare('SELECT COUNT(*) FROM user_my_plan_stages WHERE organization_id = ? AND user_id = ?');
    $st->execute([$orgId, $userId]);
    $n = (int) $st->fetchColumn();
    if ($n > 0) {
        return;
    }
    $defaults = [
        ['Не спланированы', '#3b82f6', 0],
        ['Сделаю на этой неделе', '#84cc16', 1],
        ['Сделаю на следующей неделе', '#22d3ee', 2],
    ];
    $ins = db()->prepare(
        'INSERT INTO user_my_plan_stages (organization_id, user_id, name, color, sort_order) VALUES (?,?,?,?,?)'
    );
    foreach ($defaults as $d) {
        $ins->execute([$orgId, $userId, $d[0], $d[1], $d[2]]);
    }
}

/**
 * Перенос старого tasks.my_plan_lane в task_my_plan_placement для текущего пользователя.
 */
function planner_my_plan_migrate_legacy_for_user(int $orgId, int $userId): void
{
    if (!planner_table_has_column('tasks', 'my_plan_lane')) {
        return;
    }
    $stages = planner_my_plan_stages_for_user($orgId, $userId);
    if (count($stages) < 3) {
        return;
    }
    $bySort = [];
    foreach ($stages as $s) {
        $bySort[$s['sort_order']] = $s['id'];
    }
    $map = [
        'unplanned' => $bySort[0] ?? $stages[0]['id'],
        'active' => $bySort[1] ?? $stages[1]['id'],
        'week' => $bySort[2] ?? $stages[2]['id'],
    ];
    $st = db()->prepare(
        'SELECT t.id, t.my_plan_lane FROM tasks t
         WHERE t.organization_id = ?
           AND t.my_plan_lane IS NOT NULL
           AND t.my_plan_lane IN (\'unplanned\',\'active\',\'week\')
           AND (t.assignee_id = ? OR t.author_id = ?)'
    );
    $st->execute([$orgId, $userId, $userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $up = db()->prepare(
        'INSERT INTO task_my_plan_placement (organization_id, task_id, user_id, stage_id)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE stage_id = VALUES(stage_id), organization_id = VALUES(organization_id)'
    );
    foreach ($rows as $r) {
        $tid = (int) ($r['id'] ?? 0);
        $lane = (string) ($r['my_plan_lane'] ?? '');
        if ($tid <= 0 || !isset($map[$lane])) {
            continue;
        }
        $up->execute([$orgId, $tid, $userId, $map[$lane]]);
    }
}

function planner_my_plan_stage_owned_by_user(int $orgId, int $userId, int $stageId): bool
{
    if ($stageId <= 0) {
        return false;
    }
    $st = db()->prepare(
        'SELECT 1 FROM user_my_plan_stages WHERE id = ? AND organization_id = ? AND user_id = ? LIMIT 1'
    );
    $st->execute([$stageId, $orgId, $userId]);
    return (bool) $st->fetchColumn();
}

function planner_my_plan_first_stage_id(int $orgId, int $userId): int
{
    $stages = planner_my_plan_stages_for_user($orgId, $userId);
    return $stages !== [] ? $stages[0]['id'] : 0;
}

function planner_my_plan_upsert_placement(int $orgId, int $userId, int $taskId, ?int $stageId): void
{
    if ($stageId === null || $stageId <= 0) {
        db()->prepare('DELETE FROM task_my_plan_placement WHERE task_id = ? AND user_id = ?')
            ->execute([$taskId, $userId]);
        return;
    }
    if (!planner_my_plan_stage_owned_by_user($orgId, $userId, $stageId)) {
        json_response(['ok' => false, 'error' => 'bad_plan_stage'], 422);
    }
    db()->prepare(
        'INSERT INTO task_my_plan_placement (organization_id, task_id, user_id, stage_id)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE stage_id = VALUES(stage_id), organization_id = VALUES(organization_id)'
    )->execute([$orgId, $taskId, $userId, $stageId]);
}

function planner_my_plan_body_is_only_stage_move(array $body): bool
{
    $keys = array_keys($body);
    sort($keys);
    return $keys === ['my_plan_stage_id'];
}
