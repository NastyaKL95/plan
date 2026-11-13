<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/app.php';

$issued = cert_issued_by_code((string) ($_GET['code'] ?? ''));
$tpl = $issued ? cert_template((int) $issued['template_id']) : null;
if (!$issued || !$tpl) {
    http_response_code(404);
    page_start('Сертификат не найден');
    echo '<div class="card public-card"><div class="result-icon result-icon--bad">✕</div><h1 class="public-title">Сертификат не найден</h1>'
        . '<p class="lead">Проверьте код.</p></div>';
    page_end();
    exit;
}
if ($issued['revoked_at'] && !current_admin()) {
    http_response_code(410);
    page_start('Сертификат аннулирован');
    echo '<div class="card public-card"><div class="result-icon result-icon--bad">✕</div><h1 class="public-title">Сертификат аннулирован</h1>'
        . '<p class="lead">Сертификат № ' . h($issued['number']) . ' аннулирован ' . h(format_dt($issued['revoked_at'])) . '. Обратитесь к организатору.</p></div>';
    page_end();
    exit;
}

$pdf = cert_issued_pdf($tpl, $issued);
if (!current_admin()) {
    db()->prepare('UPDATE cert_issued SET downloads = downloads + 1 WHERE id = ?')->execute([$issued['id']]);
}
$name = cert_holder_name($tpl, $issued['values']);
$filename = 'Сертификат ' . $issued['number'] . ($name !== '' ? ' — ' . $name : '') . '.pdf';
cert_send_pdf($pdf, $filename, !empty($_GET['inline']));
