<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$method = effective_request_method();

if (!planner_table_exists('departments')) {
    if ($method === 'GET') {
        json_response(['ok' => true, 'items' => []]);
    }
    json_response(['ok' => false, 'error' => 'not_configured'], 501);
}

if ($method === 'GET') {
    $st = db()->prepare(
        'SELECT d.id, d.name, d.sort_order
         FROM departments d
         WHERE d.organization_id = ?
         ORDER BY d.sort_order, d.id'
    );
    $st->execute([$orgId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($items !== [] && planner_table_exists('department_heads')) {
        $ids = array_values(
            array_filter(
                array_map('intval', array_column($items, 'id')),
                static function ($x) {
                    return (int) $x > 0;
                }
            )
        );
        if ($ids !== []) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $qh = db()->prepare(
                "SELECT dh.department_id, dh.user_id, u.name FROM department_heads dh
                 INNER JOIN users u ON u.id = dh.user_id
                 WHERE dh.department_id IN ($ph)
                 ORDER BY u.name"
            );
            $qh->execute($ids);
            $byDept = [];
            $byDeptUsers = [];
            foreach ($qh->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $did = (int) $r['department_id'];
                if (!isset($byDept[$did])) {
                    $byDept[$did] = [];
                    $byDeptUsers[$did] = [];
                }
                $byDept[$did][] = $r['name'];
                $byDeptUsers[$did][] = [
                    'user_id' => (int) $r['user_id'],
                    'name' => (string) $r['name'],
                ];
            }
            foreach ($items as &$row) {
                $did = (int) $row['id'];
                $row['heads'] = $byDept[$did] ?? [];
                $row['head_users'] = $byDeptUsers[$did] ?? [];
            }
            unset($row);
        } else {
            foreach ($items as &$row) {
                $row['heads'] = [];
                $row['head_users'] = [];
            }
            unset($row);
        }
    } else {
        foreach ($items as &$row) {
            $row['heads'] = [];
            $row['head_users'] = [];
        }
        unset($row);
    }

    json_response(['ok' => true, 'items' => $items]);
}

if ($method === 'POST') {
    if (!user_may_manage_departments($user)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $body = read_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '' || strlen($name) > 255) {
        json_response(['ok' => false, 'error' => 'bad_name'], 422);
    }
    $sortOrder = isset($body['sort_order']) ? (int) $body['sort_order'] : 0;
    if ($sortOrder < 0) {
        $sortOrder = 0;
    }
    $st = db()->prepare(
        'INSERT INTO departments (organization_id, name, sort_order) VALUES (?,?,?)'
    );
    $st->execute([$orgId, $name, $sortOrder]);
    $id = (int) db()->lastInsertId();
    log_activity($orgId, $userId, 'create', 'department', $id, 'Создано подразделение: ' . $name);
    json_response(['ok' => true, 'id' => $id]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
