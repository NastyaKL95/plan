<?php
declare(strict_types=1);

require_once __DIR__ . '/pdf.php';

const CERT_LINE_HEIGHT = 1.2;

/** @return array<string, array{file:string,label:string,family:string,weight:int,style:string}> */
function cert_fonts(): array
{
    return [
        'tinos' => ['file' => 'Tinos-Regular.ttf', 'label' => 'С засечками', 'family' => 'CertTinos', 'weight' => 400, 'style' => 'normal'],
        'tinos-bold' => ['file' => 'Tinos-Bold.ttf', 'label' => 'С засечками, жирный', 'family' => 'CertTinos', 'weight' => 700, 'style' => 'normal'],
        'tinos-italic' => ['file' => 'Tinos-Italic.ttf', 'label' => 'С засечками, курсив', 'family' => 'CertTinos', 'weight' => 400, 'style' => 'italic'],
        'tinos-bolditalic' => ['file' => 'Tinos-BoldItalic.ttf', 'label' => 'С засечками, жирный курсив', 'family' => 'CertTinos', 'weight' => 700, 'style' => 'italic'],
        'arimo' => ['file' => 'Arimo-Regular.ttf', 'label' => 'Без засечек', 'family' => 'CertArimo', 'weight' => 400, 'style' => 'normal'],
        'arimo-bold' => ['file' => 'Arimo-Bold.ttf', 'label' => 'Без засечек, жирный', 'family' => 'CertArimo', 'weight' => 700, 'style' => 'normal'],
    ];
}

function cert_font(string $key): TtfFont
{
    static $cache = [];
    $fonts = cert_fonts();
    if (!isset($fonts[$key])) {
        $key = 'tinos';
    }
    if (!isset($cache[$key])) {
        $cache[$key] = new TtfFont(CERT_PUBLIC . '/assets/fonts/' . $fonts[$key]['file'], pathinfo($fonts[$key]['file'], PATHINFO_FILENAME));
    }

    return $cache[$key];
}

/** Подстановки, которые система заполняет сама. */
function cert_system_placeholders(): array
{
    return [
        'number' => 'номер сертификата',
        'date' => 'дата выдачи, 13.11.2026',
        'date_long' => 'дата выдачи, «13» ноября 2026 г.',
        'code' => 'код проверки',
        'verify_url' => 'адрес страницы проверки',
        'title' => 'название шаблона',
    ];
}

function cert_default_inputs(): array
{
    return [
        ['key' => 'fio', 'label' => 'Фамилия, имя, отчество', 'hint' => 'Например: Иванова Анна Сергеевна', 'required' => true, 'maxlen' => 120, 'transform' => 'title'],
        ['key' => 'org', 'label' => 'Организация', 'hint' => 'Полное или краткое название', 'required' => true, 'maxlen' => 255, 'transform' => 'none'],
    ];
}

function cert_default_fields(): array
{
    return [
        ['text' => '{fio}', 'x' => 0.5, 'y' => 0.46, 'size' => 34, 'font' => 'tinos-bolditalic', 'color' => '#1f3b63', 'align' => 'center', 'maxw' => 0.7, 'lines' => 1],
        ['text' => '{org}', 'x' => 0.5, 'y' => 0.56, 'size' => 18, 'font' => 'tinos', 'color' => '#333333', 'align' => 'center', 'maxw' => 0.7, 'lines' => 2],
        ['text' => '№ {number} от {date}', 'x' => 0.08, 'y' => 0.93, 'size' => 9, 'font' => 'arimo', 'color' => '#555555', 'align' => 'left', 'maxw' => 0.4, 'lines' => 1],
        ['text' => 'Проверка подлинности: {verify_url}', 'x' => 0.92, 'y' => 0.93, 'size' => 9, 'font' => 'arimo', 'color' => '#555555', 'align' => 'right', 'maxw' => 0.5, 'lines' => 1],
    ];
}

