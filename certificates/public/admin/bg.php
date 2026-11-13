<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

require_admin();
$tpl = cert_template((int) ($_GET['id'] ?? 0));
$path = $tpl ? cert_background_path($tpl) : null;
if ($path === null) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, max-age=86400');
readfile($path);
