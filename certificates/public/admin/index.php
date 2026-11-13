<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
        if ($title === '') {
            flash('Укажите название шаблона.', 'error');
            redirect('admin/index.php');
        }
        db()->prepare(
            'INSERT INTO cert_templates (title, inputs_json, fields_json, number_prefix) VALUES (?, ?, ?, ?)'
        )->execute([
            $title,
            json_encode(cert_default_inputs(), JSON_UNESCAPED_UNICODE),
            json_encode(cert_default_fields(), JSON_UNESCAPED_UNICODE),
            date('Y') . '-',
        ]);
        redirect('admin/template.php?id=' . (int) db()->lastInsertId());
    }
    $id = (int) ($_POST['id'] ?? 0);
    $tpl = $id > 0 ? cert_template($id) : null;
    if ($tpl && $action === 'copy') {
        $bg = null;
        $src = cert_background_path($tpl);
        if ($src !== null) {
            $bg = bin2hex(random_bytes(16)) . '.jpg';
            copy($src, CERT_STORAGE . '/backgrounds/' . $bg);
        }
        db()->prepare(
            'INSERT INTO cert_templates (title, bg_file, bg_width, bg_height, inputs_json, fields_json, number_prefix, number_digits)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            mb_substr($tpl['title'] . ' (копия)', 0, 255), $bg, $tpl['bg_width'], $tpl['bg_height'],
            $tpl['inputs_json'], $tpl['fields_json'], $tpl['number_prefix'], $tpl['number_digits'],
        ]);
        flash('Копия создана. Нумерация и выданные сертификаты у неё свои.');
        redirect('admin/template.php?id=' . (int) db()->lastInsertId());
    }
    if ($tpl && $action === 'delete') {
        $bg = cert_background_path($tpl);
        db()->prepare('DELETE FROM cert_templates WHERE id = ?')->execute([$id]);
        if ($bg !== null) {
            @unlink($bg);
        }
        flash('Шаблон «' . $tpl['title'] . '» удалён вместе со ссылками и реестром.');
    }
    redirect('admin/index.php');
}

$rows = db()->query(
    'SELECT t.id, t.title, t.bg_file, t.updated_at,
            (SELECT COUNT(*) FROM cert_issued i WHERE i.template_id = t.id AND i.revoked_at IS NULL) AS issued,
            (SELECT COUNT(*) FROM cert_links l WHERE l.template_id = t.id AND l.is_active = 1
                AND (l.expires_at IS NULL OR l.expires_at > NOW())) AS links
     FROM cert_templates t ORDER BY t.id DESC'
)->fetchAll();

page_start('Шаблоны', $admin, 'templates');
?>
<div class="page-head">
  <div>
    <h1>Шаблоны сертификатов</h1>
    <p class="lead">Шаблон — это фон и расположение надписей. Слушатели получают сертификат по ссылке с ограниченным сроком.</p>
  </div>
  <form method="post" class="actions">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <input type="text" name="title" placeholder="Название, например «Семинар 20 ноября»" required style="min-width:300px">
    <button class="btn btn-primary">Создать шаблон</button>
  </form>
</div>

<?php if ($rows === []): ?>
  <div class="card empty">Шаблонов пока нет. Введите название и нажмите «Создать шаблон».</div>
<?php else: ?>
  <div class="tpl-grid">
    <?php foreach ($rows as $r): ?>
      <div class="tpl-card">
        <a class="tpl-thumb" href="<?= h(url('admin/template.php?id=' . $r['id'])) ?>"
           <?php if ($r['bg_file']): ?>style="background-image:url('<?= h(url('admin/bg.php?id=' . $r['id'] . '&v=' . strtotime((string) $r['updated_at']))) ?>')"<?php endif; ?>>
          <?= $r['bg_file'] ? '' : 'Фон не загружен' ?>
        </a>
        <div class="tpl-body">
          <a class="tpl-title" href="<?= h(url('admin/template.php?id=' . $r['id'])) ?>"><?= h($r['title']) ?></a>
          <div class="tpl-meta">
            Выдано: <?= (int) $r['issued'] ?> ·
            <?= (int) $r['links'] > 0 ? '<span class="badge badge--ok">ссылок: ' . (int) $r['links'] . '</span>' : '<span class="badge">нет активных ссылок</span>' ?>
          </div>
          <div class="actions">
            <a class="btn btn-sm" href="<?= h(url('admin/template.php?id=' . $r['id'])) ?>">Макет</a>
            <a class="btn btn-sm" href="<?= h(url('admin/links.php?template=' . $r['id'])) ?>">Ссылки</a>
            <a class="btn btn-sm" href="<?= h(url('admin/issued.php?template=' . $r['id'])) ?>">Реестр</a>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="copy"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-sm" title="Создать копию шаблона">Копия</button>
            </form>
            <form method="post" class="inline-form" onsubmit="return confirm('Удалить шаблон, его ссылки и реестр выданных сертификатов? Скачать выданные будет нельзя.')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-sm btn-danger">Удалить</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
page_end();