function cert_normalize_inputs($raw): array
{
    $out = [];
    $seen = [];
    foreach (is_array($raw) ? $raw : [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string) ($item['key'] ?? '')) ?? '');
        $label = trim((string) ($item['label'] ?? ''));
        if ($key === '' || $label === '' || isset($seen[$key]) || isset(cert_system_placeholders()[$key])) {
            continue;
        }
        $seen[$key] = true;
        $transform = (string) ($item['transform'] ?? 'none');
        $out[] = [
            'key' => substr($key, 0, 30),
            'label' => mb_substr($label, 0, 120),
            'hint' => mb_substr(trim((string) ($item['hint'] ?? '')), 0, 200),
            'required' => !empty($item['required']),
            'maxlen' => max(1, min(500, (int) ($item['maxlen'] ?? 200))),
            'transform' => in_array($transform, ['none', 'upper', 'title'], true) ? $transform : 'none',
        ];
    }

    return array_slice($out, 0, 12);
}

function cert_normalize_fields($raw): array
{
    $fonts = cert_fonts();
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $text = mb_substr(trim((string) ($item['text'] ?? '')), 0, 500);
        if ($text === '') {
            continue;
        }
        $color = (string) ($item['color'] ?? '#000000');
        $align = (string) ($item['align'] ?? 'center');
        $font = (string) ($item['font'] ?? 'tinos');
        $out[] = [
            'text' => $text,
            'x' => round(max(0.0, min(1.0, (float) ($item['x'] ?? 0.5))), 4),
            'y' => round(max(0.0, min(1.0, (float) ($item['y'] ?? 0.5))), 4),
            'size' => round(max(4.0, min(160.0, (float) ($item['size'] ?? 14))), 1),
            'font' => isset($fonts[$font]) ? $font : 'tinos',
            'color' => preg_match('/^#[0-9a-f]{6}$/i', $color) ? strtolower($color) : '#000000',
            'align' => in_array($align, ['left', 'center', 'right'], true) ? $align : 'center',
            'maxw' => round(max(0.05, min(1.0, (float) ($item['maxw'] ?? 0.8))), 4),
            'lines' => max(1, min(6, (int) ($item['lines'] ?? 1))),
        ];
    }

    return array_slice($out, 0, 30);
}

function cert_template(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM cert_templates WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $row['inputs'] = cert_normalize_inputs(json_decode((string) $row['inputs_json'], true));
    $row['fields'] = cert_normalize_fields(json_decode((string) $row['fields_json'], true));

    return $row;
}

function cert_background_path(array $template): ?string
{
    $file = (string) ($template['bg_file'] ?? '');
    if ($file === '' || !preg_match('/^[a-f0-9]{32}\.jpg$/', $file)) {
        return null;
    }
    $path = CERT_STORAGE . '/backgrounds/' . $file;

    return is_file($path) ? $path : null;
}

/** @return array{0:float,1:float} ширина и высота страницы в пунктах */
function cert_page_size(array $template): array
{
    $w = (int) ($template['bg_width'] ?? 0);
    $h = (int) ($template['bg_height'] ?? 0);
    if ($w <= 0 || $h <= 0) {
        return [841.89, 595.28];
    }
    $base = $w >= $h ? 841.89 : 595.28;

    return [$base, round($base * $h / $w, 2)];
}

function cert_clean_input(string $value, int $maxlen): string
{
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

    return mb_substr($value, 0, $maxlen);
}

function cert_transform(string $value, string $transform): string
{
    if ($transform === 'upper') {
        return mb_strtoupper($value);
    }
    if ($transform === 'title') {
        return mb_convert_case(mb_strtolower($value), MB_CASE_TITLE);
    }

    return $value;
}

