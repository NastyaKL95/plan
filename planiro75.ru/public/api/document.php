<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/documents.php';

if (!planner_documents_ensure_schema()) {
    json_response(['ok' => false, 'error' => 'schema_missing'], 503);
}

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM documents WHERE id = ? AND organization_id = ? LIMIT 1');
$st->execute([$id, $orgId]);
$doc = $st->fetch(PDO::FETCH_ASSOC);
if (!$doc) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$mayManage = user_may_manage_documents($user);
$method = effective_request_method();

if ($method === 'GET') {
    $doc['has_text'] = trim((string) $doc['content_text']) !== '' ? 1 : 0;
    json_response(['ok' => true, 'item' => planner_document_public($doc, $mayManage)]);
}

if (!$mayManage) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

if ($method === 'DELETE') {
    db()->prepare('DELETE FROM documents WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
    @unlink(planner_document_path($orgId, (string) $doc['stored_name']));
    log_activity($orgId, $userId, 'delete', 'document', $id, 'Удалён документ: ' . $doc['title']);
    json_response(['ok' => true]);
}

if ($method === 'POST' || $method === 'PATCH') {
    $in = $_POST;
    if ($in === []) {
        $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    }
    if (!empty($in['pin_only'])) {
        $pinned = in_array($in['is_pinned'] ?? 0, [true, 1, '1', 'true'], true) ? 1 : 0;
        db()->prepare('UPDATE documents SET is_pinned = ? WHERE id = ? AND organization_id = ?')->execute([$pinned, $id, $orgId]);
        json_response(['ok' => true]);
    }
    [$fields, $err] = planner_document_fields_from_request($orgId, $in);
    if ($err !== null) {
        json_response(['ok' => false, 'error' => $err], 422);
    }
    $sets = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $sets[] = "`$col` = ?";
        $params[] = $val;
    }
    $newFile = null;
    if (!empty($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $newFile = planner_document_store_upload($orgId, $_FILES['file']);
        if (!$newFile['ok']) {
            json_response(['ok' => false, 'error' => $newFile['error']], 422);
        }
        foreach (['original_name', 'stored_name', 'mime', 'size_bytes'] as $col) {
            $sets[] = "`$col` = ?";
            $params[] = $newFile[$col];
        }
        $sets[] = '`content_text` = ?';
        $params[] = $newFile['content_text'] ?: null;
    }
    $params[] = $id;
    $params[] = $orgId;
    try {
        db()->prepare('UPDATE documents SET ' . implode(', ', $sets) . ' WHERE id = ? AND organization_id = ?')->execute($params);
    } catch (Throwable $e) {
        if ($newFile) {
            @unlink(planner_document_path($orgId, $newFile['stored_name']));
        }
        throw $e;
    }
    if ($newFile) {
        @unlink(planner_document_path($orgId, (string) $doc['stored_name']));
    }
    log_activity($orgId, $userId, 'update', 'document', $id, 'Документ изменён: ' . $fields['title']);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
