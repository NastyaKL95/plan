<?php
declare(strict_types=1);

require __DIR__ . '/../lib/events.php';

$data = vitrina_load();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');
if ($data === null) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Источник событий недоступен'], JSON_UNESCAPED_UNICODE);
    exit;
}

$when = (string) ($_GET['when'] ?? 'all');
$cat = (string) ($_GET['cat'] ?? '');
$limit = max(1, min(500, (int) ($_GET['limit'] ?? 500)));
$nowIso = (new DateTimeImmutable('now'))->format(DATE_ATOM);
$events = array_values(array_filter($data['events'], static function (array $e) use ($when, $cat, $nowIso): bool {
    if ($cat !== '' && $e['category'] !== $cat) {
        return false;
    }
    if ($when === 'upcoming') {
        return $e['end'] >= $nowIso;
    }
    if ($when === 'past') {
        return $e['end'] < $nowIso;
    }
    return true;
}));
if ($when === 'past') {
    $events = array_reverse($events);
}

echo json_encode([
    'ok' => true,
    'updated_at' => $data['updated_at'],
    'source' => $data['source'],
    'categories' => $data['categories'],
    'events' => array_slice($events, 0, $limit),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