function cert_dedupe_hash(array $template, array $values): string
{
    $parts = [];
    foreach ($template['inputs'] as $input) {
        $v = mb_strtolower((string) ($values[$input['key']] ?? ''));
        $parts[] = str_replace('ё', 'е', preg_replace('/\s+/u', ' ', trim($v)) ?? '');
    }

    return hash('sha256', implode("\x1F", $parts));
}

function cert_date_long(string $date): string
{
    $months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $ts = strtotime($date) ?: time();

    return '«' . date('j', $ts) . '» ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts) . ' г.';
}

function cert_format_number(array $template, int $seq): string
{
    $digits = max(1, min(10, (int) ($template['number_digits'] ?? 4)));

    return (string) ($template['number_prefix'] ?? '') . str_pad((string) $seq, $digits, '0', STR_PAD_LEFT);
}

/**
 * @param array<string, string> $values значения полей слушателя
 * @param array{number?:string,code?:string,issued_on?:string} $meta
 * @return array<string, string>
 */
function cert_placeholder_values(array $template, array $values, array $meta): array
{
    $issued = (string) ($meta['issued_on'] ?? date('Y-m-d'));
    $code = (string) ($meta['code'] ?? '');
    $map = [
        'number' => (string) ($meta['number'] ?? ''),
        'date' => date('d.m.Y', strtotime($issued) ?: time()),
        'date_long' => cert_date_long($issued),
        'code' => $code,
        'verify_url' => $code !== '' ? absolute_url('verify.php?code=' . $code) : '',
        'title' => (string) ($template['title'] ?? ''),
    ];
    foreach ($template['inputs'] as $input) {
        $map[$input['key']] = (string) ($values[$input['key']] ?? '');
    }

    return $map;
}

function cert_fill(string $text, array $map): string
{
    return trim(preg_replace_callback('/\{([a-z0-9_]+)\}/i', static function (array $m) use ($map): string {
        return $map[strtolower($m[1])] ?? '';
    }, $text) ?? '');
}

/** @return list<string> */
function cert_wrap(TtfFont $font, string $text, float $size, float $maxWidth): array
{
    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $try = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && $font->textWidth($try) * $size / $font->unitsPerEm > $maxWidth) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

/** @return array{0:float,1:list<string>} */
function cert_fit(TtfFont $font, string $text, float $size, float $maxWidth, int $maxLines): array
{
    $min = max(4.0, $size * 0.5);
    for ($s = $size; $s >= $min; $s -= 0.5) {
        $lines = cert_wrap($font, $text, $s, $maxWidth);
        if (count($lines) > $maxLines) {
            continue;
        }
        $fits = true;
        foreach ($lines as $line) {
            if ($font->textWidth($line) * $s / $font->unitsPerEm > $maxWidth + 0.01) {
                $fits = false;
                break;
            }
        }
        if ($fits) {
            return [$s, $lines];
        }
    }

    return [$min, cert_wrap($font, $text, $min, $maxWidth)];
}

function cert_render_pdf(array $template, array $map): string
{
    [$w, $h] = cert_page_size($template);
    $pdf = new CertPdf($w, $h);
    static $bgCache = [];
    $bg = cert_background_path($template);
    if ($bg !== null) {
        if (!isset($bgCache[$bg])) {
            $info = getimagesize($bg);
            $bgCache = [$bg => $info !== false && ($info[2] ?? 0) === IMAGETYPE_JPEG
                ? [(string) file_get_contents($bg), (int) $info[0], (int) $info[1], (int) ($info['channels'] ?? 3)] : null];
        }
        if ($bgCache[$bg] !== null) {
            $pdf->setBackground(...$bgCache[$bg]);
        }
    }
    foreach (array_keys(cert_fonts()) as $key) {
        $pdf->addFont($key, cert_font($key));
    }
    foreach ($template['fields'] as $field) {
        $text = cert_fill($field['text'], $map);
        if ($text === '') {
            continue;
        }
        $font = cert_font($field['font']);
        $maxWidth = $field['maxw'] * $w;
        [$size, $lines] = cert_fit($font, $text, (float) $field['size'], $maxWidth, (int) $field['lines']);
        $rgb = [
            hexdec(substr($field['color'], 1, 2)) / 255,
            hexdec(substr($field['color'], 3, 2)) / 255,
            hexdec(substr($field['color'], 5, 2)) / 255,
        ];
        $anchor = $field['x'] * $w;
        $baseline = $h - $field['y'] * $h;
        foreach ($lines as $i => $line) {
            $lineWidth = $font->textWidth($line) * $size / $font->unitsPerEm;
            $x = $anchor;
            if ($field['align'] === 'center') {
                $x -= $lineWidth / 2;
            } elseif ($field['align'] === 'right') {
                $x -= $lineWidth;
            }
            $pdf->text($field['font'], $size, $rgb, $x, $baseline - $i * $size * CERT_LINE_HEIGHT, $line);
        }
    }

    return $pdf->output((string) ($template['title'] ?? 'Сертификат'));
}

