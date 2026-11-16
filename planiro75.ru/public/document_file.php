<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/documents.php';

$user = current_user();
if (!$user || planner_login_block_message($user) !== null) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Доступ запрещён';
    exit;
}
planner_maintenance_enforce_page($user);

$orgId = (int) $user['organization_id'];
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !planner_documents_ensure_schema()) {
    http_response_code(404);
    exit;
}
$st = db()->prepare('SELECT original_name, stored_name, mime FROM documents WHERE id = ? AND organization_id = ? LIMIT 1');
$st->execute([$id, $orgId]);
$doc = $st->fetch(PDO::FETCH_ASSOC);
$path = $doc ? planner_document_path($orgId, (string) $doc['stored_name']) : '';
if (!$doc || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Файл не найден';
    exit;
}
db()->prepare('UPDATE documents SET downloads = downloads + 1 WHERE id = ?')->execute([$id]);

$name = (string) $doc['original_name'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$inlineTypes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'txt' => 'text/plain; charset=utf-8'];
$inline = !empty($_GET['inline']) && isset($inlineTypes[$ext]);
$mime = $inline ? $inlineTypes[$ext] : ((string) $doc['mime'] ?: 'application/octet-stream');
$ascii = trim(preg_replace('/[^A-Za-z0-9.-]+/', '_', pathinfo($name, PATHINFO_FILENAME)) ?? '', '_.');
$ascii = ($ascii !== '' ? $ascii : 'document') . ($ext !== '' ? '.' . $ext : '');

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, max-age=0');
if ($inline && $ext === 'txt') {
    $text = (string) file_get_contents($path);
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1251');
    }
    header('Content-Length: ' . (string) strlen($text));
    echo $text;
    exit;
}
readfile($path);
exit;
