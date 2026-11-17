<?php
declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';

header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

if (!admin_has_admins()) {
    admin_redirect('setup.php');
}
if (admin_current() !== null) {
    admin_redirect('index.php');
}

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_check_csrf();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (admin_login_blocked()) {
        $error = 'Слишком много попыток входа. Подождите 15 минут.';
    } else {
        $st = vitrina_db()->prepare('SELECT id, password_hash FROM ' . vitrina_table('admins') . ' WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row && password_verify((string) ($_POST['password'] ?? ''), $row['password_hash'])) {
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                vitrina_db()->prepare('UPDATE ' . vitrina_table('admins') . ' SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $row['id']]);
            }
            admin_login((int) $row['id']);
            admin_redirect('index.php');
        }
        admin_login_failed();
        usleep(400000);
        $error = 'Неверная почта или пароль.';
    }
}

admin_header('Вход', null);
?>
<section class="a-auth">
  <h1>Вход в админку</h1>
  <p class="a-muted">Здесь редакторы добавляют и публикуют события витрины.</p>
  <?php if ($error !== null): ?><div class="a-flash a-flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="a-form">
    <?= admin_csrf_field() ?>
    <label>Почта<input type="email" name="email" required value="<?= h($email) ?>" autocomplete="username" autofocus></label>
    <label>Пароль<input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit" class="a-btn a-btn-primary">Войти</button>
  </form>
  <p class="a-muted a-small"><a href="../">← На витрину</a></p>
</section>
<?php admin_footer();
