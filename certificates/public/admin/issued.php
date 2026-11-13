<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

$admin = require_admin();
$tpl = cert_template((int) ($_GET['template'] ?? 0));
if (!$tpl) {
    flash('Шаблон не найден.', 'error');
    redirect('admin/index.php');
}
$linkFilter = (int) ($_GET['link'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$self = 'admin/issued.php?template=' . $tpl['id'];
$selfQuery = $self . ($linkFilter ? '&link=' . $linkFilter : '') . ($q !== '' ? '&q=' . rawurlencode($q) : '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'revoke' && $id) {
        db()->prepare('UPDATE cert_issued SET revoked_at = NOW() WHERE id = ? AND template_id = ? AND revoked_at IS NULL')->execute([$id, $tpl['id']]);
        flash('Сертификат аннулирован: при проверке он будет помечен недействительным.');
    } elseif ($action === 'restore' && $id) {
        db()->prepare('UPDATE cert_issued SET revoked_at = NULL WHERE id = ? AND template_id = ?')->execute([$id, $tpl['id']]);
        flash('Сертификат снова действителен.');
    } elseif ($action === 'issue') {
        [$values, $errors] = cert_collect_values($tpl, $_POST);
        if ($errors) {
            flash('Заполните обязательные поля.', 'error');
        } else {
            [$row, $isNew] = cert_issue($tpl, null, $values, null);
            flash(($isNew ? 'Выдан сертификат № ' : 'Такой сертификат уже есть: № ') . $row['number'] . '.');
        }
    }
    redirect($selfQuery);
}

$where = 'i.template_id = ?';
$params = [$tpl['id']];
if ($linkFilter) {
    $where .= ' AND i.link_id = ?';
    $params[] = $linkFilter;
}
if ($q !== '') {
    $where .= ' AND (i.number LIKE ? OR i.code LIKE ? OR i.values_json LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $jsonLike = '%' . addcslashes(trim(json_encode($q, JSON_UNESCAPED_UNICODE), '"'), '%_\\') . '%';
    array_push($params, $like, $like, $jsonLike);
}

if (($_GET['export'] ?? '') === 'csv') {
    $st = db()->prepare("SELECT i.*, l.note AS link_note FROM cert_issued i LEFT JOIN cert_links l ON l.id = i.link_id WHERE $where ORDER BY i.id");
    $st->execute($params);
    $filename = 'Реестр — ' . $tpl['title'] . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"registry.csv\"; filename*=UTF-8''" . rawurlencode($filename));
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    $head = ['Номер'];
    foreach ($tpl['inputs'] as $input) {
        $head[] = $input['label'];
    }
    array_push($head, 'Дата выдачи', 'Код проверки', 'Ссылка', 'Скачиваний', 'Статус', 'Проверка');
    fputcsv($out, $head, ';');
    while ($row = $st->fetch()) {
        $values = json_decode((string) $row['values_json'], true) ?: [];
        $line = [$row['number']];
        foreach ($tpl['inputs'] as $input) {
            $line[] = (string) ($values[$input['key']] ?? '');
        }
        array_push(
            $line,
            date('d.m.Y', strtotime((string) $row['issued_on'])),
            $row['code'],
            $row['link_id'] ? ((string) $row['link_note'] ?: 'ссылка #' . $row['link_id']) : 'вручную',
            (int) $row['downloads'],
            $row['revoked_at'] ? 'аннулирован ' . format_dt($row['revoked_at']) : 'действителен',
            absolute_url('verify.php?code=' . $row['code'])
        );
        fputcsv($out, $line, ';');
    }
    exit;
}

$perPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$cnt = db()->prepare("SELECT COUNT(*) FROM cert_issued i WHERE $where");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$st = db()->prepare(
    "SELECT i.*, l.note AS link_note FROM cert_issued i LEFT JOIN cert_links l ON l.id = i.link_id
     WHERE $where ORDER BY i.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage)
);
$st->execute($params);
$rows = $st->fetchAll();

page_start('Реестр — ' . $tpl['title'], $admin, 'templates');
template_tabs($tpl, 'issued');
?>
<div class="card">
  <form method="get" class="actions" style="margin-bottom:12px">
    <input type="hidden" name="template" value="<?= (int) $tpl['id'] ?>">
    <?php if ($linkFilter): ?><input type="hidden" name="link" value="<?= $linkFilter ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="ФИО, организация, номер или код" style="flex:1;min-width:220px">
    <button class="btn">Найти</button>
    <a class="btn" href="<?= h(url($selfQuery . '&export=csv')) ?>">Скачать CSV</a>
  </form>
  <p class="muted small">
    <?= $total ?> <?= plural_ru($total, 'сертификат', 'сертификата', 'сертификатов') ?>
    <?php if ($linkFilter || $q !== ''): ?> · <a href="<?= h(url($self)) ?>">сбросить фильтр</a><?php endif; ?>
  </p>
  <?php if (!$rows): ?>
    <p class="empty">Пока никто не получил сертификат.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Номер</th><?php foreach ($tpl['inputs'] as $input): ?><th><?= h($input['label']) ?></th><?php endforeach; ?><th>Выдан</th><th>Код</th><th>Скач.</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): $values = json_decode((string) $row['values_json'], true) ?: []; ?>
      <tr<?= $row['revoked_at'] ? ' style="opacity:.55"' : '' ?>>
        <td><b><?= h($row['number']) ?></b><?php if ($row['revoked_at']): ?><br><span class="badge badge--off">аннулирован</span><?php endif; ?></td>
        <?php foreach ($tpl['inputs'] as $input): ?><td><?= h($values[$input['key']] ?? '') ?></td><?php endforeach; ?>
        <td class="small"><?= h(format_dt($row['created_at'])) ?><br><span class="muted"><?= $row['link_id'] ? h($row['link_note'] ?: 'по ссылке') : 'вручную' ?></span></td>
        <td class="mono small"><a href="<?= h(url('verify.php?code=' . $row['code'])) ?>" target="_blank" rel="noopener"><?= h($row['code']) ?></a></td>
        <td><?= (int) $row['downloads'] ?></td>
        <td>
          <form method="post" class="actions inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <a class="btn btn-sm" href="<?= h(url('download.php?code=' . $row['code'])) ?>">PDF</a>
            <?php if ($row['revoked_at']): ?>
              <button class="btn btn-sm" name="action" value="restore">Вернуть</button>
            <?php else: ?>
              <button class="btn btn-sm btn-danger" name="action" value="revoke" onclick="return confirm('Аннулировать сертификат № <?= h($row['number']) ?>?')">Аннулировать</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($pages > 1): ?>
    <div class="actions" style="margin-top:12px">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a class="btn btn-sm<?= $p === $page ? ' btn-primary' : '' ?>" href="<?= h(url($selfQuery . '&page=' . $p)) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<details class="card">
  <summary><b>Выдать вручную</b> <span class="muted small">— если слушателю неудобно заполнять форму самому</span></summary>
  <form method="post" style="margin-top:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="issue">
    <div class="grid-2">
    <?php foreach ($tpl['inputs'] as $input): ?>
      <label class="field"><span class="field-label"><?= h($input['label']) ?><?= $input['required'] ? ' <span class="req">*</span>' : '' ?></span>
        <input type="text" name="v[<?= h($input['key']) ?>]" maxlength="<?= (int) $input['maxlen'] ?>" <?= $input['required'] ? 'required' : '' ?>></label>
    <?php endforeach; ?>
    </div>
    <button class="btn btn-primary">Выдать</button>
  </form>
</details>
<?php
page_end();
