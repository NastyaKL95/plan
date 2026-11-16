<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

/**
 * Документы организации: инструкции, положения, регламенты, приказы.
 */

/** @return array<string, array{label: string, plural: string, color: string}> */
function planner_document_types(): array
{
    return [
        'instruction' => ['label' => 'Инструкция', 'plural' => 'Инструкции', 'color' => '#0ea5e9'],
        'regulation' => ['label' => 'Положение', 'plural' => 'Положения', 'color' => '#8b5cf6'],
        'reglament' => ['label' => 'Регламент', 'plural' => 'Регламенты', 'color' => '#f59e0b'],
        'order' => ['label' => 'Приказ', 'plural' => 'Приказы', 'color' => '#ef4444'],
        'other' => ['label' => 'Другой документ', 'plural' => 'Другие', 'color' => '#64748b'],
    ];
}

/** @return list<string> */
function planner_document_extensions(): array
{
    return ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp', 'png', 'jpg', 'jpeg', 'zip'];
}

const PLANNER_DOCUMENT_MAX_BYTES = 40 * 1024 * 1024;

/** Добавлять, менять и удалять документы — администратор и владелец организации. */
function user_may_manage_documents(array $user): bool
{
    return in_array($user['role'] ?? '', ['admin', 'owner'], true);
}

function planner_documents_ensure_schema(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $st = db()->query("SHOW TABLES LIKE 'documents'");
        if (!$st->fetch()) {
            db()->exec(planner_documents_create_sql());
        }
        $ready = true;
    } catch (Throwable $e) {
        error_log('planner documents schema: ' . $e->getMessage());
        $ready = false;
    }

    return $ready;
}

function planner_documents_create_sql(): string
{
    return "CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_type VARCHAR(20) NOT NULL,
  title VARCHAR(500) NOT NULL,
  doc_number VARCHAR(60) NULL,
  doc_date DATE NULL,
  description TEXT NULL,
  department_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(64) NOT NULL,
  mime VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  content_text MEDIUMTEXT NULL,
  downloads INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_documents_org_type (organization_id, doc_type, doc_date),
  KEY idx_documents_dept (department_id),
  CONSTRAINT fk_documents_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_documents_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function planner_document_path(int $orgId, string $storedName): string
{
    return planner_upload_root() . '/' . $orgId . '/documents/' . basename($storedName);
}

/**
 * Текст документа для поиска по содержимому (docx, odt, txt). Для остальных форматов — пустая строка.
 */
function planner_document_extract_text(string $path, string $ext): string
{
    $text = '';
    try {
        if ($ext === 'txt') {
            $text = (string) file_get_contents($path, false, null, 0, 2 * 1024 * 1024);
            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1251');
            }
        } elseif (($ext === 'docx' || $ext === 'odt') && class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $xml = (string) $zip->getFromName($ext === 'docx' ? 'word/document.xml' : 'content.xml');
                $zip->close();
                $xml = preg_replace('~</(w:p|text:p|text:h)>|<(w:br|w:tab|text:tab|text:line-break)\b[^>]*/?>~', "\n", $xml) ?? '';
                $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
    } catch (Throwable $e) {
        return '';
    }
    $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? '';
    $text = trim(preg_replace("/\s*\n\s*/u", "\n", $text) ?? '');

    return mb_substr($text, 0, 300000);
}

/** Фрагмент текста вокруг первого найденного слова. */
function planner_document_snippet(string $text, array $words, int $radius = 90): string
{
    if ($text === '' || $words === []) {
        return '';
    }
    $pos = false;
    foreach ($words as $w) {
        $p = mb_stripos($text, $w);
        if ($p !== false && ($pos === false || $p < $pos)) {
            $pos = $p;
        }
    }
    if ($pos === false) {
        return '';
    }
    $start = max(0, $pos - $radius);
    $snippet = mb_substr($text, $start, $radius * 2 + 40);
    $snippet = preg_replace('/\s+/u', ' ', $snippet) ?? $snippet;

    return ($start > 0 ? '…' : '') . trim($snippet) . ($start + mb_strlen($snippet) < mb_strlen($text) ? '…' : '');
}

/** @return list<string> */
function planner_document_search_words(string $q): array
{
    $q = mb_substr(trim($q), 0, 200);
    $words = [];
    foreach (preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
        // «№45-од» и «№ 45-од» ищут номер «45-од»: знак номера в поле обычно не хранится.
        $w = ltrim($w, '№#');
        if ($w !== '' && !in_array(mb_strtolower($w), array_map('mb_strtolower', $words), true)) {
            $words[] = $w;
        }
    }

    return array_slice($words, 0, 8);
}

function planner_document_public(array $row, bool $mayManage): array
{
    $types = planner_document_types();
    $ext = strtolower(pathinfo((string) $row['original_name'], PATHINFO_EXTENSION));

    return [
        'id' => (int) $row['id'],
        'doc_type' => (string) $row['doc_type'],
        'type_label' => $types[$row['doc_type']]['label'] ?? 'Документ',
        'title' => (string) $row['title'],
        'doc_number' => (string) ($row['doc_number'] ?? ''),
        'doc_date' => $row['doc_date'] ? (string) $row['doc_date'] : null,
        'description' => (string) ($row['description'] ?? ''),
        'department_id' => $row['department_id'] !== null ? (int) $row['department_id'] : null,
        'department_name' => (string) ($row['department_name'] ?? ''),
        'is_active' => (int) $row['is_active'] === 1,
        'is_pinned' => (int) $row['is_pinned'] === 1,
        'original_name' => (string) $row['original_name'],
        'ext' => $ext,
        'size_bytes' => (int) $row['size_bytes'],
        'has_text' => (int) ($row['has_text'] ?? 0) === 1,
        'downloads' => (int) $row['downloads'],
        'uploaded_by_name' => (string) ($row['uploaded_by_name'] ?? ''),
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
        'snippet' => (string) ($row['snippet'] ?? ''),
        'can_view_inline' => in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'txt'], true),
        'may_manage' => $mayManage,
    ];
}

