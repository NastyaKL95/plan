<?php
declare(strict_types=1);

$local = __DIR__ . '/config.local.php';
$overrides = is_file($local) ? (array) require $local : [];

return array_replace_recursive([
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'vitrina',
        'user' => 'vitrina',
        'pass' => '',
    ],
    'table_prefix' => 'vitrina_',
    'timezone' => 'Asia/Chita',
    // Ключ для первичной настройки в admin/setup.php. Пока он пуст, настройка через браузер закрыта.
    'setup_key' => '',
    'upload_max_mb' => 8,
    'site_name' => 'Витрина событий ИРО Забайкальского края',
    'ecosystem_url' => 'https://planiro75.ru/public/ecosystem.html',
], $overrides);
