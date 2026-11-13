<?php
declare(strict_types=1);

require_once __DIR__ . '/task_extras.php';

/**
 * Индивидуальный план/отчёт преподавателя: виды работы в задаче и выгрузка Word.
 */

function planner_teacher_report_kinds(): array
{
    return [
        'work_edu' => ['label' => 'Учебная работа', 'plan' => 'plan_hours_edu'],
        'work_edu_method' => ['label' => 'Учебно-методическая работа', 'plan' => 'plan_hours_edu_method'],
        'work_research' => ['label' => 'Научно-исследовательская работа', 'plan' => 'plan_hours_research'],
        'work_org_method' => ['label' => 'Организационно-методическая работа', 'plan' => 'plan_hours_org_method'],
    ];
}

function planner_teacher_report_ensure_schema(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $columns = [
        'tasks' => [
            'work_edu' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'work_edu_method' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'work_research' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'work_org_method' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'work_start' => 'DATE NULL',
            'work_period' => 'VARCHAR(160) NULL',
            'work_indicator' => 'VARCHAR(255) NULL',
            'work_hours' => 'DECIMAL(8,2) NULL',
            'work_hours_first' => 'DECIMAL(8,2) NULL',
            'work_hours_second' => 'DECIMAL(8,2) NULL',
        ],
        'users' => [
            'job_title' => 'VARCHAR(160) NULL',
            'academic_degree' => 'VARCHAR(120) NULL',
            'academic_rank' => 'VARCHAR(120) NULL',
            'plan_hours_edu' => 'DECIMAL(8,2) NULL',
            'plan_hours_edu_method' => 'DECIMAL(8,2) NULL',
            'plan_hours_research' => 'DECIMAL(8,2) NULL',
            'plan_hours_org_method' => 'DECIMAL(8,2) NULL',
        ],
        'subtasks' => [
            'work_kind' => 'VARCHAR(32) NULL',
            'work_hours' => 'DECIMAL(8,2) NULL',
            'work_indicator' => 'VARCHAR(255) NULL',
            'work_start' => 'DATE NULL',
            'work_end' => 'DATE NULL',
        ],
    ];
    try {
        $names = [];
        foreach ($columns as $table => $defs) {
            foreach (array_keys($defs) as $column) {
                $names[] = $column;
            }
        }
        $ph = implode(',', array_fill(0, count($names), '?'));
        $st = db()->prepare(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('tasks', 'users', 'subtasks')
               AND COLUMN_NAME IN ($ph)"
        );
        $st->execute($names);
        $have = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $have[$row['TABLE_NAME'] . '.' . $row['COLUMN_NAME']] = true;
        }
        foreach ($columns as $table => $defs) {
            if (!planner_table_exists($table)) {
                $ready = false;
                return false;
            }
            foreach ($defs as $column => $ddl) {
                if (isset($have[$table . '.' . $column])) {
                    continue;
                }
                try {
                    db()->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $ddl);
                } catch (Throwable $e) {
                    $message = $e->getMessage();
                    if (!str_contains($message, '1060') && !str_contains($message, 'Duplicate')) {
                        throw $e;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('teacher report schema: ' . $e->getMessage());
        $ready = false;
        return false;
    }
    $ready = planner_table_has_column('tasks', 'work_edu')
        && planner_table_has_column('tasks', 'work_hours_first')
        && planner_table_has_column('tasks', 'work_hours_second')
        && planner_table_has_column('tasks', 'work_hours')
        && planner_table_has_column('tasks', 'work_start')
        && planner_table_has_column('users', 'job_title');

    return $ready;
}

function planner_teacher_report_clip($raw, int $max): ?string
{
    $text = trim((string) $raw);
    if ($text === '') {
        return null;
    }
    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, $max, 'UTF-8');
    } else {
        $text = substr($text, 0, $max);
    }

    return $text;
}

function planner_teacher_report_hours_value($raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_string($raw)) {
        $raw = str_replace(',', '.', trim($raw));
    }
    if (!is_numeric($raw)) {
        return null;
    }
    $n = round((float) $raw, 2);
    if ($n < 0 || $n > 100000) {
        return null;
    }

    return number_format($n, 2, '.', '');
}

/**
 * Поля вида работы из тела создания или правки задачи.
 *
 * @param array<int, string> $fields
 * @param array<int, mixed> $params
 */
function planner_task_work_report_append_fields(array $body, array &$fields, array &$params): void
{
    foreach (array_keys(planner_teacher_report_kinds()) as $column) {
        if (!planner_table_has_column('tasks', $column) || !array_key_exists($column, $body)) {
            continue;
        }
        $fields[] = $column . ' = ?';
        $params[] = !empty($body[$column]) ? 1 : 0;
    }
    if (planner_table_has_column('tasks', 'work_start') && array_key_exists('work_start', $body)) {
        $fields[] = 'work_start = ?';
        $params[] = planner_teacher_report_date_value($body['work_start']);
    }
    if (planner_table_has_column('tasks', 'work_indicator') && array_key_exists('work_indicator', $body)) {
        $fields[] = 'work_indicator = ?';
        $params[] = planner_teacher_report_clip($body['work_indicator'], 255);
    }
    if (planner_table_has_column('tasks', 'work_hours') && array_key_exists('work_hours', $body)) {
        $fields[] = 'work_hours = ?';
        $params[] = planner_teacher_report_hours_value($body['work_hours']);
    }
    foreach (['work_hours_first', 'work_hours_second'] as $column) {
        if (!planner_table_has_column('tasks', $column) || !array_key_exists($column, $body)) {
            continue;
        }
        $fields[] = $column . ' = ?';
        $params[] = planner_teacher_report_hours_value($body[$column]);
    }
}

