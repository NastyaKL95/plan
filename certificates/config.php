<?php
declare(strict_types=1);

/**
 * Настройки. Значения для сервера кладите в config.local.php рядом (в git он не попадает):
 * <?php return ['db_name' => '...', 'db_user' => '...', 'db_pass' => '...'];
 */
$config = [
    'db_host' => getenv('CERT_DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('CERT_DB_NAME') ?: 'certificates',
    'db_user' => getenv('CERT_DB_USER') ?: 'root',
    'db_pass' => getenv('CERT_DB_PASS') ?: '',
    'timezone' => 'Asia/Chita',
    'base_url' => getenv('CERT_BASE_URL') ?: '',
    'app_name' => 'Сертификаты',
    'max_background_mb' => 8,
    'max_per_ip_hour' => 30,
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_merge($config, $override);
    }
}

return $config;
