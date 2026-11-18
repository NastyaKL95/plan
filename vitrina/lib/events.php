<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const VITRINA_FORMATS = ['offline' => 'Очно', 'online' => 'Онлайн', 'hybrid' => 'Очно и онлайн'];
const VITRINA_SESSION_KINDS = ['session' => 'Площадка', 'plenary' => 'Пленарное заседание', 'break' => 'Перерыв, регистрация'];

/** Иконки типов событий: содержимое SVG 24×24 со stroke="currentColor". */
function vitrina_icons(): array
{
    return [
        'conference' => ['Здание', '<path d="M3 21h18M5 21V10l7-5 7 5v11"/><path d="M9 21v-6h6v6"/>'],
        'seminar' => ['Доска', '<path d="M4 5h16v11H4z"/><path d="M8 20h8M12 16v4"/><path d="M8 9h8M8 12h5"/>'],
        'webinar' => ['Экран', '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/><path d="M10 8l4 2-4 2z"/>'],
        'symposium' => ['Люди', '<circle cx="12" cy="8" r="3"/><circle cx="5" cy="11" r="2"/><circle cx="19" cy="11" r="2"/><path d="M7 20c0-3 2.2-5 5-5s5 2 5 5M1.5 19c0-2 1.5-3.5 3.5-3.5M22.5 19c0-2-1.5-3.5-3.5-3.5"/>'],
        'contest' => ['Кубок', '<path d="M8 21h8M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>'],
        'course' => ['Книга', '<path d="M2 5.5C4.5 4 8 4 12 6c4-2 7.5-2 10-.5V19c-2.5-1.5-6-1.5-10 .5-4-2-7.5-2-10-.5z"/><path d="M12 6v13.5"/>'],
        'lecture' => ['Микрофон', '<rect x="9" y="2.5" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3.5M8.5 21.5h7"/>'],
        'award' => ['Медаль', '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>'],
        'other' => ['Календарь', '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M8 3v4M16 3v4M3 10h18"/>'],
    ];
}

function vitrina_icon_svg(string $key, int $size = 20): string
{
    $icons = vitrina_icons();
    $paths = ($icons[$key] ?? $icons['other'])[1];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

function vitrina_categories(): array
{
    return vitrina_db()->query('SELECT id, slug, name, color, icon, sort_order FROM ' . vitrina_table('categories') . ' ORDER BY sort_order, name')->fetchAll();
}

/** Дата и время события в ISO 8601 с поясом сайта; событие без времени занимает весь день. */
function vitrina_iso(string $date, ?string $time, bool $isEnd): string
{
    $t = $time !== null && $time !== '' ? substr($time, 0, 5) : ($isEnd ? '23:59' : '00:00');
    return (new DateTimeImmutable($date . ' ' . $t))->format(DATE_ATOM);
}

/** Анонс для карточки: заданный вручную или начало описания. */
function vitrina_excerpt(array $e, int $limit = 220): string
{
    $text = trim((string) $e['excerpt']);
    if ($text === '') {
        $text = trim((string) preg_replace('~\s+~u', ' ', (string) $e['description']));
    }
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    $cut = mb_substr($text, 0, $limit);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > $limit * 0.6) {
        $cut = mb_substr($cut, 0, $space);
    }
    return preg_replace('~[\s,.;:—-]+$~u', '', $cut) . '…';
}

function vitrina_place_text(array $e): string
{
    $place = trim((string) $e['place']);
    if ($e['format'] === 'online') {
        return $place !== '' ? $place : 'Онлайн';
    }
    if ($e['format'] === 'hybrid') {
        return $place !== '' ? $place . ' и онлайн' : 'Очно и онлайн';
    }
    return $place;
}

function vitrina_upload_url(string $path): string
{
    return $path === '' ? '' : 'uploads/' . $path;
}

