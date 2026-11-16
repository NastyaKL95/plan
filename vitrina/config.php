<?php
declare(strict_types=1);

$local = __DIR__ . '/config.local.php';
$overrides = is_file($local) ? (array) require $local : [];

return array_replace([
    'source' => 'https://eventt.zabedu.ru',
    'cache_dir' => __DIR__ . '/cache',
    // Через сколько секунд данные считаются устаревшими и обновляются в фоне.
    'ttl' => 1800,
    'timezone' => 'Asia/Chita',
    'http_timeout' => 12,
    'parallel' => 6,
    // Оформление по умолчанию: eco — как страница экосистемы, flag — цвета флага края.
    'theme' => 'eco',
    'site_name' => 'Витрина событий ИРО Забайкальского края',
    'ecosystem_url' => 'https://planiro75.ru/public/ecosystem.html',
], $overrides);
