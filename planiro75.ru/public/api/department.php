<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('departments')) {
    json_response(['ok' => false, 'error' => 'not_configured'], 501);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['ok' => false, 'error' => 'bad_id'], 422);
}

$st = db()->prepare(
    'SELECT id, name, sort_order FROM departments WHERE id = ? AND organization_id = ? LIMIT 1'
);
$st->execute([$id, $orgId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$method = effective_request_method();

if ($method === 'PATCH' || $method === 'PUT') {
    if (!user_may_manage_departments($user)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $body = read_json_body();
    $fields = [];
    $params = [];
    if (array_key_exists('name', $body)) {
        $name = trim((string) $body['name']);
        if ($name === '' || strlen($name) > 255) {
            json_response(['ok' => false, 'error' => 'bad_name'], 422);
        }
        $fields[] = 'name = ?';
        $params[] = $name;
    }
    if (array_key_exists('sort_order', $body)) {
        $so = (int) $body['sort_order'];
        if ($so < 0) {
            $so = 0;
        }
        $fields[] = 'sort_order = ?';
        $params[] = $so;
    }
    if ($fields === []) {
        json_response(['ok' => false, 'error' => 'no_fields'], 422);
    }
    $params[] = $id;
    $params[] = $orgId;
    $sql = 'UPDATE departments SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?';
    db()->prepare($sql)->execute($params);
    log_activity($orgId, $userId, 'update', 'department', $id, 'Изменено подразделение');
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    if (!user_may_manage_departments($user)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    if (planner_table_has_column('projects', 'department_id')) {
        $qc = db()->prepare(
            'SELECT COUNT(*) FROM projects WHERE department_id = ? AND organization_id = ?'
        );
        $qc->execute([$id, $orgId]);
        $n = (int) $qc->fetchColumn();
        if ($n > 0) {
            json_response(['ok' => false, 'error' => 'has_projects'], 409);
        }
    }
    db()->prepare('DELETE FROM departments WHERE id = ? AND organization_id = ?')->execute([$id, $orgId]);
    log_activity($orgId, $userId, 'delete', 'department', $id, 'Удалено подразделение');
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
