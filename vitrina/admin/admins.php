<?php
declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';

$user = admin_require();
$db = vitrina_db();
$T = vitrina_table('admins');

$errors = [];
$new = ['name' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_check_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $new['name'] = trim((string) ($_POST['name'] ?? ''));
        $new['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($new['name'] === '' || mb_strlen($new['name']) > 150) {
            $errors['create'] = 'Укажите имя.';
        } elseif (!filter_var($new['email'], FILTER_VALIDATE_EMAIL) || strlen($new['email']) > 190) {
            $errors['create'] = 'Укажите корректную почту.';
        } elseif (($pe = admin_password_error($password, (string) ($_POST['password2'] ?? ''))) !== null) {
            $errors['create'] = $pe;
        } else {
            $st = $db->prepare("SELECT COUNT(*) FROM $T WHERE email = ?");
            $st->execute([$new['email']]);
            if ((int) $st->fetchColumn() > 0) {
                $errors['create'] = 'Администратор с такой почтой уже есть.';
            }
        }
        if (!isset($errors['create'])) {
            $db->prepare("INSERT INTO $T (email, name, password_hash, created_at) VALUES (?, ?, ?, ?)")
                ->execute([$new['email'], $new['name'], password_hash($password, PASSWORD_DEFAULT), vitrina_now()]);
            admin_flash('Администратор ' . $new['email'] . ' добавлен. Передайте ему пароль лично.');
            admin_redirect('admins.php');
        }
    }

    if ($action === 'password') {
        $st = $db->prepare("SELECT password_hash FROM $T WHERE id = ?");
        $st->execute([$user['id']]);
        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $st->fetchColumn())) {
            $errors['password'] = 'Текущий пароль указан неверно.';
        } elseif (($pe = admin_password_error((string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''))) !== null) {
            $errors['password'] = $pe;
        } else {
            $db->prepare("UPDATE $T SET password_hash = ? WHERE id = ?")->execute([password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $user['id']]);
            admin_flash('Пароль изменён.');
            admin_redirect('admins.php');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $user['id']) {
            admin_flash('Нельзя удалить самого себя.', 'error');
        } else {
            $db->prepare("DELETE FROM $T WHERE id = ?")->execute([$id]);
            admin_flash('Администратор удалён.');
        }
        admin_redirect('admins.php');
    }
}

$rows = $db->query("SELECT id, email, name, created_at, last_login_at FROM $T ORDER BY name")->fetchAll();

admin_header('Администраторы', $user, 'admins');
?>
<div class="a-head">
  <div>
    <h1>Администраторы</h1>
    <p class="a-muted">Все администраторы могут добавлять, публиковать и удалять события.</p>
  </div>
</div>

<div class="a-table-wrap">
  <table class="a-table">
    <thead><tr><th>Имя</th><th>Почта</th><th>Последний вход</th><th class="a-col-actions"><span class="a-sr">Действия</span></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><b><?= h($r['name']) ?></b><?= (int) $r['id'] === (int) $user['id'] ? ' <span class="a-badge">это вы</span>' : '' ?></td>
          <td><?= h($r['email']) ?></td>
          <td class="a-nowrap"><?= $r['last_login_at'] ? h(date('d.m.Y H:i', strtotime($r['last_login_at']))) : '<span class="a-muted">ещё не входил</span>' ?></td>
          <td class="a-col-actions">
            <?php if ((int) $r['id'] !== (int) $user['id']): ?>
              <form method="post"><?= admin_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="a-btn a-btn-sm a-btn-danger" name="action" value="delete" data-confirm="Удалить администратора <?= h($r['email']) ?>?">Удалить</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="a-two">
  <section class="a-card">
    <h2>Добавить администратора</h2>
    <?php if (isset($errors['create'])): ?><div class="a-flash a-flash-error" role="alert"><?= h($errors['create']) ?></div><?php endif; ?>
    <form method="post" class="a-form">
      <?= admin_csrf_field() ?>
      <label class="a-field">Имя<input name="name" required maxlength="150" value="<?= h($new['name']) ?>"></label>
      <label class="a-field">Почта (логин)<input type="email" name="email" required maxlength="190" value="<?= h($new['email']) ?>" autocomplete="off"></label>
      <label class="a-field">Пароль, не короче 10 символов<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <label class="a-field">Пароль ещё раз<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
      <button type="submit" class="a-btn a-btn-primary" name="action" value="create">Добавить</button>
    </form>
  </section>
  <section class="a-card">
    <h2>Сменить свой пароль</h2>
    <?php if (isset($errors['password'])): ?><div class="a-flash a-flash-error" role="alert"><?= h($errors['password']) ?></div><?php endif; ?>
    <form method="post" class="a-form">
      <?= admin_csrf_field() ?>
      <label class="a-field">Текущий пароль<input type="password" name="current" required autocomplete="current-password"></label>
      <label class="a-field">Новый пароль<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <label class="a-field">Новый пароль ещё раз<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
      <button type="submit" class="a-btn a-btn-primary" name="action" value="password">Сменить пароль</button>
    </form>
  </section>
</div>
<?php admin_footer();
