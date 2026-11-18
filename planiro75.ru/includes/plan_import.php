<?php
declare(strict_types=1);

require_once __DIR__ . '/task_extras.php';

/** @return list<string> */
function planner_plan_import_headers(): array
{
    return [
        'Задача',
        'Проект',
        'Подразделение (необязательно)',
        'Срок (ДД.ММ.ГГГГ или ГГГГ-ММ-ДД)',
        'Email исполнителя',
    ];
}

/**
 * @param list<list<string>> $rows
 * @return array{created: int, errors: list<string>}
 */
function planner_plan_import_from_rows(
    PDO $pdo,
    int $orgId,
    int $actorUserId,
    int $targetWorkYear,
    array $rows
): array {
    if (!planner_table_has_column('tasks', 'work_year')) {
        return ['created' => 0, 'errors' => ['В схеме БД нет привязки задач к году (work_year).']];
    }
    $detect = planner_plan_import_detect_columns($rows);
    $start = $detect['start'];
    $map = $detect['map'];
    $created = 0;
    $errors = [];
    $maxRows = 800;
    $rowNum = $start;
    $total = count($rows);
    for ($i = $start; $i < $total; $i++) {
        if ($i - $start >= $maxRows) {
            $errors[] = 'Достигнут лимит строк за один импорт (' . $maxRows . '). Остальные строки пропущены.';
            break;
        }
        $rowNum = $i + 1;
        $row = $rows[$i];
        $title = planner_plan_import_cell($row, $map['title'] ?? 0);
        if ($title === '') {
            continue;
        }
        $projectName = planner_plan_import_cell($row, $map['project'] ?? 1);
        if ($projectName === '') {
            $errors[] = 'Строка ' . $rowNum . ': не указано название проекта.';
            continue;
        }
        $deptName = null;
        if (isset($map['department']) && $map['department'] >= 0) {
            $d = planner_plan_import_cell($row, $map['department']);
            $deptName = $d !== '' ? $d : null;
        }
        $projectId = planner_plan_import_find_project_id($pdo, $orgId, $targetWorkYear, $projectName, $deptName);
        if ($projectId === null) {
            $errors[] = 'Строка ' . $rowNum . ': проект «' . $projectName . '» не найден для года ' . $targetWorkYear
                . ($deptName ? ' (подразделение «' . $deptName . '»)' : '') . '.';
            continue;
        }
        $dueRaw = '';
        if (isset($map['due']) && $map['due'] >= 0) {
            $dueRaw = planner_plan_import_cell($row, $map['due']);
        }
        $dueDate = planner_plan_import_normalize_due_cell($dueRaw);
        if ($dueRaw !== '' && $dueDate === null) {
            $errors[] = 'Строка ' . $rowNum . ': не распознан срок «' . $dueRaw
                . '» (укажите ДД.ММ.ГГГГ, ГГГГ-ММ-ДД или дату в формате Excel).';
            continue;
        }
        $assigneeId = null;
        if (isset($map['assignee']) && $map['assignee'] >= 0) {
            $em = trim(planner_plan_import_cell($row, $map['assignee']));
            if ($em !== '') {
                $assigneeId = planner_plan_import_find_user_id_by_email($pdo, $orgId, $em);
                if ($assigneeId === null) {
                    $errors[] = 'Строка ' . $rowNum . ': пользователь с email «' . $em . '» не найден в организации.';
                    continue;
                }
            }
        }
        try {
            planner_plan_import_insert_task(
                $pdo,
                $orgId,
                $targetWorkYear,
                $projectId,
                $title,
                $assigneeId,
                $dueDate,
                $actorUserId
            );
            $created++;
        } catch (Throwable $e) {
            $errors[] = 'Строка ' . $rowNum . ': ошибка сохранения — ' . $e->getMessage();
        }
    }

    return ['created' => $created, 'errors' => $errors];
}