function cert_sample_values(array $template): array
{
    $samples = ['fio' => 'Иванова Анна Сергеевна', 'org' => 'МОУ «Средняя общеобразовательная школа № 1» г. Читы'];
    $values = [];
    foreach ($template['inputs'] as $input) {
        $values[$input['key']] = cert_transform($samples[$input['key']] ?? $input['label'], $input['transform']);
    }

    return $values;
}

/**
 * Проверка загруженного фона. Ошибка — RuntimeException с текстом для администратора.
 *
 * @return array{tmp:string,w:int,h:int}|null null — файл не выбран
 */
function cert_uploaded_background(?array $file): ?array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Файл фона не загрузился. Возможно, он слишком большой.');
    }
    if ((int) $file['size'] > (int) cfg('max_background_mb') * 1024 * 1024) {
        throw new RuntimeException('Фон больше ' . (int) cfg('max_background_mb') . ' МБ.');
    }
    $info = @getimagesize((string) $file['tmp_name']);
    if ($info === false || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
        throw new RuntimeException('Фон должен быть картинкой JPG. PNG браузер переводит в JPG сам — обновите страницу и выберите файл ещё раз.');
    }
    if (($info['channels'] ?? 3) === 4) {
        throw new RuntimeException('JPG в цветах CMYK не поддерживается. Сохраните картинку в RGB.');
    }
    if (max((int) $info[0], (int) $info[1]) < 600) {
        throw new RuntimeException('Картинка слишком маленькая для печати: нужно хотя бы 600 точек по длинной стороне.');
    }

    return ['tmp' => (string) $file['tmp_name'], 'w' => (int) $info[0], 'h' => (int) $info[1]];
}

/** Шаблон с полями из формы редактора (для сохранения и предпросмотра). */
function cert_template_from_post(array $tpl, array $post): array
{
    $tpl['title'] = mb_substr(trim((string) ($post['title'] ?? $tpl['title'])), 0, 255) ?: $tpl['title'];
    $tpl['inputs'] = cert_normalize_inputs(json_decode((string) ($post['inputs_json'] ?? ''), true));
    $tpl['fields'] = cert_normalize_fields(json_decode((string) ($post['fields_json'] ?? ''), true));
    $tpl['number_prefix'] = mb_substr(trim((string) ($post['number_prefix'] ?? '')), 0, 40);
    $tpl['number_digits'] = max(1, min(10, (int) ($post['number_digits'] ?? 4)));

    return $tpl;
}

/**
 * Значения из формы слушателя: очистка, регистр, проверка обязательных.
 *
 * @return array{0: array<string,string>, 1: array<string,string>} [значения, ошибки по ключам]
 */
