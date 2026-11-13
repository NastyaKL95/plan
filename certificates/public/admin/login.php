<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

if ((int) db()->query('SELECT COUNT(*) FROM cert_admins')->fetchColumn() === 0) {
    redirect('admin/setup.php');
}
if (current_admin()) {
    redirect('admin/index.php');
}

$error = '';
$login = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $login = trim((string) ($_POST['login'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $fails = (int) ($_SESSION['login_fails'] ?? 0);
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
    if ($lockedUntil > time()) {
        $error = 'Слишком много попыток. Подождите минуту.';
    } else {
        $st = db()->prepare('SELECT id, password_hash FROM cert_admins WHERE login = ?');
        $st->execute([$login]);
        $row = $st->fetch();
        if ($row && password_verify($pass, (string) $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $row['id'];
            unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
            redirect('admin/index.php');
        }
        $fails++;
        $_SESSION['login_fails'] = $fails;
        if ($fails >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_fails'] = 0;
        }
        $error = 'Неверный логин или пароль.';
    }
}

page_start('Вход');
?>
<div class="card public-card">
  <h1 class="public-title">Вход для администратора</h1>
  <p class="public-sub">Управление шаблонами и ссылками на сертификаты.</p>
  <?php if ($error !== ''): ?><div class="flash flash--error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label class="field"><span class="field-label">Логин</span>
      <input type="text" name="login" value="<?= h($login) ?>" required autofocus autocomplete="username"></label>
    <label class="field"><span class="field-label">Пароль</span>
      <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Войти</button>
  </form>
</div>
<?php
page_end();
