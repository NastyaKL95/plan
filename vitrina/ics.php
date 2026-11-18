<?php
declare(strict_types=1);

require __DIR__ . '/lib/events.php';

$id = (int) ($_GET['id'] ?? 0);
$st = vitrina_db()->prepare('SELECT * FROM ' . vitrina_table('events') . ' WHERE id = ? AND is_published = 1');
$st->execute([$id]);
$e = $st->fetch();
if (!$e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Событие не найдено');
}

function ics_escape(string $s): string
{
    return str_replace(["\\", "\r\n", "\n", ',', ';'], ["\\\\", '\n', '\n', '\,', '\;'], $s);
}

/** Строки iCalendar не длиннее 75 байт, перенос не разрывает символы UTF-8. */
function ics_fold(string $line): string
{
    $out = '';
    $cur = '';
    foreach (mb_str_split($line) as $ch) {
        if (strlen($cur) + strlen($ch) > 74) {
            $out .= $cur . "\r\n ";
            $cur = '';
        }
        $cur .= $ch;
    }
    return $out . $cur . "\r\n";
}

$utc = new DateTimeZone('UTC');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$host = preg_replace('~[^a-z0-9.\-:]~i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$url = ($https ? 'https://' : 'http://') . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/ics.php'), '/') . '/#e-' . $e['id'];

$lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//IRO Zabaykalsky//Vitrina//RU', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT'];
$lines[] = 'UID:vitrina-' . $e['id'] . '@' . $host;
$lines[] = 'DTSTAMP:' . (new DateTimeImmutable($e['updated_at']))->setTimezone($utc)->format('Ymd\THis\Z');
if ($e['start_time'] === null) {
    $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $e['start_date']);
    $lines[] = 'DTEND;VALUE=DATE:' . (new DateTimeImmutable($e['end_date']))->modify('+1 day')->format('Ymd');
} else {
    $start = new DateTimeImmutable($e['start_date'] . ' ' . $e['start_time']);
    $end = $e['end_time'] !== null
        ? new DateTimeImmutable($e['end_date'] . ' ' . $e['end_time'])
        : ($e['end_date'] === $e['start_date'] ? $start->modify('+1 hour') : new DateTimeImmutable($e['end_date'] . ' 23:59'));
    $lines[] = 'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z');
    $lines[] = 'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z');
}
$lines[] = 'SUMMARY:' . ics_escape($e['title']);
$place = vitrina_place_text($e);
if ($place !== '') {
    $lines[] = 'LOCATION:' . ics_escape($place);
}
$program = '';
$sessions = vitrina_sessions([(int) $e['id']])[(int) $e['id']] ?? [];
$lastDay = null;
foreach ($sessions as $sess) {
    if ($sess['day_date'] !== $lastDay && $sessions[0]['day_date'] !== end($sessions)['day_date']) {
        $program .= "\n" . date('d.m.Y', strtotime($sess['day_date'])) . "\n";
    }
    $lastDay = $sess['day_date'];
    $program .= trim(vitrina_session_time($sess) . ' ' . $sess['title'])
        . ($sess['place'] !== '' ? ' (' . $sess['place'] . ')' : '')
        . ($sess['join_url'] !== '' ? ' — ' . $sess['join_url'] : '') . "\n";
}
$desc = trim($e['description']
    . ($program !== '' ? "\n\nПрограмма:\n" . trim($program) : '')
    . ($e['link_url'] !== '' ? "\n\n" . ($e['link_label'] ?: 'Подробнее') . ': ' . $e['link_url'] : ''));
if ($desc !== '') {
    $lines[] = 'DESCRIPTION:' . ics_escape($desc);
}
$lines[] = 'URL:' . $url;
$lines[] = 'END:VEVENT';
$lines[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="event-' . $e['id'] . '.ics"');
echo implode('', array_map('ics_fold', $lines));