function cert_collect_values(array $tpl, array $post): array
{
    $values = [];
    $errors = [];
    foreach ($tpl['inputs'] as $input) {
        $v = cert_transform(cert_clean_input((string) ($post['v'][$input['key']] ?? ''), (int) $input['maxlen']), (string) $input['transform']);
        if ($v === '' && $input['required']) {
            $errors[$input['key']] = 'Заполните поле.';
        }
        $values[$input['key']] = $v;
    }

    return [$values, $errors];
}

/**
 * Выдаёт сертификат. Повторный запрос с теми же данными возвращает уже выданный.
 * Номер берётся под блокировкой строки шаблона, поэтому номера идут без пропусков и повторов.
 *
 * @return array{0: array, 1: bool} [строка cert_issued, выдан ли сейчас]
 */
function cert_issue(array $tpl, ?array $link, array $values, ?string $ip): array
{
    $pdo = db();
    $hash = cert_dedupe_hash($tpl, $values);
    $find = $pdo->prepare('SELECT * FROM cert_issued WHERE template_id = ? AND dedupe_hash = ?');
    $find->execute([$tpl['id'], $hash]);
    $row = $find->fetch();
    if ($row) {
        return [$row, false];
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT next_number FROM cert_templates WHERE id = ? FOR UPDATE');
        $lock->execute([$tpl['id']]);
        $seq = (int) $lock->fetchColumn();
        if ($link && !empty($link['max_uses'])) {
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM cert_issued WHERE link_id = ?');
            $cnt->execute([$link['id']]);
            if ((int) $cnt->fetchColumn() >= (int) $link['max_uses']) {
                throw new RuntimeException('По этой ссылке уже выдано максимальное число сертификатов.');
            }
        }
        $exists = $pdo->prepare('SELECT 1 FROM cert_issued WHERE code = ?');
        do {
            $code = random_code(10);
            $exists->execute([$code]);
        } while ($exists->fetchColumn());

        $pdo->prepare(
            'INSERT INTO cert_issued (template_id, link_id, number, code, values_json, dedupe_hash, issued_on, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $tpl['id'], $link['id'] ?? null, cert_format_number($tpl, $seq), $code,
            json_encode($values, JSON_UNESCAPED_UNICODE), $hash, date('Y-m-d'), $ip,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE cert_templates SET next_number = ? WHERE id = ?')->execute([$seq + 1, $tpl['id']]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {
            $find->execute([$tpl['id'], $hash]);
            $row = $find->fetch();
            if ($row) {
                return [$row, false];
            }
        }
        throw $e;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $get = $pdo->prepare('SELECT * FROM cert_issued WHERE id = ?');
    $get->execute([$id]);

    return [$get->fetch(), true];
}

function cert_issued_by_code(string $code): ?array
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if ($code === '' || strlen($code) > 16) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM cert_issued WHERE code = ?');
    $st->execute([$code]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $row['values'] = json_decode((string) $row['values_json'], true) ?: [];

    return $row;
}

function cert_issued_pdf(array $tpl, array $issued): string
{
    $values = is_array($issued['values'] ?? null) ? $issued['values'] : (json_decode((string) $issued['values_json'], true) ?: []);

    return cert_render_pdf($tpl, cert_placeholder_values($tpl, $values, [
        'number' => $issued['number'],
        'code' => $issued['code'],
        'issued_on' => $issued['issued_on'],
    ]));
}

/** @return list<string> */
function cert_registry_head(array $tpl): array
{
    $head = ['Номер'];
    foreach ($tpl['inputs'] as $input) {
        $head[] = $input['label'];
    }
    array_push($head, 'Дата выдачи', 'Код проверки', 'Ссылка', 'Скачиваний', 'Статус', 'Проверка');

    return $head;
}

/** @param array $row строка cert_issued с полем link_note */
function cert_registry_line(array $tpl, array $row): array
{
    $values = json_decode((string) $row['values_json'], true) ?: [];
    $line = [$row['number']];
    foreach ($tpl['inputs'] as $input) {
        $line[] = (string) ($values[$input['key']] ?? '');
    }
    array_push(
        $line,
        date('d.m.Y', strtotime((string) $row['issued_on'])),
        $row['code'],
        $row['link_id'] ? ((string) ($row['link_note'] ?? '') ?: 'ссылка #' . $row['link_id']) : 'список или вручную',
        (int) $row['downloads'],
        $row['revoked_at'] ? 'аннулирован ' . format_dt($row['revoked_at']) : 'действителен',
        absolute_url('verify.php?code=' . $row['code'])
    );

    return $line;
}

function cert_csv_line(array $cells): string
{
    $fh = fopen('php://temp', 'w+b');
    fputcsv($fh, $cells, ';', '"', '');
    rewind($fh);
    $line = (string) stream_get_contents($fh);
    fclose($fh);

    return $line;
}

function cert_safe_filename(string $name): string
{
    $name = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $name) ?? '');
    $name = preg_replace('/\s+/u', ' ', $name) ?? '';

    return mb_substr($name, 0, 120) ?: 'certificate';
}

