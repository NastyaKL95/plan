<?php
declare(strict_types=1);

/**
 * Минимальная генерация .xlsx (Office Open XML) без внешних библиотек.
 * Единый стиль: заголовок отчёта (заливка, жирный), строка заголовков колонок (жирный, границы), данные (границы).
 */

function planner_xlsx_xml_escape(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Индекс колонки 0-based → A, B, … Z, AA, … */
function planner_xlsx_col_letter(int $colIndex): string
{
    $s = '';
    $n = $colIndex + 1;
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = intdiv($n - 1, 26);
    }
    return $s;
}

/**
 * @param list<string> $headers
 * @param list<list<string|int|float>> $rows
 */
function planner_xlsx_build(
    string $sheetName,
    string $reportTitle,
    array $headers,
    array $rows
): string {
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Расширение PHP zip (ZipArchive) недоступно.');
    }
    $sheetName = planner_xlsx_sanitize_sheet_name($sheetName);
    $numCols = max(1, count($headers));
    $lastCol = planner_xlsx_col_letter($numCols - 1);
    $lastRow = 2 + count($rows);
    $dim = 'A1:' . $lastCol . $lastRow;

    $sheetXml = planner_xlsx_sheet_xml($reportTitle, $headers, $rows, $lastCol, $numCols);
    $stylesXml = planner_xlsx_styles_xml();

    $tmp = tempnam(sys_get_temp_dir(), 'plxlsx');
    if ($tmp === false) {
        throw new RuntimeException('Не удалось создать временный файл.');
    }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Не удалось открыть ZIP для XLSX.');
    }

    $zip->addFromString('[Content_Types].xml', planner_xlsx_content_types_xml());
    $zip->addFromString('_rels/.rels', planner_xlsx_root_rels_xml());
    $zip->addFromString('xl/workbook.xml', planner_xlsx_workbook_xml($sheetName));
    $zip->addFromString('xl/_rels/workbook.xml.rels', planner_xlsx_workbook_rels_xml());
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $bytes = file_get_contents($tmp);
    @unlink($tmp);
    if ($bytes === false) {
        throw new RuntimeException('Не удалось прочитать сформированный XLSX.');
    }
    return $bytes;
}

function planner_xlsx_sanitize_sheet_name(string $name): string
{
    $name = str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', $name);
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        $name = 'Отчёт';
    }
    return mb_substr($name, 0, 31, 'UTF-8');
}

function planner_xlsx_content_types_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function planner_xlsx_root_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function planner_xlsx_workbook_xml(string $sheetName): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . planner_xlsx_xml_escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function planner_xlsx_workbook_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function planner_xlsx_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3">'
        . '<font><sz val="11"/><color rgb="FF1E293B"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="14"/><color rgb="FF0F172A"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="2">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF5"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border>'
        . '<left style="thin"><color rgb="FF94A3B8"/></left>'
        . '<right style="thin"><color rgb="FF94A3B8"/></right>'
        . '<top style="thin"><color rgb="FF94A3B8"/></top>'
        . '<bottom style="thin"><color rgb="FF94A3B8"/></bottom>'
        . '<diagonal/>'
        . '</border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
        . '<xf numFmtId="0" fontId="2" fillId="1" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
        . '</cellXfs>'
        . '</styleSheet>';
}

/**
 * @param list<string> $headers
 * @param list<list<string|int|float>> $rows
 */
function planner_xlsx_sheet_xml(
    string $reportTitle,
    array $headers,
    array $rows,
    string $lastCol,
    int $numCols
): string {
    $dimLastRow = 2 + count($rows);
    $mergeRef = 'A1:' . $lastCol . '1';

    $parts = [];
    $parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $parts[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $parts[] = '<dimension ref="A1:' . $lastCol . $dimLastRow . '"/>';
    $parts[] = '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
    $parts[] = '<sheetFormatPr defaultRowHeight="15"/>';
    $parts[] = '<cols>';
    for ($c = 0; $c < $numCols; $c++) {
        $w = $c === 0 ? 38 : 22;
        $parts[] = '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $parts[] = '</cols>';
    $parts[] = '<sheetData>';

    // Row 1: заголовок отчёта (стиль 2 = жирный + заливка + границы)
    $parts[] = '<row r="1" ht="22" customHeight="1">';
    $parts[] = '<c r="A1" s="2" t="inlineStr"><is><t>' . planner_xlsx_xml_escape($reportTitle) . '</t></is></c>';
    for ($c = 1; $c < $numCols; $c++) {
        $letter = planner_xlsx_col_letter($c);
        $parts[] = '<c r="' . $letter . '1" s="2"/>';
    }
    $parts[] = '</row>';

    // Row 2: заголовки колонок (стиль 1)
    $parts[] = '<row r="2">';
    for ($c = 0; $c < $numCols; $c++) {
        $letter = planner_xlsx_col_letter($c);
        $h = $headers[$c] ?? '';
        $parts[] = '<c r="' . $letter . '2" s="1" t="inlineStr"><is><t>' . planner_xlsx_xml_escape((string) $h) . '</t></is></c>';
    }
    $parts[] = '</row>';

    $rNum = 3;
    foreach ($rows as $row) {
        $parts[] = '<row r="' . $rNum . '">';
        for ($c = 0; $c < $numCols; $c++) {
            $letter = planner_xlsx_col_letter($c);
            $val = $row[$c] ?? '';
            if (is_int($val) || is_float($val)) {
                $parts[] = '<c r="' . $letter . $rNum . '" s="0"><v>' . planner_xlsx_xml_escape((string) $val) . '</v></c>';
            } else {
                $parts[] = '<c r="' . $letter . $rNum . '" s="0" t="inlineStr"><is><t>'
                    . planner_xlsx_xml_escape((string) $val) . '</t></is></c>';
            }
        }
        $parts[] = '</row>';
        $rNum++;
    }

    $parts[] = '</sheetData>';
    $parts[] = '<mergeCells count="1"><mergeCell ref="' . $mergeRef . '"/></mergeCells>';
    $parts[] = '</worksheet>';

    return implode('', $parts);
}
