<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

require_admin();
$tpl = cert_template((int) ($_GET['id'] ?? 0));
if (!$tpl) {
    http_response_code(404);
    exit('Шаблон не найден.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $tpl = cert_template_from_post($tpl, $_POST);
    try {
        $upload = cert_uploaded_background($_FILES['bg'] ?? null);
    } catch (RuntimeException $e) {
        http_response_code(422);
        exit($e->getMessage());
    }
    if ($upload !== null) {
        $tmpName = bin2hex(random_bytes(16)) . '.jpg';
        $tmpPath = CERT_STORAGE . '/backgrounds/' . $tmpName;
        copy($upload['tmp'], $tmpPath);
        $tpl['bg_file'] = $tmpName;
        $tpl['bg_width'] = $upload['w'];
        $tpl['bg_height'] = $upload['h'];
    }
}

$map = cert_placeholder_values($tpl, cert_sample_values($tpl), [
    'number' => cert_format_number($tpl, (int) $tpl['next_number']),
    'code' => 'PRVW7K3M9Q',
    'issued_on' => date('Y-m-d'),
]);
try {
    $pdf = cert_render_pdf($tpl, $map);
} finally {
    if (isset($tmpPath)) {
        @unlink($tmpPath);
    }
}
cert_send_pdf($pdf, 'Предпросмотр — ' . $tpl['title'] . '.pdf', true);
