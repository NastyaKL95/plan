<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!in_array((string) ($user['role'] ?? ''), ['admin', 'owner'], true) && !user_is_institute_manager($user)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

$path = dirname(__DIR__, 2) . '/var/log/sql-slow.log';
if (!is_file($path)) {
    json_response(['ok' => true, 'items' => [], 'total' => 0, 'hint' => 'log_not_found']);
}

$rows = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($rows)) {
    json_response(['ok' => true, 'items' => [], 'total' => 0, 'hint' => 'read_failed']);
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
if ($limit <= 0 || $limit > 200) {
    $limit = 30;
}

$agg = [];
foreach ($rows as $line) {
    $j = json_decode((string) $line, true);
    if (!is_array($j)) {
        continue;
    }
    $ctx = (string) ($j['context'] ?? 'unknown');
    $sql = (string) ($j['sql'] ?? '');
    $key = md5($ctx . '|' . $sql);
    if (!isset($agg[$key])) {
        $agg[$key] = [
            'context' => $ctx,
            'sql' => $sql,
            'count' => 0,
            'max_ms' => 0.0,
            'sum_ms' => 0.0,
            'last_ts' => null,
        ];
    }
    $ms = (float) ($j['elapsed_ms'] ?? 0);
    $agg[$key]['count']++;
    $agg[$key]['sum_ms'] += $ms;
    if ($ms > $agg[$key]['max_ms']) {
        $agg[$key]['max_ms'] = $ms;
    }
    $agg[$key]['last_ts'] = (string) ($j['ts'] ?? $agg[$key]['last_ts']);
}

$items = array_values($agg);
foreach ($items as &$it) {
    $it['avg_ms'] = $it['count'] > 0 ? round($it['sum_ms'] / $it['count'], 2) : 0.0;
    $it['max_ms'] = round((float) $it['max_ms'], 2);
    unset($it['sum_ms']);
}
unset($it);

usort($items, static function (array $a, array $b): int {
    if ((float) $b['max_ms'] !== (float) $a['max_ms']) {
        return (float) $b['max_ms'] <=> (float) $a['max_ms'];
    }
    return (int) $b['count'] <=> (int) $a['count'];
});

json_response([
    'ok' => true,
    'total' => count($items),
    'items' => array_slice($items, 0, $limit),
]);