function planner_task_apply_work_report(int $taskId, int $orgId, array $body): void
{
    if ($taskId <= 0) {
        return;
    }
    $fields = [];
    $params = [];
    planner_task_work_report_append_fields($body, $fields, $params);
    if ($fields !== []) {
        $params[] = $taskId;
        $params[] = $orgId;
        db()->prepare(
            'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?'
        )->execute($params);
    }
    planner_task_work_report_sync($taskId, $orgId, array_key_exists('work_hours', $body));
}

function planner_teacher_report_date_value($raw): ?string
{
    $date = planner_teacher_report_parse_date($raw);

    return $date ? $date->format('Y-m-d') : null;
}

function planner_teacher_report_parse_date($raw): ?DateTimeImmutable
{
    $text = trim((string) $raw);
    if ($text === '') {
        return null;
    }
    $text = substr(str_replace('T', ' ', $text), 0, 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
    $errors = DateTimeImmutable::getLastErrors();
    if (
        !$date
        || (is_array($errors) && ((($errors['warning_count'] ?? 0) > 0) || (($errors['error_count'] ?? 0) > 0)))
    ) {
        return null;
    }

    return $date;
}

/**
 * @return list<array{0:int,1:int}>
 */
function planner_teacher_report_month_span(?string $startRaw, ?string $endRaw): array
{
    $end = planner_teacher_report_parse_date($endRaw);
    if ($end === null) {
        return [];
    }
    $start = planner_teacher_report_parse_date($startRaw) ?? $end;
    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }
    $cursor = $start->modify('first day of this month');
    $last = $end->modify('first day of this month');
    $span = [];
    while ($cursor <= $last && count($span) < 36) {
        $span[] = [(int) $cursor->format('Y'), (int) $cursor->format('n')];
        $cursor = $cursor->modify('+1 month');
    }

    return $span;
}

function planner_teacher_report_month_names(): array
{
    return [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
    ];
}

function planner_teacher_report_period_from_dates(?string $startRaw, ?string $endRaw): string
{
    $span = planner_teacher_report_month_span($startRaw, $endRaw);
    if ($span === []) {
        return '';
    }
    $names = planner_teacher_report_month_names();
    $first = $span[0];
    $last = $span[count($span) - 1];
    $from = ($names[$first[1]] ?? '') . ' ' . $first[0];
    if ($first === $last) {
        return $from;
    }
    if ($first[0] === $last[0]) {
        return ($names[$first[1]] ?? '') . '–' . ($names[$last[1]] ?? '') . ' ' . $first[0];
    }

    return $from . ' – ' . ($names[$last[1]] ?? '') . ' ' . $last[0];
}

/**
 * @return array{h1:?float,h2:?float,year:?float}
 */
function planner_teacher_report_split_hours(float $total, ?string $startRaw, ?string $endRaw): array
{
    $span = planner_teacher_report_month_span($startRaw, $endRaw);
    if ($span === []) {
        return ['h1' => null, 'h2' => null, 'year' => $total];
    }
    $h1n = 0;
    $h2n = 0;
    foreach ($span as $point) {
        if ($point[1] <= 6) {
            $h1n++;
        } else {
            $h2n++;
        }
    }
    $count = $h1n + $h2n;
    if ($h2n === 0) {
        return ['h1' => $total, 'h2' => 0.0, 'year' => $total];
    }
    if ($h1n === 0) {
        return ['h1' => 0.0, 'h2' => $total, 'year' => $total];
    }
    $h1 = round($total * $h1n / $count, 2);

    return ['h1' => $h1, 'h2' => round($total - $h1, 2), 'year' => $total];
}

function planner_task_work_report_sync(int $taskId, int $orgId, bool $hoursTouched): void
{
    if ($taskId <= 0 || !planner_table_has_column('tasks', 'work_hours') || !planner_table_has_column('tasks', 'work_start')) {
        return;
    }
    $st = db()->prepare(
        'SELECT due_date, work_start, work_hours FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $st->execute([$taskId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $period = planner_teacher_report_period_from_dates(
        isset($row['work_start']) ? (string) $row['work_start'] : null,
        isset($row['due_date']) ? (string) $row['due_date'] : null
    );
    $fields = [];
    $params = [];
    if ($period !== '' && planner_table_has_column('tasks', 'work_period')) {
        $fields[] = 'work_period = ?';
        $params[] = $period;
    }
    $total = planner_teacher_report_hours_value($row['work_hours'] ?? null);
    if ($total !== null) {
        $split = planner_teacher_report_split_hours(
            (float) $total,
            isset($row['work_start']) ? (string) $row['work_start'] : null,
            isset($row['due_date']) ? (string) $row['due_date'] : null
        );
        $fields[] = 'work_hours_first = ?';
        $params[] = $split['h1'] === null ? null : number_format($split['h1'], 2, '.', '');
        $fields[] = 'work_hours_second = ?';
        $params[] = $split['h2'] === null ? null : number_format($split['h2'], 2, '.', '');
    } elseif ($hoursTouched) {
        $fields[] = 'work_hours_first = ?';
        $params[] = null;
        $fields[] = 'work_hours_second = ?';
        $params[] = null;
    }
    if ($fields === []) {
        return;
    }
    $params[] = $taskId;
    $params[] = $orgId;
    db()->prepare(
        'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?'
    )->execute($params);
}