/**
 * Отдаёт ZIP с PDF всех переданных сертификатов и реестром в CSV. Аннулированные пропускаются.
 *
 * @param iterable<array> $rows строки cert_issued (с link_note)
 */
function cert_stream_zip(array $tpl, iterable $rows, string $archiveName): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    @set_time_limit(0);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $archiveName = cert_safe_filename($archiveName) . '.zip';
    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"certificates.zip\"; filename*=UTF-8''" . rawurlencode($archiveName));
    header('Cache-Control: private, no-store');
    header('X-Accel-Buffering: no');

    $zip = new ZipStreamWriter();
    $csv = "\xEF\xBB\xBF" . cert_csv_line(cert_registry_head($tpl));
    foreach ($rows as $row) {
        if (!empty($row['revoked_at'])) {
            continue;
        }
        $row['values'] = json_decode((string) $row['values_json'], true) ?: [];
        $name = cert_holder_name($tpl, $row['values']);
        $zip->add(cert_safe_filename($row['number'] . ($name !== '' ? ' — ' . $name : '')) . '.pdf', cert_issued_pdf($tpl, $row), false);
        $csv .= cert_csv_line(cert_registry_line($tpl, $row));
    }
    $zip->add('Реестр.csv', $csv);
    $zip->finish();
}

/** Первое заполненное поле — обычно ФИО. */
function cert_holder_name(array $tpl, array $values): string
{
    foreach ($tpl['inputs'] as $input) {
        if (($values[$input['key']] ?? '') !== '') {
            return (string) $values[$input['key']];
        }
    }

    return '';
}

function cert_send_pdf(string $pdf, string $filename, bool $inline = false): void
{
    $ascii = trim(preg_replace('/[^A-Za-z0-9.-]+/', '_', $filename) ?? '', '_.');
    $ascii = 'certificate' . ($ascii !== '' && $ascii !== 'pdf' ? '_' . preg_replace('/_?\.?pdf$/i', '', $ascii) : '') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
}

/** Проверка ссылки: null — можно выдавать, иначе текст причины. */
function cert_link_problem(array $link, int $issuedCount): ?string
{
    $now = time();
    if (empty($link['is_active'])) {
        return 'Ссылка отключена организатором.';
    }
    if (!empty($link['starts_at']) && strtotime((string) $link['starts_at']) > $now) {
        return 'Выдача сертификатов начнётся ' . format_dt((string) $link['starts_at']) . '.';
    }
    if (!empty($link['expires_at']) && strtotime((string) $link['expires_at']) <= $now) {
        return 'Срок действия ссылки истёк ' . format_dt((string) $link['expires_at']) . '.';
    }
    if (!empty($link['max_uses']) && $issuedCount >= (int) $link['max_uses']) {
        return 'По этой ссылке уже выдано максимальное число сертификатов.';
    }

    return null;
}
