<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!function_exists('user_may_access_org_plan_console') || !user_may_access_org_plan_console($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

require_once dirname(__DIR__, 2) . '/includes/xlsx_simple.php';
require_once dirname(__DIR__, 2) . '/includes/xlsx_read_simple.php';
require_once dirname(__DIR__, 2) . '/includes/plan_import.php';

$method = effective_request_method();

if ($method === 'GET' && isset($_GET['template'])) {
    $headers = planner_plan_import_headers();
    $example = ['Пример: отчёт по практике', 'Название проекта как в системе', 'Кафедра (если проектов с таким именем несколько)', '31.12.2026', 'user@example.com'];
    $bytes = planner_xlsx_build('Импорт', 'Шаблон плана задач на год', $headers, [$example]);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="plan_import_template.xlsx"');
    header('Cache-Control: private, max-age=0');
    echo $bytes;
    exit;
}

if ($method === 'GET' && isset($_GET['export'])) {
    $targetYear = isset($_GET['work_year']) ? (int) $_GET['work_year'] : (int) ($user['organization_work_year'] ?? date('Y'));
    if ($targetYear < 1990 || $targetYear > 2100) {
        json_response(['ok' => false, 'error' => 'bad_year'], 422);
    }
    try {
        $dataRows = planner_plan_export_rows(db(), $orgId, $targetYear);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'schema', 'detail' => $e->getMessage()], 422);
    }
    $headers = planner_plan_import_headers();
    $bytes = planner_xlsx_build('Импорт', 'Задачи за ' . $targetYear . ' год', $headers, $dataRows);
    if (count($dataRows) > 0) {
        log_activity(
            $orgId,
            $userId,
            'read',
            'organization',
            $orgId,
            'Экспорт Excel: выгружено задач ' . count($dataRows) . ' (год ' . $targetYear . ')'
        );
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="plan_tasks_' . $targetYear . '.xlsx"');
    header('Cache-Control: private, max-age=0');
    echo $bytes;
    exit;
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    json_response(['ok' => false, 'error' => 'no_file'], 400);
}
$err = (int) ($_FILES['file']['error'] ?? 0);
if ($err !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'upload_error'], 400);
}
$size = (int) ($_FILES['file']['size'] ?? 0);
if ($size > 8 * 1024 * 1024) {
    json_response(['ok' => false, 'error' => 'file_too_large'], 400);
}
$name = (string) ($_FILES['file']['name'] ?? '');
if (!preg_match('/\.xlsx$/i', $name)) {
    json_response(['ok' => false, 'error' => 'bad_extension'], 400);
}

$targetYear = isset($_POST['work_year']) ? (int) $_POST['work_year'] : (int) ($user['organization_work_year'] ?? date('Y'));
if ($targetYear < 1990 || $targetYear > 2100) {
    json_response(['ok' => false, 'error' => 'bad_year'], 422);
}

try {
    $rows = planner_xlsx_read_first_sheet($_FILES['file']['tmp_name']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'parse_failed', 'detail' => $e->getMessage()], 422);
}

$result = planner_plan_import_from_rows(db(), $orgId, $userId, $targetYear, $rows);
if ($result['created'] > 0) {
    log_activity(
        $orgId,
        $userId,
        'update',
        'organization',
        $orgId,
        'Импорт Excel: создано задач ' . $result['created'] . ' (год ' . $targetYear . ')'
    );
}

json_response(array_merge(['ok' => true], $result));
