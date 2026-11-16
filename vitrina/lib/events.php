<?php
declare(strict_types=1);

/*
 * Ответы источника больше ~16 КБ часто зависают на середине передачи, маленькие проходят всегда.
 * Поэтому список событий и медиа запрашиваются мелкими страницами.
 */
const VITRINA_PAGE = 10;

function vitrina_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config.php';
        date_default_timezone_set((string) $cfg['timezone']);
    }
    return $cfg;
}

function vitrina_cache_file(): string
{
    return rtrim((string) vitrina_config()['cache_dir'], '/') . '/events.json';
}

/** @return array{status:int, headers:array<string,string>, body:string} */
function vitrina_http_get(string $url): array
{
    $res = vitrina_http_multi([$url]);
    return $res[$url];
}

/**
 * @param list<string> $urls
 * @return array<string, array{status:int, headers:array<string,string>, body:string}>
 */
function vitrina_http_multi(array $urls): array
{
    $cfg = vitrina_config();
    $out = [];
    $queue = array_values(array_unique($urls));
    $mh = curl_multi_init();
    $active = [];
    $start = static function (string $url) use ($mh, $cfg, &$active): void {
        $ch = curl_init($url);
        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => (int) $cfg['http_timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'VitrinaIRO/1.0 (+https://planiro75.ru)',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
                }
                return strlen($line);
            },
        ]);
        curl_multi_add_handle($mh, $ch);
        $active[(int) $ch] = ['url' => $url, 'ch' => $ch, 'headers' => &$headers];
    };
    $parallel = max(1, (int) $cfg['parallel']);
    while ($queue !== [] && count($active) < $parallel) {
        $start(array_shift($queue));
    }
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 1.0);
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $item = $active[(int) $ch];
            $out[$item['url']] = [
                'status' => $info['result'] === CURLE_OK ? (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) : 0,
                'headers' => $item['headers'],
                'body' => (string) curl_multi_getcontent($ch),
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($active[(int) $ch]);
            if ($queue !== []) {
                $start(array_shift($queue));
                $running = 1;
            }
        }
    } while ($running > 0 || $active !== []);
    curl_multi_close($mh);
    return $out;
}

function vitrina_json_get(string $url, ?array &$headers = null, int $attempts = 6): ?array
{
    for ($i = 0; $i < $attempts; $i++) {
        $r = vitrina_http_get($url);
        $headers = $r['headers'];
        $data = $r['status'] === 200 ? json_decode($r['body'], true) : null;
        if (is_array($data)) {
            return $data;
        }
    }
    return null;
}

/**
 * Параллельные JSON-запросы с повтором неудачных.
 *
 * @param list<string> $urls
 * @return array<string, ?array>
 */
function vitrina_json_multi(array $urls, int $rounds = 5): array
{
    $out = array_fill_keys($urls, null);
    $todo = $urls;
    for ($i = 0; $i < $rounds && $todo !== []; $i++) {
        foreach (vitrina_http_multi($todo) as $url => $r) {
            $data = $r['status'] === 200 ? json_decode($r['body'], true) : null;
            if (is_array($data)) {
                $out[$url] = $data;
            }
        }
        $todo = array_keys(array_filter($out, static fn ($v) => $v === null));
    }
    return $out;
}

function vitrina_text(string $html): string
{
    $s = preg_replace('~<(script|style)\b.*?</\1>~is', ' ', $html) ?? $html;
    $s = preg_replace('~<br\s*/?>|</p>~i', "\n", $s) ?? $s;
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\u{00A0}", ' ', $s);
    $s = preg_replace("~[ \t]+~u", ' ', $s) ?? $s;
    $s = preg_replace("~\s*\n\s*~u", "\n", $s) ?? $s;
    return trim($s);
}

function vitrina_trust_url(string $url): string
{
    $base = rtrim((string) vitrina_config()['source'], '/') . '/';
    return str_starts_with($url, $base) ? $url : '';
}

/** @return array<string, array{value:string, params:array<string,string>}> */
function vitrina_parse_ics_event(string $ics): array
{
    $ics = str_replace(["\r\n", "\r"], "\n", $ics);
    $ics = preg_replace("~\n[ \t]~", '', $ics) ?? $ics;
    $props = [];
    $inEvent = false;
    foreach (explode("\n", $ics) as $line) {
        if ($line === 'BEGIN:VEVENT') {
            $inEvent = true;
            continue;
        }
        if ($line === 'END:VEVENT') {
            break;
        }
        if (!$inEvent || ($p = strpos($line, ':')) === false) {
            continue;
        }
        $head = explode(';', substr($line, 0, $p));
        $name = strtoupper(array_shift($head));
        $params = [];
        foreach ($head as $h) {
            [$k, $v] = array_pad(explode('=', $h, 2), 2, '');
            $params[strtoupper($k)] = $v;
        }
        if (!isset($props[$name])) {
            $props[$name] = ['value' => substr($line, $p + 1), 'params' => $params];
        }
    }
    return $props;
}