/**
 * Сохраняет загруженный файл документа. Возвращает поля для БД или код ошибки API.
 *
 * @return array{ok: true, original_name: string, stored_name: string, mime: string, size_bytes: int, content_text: string}|array{ok: false, error: string}
 */
function planner_document_store_upload(int $orgId, ?array $file): array
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (!$file || $err === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'file_required'];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'file_too_large' : 'upload_error'];
    }
    $storageErr = planner_upload_storage_error();
    if ($storageErr !== null) {
        return ['ok' => false, 'error' => $storageErr];
    }
    $orig = trim(basename(str_replace('\\', '/', (string) ($file['name'] ?? 'document'))));
    if (mb_strlen($orig) > 200) {
        $orig = mb_substr($orig, 0, 200);
    }
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, planner_document_extensions(), true)) {
        return ['ok' => false, 'error' => 'bad_extension'];
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size > PLANNER_DOCUMENT_MAX_BYTES) {
        return ['ok' => false, 'error' => 'file_too_large'];
    }
    $tmp = (string) $file['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    $stored = planner_random_filename() . '.' . $ext;
    $dir = planner_ensure_upload_dir($orgId . '/documents');
    if (!move_uploaded_file($tmp, $dir . '/' . $stored)) {
        return ['ok' => false, 'error' => 'upload_failed'];
    }

    return [
        'ok' => true,
        'original_name' => $orig,
        'stored_name' => $stored,
        'mime' => $mime,
        'size_bytes' => $size,
        'content_text' => planner_document_extract_text($dir . '/' . $stored, $ext),
    ];
}

/**
 * Поля карточки документа из запроса. Возвращает [поля, код ошибки|null].
 *
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function planner_document_fields_from_request(int $orgId, array $in): array
{
    $type = (string) ($in['doc_type'] ?? '');
    if (!isset(planner_document_types()[$type])) {
        return [[], 'bad_type'];
    }
    $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')) ?? '');
    if ($title === '') {
        return [[], 'title_required'];
    }
    $date = trim((string) ($in['doc_date'] ?? ''));
    if ($date !== '') {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < 1900) {
            return [[], 'bad_date'];
        }
    }
    $deptId = (int) ($in['department_id'] ?? 0);
    if ($deptId > 0) {
        $st = db()->prepare('SELECT id FROM departments WHERE id = ? AND organization_id = ? LIMIT 1');
        $st->execute([$deptId, $orgId]);
        if (!$st->fetch()) {
            $deptId = 0;
        }
    }
    $flag = static fn($v): int => in_array($v, [true, 1, '1', 'true', 'on'], true) ? 1 : 0;
    $number = mb_substr(trim((string) ($in['doc_number'] ?? '')), 0, 60);
    $description = mb_substr(trim((string) ($in['description'] ?? '')), 0, 5000);

    return [[
        'doc_type' => $type,
        'title' => mb_substr($title, 0, 500),
        'doc_number' => $number !== '' ? $number : null,
        'doc_date' => $date !== '' ? $date : null,
        'description' => $description !== '' ? $description : null,
        'department_id' => $deptId > 0 ? $deptId : null,
        'is_active' => array_key_exists('is_active', $in) ? $flag($in['is_active']) : 1,
        'is_pinned' => $flag($in['is_pinned'] ?? 0),
    ], null];
}
