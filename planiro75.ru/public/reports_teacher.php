<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/teacher_report.php';

$user = require_login();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . planner_url('/reports.php'), true, 303);
    exit;
}
$sent = (string) ($_POST['_csrf'] ?? '');
$expected = planner_csrf_token();
if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
    http_response_code(403);
    echo 'Сессия устарела. Откройте отчёты ещё раз и повторите выгрузку.';
    exit;
}

$subjectId = (int) ($_POST['user_id'] ?? 0);
$orgId = (int) ($user['organization_id'] ?? 0);
if ($subjectId <= 0 || !planner_teacher_report_may_view($user, $subjectId)) {
    http_response_code(403);
    echo 'Этот отчёт вам недоступен.';
    exit;
}
if (!planner_teacher_report_ensure_schema()) {
    http_response_code(500);
    echo 'Не удалось подготовить поля отчёта. Выполните sql/migration_teacher_report_v1.sql.';
    exit;
}

$card = [
    'job_title' => $_POST['job_title'] ?? '',
    'academic_degree' => $_POST['academic_degree'] ?? '',
    'academic_rank' => $_POST['academic_rank'] ?? '',
    'plan_hours_edu' => $_POST['plan_hours_edu'] ?? '',
    'plan_hours_edu_method' => $_POST['plan_hours_edu_method'] ?? '',
    'plan_hours_research' => $_POST['plan_hours_research'] ?? '',
    'plan_hours_org_method' => $_POST['plan_hours_org_method'] ?? '',
];
$actorId = (int) ($user['id'] ?? 0);
if ($subjectId === $actorId || user_is_organization_admin($user)) {
    planner_teacher_report_save_card($subjectId, $orgId, $card);
}

$subject = planner_teacher_report_subject($orgId, $subjectId);
if ($subject === null) {
    http_response_code(404);
    echo 'Сотрудник не найден.';
    exit;
}
foreach ($card as $key => $value) {
    if (str_starts_with($key, 'plan_hours_')) {
        $subject[$key] = planner_teacher_report_hours_value($value);
    } else {
        $max = $key === 'job_title' ? 160 : 120;
        $subject[$key] = planner_teacher_report_clip($value, $max) ?? '';
    }
}

$mode = (string) ($_POST['mode'] ?? 'plan');
if (!in_array($mode, ['plan', 'report'], true)) {
    $mode = 'plan';
}
$year = planner_org_work_year_for_org($orgId);
$tasks = planner_teacher_report_tasks($orgId, $subjectId, $year, $mode);
try {
    $binary = planner_teacher_report_docx($subject, $tasks, $year, $mode);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Не удалось собрать документ Word.';
    exit;
}

$fio = planner_teacher_report_split_fio((string) ($subject['name'] ?? ''));
$downloadName = ($mode === 'report' ? 'Отчет ' : 'План ')
    . ($fio['last'] !== '' ? $fio['last'] : 'преподавателя')
    . ' '
    . $year
    . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="teacher-report.docx"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . (string) strlen($binary));
header('Cache-Control: no-store');
echo $binary;
