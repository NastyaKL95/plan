<?php
declare(strict_types=1);

require_once __DIR__ . '/events.php';

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('vitrina_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x'), '/') . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_has_admins(): bool
{
    try {
        return (int) vitrina_db()->query('SELECT COUNT(*) FROM ' . vitrina_table('admins'))->fetchColumn() > 0;
    } catch (PDOException) {
        return false;
    }
}

function admin_current(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    admin_session_start();
    $user = null;
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id > 0) {
        $st = vitrina_db()->prepare('SELECT id, email, name FROM ' . vitrina_table('admins') . ' WHERE id = ?');
        $st->execute([$id]);
        $user = $st->fetch() ?: null;
        if ($user === null) {
            unset($_SESSION['admin_id']);
        }
    }
    return $user;
}

function admin_run_sql_file(string $file): void
{
    $prefix = (string) vitrina_config()['table_prefix'];
    $sql = (string) file_get_contents(VITRINA_ROOT . '/install/' . $file);
    $sql = str_replace('vitrina_', $prefix, (string) preg_replace('~^--.*$~m', '', $sql));
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
        vitrina_db()->exec($statement);
    }
}

/** После обновления сайта досоздаёт новые таблицы, чтобы не запускать миграции вручную. */
function admin_ensure_schema(): void
{
    $st = vitrina_db()->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $st->execute([vitrina_config()['table_prefix'] . 'sessions']);
    if ($st->fetchColumn() === false) {
        admin_run_sql_file('schema.sql');
    }
}

function admin_require(): array
{
    try {
        admin_ensure_schema();
    } catch (PDOException $ex) {
        if (!admin_has_admins()) {
            admin_redirect('setup.php');
        }
        error_log('vitrina schema: ' . $ex->getMessage());
        http_response_code(500);
        exit('Не удалось обновить таблицы витрины в базе данных. Подробности в журнале ошибок PHP.');
    }
    $user = admin_current();
    if ($user === null) {
        header('Location: ' . (admin_has_admins() ? 'login.php' : 'setup.php'));
        exit;
    }
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    return $user;
}

function admin_csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(admin_csrf_token()) . '">';
}

function admin_check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        exit('Отправленные данные слишком большие (скорее всего, картинка). Вернитесь назад и выберите файл поменьше.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(admin_csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Форма устарела. Вернитесь назад и обновите страницу.');
    }
}

function admin_flash(?string $message = null, string $type = 'ok'): ?array
{
    admin_session_start();
    if ($message !== null) {
        $_SESSION['flash'] = [$type, $message];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function admin_redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function admin_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Не больше 10 неудачных входов с одного адреса за 15 минут. */
function admin_login_blocked(): bool
{
    $db = vitrina_db();
    $db->prepare('DELETE FROM ' . vitrina_table('login_attempts') . ' WHERE attempted_at < ?')
        ->execute([(new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s')]);
    $st = $db->prepare('SELECT COUNT(*) FROM ' . vitrina_table('login_attempts') . ' WHERE ip = ? AND attempted_at > ?');
    $st->execute([admin_client_ip(), (new DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s')]);
    return (int) $st->fetchColumn() >= 10;
}

function admin_login_failed(): void
{
    vitrina_db()->prepare('INSERT INTO ' . vitrina_table('login_attempts') . ' (ip, attempted_at) VALUES (?, ?)')
        ->execute([admin_client_ip(), vitrina_now()]);
}

function admin_login(int $id): void
{
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $id;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    vitrina_db()->prepare('UPDATE ' . vitrina_table('admins') . ' SET last_login_at = ? WHERE id = ?')->execute([vitrina_now(), $id]);
    vitrina_db()->prepare('DELETE FROM ' . vitrina_table('login_attempts') . ' WHERE ip = ?')->execute([admin_client_ip()]);
}

function admin_password_error(string $password, string $repeat): ?string
{
    if (mb_strlen($password) < 10) {
        return 'Пароль должен быть не короче 10 символов.';
    }
    if ($password !== $repeat) {
        return 'Пароли не совпадают.';
    }
    return null;
}

function admin_assets_version(string $file): int
{
    return (int) @filemtime(VITRINA_ROOT . '/' . $file);
}

function admin_header(string $title, ?array $user, string $active = ''): void
{
    $nav = [
        'events' => ['index.php', 'События'],
        'categories' => ['categories.php', 'Типы событий'],
        'admins' => ['admins.php', 'Администраторы'],
    ];
    $flash = admin_flash();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($title) ?> — админка витрины</title>
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="../assets/admin.css?v=<?= admin_assets_version('assets/admin.css') ?>">
</head>
<body>
  <header class="a-top">
    <div class="a-wrap">
      <a class="a-brand" href="index.php"><img src="../assets/favicon.svg" alt="" width="30" height="30"><span>Витрина событий<small>Администрирование</small></span></a>
      <?php if ($user !== null): ?>
        <nav class="a-nav" aria-label="Разделы">
          <?php foreach ($nav as $key => [$href, $label]): ?>
            <a href="<?= h($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
          <?php endforeach; ?>
        </nav>
        <div class="a-user">
          <a href="../" target="_blank" rel="noopener">Открыть сайт</a>
          <span title="<?= h($user['email']) ?>"><?= h($user['name']) ?></span>
          <form method="post" action="logout.php"><?= admin_csrf_field() ?><button type="submit" class="a-link">Выйти</button></form>
        </div>
      <?php endif; ?>
    </div>
  </header>
  <main class="a-wrap a-main">
    <?php if ($flash !== null): ?>
      <div class="a-flash a-flash-<?= h($flash[0]) ?>" role="status"><?= h($flash[1]) ?></div>
    <?php endif; ?>
    <?php
}

function admin_footer(): void
{
    ?>
  </main>
  <script src="../assets/admin.js?v=<?= admin_assets_version('assets/admin.js') ?>"></script>
</body>
</html>
    <?php
}
