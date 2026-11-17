<?php
declare(strict_types=1);

const VITRINA_ROOT = __DIR__ . '/..';

function vitrina_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require VITRINA_ROOT . '/config.php';
        date_default_timezone_set((string) $cfg['timezone']);
    }
    return $cfg;
}

vitrina_config();

function vitrina_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $db = vitrina_config()['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['name']);
        $pdo = new PDO($dsn, (string) $db['user'], (string) $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . (new DateTimeImmutable('now'))->format('P') . "'");
    }
    return $pdo;
}

/** Имя таблицы с префиксом из конфига: vitrina_table('events') → vitrina_events. */
function vitrina_table(string $name): string
{
    $prefix = (string) vitrina_config()['table_prefix'];
    if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
        throw new RuntimeException('Недопустимый table_prefix');
    }
    return '`' . $prefix . $name . '`';
}

function vitrina_now(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function vitrina_plural(int $n, string $one, string $few, string $many): string
{
    $m10 = $n % 10;
    $m100 = $n % 100;
    if ($m10 === 1 && $m100 !== 11) {
        return $one;
    }
    return $m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14) ? $few : $many;
}