/** Время для показа: «10:00», «10:00–12:00» или пусто для событий на весь день. */
function vitrina_time_text(array $e): string
{
    if ($e['start_time'] === null) {
        return '';
    }
    $t = substr($e['start_time'], 0, 5);
    if ($e['end_time'] !== null && $e['end_date'] === $e['start_date'] && substr($e['end_time'], 0, 5) !== $t) {
        $t .= '–' . substr($e['end_time'], 0, 5);
    }
    return $t;
}

function vitrina_session_time(array $s): string
{
    if ($s['start_time'] === null) {
        return '';
    }
    $t = substr($s['start_time'], 0, 5);
    if ($s['end_time'] !== null && substr($s['end_time'], 0, 5) !== $t) {
        $t .= '–' . substr($s['end_time'], 0, 5);
    }
    return $t;
}

/** Пункты программы событий в порядке показа: по дню, времени и порядку в админке. */
function vitrina_sessions(array $eventIds): array
{
    if ($eventIds === []) {
        return [];
    }
    $st = vitrina_db()->prepare('SELECT * FROM ' . vitrina_table('sessions') . ' WHERE event_id IN (' . implode(',', array_fill(0, count($eventIds), '?')) . ')
        ORDER BY event_id, day_date, start_time IS NULL, start_time, sort_order, id');
    $st->execute(array_values($eventIds));
    $out = [];
    foreach ($st->fetchAll() as $s) {
        $out[(int) $s['event_id']][] = $s;
    }
    return $out;
}

/** Программа для витрины: дни, в каждом пункты со временем, местом, ведущими и ссылкой подключения. */
function vitrina_program(array $sessions): array
{
    $days = [];
    foreach ($sessions as $s) {
        $days[$s['day_date']][] = [
            'start' => $s['start_time'] !== null ? substr($s['start_time'], 0, 5) : '',
            'end' => $s['end_time'] !== null ? substr($s['end_time'], 0, 5) : '',
            'time' => vitrina_session_time($s),
            'kind' => $s['kind'],
            'title' => $s['title'],
            'place' => $s['place'],
            'moderators' => $s['moderators'],
            'description' => $s['description'],
            'joinUrl' => $s['join_url'],
            'joinLabel' => $s['join_label'] !== '' ? $s['join_label'] : 'Подключиться',
        ];
    }
    $program = [];
    foreach ($days as $date => $items) {
        $program[] = ['date' => $date, 'items' => $items];
    }
    return $program;
}

/** Опубликованные события для витрины и API, по возрастанию даты. */
function vitrina_public_events(): array
{
    $rows = vitrina_db()->query(
        'SELECT e.*, c.slug AS category_slug, c.name AS category_name FROM ' . vitrina_table('events') . ' e
         JOIN ' . vitrina_table('categories') . ' c ON c.id = e.category_id
         WHERE e.is_published = 1
         ORDER BY e.start_date, e.start_time IS NOT NULL, e.start_time, e.id'
    )->fetchAll();
    try {
        $sessions = vitrina_sessions(array_map(static fn (array $e): int => (int) $e['id'], $rows));
    } catch (PDOException) {
        $sessions = [];
    }

    return array_map(static fn (array $e): array => [
        'id' => (int) $e['id'],
        'title' => $e['title'],
        'cat' => $e['category_slug'],
        'catName' => $e['category_name'],
        'start' => vitrina_iso($e['start_date'], $e['start_time'], false),
        'end' => vitrina_iso($e['end_date'], $e['end_time'], true),
        'time' => vitrina_time_text($e),
        'format' => $e['format'],
        'formatLabel' => VITRINA_FORMATS[$e['format']] ?? '',
        'place' => vitrina_place_text($e),
        'excerpt' => vitrina_excerpt($e),
        'description' => (string) $e['description'],
        'link' => (string) $e['link_url'],
        'linkLabel' => $e['link_label'] !== '' ? $e['link_label'] : 'Подробнее',
        'image' => vitrina_upload_url($e['image_thumb'] !== '' ? $e['image_thumb'] : $e['image']),
        'imageLarge' => vitrina_upload_url($e['image']),
        'pinned' => (bool) $e['is_pinned'],
        'program' => vitrina_program($sessions[(int) $e['id']] ?? []),
    ], $rows);
}
