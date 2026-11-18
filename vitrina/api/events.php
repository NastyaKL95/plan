<?php
declare(strict_types=1);

require __DIR__ . '/../lib/events.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

try {
    $events = vitrina_public_events();
    $categories = vitrina_categories();
} catch (PDOException $ex) {
    error_log('vitrina api: ' . $ex->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Витрина временно недоступна'], JSON_UNESCAPED_UNICODE);
    exit;
}

$when = (string) ($_GET['when'] ?? 'all');
$cat = (string) ($_GET['cat'] ?? '');
$limit = max(1, min(500, (int) ($_GET['limit'] ?? 500)));
$nowIso = (new DateTimeImmutable('now'))->format(DATE_ATOM);
$events = array_values(array_filter($events, static function (array $e) use ($when, $cat, $nowIso): bool {
    if ($cat !== '' && $e['cat'] !== $cat) {
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

$host = preg_replace('~[^a-z0-9.\-:]~i', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
$base = ($host !== '' ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $host : '')
    . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/events.php', 2), '/') . '/';
$events = array_map(static function (array $e) use ($base): array {
    $e['url'] = $base . '#e-' . $e['id'];
    $e['ics'] = $base . 'ics.php?id=' . $e['id'];
    foreach (['image', 'imageLarge'] as $k) {
        $e[$k] = $e[$k] !== '' ? $base . $e[$k] : '';
    }
    return $e;
}, array_slice($events, 0, $limit));

echo json_encode([
    'ok' => true,
    'categories' => array_map(static fn (array $c): array => ['key' => $c['slug'], 'label' => $c['name'], 'color' => $c['color']], $categories),
    'events' => $events,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
