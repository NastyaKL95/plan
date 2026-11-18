<?php
declare(strict_types=1);

const BULK_MAX_ROWS = 2000;

/**
 * Строки списка из загруженного файла (.xlsx, .csv, .txt) или вставленного из Excel текста.
 *
 * @return list<list<string>>
 */
function bulk_parse_input(?array $file, string $pasted): array
{
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Файл не загрузился. Возможно, он слишком большой.');
        }
        $bin = (string) file_get_contents((string) $file['tmp_name']);
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xls') {
            throw new RuntimeException('Старый формат .xls не поддерживается. Сохраните таблицу как .xlsx или скопируйте строки и вставьте их в поле ниже.');
        }
        $rows = str_starts_with($bin, "PK\x03\x04") ? bulk_read_xlsx($bin) : bulk_read_text($bin, true);
    } else {
        $rows = bulk_read_text($pasted, false);
    }
    if ($rows === []) {
        throw new RuntimeException('В списке нет ни одной строки.');
    }
    if (count($rows) > BULK_MAX_ROWS) {
        throw new RuntimeException('В списке больше ' . BULK_MAX_ROWS . ' строк. Разделите его на части.');
    }

    return $rows;
}

/** @return list<list<string>> */
function bulk_read_text(string $text, bool $isFile): array
{
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    }
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1251');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $firstLine = strtok(ltrim($text, "\n"), "\n") ?: '';
    $delimiter = str_contains($firstLine, "\t") ? "\t" : (str_contains($firstLine, ';') ? ';' : ($isFile && str_contains($firstLine, ',') ? ',' : null));

    $rows = [];
    if ($delimiter === null) {
        foreach (explode("\n", $text) as $line) {
            $rows[] = [$line];
        }
    } else {
        $fh = fopen('php://temp', 'w+b');
        fwrite($fh, $text);
        rewind($fh);
        while (($cells = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(static fn($c) => (string) $c, $cells);
        }
        fclose($fh);
    }

    return bulk_clean_rows($rows);
}

/** @return list<list<string>> */
function bulk_read_xlsx(string $bin): array
{
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('На сервере нет расширения SimpleXML для чтения .xlsx. Сохраните таблицу как CSV.');
    }
    $files = zip_read_entries($bin, static fn(string $n): bool => $n === 'xl/sharedStrings.xml' || $n === 'xl/workbook.xml'
        || $n === 'xl/_rels/workbook.xml.rels' || str_starts_with($n, 'xl/worksheets/sheet'));
    $load = static function (string $xml): SimpleXMLElement {
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($doc === false) {
            throw new RuntimeException('Не удалось прочитать таблицу Excel.');
        }

        return $doc;
    };

    $sheetPath = null;
    if (isset($files['xl/workbook.xml'], $files['xl/_rels/workbook.xml.rels'])) {
        $wb = $load($files['xl/workbook.xml']);
        $first = $wb->sheets->sheet[0] ?? null;
        $rid = $first ? (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
        foreach ($load($files['xl/_rels/workbook.xml.rels'])->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');
                $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
    }
    if ($sheetPath === null || !isset($files[$sheetPath])) {
        $sheets = array_values(array_filter(array_keys($files), static fn($n) => str_starts_with($n, 'xl/worksheets/sheet')));
        natsort($sheets);
        $sheetPath = $sheets ? reset($sheets) : null;
    }
    if ($sheetPath === null) {
        throw new RuntimeException('В файле Excel не найдено ни одного листа.');
    }

    $shared = [];
    if (isset($files['xl/sharedStrings.xml'])) {
        foreach ($load($files['xl/sharedStrings.xml'])->si as $si) {
            $text = isset($si->t) ? (string) $si->t : '';
            foreach ($si->r as $run) {
                $text .= (string) $run->t;
            }
            $shared[] = $text;
        }
    }

    $rows = [];
    foreach ($load($files[$sheetPath])->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $col = count($cells);
            if (preg_match('/^([A-Z]+)/', $ref, $m)) {
                $col = 0;
                foreach (str_split($m[1]) as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $col--;
            }
            $type = (string) $c['t'];
            if ($type === 's') {
                $value = $shared[(int) $c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) $c->is->t;
                foreach ($c->is->r as $run) {
                    $value .= (string) $run->t;
                }
            } else {
                $value = (string) $c->v;
            }
            while (count($cells) < $col) {
                $cells[] = '';
            }
            $cells[$col] = $value;
        }
        $rows[] = $cells;
    }

    return bulk_clean_rows($rows);
}

/**
 * @param list<list<string>> $rows
 * @return list<list<string>>
 */
function bulk_clean_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $row = array_map(static fn($c) => trim(preg_replace('/\s+/u', ' ', (string) $c) ?? ''), $row);
        while ($row && end($row) === '') {
            array_pop($row);
        }
        if ($row) {
            $out[] = $row;
        }
    }

    return $out;
}

function bulk_norm(string $s): string
{
    $s = str_replace('ё', 'е', mb_strtolower($s));

    return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '');
}

/**
 * Подбирает столбцы для полей шаблона по заголовкам. Если заголовков нет — по порядку.
 *
 * @param list<list<string>> $rows
 * @return array{header: bool, map: array<string, list<int>>}
 */
function bulk_guess_mapping(array $tpl, array $rows): array
{
    $synonyms = [
        'fio' => ['фио', 'ф и о', 'фамилия имя отчество', 'участник', 'слушатель', 'фио участника', 'фио слушателя'],
        'org' => ['организация', 'оо', 'оу', 'образовательная организация', 'учреждение', 'место работы', 'школа', 'наименование организации'],
    ];
    $head = array_map('bulk_norm', $rows[0] ?? []);
    $map = [];
    foreach ($tpl['inputs'] as $input) {
        $names = array_merge([bulk_norm($input['label']), bulk_norm($input['key'])], $synonyms[$input['key']] ?? []);
        foreach ($head as $i => $h) {
            if ($h !== '' && in_array($h, $names, true)) {
                $map[$input['key']] = [$i];
                break;
            }
        }
    }
    if (!isset($map['fio']) && in_array('fio', array_column($tpl['inputs'], 'key'), true)) {
        $parts = [];
        foreach (['фамилия', 'имя', 'отчество'] as $word) {
            $i = array_search($word, $head, true);
            if ($i !== false) {
                $parts[] = (int) $i;
            }
        }
        if (count($parts) >= 2) {
            $map['fio'] = $parts;
        }
    }
    if ($map !== []) {
        return ['header' => true, 'map' => $map];
    }
    $i = 0;
    foreach ($tpl['inputs'] as $input) {
        $map[$input['key']] = [$i++];
    }

    return ['header' => false, 'map' => $map];
}

/** @param array<string, list<int>> $map */
function bulk_row_values(array $tpl, array $row, array $map): array
{
    $v = [];
    foreach ($tpl['inputs'] as $input) {
        $parts = [];
        foreach ($map[$input['key']] ?? [] as $col) {
            if (($row[$col] ?? '') !== '') {
                $parts[] = $row[$col];
            }
        }
        $v[$input['key']] = implode(' ', $parts);
    }

    return $v;
}

function bulk_column_name(int $i): string
{
    $name = '';
    for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) {
        $name = chr(65 + ($n - 1) % 26) . $name;
    }

    return $name;
}