function vitrina_ics_unescape(string $v): string
{
    return trim(strtr($v, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\\;' => ';', '\\\\' => '\\']));
}

/** @param array{value:string, params:array<string,string>}|null $prop */
function vitrina_ics_date(?array $prop): ?array
{
    if ($prop === null) {
        return null;
    }
    $v = $prop['value'];
    $local = new DateTimeZone((string) vitrina_config()['timezone']);
    if (preg_match('~^(\d{8})$~', $v)) {
        $d = DateTimeImmutable::createFromFormat('!Ymd', $v, $local);
        return $d ? ['dt' => $d, 'all_day' => true] : null;
    }
    if (!preg_match('~^(\d{8}T\d{4,6})(Z?)$~', $v, $m)) {
        return null;
    }
    $tz = $local;
    if ($m[2] === 'Z') {
        $tz = new DateTimeZone('UTC');
    } elseif (!empty($prop['params']['TZID'])) {
        try {
            $tz = new DateTimeZone($prop['params']['TZID']);
        } catch (Exception) {
        }
    }
    $fmt = strlen($m[1]) === 13 ? '!Ymd\THi' : '!Ymd\THis';
    $d = DateTimeImmutable::createFromFormat($fmt, $m[1], $tz);
    return $d ? ['dt' => $d->setTimezone($local), 'all_day' => false] : null;
}

function vitrina_category_info(string $name): array
{
    $archived = (bool) preg_match('~[_\s-]*архив$~iu', $name);
    $base = trim((string) preg_replace('~[_\s-]*архив$~iu', '', $name));
    $plural = [
        'вебинар' => 'Вебинары',
        'симпозиум' => 'Симпозиумы',
        'семинар' => 'Семинары',
        'конференция' => 'Конференции',
    ];
    $lower = mb_strtolower($base);
    $label = $plural[$lower] ?? $base;
    $keyMap = [
        'вебинары' => 'webinar',
        'семинары' => 'seminar',
        'конференции' => 'conference',
        'симпозиумы' => 'symposium',
        'конкурсы' => 'contest',
    ];
    $key = $keyMap[mb_strtolower($label)] ?? ('c' . substr(md5($label), 0, 8));
    return ['key' => $key, 'label' => $label, 'archived' => $archived];
}

function vitrina_excerpt(string $text, int $limit = 300): string
{
    $lines = array_filter(
        preg_split('~\n+~u', $text) ?: [],
        static fn (string $l): bool => !preg_match('~^\s*(Дата|Время|Место(\s+проведения)?|Адрес|Формат)\s*[:—-]~iu', $l)
    );
    $text = trim((string) preg_replace('~\s+~u', ' ', implode(' ', $lines)));
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    $cut = mb_substr($text, 0, $limit);
    $sp = mb_strrpos($cut, ' ');
    return preg_replace('~[\s,.;:—-]+$~u', '', mb_substr($cut, 0, $sp ?: $limit)) . '…';
}

function vitrina_extract_place(string $text): string
{
    if (preg_match('~(?:^|\n|\s)(?:Место(?:\s+проведения)?|Адрес(?:\s+проведения)?)\s*[:—-]\s*([^\n]+)~iu', $text, $m)) {
        $place = trim($m[1]);
        $place = preg_replace('~\s*(&hellip;|…|\.\.\.)\s*$~u', '', $place) ?? $place;
        return rtrim($place, " ,.;");
    }
    return '';
}

/**
 * Собирает события с сайта-источника. Сведения о дате берутся из iCal каждого события,
 * потому что REST API плагина MEC не отдаёт даты проведения.
 */
function vitrina_build_dataset(?array $previous = null): array
{
    $src = rtrim((string) vitrina_config()['source'], '/');
    $listUrl = static fn (int $page): string => $src . '/wp-json/wp/v2/mec-events?per_page=' . VITRINA_PAGE . '&page=' . $page
        . '&_fields=id,title,link,modified,featured_media,mec_category';
    $headers = [];
    $first = vitrina_json_get($listUrl(1), $headers);
    if ($first === null) {
        throw new RuntimeException('Источник событий недоступен');
    }
    $pages = min(200, max(1, (int) ($headers['x-wp-totalpages'] ?? 1)));
    $posts = $first;
    $rest = vitrina_json_multi(array_map($listUrl, $pages > 1 ? range(2, $pages) : []));
    foreach ($rest as $url => $chunk) {
        if ($chunk === null) {
            throw new RuntimeException('Источник событий недоступен: ' . $url);
        }
        array_push($posts, ...$chunk);
    }

    $cats = [];
    foreach (vitrina_json_get($src . '/wp-json/wp/v2/mec_category?per_page=100&_fields=id,name') ?? [] as $c) {
        $cats[(int) $c['id']] = vitrina_category_info(vitrina_text((string) $c['name']));
    }

    $mediaIds = array_values(array_unique(array_filter(array_map(static fn ($p) => (int) ($p['featured_media'] ?? 0), $posts))));
    $mediaUrls = array_map(
        static fn (array $ids): string => $src . '/wp-json/wp/v2/media?per_page=' . count($ids) . '&include=' . implode(',', $ids) . '&_fields=id,source_url,media_details',
        array_chunk($mediaIds, 4)
    );
    $media = [];
    foreach (vitrina_json_multi($mediaUrls) as $rows) {
        foreach ($rows ?? [] as $m) {
            $sizes = $m['media_details']['sizes'] ?? [];
            $pick = static function (array $names) use ($sizes, $m): string {
                foreach ($names as $n) {
                    if (!empty($sizes[$n]['source_url'])) {
                        return (string) $sizes[$n]['source_url'];
                    }
                }
                return (string) ($m['source_url'] ?? '');
            };
            $media[(int) $m['id']] = [
                'card' => vitrina_trust_url($pick(['medium_large', 'large', 'gridsquare'])),
                'large' => vitrina_trust_url($pick(['large', 'medium_large'])),
            ];
        }
    }

    $prevById = [];
    foreach ($previous['events'] ?? [] as $e) {
        $prevById[(int) $e['id']] = $e;
    }
    $needIcs = [];
    foreach ($posts as $p) {
        $id = (int) $p['id'];
        $prev = $prevById[$id] ?? null;
        if ($prev === null || ($prev['modified'] ?? '') !== (string) $p['modified'] || empty($prev['start'])) {
            $needIcs[$id] = $src . '/?method=ical&id=' . $id;
        }
    }
    $icsBodies = $needIcs === [] ? [] : vitrina_http_multi(array_values($needIcs));
    for ($i = 0; $i < 3; $i++) {
        $retry = array_values(array_filter($needIcs, static fn ($u) => ($icsBodies[$u]['status'] ?? 0) !== 200));
        if ($retry === []) {
            break;
        }
        $icsBodies = vitrina_http_multi($retry) + $icsBodies;
    }

    $events = [];
    foreach ($posts as $p) {
        $id = (int) $p['id'];
        $title = vitrina_text((string) ($p['title']['rendered'] ?? ''));
        $catIds = array_map('intval', (array) ($p['mec_category'] ?? []));
        $cat = null;
        foreach ($catIds as $cid) {
            if (isset($cats[$cid])) {
                $cat = $cats[$cid];
                break;
            }
        }
        $cat ??= ['key' => 'other', 'label' => 'Другое', 'archived' => false];
        $img = $media[(int) ($p['featured_media'] ?? 0)] ?? ['card' => '', 'large' => ''];

        $detail = null;
        if (isset($needIcs[$id])) {
            $r = $icsBodies[$needIcs[$id]] ?? null;
            if ($r && $r['status'] === 200 && str_contains($r['body'], 'BEGIN:VEVENT')) {
                $ics = vitrina_parse_ics_event($r['body']);
                $s = vitrina_ics_date($ics['DTSTART'] ?? null);
                $e = vitrina_ics_date($ics['DTEND'] ?? null);
                if ($s !== null) {
                    $desc = vitrina_ics_unescape($ics['DESCRIPTION']['value'] ?? '');
                    $detail = [
                        'start' => $s['dt']->format(DATE_ATOM),
                        'end' => $e ? $e['dt']->format(DATE_ATOM) : $s['dt']->format(DATE_ATOM),
                        'all_day' => $s['all_day'],
                        'description' => mb_substr($desc, 0, 2000),
                        'location' => vitrina_ics_unescape($ics['LOCATION']['value'] ?? ''),
                        'image_ics' => vitrina_trust_url(trim($ics['ATTACH']['value'] ?? '')),
                    ];
                }
            }
        } elseif (isset($prevById[$id])) {
            $prev = $prevById[$id];
            $detail = [
                'start' => $prev['start'],
                'end' => $prev['end'],
                'all_day' => $prev['all_day'],
                'description' => $prev['description'],
                'location' => (string) ($prev['location'] ?? ''),
                'image_ics' => '',
            ];
        }
        if ($detail === null) {
            continue;
        }
        $place = $detail['location'] ?: vitrina_extract_place($detail['description']);
        if ($place === '' && $cat['key'] === 'webinar') {
            $place = 'Онлайн';
        }
        $events[] = [
            'id' => $id,
            'title' => $title,
            'url' => vitrina_trust_url((string) $p['link']) ?: $src . '/?p=' . $id,
            'ics' => $src . '/?method=ical&id=' . $id,
            'modified' => (string) $p['modified'],
            'start' => $detail['start'],
            'end' => $detail['end'],
            'all_day' => $detail['all_day'],
            'category' => $cat['key'],
            'category_label' => $cat['label'],
            'archived_category' => $cat['archived'],
            'place' => $place,
            'location' => $detail['location'],
            'excerpt' => vitrina_excerpt($detail['description']),
            'description' => $detail['description'],
            'image' => $img['card'] ?: $detail['image_ics'],
            'image_large' => $img['large'] ?: $detail['image_ics'],
        ];
    }

    usort($events, static fn ($a, $b) => strcmp($a['start'], $b['start']) ?: $a['id'] <=> $b['id']);

    $categories = [];
    foreach ($events as $e) {
        $categories[$e['category']] ??= ['key' => $e['category'], 'label' => $e['category_label'], 'count' => 0];
        $categories[$e['category']]['count']++;
    }
    $order = ['conference', 'seminar', 'webinar', 'symposium', 'contest'];
    uasort($categories, static function ($a, $b) use ($order) {
        $ia = array_search($a['key'], $order, true);
        $ib = array_search($b['key'], $order, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strcmp($a['label'], $b['label']);
    });

    return [
        'updated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        'source' => $src,
        'categories' => array_values($categories),
        'events' => $events,
    ];
}

function vitrina_read_cache(): ?array
{
    $f = vitrina_cache_file();
    if (!is_file($f)) {
        return null;
    }
    $d = json_decode((string) file_get_contents($f), true);
    return is_array($d) && isset($d['events']) ? $d : null;
}

/** Обновляет кэш; если другой процесс уже обновляет, ничего не делает. */
function vitrina_refresh(bool $wait = false): ?array
{
    $dir = (string) vitrina_config()['cache_dir'];
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Нет доступа к папке кэша');
    }
    $lock = fopen($dir . '/refresh.lock', 'c');
    if ($lock === false || !flock($lock, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
        return null;
    }
    try {
        $data = vitrina_build_dataset(vitrina_read_cache());
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new RuntimeException('Не удалось сохранить события: ' . json_last_error_msg());
        }
        $tmp = vitrina_cache_file() . '.' . getmypid() . '.tmp';
        file_put_contents($tmp, $json);
        rename($tmp, vitrina_cache_file());
        return $data;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function vitrina_is_stale(?array $data): bool
{
    if ($data === null) {
        return true;
    }
    $t = strtotime((string) ($data['updated_at'] ?? '')) ?: 0;
    return time() - $t > (int) vitrina_config()['ttl'];
}

/**
 * Возвращает данные из кэша. Без кэша загружает их сразу; устаревший кэш обновляется
 * после отправки ответа, чтобы страница не ждала медленный источник.
 */
function vitrina_load(): ?array
{
    $data = vitrina_read_cache();
    if ($data === null) {
        try {
            $data = vitrina_refresh(true) ?? vitrina_read_cache();
        } catch (Throwable $e) {
            error_log('vitrina refresh: ' . $e->getMessage());
        }
        return $data;
    }
    $canDetach = function_exists('fastcgi_finish_request');
    // Без PHP-FPM обновление задержит ответ посетителю, поэтому там его делает cron, а здесь только страховка.
    $staleFor = time() - (strtotime((string) ($data['updated_at'] ?? '')) ?: 0);
    if ($staleFor > (int) vitrina_config()['ttl'] * ($canDetach ? 1 : 3)) {
        register_shutdown_function(static function (): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            try {
                vitrina_refresh(false);
            } catch (Throwable $e) {
                error_log('vitrina refresh: ' . $e->getMessage());
            }
        });
    }
    return $data;
}