function planner_subtask_work_ready(): bool
{
    return planner_table_has_column('subtasks', 'work_kind')
        && planner_table_has_column('subtasks', 'work_hours')
        && planner_table_has_column('subtasks', 'work_end');
}

function planner_subtask_work_select_sql(string $alias = ''): string
{
    if (!planner_subtask_work_ready()) {
        return '';
    }
    $p = $alias !== '' ? $alias . '.' : '';

    return ', ' . $p . 'work_kind, ' . $p . 'work_hours, ' . $p . 'work_indicator, ' . $p . 'work_start, ' . $p . 'work_end';
}

/**
 * Поля вида работы пункта чек-листа из тела PATCH.
 *
 * @param array<int, string> $parts
 * @param array<int, mixed> $params
 */
function planner_subtask_work_append_fields(array $body, array &$parts, array &$params): bool
{
    if (!planner_subtask_work_ready()) {
        return false;
    }
    $touched = false;
    if (array_key_exists('work_kind', $body)) {
        $kind = (string) ($body['work_kind'] ?? '');
        $parts[] = 'work_kind = ?';
        $params[] = isset(planner_teacher_report_kinds()[$kind]) ? $kind : null;
        $touched = true;
    }
    if (array_key_exists('work_hours', $body)) {
        $parts[] = 'work_hours = ?';
        $params[] = planner_teacher_report_hours_value($body['work_hours']);
        $touched = true;
    }
    if (array_key_exists('work_indicator', $body)) {
        $parts[] = 'work_indicator = ?';
        $params[] = planner_teacher_report_clip($body['work_indicator'], 255);
        $touched = true;
    }
    foreach (['work_start', 'work_end'] as $column) {
        if (array_key_exists($column, $body)) {
            $parts[] = $column . ' = ?';
            $params[] = planner_teacher_report_date_value($body[$column]);
            $touched = true;
        }
    }

    return $touched;
}

/**
 * Пункты чек-листа, которые идут в план/отчёт вместо задачи целиком.
 *
 * @return list<array<string, mixed>>
 */