function planner_plan_format_due_for_export(?string $dueDateYmd): string
{
    if ($dueDateYmd === null) {
        return '';
    }
    $dueDateYmd = trim($dueDateYmd);
    if ($dueDateYmd === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dueDateYmd, $m)) {
        $year = (int) $m[1];
        $mon = (int) $m[2];
        $day = (int) $m[3];
        if (checkdate($mon, $day, $year)) {
            return sprintf('%02d.%02d.%04d', $day, $mon, $year);
        }
    }

    return $dueDateYmd;
}

/**
 * Строки для Excel в формате шаблона импорта (все задачи организации за год).
 *
 * @return list<list<string>>
 */
function planner_plan_export_rows(PDO $pdo, int $orgId, int $workYear): array
{
    if (!planner_table_has_column('tasks', 'work_year')) {
        throw new RuntimeException('В схеме БД нет привязки задач к году (work_year).');
    }

    $hasProjectDept = planner_table_has_column('projects', 'department_id');
    if ($hasProjectDept) {
        $sql = 'SELECT t.title, p.name AS project_name, d.name AS department_name,
                       t.due_date, ua.email AS assignee_email
                  FROM tasks t
                  INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id
                  LEFT JOIN departments d ON d.id = p.department_id
                  LEFT JOIN users ua ON ua.id = t.assignee_id
                 WHERE t.organization_id = ? AND t.work_year = ?
              ORDER BY p.name ASC, t.title ASC, t.id ASC';
    } else {
        $sql = 'SELECT t.title, p.name AS project_name, NULL AS department_name,
                       t.due_date, ua.email AS assignee_email
                  FROM tasks t
                  LEFT JOIN projects p ON p.id = t.project_id
                  LEFT JOIN users ua ON ua.id = t.assignee_id
                 WHERE t.organization_id = ? AND t.work_year = ?
              ORDER BY p.name ASC, t.title ASC, t.id ASC';
    }

    $st = $pdo->prepare($sql);
    $st->execute([$orgId, $workYear]);

    $rows = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = [
            trim((string) ($r['title'] ?? '')),
            trim((string) ($r['project_name'] ?? '')),
            trim((string) ($r['department_name'] ?? '')),
            planner_plan_format_due_for_export(isset($r['due_date']) ? (string) $r['due_date'] : null),
            trim((string) ($r['assignee_email'] ?? '')),
        ];
    }

    return $rows;
}

/**
 * @param list<list<string>> $rows
 * @return array{start: int, map: array<string, int>}
 */
function planner_plan_import_detect_columns(array $rows): array
{
    $default = ['title' => 0, 'project' => 1, 'department' => 2, 'due' => 3, 'assignee' => 4];
    if ($rows === []) {
        return ['start' => 0, 'map' => $default];
    }
    $h = $rows[0];
    $norm = [];
    foreach ($h as $i => $cell) {
        $norm[$i] = mb_strtolower(trim((string) $cell), 'UTF-8');
    }
    $keywords = [
        'title' => ['задача', 'название', 'title', 'наименование', 'тема'],
        'project' => ['проект', 'project'],
        'department' => ['подразделение', 'отдел', 'кафедра', 'department'],
        'due' => ['срок', 'deadline', 'due', 'дедлайн', 'дата окончания', 'окончание', 'срок исполнения'],
        'assignee' => ['исполнитель', 'email', 'e-mail', 'почта', 'ответственный'],
    ];
    $map = [];
    foreach ($keywords as $key => $words) {
        foreach ($norm as $colIdx => $text) {
            if ($text === '') {
                continue;
            }
            foreach ($words as $w) {
                if ($text === $w) {
                    $map[$key] = $colIdx;
                    break 2;
                }
                if (mb_strlen($w, 'UTF-8') >= 4 && str_contains($text, $w)) {
                    $map[$key] = $colIdx;
                    break 2;
                }
            }
        }
    }
    if (isset($map['title'], $map['project'])) {
        $used = array_values($map);
        if (!isset($map['department'])) {
            $map['department'] = -1;
        }
        foreach (['due', 'assignee'] as $opt) {
            if (isset($map[$opt])) {
                continue;
            }
            $n = count($norm);
            for ($i = 0; $i < $n; $i++) {
                if (in_array($i, $used, true)) {
                    continue;
                }
                $map[$opt] = $i;
                $used[] = $i;
                break;
            }
            if (!isset($map[$opt])) {
                $map[$opt] = -1;
            }
        }

        return ['start' => 1, 'map' => $map];
    }

    return ['start' => 0, 'map' => $default];
}

