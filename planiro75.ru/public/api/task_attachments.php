<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$taskId = (int) ($_GET['task_id'] ?? 0);
if ($taskId <= 0 || !planner_table_exists('task_attachments')) {
    json_response(['ok' => false, 'error' => 'bad_request'], $taskId <= 0 ? 422 : 501);
}

$st = db()->prepare('SELECT id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
$st->execute([$taskId, $orgId]);
if (!$st->fetch()) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}
if (!task_visible_to_user($user, $taskId)) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$method = effective_request_method();

if ($method === 'POST' && !user_may_edit_task($user, $taskId)) {
    json_response(['ok' => false, 'error' => 'forbidden_edit'], 403);
}

if ($method === 'GET') {
    $st = db()->prepare(
        'SELECT a.id, a.original_name, a.mime, a.size_bytes, a.comment_id, a.created_at, u.name AS uploaded_by_name
         FROM task_attachments a
         LEFT JOIN users u ON u.id = a.uploaded_by
         WHERE a.task_id = ? AND a.organization_id = ?
         ORDER BY a.id DESC'
    );
    $st->execute([$taskId, $orgId]);
    json_response(['ok' => true, 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'POST') {
    $storageErr = planner_upload_storage_error();
    if ($storageErr !== null) {
        json_response(['ok' => false, 'error' => $storageErr], 500);
    }
    $uploadErr = (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        json_response(['ok' => false, 'error' => 'file_required'], 422);
    }
    if ($uploadErr !== UPLOAD_ERR_OK) {
        $errCode = match ($uploadErr) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'file_too_large',
            UPLOAD_ERR_NO_FILE => 'file_required',
            UPLOAD_ERR_PARTIAL => 'upload_partial',
            default => 'upload_error',
        };
        json_response(['ok' => false, 'error' => $errCode], 422);
    }
    $tmp = (string) $_FILES['file']['tmp_name'];
    $orig = basename((string) ($_FILES['file']['name'] ?? 'file'));
    if (strlen($orig) > 200) {
        $orig = substr($orig, 0, 200);
    }
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv', 'zip', 'odt', 'ods'];
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        json_response(['ok' => false, 'error' => 'bad_extension'], 422);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: 'application/octet-stream';
    $size = (int) ($_FILES['file']['size'] ?? 0);
    if ($size > 40 * 1024 * 1024) {
        json_response(['ok' => false, 'error' => 'file_too_large'], 422);
    }
    $commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
    if ($commentId > 0) {
        $chk = db()->prepare('SELECT id FROM task_comments WHERE id = ? AND task_id = ? LIMIT 1');
        $chk->execute([$commentId, $taskId]);
        if (!$chk->fetch()) {
            json_response(['ok' => false, 'error' => 'bad_comment'], 422);
        }
    } else {
        $commentId = null;
    }
    $stored = planner_random_filename() . '.' . $ext;
    $dir = planner_ensure_upload_dir((string) $orgId);
    $dest = $dir . '/' . $stored;
    if (!move_uploaded_file($tmp, $dest)) {
        json_response(['ok' => false, 'error' => 'upload_failed'], 500);
    }
    $st = db()->prepare(
        'INSERT INTO task_attachments (organization_id, task_id, comment_id, uploaded_by, original_name, stored_name, mime, size_bytes)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $st->execute([$orgId, $taskId, $commentId, $userId, $orig, $stored, $mime, $size]);
    $aid = (int) db()->lastInsertId();
    log_activity($orgId, $userId, 'create', 'attachment', $aid, 'Файл к задаче #' . $taskId . ': ' . $orig);
    json_response(['ok' => true, 'id' => $aid]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
