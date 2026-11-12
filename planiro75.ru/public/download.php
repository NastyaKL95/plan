<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = current_user();
if (!$user) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Доступ запрещён';
    exit;
}
planner_maintenance_enforce_page($user);

$orgId = (int) $user['organization_id'];
$id = (int) ($_GET['attachment'] ?? $_GET['id'] ?? 0);
if ($id <= 0 || !planner_table_exists('task_attachments')) {
    http_response_code(404);
    exit;
}

$st = db()->prepare(
    'SELECT a.original_name, a.stored_name, a.mime, a.organization_id, a.task_id
     FROM task_attachments a
     WHERE a.id = ? AND a.organization_id = ? LIMIT 1'
);
$st->execute([$id, $orgId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/access.php';
if (!task_visible_to_user($user, (int) $row['task_id'])) {
    http_response_code(404);
    exit;
}

$path = planner_upload_root() . '/' . (int) $row['organization_id'] . '/' . $row['stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = (string) $row['mime'];
$name = (string) $row['original_name'];
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit;