/** @param list<string> $row */
function planner_plan_import_cell(array $row, int $col): string
{
    if ($col < 0 || !isset($row[$col])) {
        return '';
    }

    return trim((string) $row[$col]);
}

function planner_plan_import_normalize_due_cell(string $s): ?string
{
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/u', $s, $m)) {
        $day = (int) $m[1];
        $mon = (int) $m[2];
        $year = (int) $m[3];
        if (!checkdate($mon, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $mon, $day);
    }
    if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{2})$/u', $s, $m)) {
        $day = (int) $m[1];
        $mon = (int) $m[2];
        $yy = (int) $m[3];
        $year = $yy < 50 ? 2000 + $yy : 1900 + $yy;
        if (!checkdate($mon, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $mon, $day);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        $parts = explode('-', $s);
        if (checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return $s;
        }

        return null;
    }
    if (preg_match('/^\d+(\.\d+)?$/', $s)) {
        $ymd = planner_excel_serial_to_ymd((float) $s);
        if ($ymd !== null) {
            return $ymd;
        }
    }
    $p = planner_parse_due_datetime_input($s);
    if ($p !== null) {
        return substr($p, 0, 10);
    }

    return null;
}

/** Дата из числового формата Excel (дни от 1899-12-30). */
function planner_excel_serial_to_ymd(float $serial): ?string
{
    if ($serial < 1.0 || $serial >= 1000000.0) {
        return null;
    }
    $days = (int) floor($serial);
    try {
        $base = new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC'));

        return $base->modify('+' . $days . ' days')->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

/** Значение due_date для INSERT: день из Excel + время по умолчанию организации. */
function planner_plan_import_due_for_db(?string $dueDateYmd, int $orgId): ?string
{
    if ($dueDateYmd === null || $dueDateYmd === '') {
        return null;
    }

    return $dueDateYmd . ' ' . planner_org_default_due_time_hms($orgId);
}

function planner_plan_import_find_project_id(
    PDO $pdo,
    int $orgId,
    int $workYear,
    string $projectName,
    ?string $deptName
): ?int {
    $projectName = trim($projectName);
    if ($projectName === '') {
        return null;
    }
    $hasYear = planner_table_has_column('projects', 'work_year');
    $hasDept = planner_table_has_column('projects', 'department_id');
    if ($hasYear && $hasDept && $deptName !== null && trim($deptName) !== '') {
        $st = $pdo->prepare(
            'SELECT p.id FROM projects p
             INNER JOIN departments d ON d.id = p.department_id
             WHERE p.organization_id = ? AND p.work_year = ? AND p.name = ? AND d.name = ?
             ORDER BY p.id ASC LIMIT 1'
        );
        $st->execute([$orgId, $workYear, $projectName, trim($deptName)]);
        $id = $st->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        /* имя проекта + отдел не совпали — ищем только по имени проекта в том же году */
    }
    if ($hasYear) {
        $st = $pdo->prepare(
            'SELECT p.id FROM projects p
             WHERE p.organization_id = ? AND p.work_year = ? AND p.name = ?
             ORDER BY p.id ASC LIMIT 1'
        );
        $st->execute([$orgId, $workYear, $projectName]);
    } else {
        $st = $pdo->prepare(
            'SELECT p.id FROM projects p
             WHERE p.organization_id = ? AND p.name = ?
             ORDER BY p.id ASC LIMIT 1'
        );
        $st->execute([$orgId, $projectName]);
    }
    $id = $st->fetchColumn();

    return $id ? (int) $id : null;
}

function planner_plan_import_find_user_id_by_email(PDO $pdo, int $orgId, string $email): ?int
{
    $st = $pdo->prepare(
        'SELECT id FROM users WHERE organization_id = ? AND LOWER(email) = LOWER(?) LIMIT 1'
    );
    $st->execute([$orgId, trim($email)]);
    $id = $st->fetchColumn();

    return $id ? (int) $id : null;
}

function planner_plan_import_insert_task(
    PDO $pdo,
    int $orgId,
    int $insWorkYear,
    int $projectId,
    string $title,
    ?int $assigneeId,
    ?string $dueDateYmd,
    int $authorId
): void {
    $status = 'new';
    $priority = 'normal';
    $description = null;
    $recurrence = 'none';
    $remindDate = null;
    $visibleDepartment = 0;
    $visibleInstitute = 0;
    $dueForDb = planner_plan_import_due_for_db($dueDateYmd, $orgId);

    if (planner_table_has_column('tasks', 'remind_at')) {
        if (planner_table_has_column('tasks', 'work_year')) {
            $insCols = ['organization_id', 'work_year', 'project_id', 'title', 'description', 'status', 'priority', 'author_id', 'assignee_id', 'due_date', 'remind_at', 'recurrence'];
            $insVals = [$orgId, $insWorkYear, $projectId, $title, $description, $status, $priority, $authorId, $assigneeId, $dueForDb, $remindDate, $recurrence];
        } else {
            $insCols = ['organization_id', 'project_id', 'title', 'description', 'status', 'priority', 'author_id', 'assignee_id', 'due_date', 'remind_at', 'recurrence'];
            $insVals = [$orgId, $projectId, $title, $description, $status, $priority, $authorId, $assigneeId, $dueForDb, $remindDate, $recurrence];
        }
        if (planner_table_has_column('tasks', 'visible_department')) {
            $insCols[] = 'visible_department';
            $insVals[] = $visibleDepartment;
        }
        if (planner_table_has_column('tasks', 'visible_institute')) {
            $insCols[] = 'visible_institute';
            $insVals[] = $visibleInstitute;
        }
        $ph = implode(',', array_fill(0, count($insCols), '?'));
        $st = $pdo->prepare('INSERT INTO tasks (' . implode(', ', $insCols) . ') VALUES (' . $ph . ')');
        $st->execute($insVals);
    } else {
        if (planner_table_has_column('tasks', 'work_year')) {
            $insCols = ['organization_id', 'work_year', 'project_id', 'title', 'description', 'status', 'priority', 'author_id', 'assignee_id', 'due_date'];
            $insVals = [$orgId, $insWorkYear, $projectId, $title, $description, $status, $priority, $authorId, $assigneeId, $dueForDb];
        } else {
            $insCols = ['organization_id', 'project_id', 'title', 'description', 'status', 'priority', 'author_id', 'assignee_id', 'due_date'];
            $insVals = [$orgId, $projectId, $title, $description, $status, $priority, $authorId, $assigneeId, $dueForDb];
        }
        if (planner_table_has_column('tasks', 'visible_department')) {
            $insCols[] = 'visible_department';
            $insVals[] = $visibleDepartment;
        }
        if (planner_table_has_column('tasks', 'visible_institute')) {
            $insCols[] = 'visible_institute';
            $insVals[] = $visibleInstitute;
        }
        $ph = implode(',', array_fill(0, count($insCols), '?'));
        $st = $pdo->prepare('INSERT INTO tasks (' . implode(', ', $insCols) . ') VALUES (' . $ph . ')');
        $st->execute($insVals);
    }
    $id = (int) $pdo->lastInsertId();
    if ($id > 0 && planner_table_has_column('tasks', 'status_changed_at')) {
        $pdo->prepare(
            'UPDATE tasks SET status_changed_at = COALESCE(created_at, CURRENT_TIMESTAMP) WHERE id = ? AND organization_id = ?'
        )->execute([$id, $orgId]);
    }
}