function planner_task_work_items(int $taskId): array
{
    if ($taskId <= 0 || !planner_subtask_work_ready()) {
        return [];
    }
    $order = planner_table_has_column('subtasks', 'sort_order') ? 'sort_order, id' : 'id';
    $st = db()->prepare(
        'SELECT id, title, is_done' . planner_subtask_work_select_sql() . '
         FROM subtasks
         WHERE task_id = ? AND work_kind IS NOT NULL AND work_kind <> \'\' AND work_hours > 0
         ORDER BY ' . $order
    );
    $st->execute([$taskId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Если у пунктов чек-листа заданы часы, часы задачи — их сумма.
 */
function planner_task_sync_hours_from_subtasks(int $taskId, int $orgId): void
{
    if ($taskId <= 0 || !planner_subtask_work_ready() || !planner_table_has_column('tasks', 'work_hours')) {
        return;
    }
    $st = db()->prepare(
        'SELECT SUM(work_hours) FROM subtasks
         WHERE task_id = ? AND work_kind IS NOT NULL AND work_kind <> \'\' AND work_hours > 0'
    );
    $st->execute([$taskId]);
    $sum = $st->fetchColumn();
    if ($sum === null || $sum === false || (float) $sum <= 0) {
        return;
    }
    db()->prepare('UPDATE tasks SET work_hours = ? WHERE id = ? AND organization_id = ?')
        ->execute([number_format((float) $sum, 2, '.', ''), $taskId, $orgId]);
    planner_task_work_report_sync($taskId, $orgId, true);
}

/**
 * Сотрудники, чей отчёт может выгрузить текущий пользователь.
 *
 * @return list<array<string, mixed>>
 */
function planner_teacher_report_candidates(array $actor): array
{
    planner_teacher_report_ensure_schema();
    $orgId = (int) ($actor['organization_id'] ?? 0);
    $actorId = (int) ($actor['id'] ?? 0);
    if ($orgId <= 0 || $actorId <= 0) {
        return [];
    }
    $extra = '';
    foreach (['job_title', 'academic_degree', 'academic_rank', 'plan_hours_edu', 'plan_hours_edu_method', 'plan_hours_research', 'plan_hours_org_method'] as $column) {
        if (planner_table_has_column('users', $column)) {
            $extra .= ', u.' . $column;
        }
    }
    $sql = 'SELECT u.id, u.name, u.department_id, d.name AS department_name' . $extra . '
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id AND d.organization_id = u.organization_id
            WHERE u.organization_id = ?';
    $params = [$orgId];
    $level = planner_reports_visibility_level($actor);
    if ($level === 'self') {
        $sql .= ' AND u.id = ?';
        $params[] = $actorId;
    } elseif ($level === 'department') {
        $ids = array_values(array_unique(array_merge(
            user_managed_department_ids($actor),
            function_exists('user_administered_department_ids') ? user_administered_department_ids($actor) : []
        )));
        if ($ids === []) {
            $sql .= ' AND u.id = ?';
            $params[] = $actorId;
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $memberSql = planner_table_exists('user_departments')
                ? " OR u.id IN (SELECT ud.user_id FROM user_departments ud WHERE ud.department_id IN ($ph))"
                : '';
            $sql .= " AND (u.id = ? OR u.department_id IN ($ph)$memberSql)";
            $params[] = $actorId;
            $params = array_merge($params, $ids);
            if ($memberSql !== '') {
                $params = array_merge($params, $ids);
            }
        }
    }
    $sql .= ' ORDER BY u.name, u.id';
    $st = db()->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function planner_teacher_report_may_view(array $actor, int $subjectId): bool
{
    foreach (planner_teacher_report_candidates($actor) as $row) {
        if ((int) ($row['id'] ?? 0) === $subjectId) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $card
 */
function planner_teacher_report_save_card(int $userId, int $orgId, array $card): void
{
    if (!planner_teacher_report_ensure_schema() || $userId <= 0) {
        return;
    }
    $fields = [];
    $params = [];
    foreach (['job_title' => 160, 'academic_degree' => 120, 'academic_rank' => 120] as $column => $max) {
        if (!planner_table_has_column('users', $column) || !array_key_exists($column, $card)) {
            continue;
        }
        $fields[] = $column . ' = ?';
        $params[] = planner_teacher_report_clip($card[$column], $max);
    }
    foreach (['plan_hours_edu', 'plan_hours_edu_method', 'plan_hours_research', 'plan_hours_org_method'] as $column) {
        if (!planner_table_has_column('users', $column) || !array_key_exists($column, $card)) {
            continue;
        }
        $fields[] = $column . ' = ?';
        $params[] = planner_teacher_report_hours_value($card[$column]);
    }
    if ($fields === []) {
        return;
    }
    $params[] = $userId;
    $params[] = $orgId;
    db()->prepare(
        'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?'
    )->execute($params);
}

/**
 * @return list<array<string, mixed>>
 */
function planner_teacher_report_tasks(int $orgId, int $userId, int $year, string $mode = 'plan'): array
{
    if (!planner_table_has_column('tasks', 'work_edu') || !planner_table_has_column('tasks', 'work_hours_first')) {
        return [];
    }
    $yearSql = planner_table_has_column('tasks', 'work_year') ? ' AND t.work_year = ?' : '';
    $extra = '';
    if (planner_table_has_column('tasks', 'work_start')) {
        $extra .= ', t.work_start';
    }
    if (planner_table_has_column('tasks', 'work_hours')) {
        $extra .= ', t.work_hours';
    }
    $assistantSql = '';
    if (planner_table_exists('task_participants')) {
        $assistantSql = ' OR EXISTS (
            SELECT 1 FROM task_participants tp
            WHERE tp.task_id = t.id AND tp.user_id = ? AND tp.role = \'assistant\'
        )';
    }
    $itemsReady = planner_subtask_work_ready();
    $subAssistSql = '';
    $itemsSql = '';
    if ($itemsReady) {
        $itemsSql = ' OR EXISTS (
            SELECT 1 FROM subtasks ws
            WHERE ws.task_id = t.id AND ws.work_kind IS NOT NULL AND ws.work_kind <> \'\' AND ws.work_hours > 0
        )';
        if (planner_table_exists('subtask_participants')) {
            $subAssistSql = ' OR EXISTS (
                SELECT 1 FROM subtask_participants sp
                INNER JOIN subtasks ss ON ss.id = sp.subtask_id
                WHERE ss.task_id = t.id AND sp.user_id = ?
            )';
        }
    }
    $st = db()->prepare(
        'SELECT t.id, t.title, t.description, t.due_date, t.status, t.assignee_id, t.author_id,
                t.work_edu, t.work_edu_method, t.work_research, t.work_org_method,
                t.work_period, t.work_indicator, t.work_hours_first, t.work_hours_second'
        . $extra . '
         FROM tasks t
         WHERE t.organization_id = ?
           AND t.status <> \'cancelled\'
           AND (t.assignee_id = ? OR (t.assignee_id IS NULL AND t.author_id = ?)' . $assistantSql . $subAssistSql . ')
           AND (t.work_edu = 1 OR t.work_edu_method = 1 OR t.work_research = 1 OR t.work_org_method = 1' . $itemsSql . ')'
        . $yearSql
        . ' ORDER BY (t.due_date IS NULL), t.due_date, t.id'
    );
    $params = [$orgId, $userId, $userId];
    if ($assistantSql !== '') {
        $params[] = $userId;
    }
    if ($subAssistSql !== '') {
        $params[] = $userId;
    }
    if ($yearSql !== '') {
        $params[] = $year;
    }
    $st->execute($params);
    $tasks = $st->fetchAll(PDO::FETCH_ASSOC);

    $assistantTaskIds = [];
    if ($tasks !== [] && planner_table_exists('task_participants')) {
        $ids = array_map(static fn (array $t): int => (int) $t['id'], $tasks);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stA = db()->prepare(
            "SELECT task_id FROM task_participants WHERE user_id = ? AND role = 'assistant' AND task_id IN ($ph)"
        );
        $stA->execute(array_merge([$userId], $ids));
        foreach ($stA->fetchAll(PDO::FETCH_COLUMN) as $tid) {
            $assistantTaskIds[(int) $tid] = true;
        }
    }

    $rows = [];
    foreach ($tasks as $task) {
        $taskId = (int) $task['id'];
        $assigneeId = (int) ($task['assignee_id'] ?? 0);
        $authorId = (int) ($task['author_id'] ?? 0);
        $isMain = $assigneeId === $userId || ($assigneeId === 0 && $authorId === $userId);
        $isAssistant = isset($assistantTaskIds[$taskId]);
        $taskDone = (string) ($task['status'] ?? '') === 'done';
        $items = $itemsReady ? planner_task_work_items($taskId) : [];

        if ($items === []) {
            if (!$isMain && !$isAssistant) {
                continue;
            }
            if ($mode === 'report' && !$taskDone) {
                continue;
            }
            if (!$isMain) {
                $task['report_role'] = 'соисполнитель';
            }
            $rows[] = $task;
            continue;
        }

        $itemAssignees = planner_subtask_assistants_payload_map($taskId);
        $hasItemAssignments = false;
        foreach ($items as $item) {
            if (!empty($itemAssignees[(int) $item['id']])) {
                $hasItemAssignments = true;
                break;
            }
        }
        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            $mine = false;
            foreach ($itemAssignees[$itemId] ?? [] as $person) {
                if ((int) $person['id'] === $userId) {
                    $mine = true;
                    break;
                }
            }
            if (!$isMain && !$mine && !($isAssistant && !$hasItemAssignments)) {
                continue;
            }
            if ($mode === 'report' && !$taskDone && empty($item['is_done'])) {
                continue;
            }
            $row = $task;
            foreach (array_keys(planner_teacher_report_kinds()) as $column) {
                $row[$column] = $column === $item['work_kind'] ? 1 : 0;
            }
            $row['id'] = 0;
            $row['subtask_id'] = $itemId;
            $row['title'] = trim((string) $task['title']) . ' — ' . trim((string) $item['title']);
            $row['description'] = '';
            $row['work_hours'] = $item['work_hours'];
            $row['work_hours_first'] = null;
            $row['work_hours_second'] = null;
            if (!empty($item['work_start'])) {
                $row['work_start'] = $item['work_start'];
            }
            if (!empty($item['work_end'])) {
                $row['due_date'] = $item['work_end'];
            }
            $indicator = trim((string) ($item['work_indicator'] ?? ''));
            if ($indicator !== '') {
                $row['work_indicator'] = $indicator;
            }
            if (!$isMain) {
                $row['report_role'] = 'соисполнитель';
            }
            $rows[] = $row;
        }
    }

    return $rows;
}

function planner_teacher_report_subject(int $orgId, int $userId): ?array
{
    $extra = '';
    foreach (['job_title', 'academic_degree', 'academic_rank', 'plan_hours_edu', 'plan_hours_edu_method', 'plan_hours_research', 'plan_hours_org_method'] as $column) {
        if (planner_table_has_column('users', $column)) {
            $extra .= ', u.' . $column;
        }
    }
    $st = db()->prepare(
        'SELECT u.id, u.name, u.organization_id, d.name AS department_name, o.name AS organization_name'
        . $extra
        . ' FROM users u
            INNER JOIN organizations o ON o.id = u.organization_id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.id = ? AND u.organization_id = ?
            LIMIT 1'
    );
    $st->execute([$userId, $orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function planner_teacher_report_split_fio(string $name): array
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];

    return [
        'last' => (string) ($parts[0] ?? ''),
        'first' => (string) ($parts[1] ?? ''),
        'middle' => implode(' ', array_slice($parts, 2)),
    ];
}

function planner_teacher_report_hours_text($raw): string
{
    if ($raw === null || $raw === '') {
        return '';
    }
    if (!is_numeric($raw)) {
        return '';
    }
    $n = round((float) $raw, 2);
    if (abs($n - round($n)) < 0.001) {
        return (string) (int) round($n);
    }
    $text = number_format($n, 2, ',', '');

    return rtrim(rtrim($text, '0'), ',');
}

function planner_teacher_report_sum_text(array $values): string
{
    $sum = 0.0;
    $any = false;
    foreach ($values as $value) {
        if ($value === null || $value === '' || !is_numeric($value)) {
            continue;
        }
        $sum += (float) $value;
        $any = true;
    }

    return $any ? planner_teacher_report_hours_text($sum) : '';
}

function planner_teacher_report_period(array $task): string
{
    $fromDates = planner_teacher_report_period_from_dates(
        isset($task['work_start']) ? (string) $task['work_start'] : null,
        isset($task['due_date']) ? (string) $task['due_date'] : null
    );
    if ($fromDates !== '') {
        return $fromDates;
    }

    return trim((string) ($task['work_period'] ?? ''));
}

function planner_teacher_report_task_title(array $task): string
{
    $title = trim((string) ($task['title'] ?? ''));
    $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($task['description'] ?? ''))) ?? '');
    if ($description !== '' && $description !== $title) {
        if (function_exists('mb_strlen') && mb_strlen($description, 'UTF-8') > 400) {
            $description = mb_substr($description, 0, 400, 'UTF-8') . '…';
        }
        $title .= "\n" . $description;
    }

    $role = trim((string) ($task['report_role'] ?? ''));
    if ($role !== '') {
        $title .= "\n" . $role;
    }

    return $title !== '' ? $title : 'Без названия';
}

