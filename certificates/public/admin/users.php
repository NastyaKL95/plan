<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add') {
        $login = trim((string) ($_POST['login'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._@-]{3,80}$/', $login) || mb_strlen($pass) < 8) {
            flash('Логин от 3 символов латиницей, пароль не короче 8 символов.', 'error');
        } else {
            try {
                db()->prepare('INSERT INTO cert_admins (login, password_hash) VALUES (?, ?)')
                    ->execute([$login, password_hash($pass, PASSWORD_DEFAULT)]);
                flash('Администратор ' . $login . ' добавлен.');
            } catch (PDOException $e) {
                flash('Такой логин уже есть.', 'error');
            }
        }
    } elseif ($action === 'password') {
        $pass = (string) ($_POST['password'] ?? '');
        if (mb_strlen($pass) < 8) {
            flash('Пароль должен быть не короче 8 символов.', 'error');
        } else {
            db()->prepare('UPDATE cert_admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $admin['id']]);
            flash('Пароль изменён.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $admin['id']) {
            flash('Себя удалить нельзя.', 'error');
        } else {
            db()->prepare('DELETE FROM cert_admins WHERE id = ?')->execute([$id]);
            flash('Администратор удалён.');
        }
    }
    redirect('admin/users.php');
}

$rows = db()->query('SELECT id, login, created_at FROM cert_admins ORDER BY login')->fetchAll();

page_start('Администраторы', $admin, 'users');
?>
<div class="page-head"><div><h1>Администраторы</h1><p class="lead">Все администраторы видят и меняют все шаблоны.</p></div></div>
<div class="grid-2">
  <div class="card">
    <h2>Список</h2>
    <table class="table">
      <tr><th>Логин</th><th>Добавлен</th><th></th></tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['login']) ?><?= (int) $r['id'] === (int) $admin['id'] ? ' <span class="badge">вы</span>' : '' ?></td>
          <td class="muted small"><?= h(format_dt($r['created_at'])) ?></td>
          <td class="num">
            <?php if ((int) $r['id'] !== (int) $admin['id']): ?>
              <form method="post" class="inline-form" onsubmit="return confirm('Удалить администратора?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-sm btn-danger">Удалить</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div>
    <div class="card">
      <h2>Добавить администратора</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <label class="field"><span class="field-label">Логин</span><input type="text" name="login" required autocomplete="off"></label>
        <label class="field"><span class="field-label">Пароль</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <button class="btn btn-primary">Добавить</button>
      </form>
    </div>
    <div class="card">
      <h2>Сменить свой пароль</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <label class="field"><span class="field-label">Новый пароль</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <button class="btn">Сохранить</button>
      </form>
    </div>
  </div>
</div>
<?php
page_end();
