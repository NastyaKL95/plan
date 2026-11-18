<?php
declare(strict_types=1);

/**
 * Минимальное чтение первого листа .xlsx (без внешних библиотек).
 * Возвращает массив строк; каждая строка — список ячеек по колонкам A.. (пустые в конце обрезаются).
 *
 * @return list<list<string>>
 */
function planner_xlsx_read_first_sheet(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new InvalidArgumentException('Файл недоступен для чтения.');
    }
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Нужно расширение PHP zip.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Не удалось открыть файл как XLSX.');
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('В книге нет первого листа (sheet1).');
    }
    $shared = planner_xlsx_read_shared_strings($ssXml !== false ? $ssXml : null);

    $dom = new DOMDocument();
    if (!@$dom->loadXML($sheetXml)) {
        throw new RuntimeException('Повреждённый XML листа.');
    }
    $xp = new DOMXPath($dom);
    $rowNodes = $xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]');
    if ($rowNodes === false) {
        return [];
    }
    $out = [];
    foreach ($rowNodes as $rowNode) {
        $cells = [];
        $cNodes = $xp->query('.//*[local-name()="c"]', $rowNode);
        if ($cNodes === false) {
            $out[] = [];
            continue;
        }
        foreach ($cNodes as $c) {
            if (!$c instanceof DOMElement) {
                continue;
            }
            $ref = $c->getAttribute('r');
            $col = planner_xlsx_cell_col_index($ref);
            if ($col < 0) {
                continue;
            }
            $t = $c->getAttribute('t');
            $val = planner_xlsx_read_cell_value($c, $xp, $t, $shared);
            while (count($cells) <= $col) {
                $cells[] = '';
            }
            $cells[$col] = $val;
        }
        while ($cells !== []) {
            $last = count($cells) - 1;
            if ($last < 0 || $cells[$last] !== '') {
                break;
            }
            array_pop($cells);
        }
        $out[] = $cells;
    }

    return $out;
}

function planner_xlsx_cell_col_index(string $ref): int
{
    if ($ref === '' || !preg_match('/^([A-Z]+)(\d+)$/i', $ref, $m)) {
        return -1;
    }
    $letters = strtoupper($m[1]);
    $n = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

/**
 * @param list<string> $shared
 */
function planner_xlsx_read_cell_value(DOMElement $c, DOMXPath $xp, string $t, array $shared): string
{
    if ($t === 'inlineStr') {
        $nodes = $xp->query('.//*[local-name()="t"]', $c);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }
        $parts = [];
        foreach ($nodes as $tn) {
            $parts[] = $tn->textContent;
        }

        return trim(implode('', $parts));
    }
    $vNodes = $xp->query('.//*[local-name()="v"]', $c);
    if ($vNodes === false || $vNodes->length === 0) {
        return '';
    }
    $raw = trim((string) $vNodes->item(0)?->textContent);
    if ($t === 's' && $raw !== '' && ctype_digit($raw)) {
        $i = (int) $raw;

        return $shared[$i] ?? '';
    }

    return $raw;
}

/** @return list<string> */
function planner_xlsx_read_shared_strings(?string $xml): array
{
    if ($xml === null || $xml === '') {
        return [];
    }
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        return [];
    }
    $xp = new DOMXPath($dom);
    $siNodes = $xp->query('//*[local-name()="sst"]/*[local-name()="si"]');
    if ($siNodes === false) {
        return [];
    }
    $out = [];
    foreach ($siNodes as $si) {
        $texts = $xp->query('.//*[local-name()="t"]', $si);
        $parts = [];
        if ($texts !== false) {
            foreach ($texts as $t) {
                $parts[] = $t->textContent;
            }
        }
        $out[] = trim(implode('', $parts));
    }

    return $out;
}
