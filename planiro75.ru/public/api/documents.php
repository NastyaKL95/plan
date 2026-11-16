<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/documents.php';

if (!planner_documents_ensure_schema()) {
    json_response(['ok' => false, 'error' => 'schema_missing'], 503);
}

$mayManage = user_may_manage_documents($user);
$method = effective_request_method();

if ($method === 'POST') {
    if (!$mayManage) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    [$fields, $err] = planner_document_fields_from_request($orgId, $_POST);
    if ($err !== null) {
        json_response(['ok' => false, 'error' => $err], 422);
    }
    $file = planner_document_store_upload($orgId, $_FILES['file'] ?? null);
    if (!$file['ok']) {
        json_response(['ok' => false, 'error' => $file['error']], $file['error'] === 'upload_failed' ? 500 : 422);
    }
    $st = db()->prepare(
        'INSERT INTO documents (organization_id, doc_type, title, doc_number, doc_date, description, department_id, is_active, is_pinned,
                                original_name, stored_name, mime, size_bytes, content_text, uploaded_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    try {
        $st->execute([
            $orgId, $fields['doc_type'], $fields['title'], $fields['doc_number'], $fields['doc_date'], $fields['description'],
            $fields['department_id'], $fields['is_active'], $fields['is_pinned'],
            $file['original_name'], $file['stored_name'], $file['mime'], $file['size_bytes'], $file['content_text'] ?: null, $userId,
        ]);
    } catch (Throwable $e) {
        @unlink(planner_document_path($orgId, $file['stored_name']));
        throw $e;
    }
    $id = (int) db()->lastInsertId();
    log_activity($orgId, $userId, 'create', 'document', $id, 'Документ: ' . $fields['title']);
    json_response(['ok' => true, 'id' => $id]);
}

if ($method !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$types = planner_document_types();
$type = (string) ($_GET['type'] ?? '');
if (!isset($types[$type])) {
    $type = '';
}
$words = planner_document_search_words((string) ($_GET['q'] ?? ''));
$deptId = (int) ($_GET['dept'] ?? 0);
$year = (int) ($_GET['year'] ?? 0);
$status = (string) ($_GET['status'] ?? 'active');
$sort = (string) ($_GET['sort'] ?? 'date_desc');

$where = ['d.organization_id = ?'];
$params = [$orgId];
foreach ($words as $w) {
    $like = '%' . addcslashes($w, '%_\\') . '%';
    $where[] = '(d.title LIKE ? OR d.doc_number LIKE ? OR d.description LIKE ? OR d.original_name LIKE ? OR d.content_text LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($deptId > 0) {
    $where[] = 'd.department_id = ?';
    $params[] = $deptId;
}
if ($year > 0) {
    $where[] = 'YEAR(d.doc_date) = ?';
    $params[] = $year;
}
if ($status === 'active') {
    $where[] = 'd.is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'd.is_active = 0';
}

$baseWhere = implode(' AND ', $where);
$cnt = db()->prepare("SELECT d.doc_type, COUNT(*) AS n FROM documents d WHERE $baseWhere GROUP BY d.doc_type");
$cnt->execute($params);
$counts = array_fill_keys(array_keys($types), 0);
foreach ($cnt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($counts[$r['doc_type']])) {
        $counts[$r['doc_type']] = (int) $r['n'];
    }
}

$listWhere = $baseWhere;
$listParams = $params;
if ($type !== '') {
    $listWhere .= ' AND d.doc_type = ?';
    $listParams[] = $type;
}

$order = match ($sort) {
    'date_asc' => 'd.doc_date IS NULL, d.doc_date ASC, d.id ASC',
    'title' => 'd.title ASC, d.id ASC',
    'recent' => 'd.updated_at DESC, d.id DESC',
    default => 'd.doc_date IS NULL, d.doc_date DESC, d.id DESC',
};
$relevance = '';
if ($words && $sort === 'date_desc') {
    $relevance = '(' . implode(' + ', array_fill(0, count($words), '(d.title LIKE ?)')) . ') DESC, ';
    foreach ($words as $w) {
        $listParams[] = '%' . addcslashes($w, '%_\\') . '%';
    }
}
$contentSel = $words ? ', d.content_text' : '';
$st = db()->prepare(
    "SELECT d.id, d.doc_type, d.title, d.doc_number, d.doc_date, d.description, d.department_id, d.is_active, d.is_pinned,
            d.original_name, d.size_bytes, d.downloads, d.created_at, d.updated_at,
            (d.content_text IS NOT NULL AND d.content_text <> '') AS has_text,
            dep.name AS department_name, u.name AS uploaded_by_name $contentSel
     FROM documents d
     LEFT JOIN departments dep ON dep.id = d.department_id
     LEFT JOIN users u ON u.id = d.uploaded_by
     WHERE $listWhere
     ORDER BY d.is_pinned DESC, $relevance $order
     LIMIT 500"
);
$st->execute($listParams);
$items = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($words) {
        $inMeta = true;
        $meta = mb_strtolower($row['title'] . ' ' . $row['doc_number'] . ' ' . $row['description'] . ' ' . $row['original_name']);
        foreach ($words as $w) {
            if (mb_strpos($meta, mb_strtolower($w)) === false) {
                $inMeta = false;
                break;
            }
        }
        $row['snippet'] = $inMeta ? '' : planner_document_snippet((string) ($row['content_text'] ?? ''), $words);
    }
    $items[] = planner_document_public($row, $mayManage);
}

$yearsSt = db()->prepare('SELECT DISTINCT YEAR(doc_date) AS y FROM documents WHERE organization_id = ? AND doc_date IS NOT NULL ORDER BY y DESC');
$yearsSt->execute([$orgId]);

json_response([
    'ok' => true,
    'items' => $items,
    'counts' => $counts,
    'total' => array_sum($counts),
    'years' => array_map('intval', $yearsSt->fetchAll(PDO::FETCH_COLUMN)),
    'words' => $words,
]);