function planner_docx_xml(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function planner_docx_p(string $text, string $align = 'left', bool $bold = false, int $size = 22): string
{
    $jc = $align !== 'left' ? '<w:jc w:val="' . $align . '"/>' : '';
    $b = $bold ? '<w:b/>' : '';

    return '<w:p><w:pPr>' . $jc . '<w:spacing w:before="0" w:after="40"/></w:pPr>'
        . '<w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>'
        . $b . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr>'
        . '<w:t xml:space="preserve">' . planner_docx_xml($text) . '</w:t></w:r></w:p>';
}

function planner_docx_tc(string $text, int $width, array $opt = []): string
{
    $span = !empty($opt['span']) ? '<w:gridSpan w:val="' . (int) $opt['span'] . '"/>' : '';
    $vmerge = '';
    if (($opt['vmerge'] ?? '') === 'restart') {
        $vmerge = '<w:vMerge w:val="restart"/>';
    } elseif (($opt['vmerge'] ?? '') === 'continue') {
        $vmerge = '<w:vMerge/>';
    }
    $shd = !empty($opt['fill']) ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $opt['fill'] . '"/>' : '';
    $paras = '';
    $lines = preg_split("/\r\n|\n|\r/u", $text);
    if ($lines === false || $lines === []) {
        $lines = [''];
    }
    foreach ($lines as $line) {
        $paras .= planner_docx_p($line, (string) ($opt['align'] ?? 'center'), !empty($opt['bold']), (int) ($opt['size'] ?? 16));
    }
    $borders = '<w:tcBorders>'
        . '<w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '<w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '<w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '</w:tcBorders>';

    return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>' . $span . $vmerge . $shd
        . $borders . '<w:vAlign w:val="center"/></w:tcPr>' . $paras . '</w:tc>';
}

function planner_docx_tbl(array $widths, string $rows): string
{
    $grid = '';
    $total = 0;
    foreach ($widths as $width) {
        $grid .= '<w:gridCol w:w="' . (int) $width . '"/>';
        $total += (int) $width;
    }
    $borders = '<w:tblBorders>'
        . '<w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '<w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '<w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
        . '</w:tblBorders>';

    return '<w:tbl><w:tblPr><w:tblW w:w="' . $total . '" w:type="dxa"/><w:tblLayout w:type="fixed"/>' . $borders
        . '</w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>' . $rows . '</w:tbl>';
}

function planner_teacher_report_half_hours(array $task): array
{
    $total = planner_teacher_report_hours_value($task['work_hours'] ?? null);
    if ($total !== null) {
        return planner_teacher_report_split_hours(
            (float) $total,
            isset($task['work_start']) ? (string) $task['work_start'] : null,
            isset($task['due_date']) ? (string) $task['due_date'] : null
        );
    }
    $h1 = $task['work_hours_first'] ?? null;
    $h2 = $task['work_hours_second'] ?? null;
    $h1set = $h1 !== null && $h1 !== '' && is_numeric($h1);
    $h2set = $h2 !== null && $h2 !== '' && is_numeric($h2);
    $year = null;
    if ($h1set || $h2set) {
        $year = ($h1set ? (float) $h1 : 0.0) + ($h2set ? (float) $h2 : 0.0);
    }

    return [
        'h1' => $h1set ? (float) $h1 : null,
        'h2' => $h2set ? (float) $h2 : null,
        'year' => $year,
    ];
}

/**
 * @param array<string, mixed> $subject
 * @param list<array<string, mixed>> $tasks
 */
function planner_teacher_report_docx(array $subject, array $tasks, int $year, string $mode = 'plan'): string
{
    $fio = planner_teacher_report_split_fio((string) ($subject['name'] ?? ''));
    $org = trim((string) ($subject['organization_name'] ?? ''));
    $dept = trim((string) ($subject['department_name'] ?? ''));
    if (function_exists('mb_strtoupper')) {
        $deptUpper = $dept !== '' ? mb_strtoupper($dept, 'UTF-8') : '';
    } else {
        $deptUpper = $dept !== '' ? strtoupper($dept) : '';
    }
    $job = trim((string) ($subject['job_title'] ?? ''));
    $degree = trim((string) ($subject['academic_degree'] ?? ''));
    $rank = trim((string) ($subject['academic_rank'] ?? ''));
    $initials = '';
    if ($fio['first'] !== '') {
        $initials .= mb_substr($fio['first'], 0, 1, 'UTF-8') . '.';
    }
    if ($fio['middle'] !== '') {
        $initials .= mb_substr($fio['middle'], 0, 1, 'UTF-8') . '.';
    }
    $signName = trim($fio['last'] . ' ' . $initials);

    $body = '';
    $body .= planner_docx_p('Министерство образования Забайкальского края', 'center', false, 22);
    $body .= planner_docx_p('Государственное учреждение дополнительного профессионального образования', 'center', false, 22);
    $body .= planner_docx_p($org !== '' ? '«' . $org . '»' : '', 'center', false, 22);
    if ($deptUpper !== '') {
        $body .= planner_docx_p($deptUpper, 'center', true, 24);
    }
    $body .= planner_docx_p('«УТВЕРЖДАЮ»', 'right', true, 22);
    $body .= planner_docx_p(
        $mode === 'report'
            ? 'ИНДИВИДУАЛЬНЫЙ ОТЧЁТ РАБОТЫ ПРЕПОДАВАТЕЛЯ'
            : 'ИНДИВИДУАЛЬНЫЙ ПЛАН РАБОТЫ ПРЕПОДАВАТЕЛЯ',
        'center',
        true,
        28
    );
    $body .= planner_docx_p('Руководитель структурного подразделения ___________ / ___________ /', 'right', false, 20);
    $body .= planner_docx_p((string) $year . ' год', 'center', true, 24);
    $body .= planner_docx_p(date('d.m.Y') . ' г.', 'right', false, 20);
    $body .= planner_docx_p('Фамилия: ' . ($fio['last'] !== '' ? $fio['last'] : '—'), 'left', false, 22);
    $body .= planner_docx_p('Имя: ' . ($fio['first'] !== '' ? $fio['first'] : '—'), 'left', false, 22);
    $body .= planner_docx_p('Отчество: ' . ($fio['middle'] !== '' ? $fio['middle'] : '—'), 'left', false, 22);
    $body .= planner_docx_p('Должность: ' . ($job !== '' ? $job : '—'), 'left', false, 22);
    $body .= planner_docx_p('Ученая степень: ' . ($degree !== '' ? $degree : '—'), 'left', false, 22);
    $body .= planner_docx_p('Ученое звание: ' . ($rank !== '' ? $rank : '—'), 'left', false, 22);
    $body .= planner_docx_p('Преподаватель ____________________________ / ' . ($signName !== '' ? $signName : '____________') . ' /', 'left', false, 20);
    $body .= planner_docx_p('', 'left', false, 12);

    $widths1 = [1800, 1100, 1200, 1200, 1200, 1200, 1200, 1200, 1200, 1200, 1100, 1100];
    $labels = array_column(planner_teacher_report_kinds(), 'label');
    $header1 = planner_docx_tc('Должность', $widths1[0], ['vmerge' => 'restart', 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14])
        . planner_docx_tc('Объем часов', $widths1[1], ['vmerge' => 'restart', 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14]);
    foreach ($labels as $i => $label) {
        $header1 .= planner_docx_tc($label, $widths1[2] * 2, ['span' => 2, 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14]);
    }
    $header1 .= planner_docx_tc('ИТОГО', $widths1[10] + $widths1[11], ['span' => 2, 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14]);
    $header2 = planner_docx_tc('', $widths1[0], ['vmerge' => 'continue', 'fill' => 'F2F2F2'])
        . planner_docx_tc('', $widths1[1], ['vmerge' => 'continue', 'fill' => 'F2F2F2']);
    for ($i = 0; $i < 5; $i++) {
        $base = 2 + $i * 2;
        $header2 .= planner_docx_tc('план', $widths1[$base], ['bold' => true, 'fill' => 'F2F2F2', 'size' => 14])
            . planner_docx_tc('факт', $widths1[$base + 1], ['bold' => true, 'fill' => 'F2F2F2', 'size' => 14]);
    }

    $plans = [];
    $facts = [];
    $planNumbers = [];
    foreach (planner_teacher_report_kinds() as $column => $meta) {
        $planRaw = $subject[$meta['plan']] ?? null;
        $plans[$column] = planner_teacher_report_hours_text($planRaw);
        if ($planRaw !== null && $planRaw !== '' && is_numeric($planRaw)) {
            $planNumbers[] = (float) $planRaw;
        }
        $factValues = [];
        foreach ($tasks as $task) {
            if (empty($task[$column])) {
                continue;
            }
            $hours = planner_teacher_report_half_hours($task);
            if ($hours['year'] !== null) {
                $factValues[] = $hours['year'];
            }
        }
        $facts[$column] = planner_teacher_report_sum_text($factValues);
    }
    $volume = planner_teacher_report_sum_text($planNumbers);
    $planTotal = $volume;
    $factTotalValues = [];
    $factCounted = [];
    foreach ($tasks as $task) {
        $taskId = (int) ($task['id'] ?? 0);
        if ($taskId > 0 && isset($factCounted[$taskId])) {
            continue;
        }
        $inReport = false;
        foreach (array_keys(planner_teacher_report_kinds()) as $column) {
            if (!empty($task[$column])) {
                $inReport = true;
                break;
            }
        }
        if (!$inReport) {
            continue;
        }
        if ($taskId > 0) {
            $factCounted[$taskId] = true;
        }
        $hours = planner_teacher_report_half_hours($task);
        if ($hours['year'] !== null) {
            $factTotalValues[] = $hours['year'];
        }
    }
    $factTotal = planner_teacher_report_sum_text($factTotalValues);

    $data = planner_docx_tc($job !== '' ? $job : '—', $widths1[0], ['size' => 14])
        . planner_docx_tc($volume, $widths1[1], ['size' => 14]);
    $idx = 2;
    foreach (array_keys(planner_teacher_report_kinds()) as $column) {
        $data .= planner_docx_tc($plans[$column], $widths1[$idx], ['size' => 14])
            . planner_docx_tc($facts[$column], $widths1[$idx + 1], ['size' => 14]);
        $idx += 2;
    }
    $data .= planner_docx_tc($planTotal, $widths1[10], ['size' => 14])
        . planner_docx_tc($factTotal, $widths1[11], ['bold' => true, 'size' => 14]);
    $totalRow = planner_docx_tc('ВСЕГО', $widths1[0], ['bold' => true, 'size' => 14])
        . planner_docx_tc($volume, $widths1[1], ['bold' => true, 'size' => 14])
        . planner_docx_tc('', $widths1[2] * 8, ['span' => 8, 'size' => 14])
        . planner_docx_tc($planTotal, $widths1[10], ['bold' => true, 'size' => 14])
        . planner_docx_tc($factTotal, $widths1[11], ['bold' => true, 'size' => 14]);

    $body .= planner_docx_p('Раздел I. Выполнение государственных услуг', 'left', true, 24);
    $body .= planner_docx_tbl(
        $widths1,
        '<w:tr>' . $header1 . '</w:tr><w:tr>' . $header2 . '</w:tr><w:tr>' . $data . '</w:tr><w:tr>' . $totalRow . '</w:tr>'
    );
    $body .= planner_docx_p('', 'left', false, 12);
    $body .= planner_docx_p('Раздел II. Выполнение государственных работ', 'left', true, 24);

    $widths2 = [700, 5200, 2200, 2700, 1300, 1300, 1300];
    $n = 1;
    foreach (planner_teacher_report_kinds() as $column => $meta) {
        $body .= planner_docx_p($n . '. ' . $meta['label'], 'left', true, 22);
        $h1 = planner_docx_tc('№ п/п', $widths2[0], ['vmerge' => 'restart', 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14])
            . planner_docx_tc('Наименование', $widths2[1], ['vmerge' => 'restart', 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14])
            . planner_docx_tc('Сроки выполнения', $widths2[2], ['vmerge' => 'restart', 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14])
            . planner_docx_tc("Показатель выполнения\n(кол-во чел., п. л., стр. и др.)", $widths2[3], ['vmerge' => 'restart', 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14])
            . planner_docx_tc("Объем выполненной работы\n(фактическое выполнение)", $widths2[4] + $widths2[5] + $widths2[6], ['span' => 3, 'bold' => true, 'fill' => 'F2F2F2', 'size' => 14]);
        $h2 = planner_docx_tc('', $widths2[0], ['vmerge' => 'continue', 'fill' => 'F2F2F2'])
            . planner_docx_tc('', $widths2[1], ['vmerge' => 'continue', 'fill' => 'F2F2F2'])
            . planner_docx_tc('', $widths2[2], ['vmerge' => 'continue', 'fill' => 'F2F2F2'])
            . planner_docx_tc('', $widths2[3], ['vmerge' => 'continue', 'fill' => 'F2F2F2'])
            . planner_docx_tc('1 полугодие', $widths2[4], ['bold' => true, 'fill' => 'F2F2F2', 'size' => 14])
            . planner_docx_tc('2 полугодие', $widths2[5], ['bold' => true, 'fill' => 'F2F2F2', 'size' => 14])
            . planner_docx_tc('год', $widths2[6], ['bold' => true, 'fill' => 'F2F2F2', 'size' => 14]);
        $rows = '<w:tr>' . $h1 . '</w:tr><w:tr>' . $h2 . '</w:tr>';
        $sumH1 = [];
        $sumH2 = [];
        $sumYear = [];
        $index = 1;
        foreach ($tasks as $task) {
            if (empty($task[$column])) {
                continue;
            }
            $hours = planner_teacher_report_half_hours($task);
            if ($hours['h1'] !== null) {
                $sumH1[] = $hours['h1'];
            }
            if ($hours['h2'] !== null) {
                $sumH2[] = $hours['h2'];
            }
            if ($hours['year'] !== null) {
                $sumYear[] = $hours['year'];
            }
            $rows .= '<w:tr>'
                . planner_docx_tc((string) $index, $widths2[0], ['size' => 14])
                . planner_docx_tc(planner_teacher_report_task_title($task), $widths2[1], ['align' => 'left', 'size' => 14])
                . planner_docx_tc(planner_teacher_report_period($task), $widths2[2], ['size' => 14])
                . planner_docx_tc(trim((string) ($task['work_indicator'] ?? '')), $widths2[3], ['align' => 'left', 'size' => 14])
                . planner_docx_tc(planner_teacher_report_hours_text($hours['h1']), $widths2[4], ['size' => 14])
                . planner_docx_tc(planner_teacher_report_hours_text($hours['h2']), $widths2[5], ['size' => 14])
                . planner_docx_tc(planner_teacher_report_hours_text($hours['year']), $widths2[6], ['size' => 14])
                . '</w:tr>';
            $index++;
        }
        if ($index === 1) {
            $rows .= '<w:tr>'
                . planner_docx_tc('—', $widths2[0], ['size' => 14])
                . planner_docx_tc('Нет задач этого вида', $widths2[1], ['align' => 'left', 'size' => 14])
                . planner_docx_tc('', $widths2[2], ['size' => 14])
                . planner_docx_tc('', $widths2[3], ['size' => 14])
                . planner_docx_tc('', $widths2[4], ['size' => 14])
                . planner_docx_tc('', $widths2[5], ['size' => 14])
                . planner_docx_tc('', $widths2[6], ['size' => 14])
                . '</w:tr>';
        }
        $rows .= '<w:tr>'
            . planner_docx_tc('Итого', 700 + 5200 + 2200 + 2700, ['span' => 4, 'bold' => true, 'align' => 'right', 'size' => 14])
            . planner_docx_tc(planner_teacher_report_sum_text($sumH1), $widths2[4], ['bold' => true, 'size' => 14])
            . planner_docx_tc(planner_teacher_report_sum_text($sumH2), $widths2[5], ['bold' => true, 'size' => 14])
            . planner_docx_tc(planner_teacher_report_sum_text($sumYear), $widths2[6], ['bold' => true, 'size' => 14])
            . '</w:tr>';
        $body .= planner_docx_tbl($widths2, $rows);
        $body .= planner_docx_p('', 'left', false, 10);
        $n++;
    }

    $body .= '<w:sectPr>'
        . '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
        . '<w:pgMar w:top="567" w:right="567" w:bottom="567" w:left="1134" w:header="709" w:footer="709" w:gutter="0"/>'
        . '</w:sectPr>';

    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>' . $body . '</w:body></w:document>';

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive недоступен');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'docx');
    if ($tmp === false) {
        throw new RuntimeException('Не удалось создать временный файл');
    }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Не удалось собрать документ');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr>'
        . '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>'
        . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
        . '</w:rPr></w:rPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
        . '</w:styles>');
    $zip->addFromString('word/document.xml', $document);
    $zip->close();
    $binary = file_get_contents($tmp);
    @unlink($tmp);
    if ($binary === false) {
        throw new RuntimeException('Не удалось прочитать документ');
    }

    return $binary;
}
