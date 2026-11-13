<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

if ((int) db()->query('SELECT COUNT(*) FROM cert_admins')->fetchColumn() > 0) {
    redirect('admin/login.php');
}

$error = '';
$login = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $login = trim((string) ($_POST['login'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._@-]{3,80}$/', $login)) {
        $error = 'Логин: от 3 символов, латиница, цифры, точка, дефис.';
    } elseif (mb_strlen($pass) < 8) {
        $error = 'Пароль должен быть не короче 8 символов.';
    } elseif ($pass !== (string) ($_POST['password2'] ?? '')) {
        $error = 'Пароли не совпадают.';
    } else {
        db()->prepare('INSERT INTO cert_admins (login, password_hash) VALUES (?, ?)')
            ->execute([$login, password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) db()->lastInsertId();
        flash('Администратор создан. Добавьте первый шаблон сертификата.');
        redirect('admin/index.php');
    }
}

page_start('Первый запуск');
?>
<div class="card public-card">
  <h1 class="public-title">Первый запуск</h1>
  <p class="public-sub">Создайте учётную запись администратора. Позже можно добавить других.</p>
  <?php if ($error !== ''): ?><div class="flash flash--error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label class="field"><span class="field-label">Логин</span>
      <input type="text" name="login" value="<?= h($login) ?>" required autocomplete="username"></label>
    <label class="field"><span class="field-label">Пароль</span>
      <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <label class="field"><span class="field-label">Пароль ещё раз</span>
      <input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Создать и войти</button>
  </form>
</div>
<?php
page_end();
