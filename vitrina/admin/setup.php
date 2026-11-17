<?php
declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';

header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$error = null;
$dbOk = true;
try {
    vitrina_db();
} catch (PDOException) {
    $dbOk = false;
    $error = 'Нет подключения к базе данных. Проверьте раздел db в config.local.php.';
}
if ($dbOk && admin_has_admins()) {
    admin_redirect('login.php');
}
$setupKey = (string) vitrina_config()['setup_key'];
$form = ['name' => '', 'email' => ''];

if ($dbOk && $setupKey !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_check_csrf();
    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (!hash_equals($setupKey, (string) ($_POST['setup_key'] ?? ''))) {
        $error = 'Неверный ключ настройки.';
    } elseif ($form['name'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Укажите имя и корректную почту.';
    } else {
        $error = admin_password_error($password, (string) ($_POST['password2'] ?? ''));
    }
    if ($error === null) {
        admin_run_sql_file('schema.sql');
        if (!admin_has_admins()) {
            admin_run_sql_file('seed.sql');
            vitrina_db()->prepare('INSERT INTO ' . vitrina_table('admins') . ' (email, name, password_hash, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$form['email'], $form['name'], password_hash($password, PASSWORD_DEFAULT), vitrina_now()]);
            admin_login((int) vitrina_db()->lastInsertId());
            admin_flash('Готово: таблицы созданы, вы вошли как администратор. Ключ setup_key в config.local.php теперь можно удалить.');
        }
        admin_redirect('index.php');
    }
}

admin_header('Первичная настройка', null);
?>
<section class="a-auth">
  <h1>Первичная настройка</h1>
  <?php if ($error !== null): ?><div class="a-flash a-flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <?php if ($setupKey === ''): ?>
    <p>Чтобы создать таблицы и первого администратора, задайте <code>setup_key</code> в файле <code>config.local.php</code> (длинная случайная строка) и обновите страницу.</p>
  <?php elseif ($dbOk): ?>
    <p class="a-muted">Будут созданы таблицы витрины и учётная запись первого администратора.</p>
    <form method="post" class="a-form">
      <?= admin_csrf_field() ?>
      <label>Ключ настройки из config.local.php<input type="password" name="setup_key" required autocomplete="off"></label>
      <label>Ваше имя<input name="name" required maxlength="150" value="<?= h($form['name']) ?>"></label>
      <label>Почта (логин)<input type="email" name="email" required maxlength="190" value="<?= h($form['email']) ?>" autocomplete="username"></label>
      <label>Пароль, не короче 10 символов<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <label>Пароль ещё раз<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
      <button type="submit" class="a-btn a-btn-primary">Создать</button>
    </form>
  <?php endif; ?>
</section>
<?php admin_footer();
