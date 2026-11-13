<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('tags')) {
    json_response(['ok' => false, 'error' => 'not_configured'], 501);
}

$method = effective_request_method();

if ($method === 'GET') {
    $st = db()->prepare('SELECT id, name, color FROM tags WHERE organization_id = ? ORDER BY name');
    $st->execute([$orgId]);
    json_response(['ok' => true, 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'POST') {
    if (!user_may_manage_tags($user)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $body = read_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '' || strlen($name) > 80) {
        json_response(['ok' => false, 'error' => 'bad_name'], 422);
    }
    $color = trim((string) ($body['color'] ?? '#64748b'));
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $color = '#64748b';
    }
    try {
        $st = db()->prepare('INSERT INTO tags (organization_id, name, color) VALUES (?,?,?)');
        $st->execute([$orgId, $name, $color]);
    } catch (Throwable) {
        json_response(['ok' => false, 'error' => 'duplicate'], 422);
    }
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
