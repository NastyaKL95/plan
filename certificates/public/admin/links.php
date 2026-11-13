<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

$admin = require_admin();
$tpl = cert_template((int) ($_GET['template'] ?? 0));
if (!$tpl) {
    flash('Шаблон не найден.', 'error');
    redirect('admin/index.php');
}
$self = 'admin/links.php?template=' . $tpl['id'];

function links_parse_dt(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value) ?: DateTime::createFromFormat('Y-m-d H:i', $value);

    return $dt ? $dt->format('Y-m-d H:i:00') : null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $linkId = (int) ($_POST['link_id'] ?? 0);

    if ($action === 'create') {
        $starts = links_parse_dt((string) ($_POST['starts_at'] ?? ''));
        $expires = links_parse_dt((string) ($_POST['expires_at'] ?? ''));
        if ($starts && $expires && strtotime($expires) <= strtotime($starts)) {
            flash('Окончание должно быть позже начала.', 'error');
            redirect($self);
        }
        $maxUses = (int) ($_POST['max_uses'] ?? 0);
        $code = trim((string) ($_POST['access_code'] ?? ''));
        db()->prepare(
            'INSERT INTO cert_links (template_id, token, note, starts_at, expires_at, max_uses, access_code_hash) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $tpl['id'],
            random_code(14, 'abcdefghijkmnpqrstuvwxyz23456789'),
            mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255) ?: null,
            $starts,
            $expires,
            $maxUses > 0 ? $maxUses : null,
            $code !== '' ? password_hash(mb_strtolower($code), PASSWORD_DEFAULT) : null,
        ]);
        flash('Ссылка создана — скопируйте её и отправьте слушателям.');
    } elseif ($action === 'toggle' && $linkId) {
        db()->prepare('UPDATE cert_links SET is_active = 1 - is_active WHERE id = ? AND template_id = ?')->execute([$linkId, $tpl['id']]);
    } elseif ($action === 'extend' && $linkId) {
        db()->prepare(
            'UPDATE cert_links SET expires_at = DATE_ADD(GREATEST(COALESCE(expires_at, NOW()), NOW()), INTERVAL 7 DAY)
             WHERE id = ? AND template_id = ?'
        )->execute([$linkId, $tpl['id']]);
        flash('Ссылка продлена на 7 дней.');
    } elseif ($action === 'delete' && $linkId) {
        db()->prepare('DELETE FROM cert_links WHERE id = ? AND template_id = ?')->execute([$linkId, $tpl['id']]);
        flash('Ссылка удалена. Выданные по ней сертификаты остаются в реестре.');
    }
    redirect($self);
}

$stmt = db()->prepare(
    'SELECT l.*, (SELECT COUNT(*) FROM cert_issued i WHERE i.link_id = l.id) AS issued_count
     FROM cert_links l WHERE l.template_id = ? ORDER BY l.id DESC'
);
$stmt->execute([$tpl['id']]);
$links = $stmt->fetchAll();

page_start('Ссылки — ' . $tpl['title'], $admin, 'templates');
template_tabs($tpl, 'links');
if (!$tpl['bg_file']) {
    echo '<div class="flash flash--error">У шаблона ещё нет фона — слушатели получат сертификат на белом листе. Загрузите фон на вкладке «Макет».</div>';
}
?>
<div class="card">
  <h2>Новая ссылка</h2>
  <form method="post" id="linkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="grid-4">
      <label class="field"><span class="field-label">Для кого</span>
        <input type="text" name="note" maxlength="255" placeholder="Например: КПК «Цифровая школа», группа 2"></label>
      <label class="field"><span class="field-label">Действует с</span>
        <input type="datetime-local" name="starts_at" value="<?= h(date('Y-m-d\TH:i')) ?>"></label>
      <label class="field"><span class="field-label">Действует до</span>
        <input type="datetime-local" name="expires_at" id="expiresAt" value="<?= h(date('Y-m-d\T23:59', strtotime('+7 days'))) ?>">
        <span class="field-hint">
          <button type="button" class="link" data-days="1">сутки</button> ·
          <button type="button" class="link" data-days="7">неделя</button> ·
          <button type="button" class="link" data-days="30">месяц</button> ·
          <button type="button" class="link" data-days="0">без срока</button>
        </span></label>
      <label class="field"><span class="field-label">Сертификатов не больше</span>
        <input type="number" name="max_uses" min="0" placeholder="без ограничения"></label>
    </div>
    <div class="grid-4">
      <label class="field"><span class="field-label">Кодовое слово</span>
        <input type="text" name="access_code" maxlength="60" autocomplete="off" placeholder="необязательно">
        <span class="field-hint">Слушатель вводит его вместе с ФИО — так ссылку не используют посторонние.</span></label>
    </div>
    <button type="submit" class="btn btn-primary">Создать ссылку</button>
  </form>
</div>

<div class="card">
  <h2>Ссылки шаблона</h2>
  <?php if (!$links): ?>
    <p class="empty">Ссылок пока нет.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ссылка</th><th>Срок</th><th>Выдано</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($links as $link):
        $problem = cert_link_problem($link, (int) $link['issued_count']);
        $badge = $problem === null ? ['ok', 'работает']
            : (empty($link['is_active']) ? ['off', 'отключена']
            : (!empty($link['starts_at']) && strtotime((string) $link['starts_at']) > time() ? ['warn', 'ещё не началась']
            : (!empty($link['max_uses']) && (int) $link['issued_count'] >= (int) $link['max_uses'] ? ['warn', 'лимит исчерпан'] : ['off', 'истекла'])));
        $publicUrl = absolute_url('c.php?t=' . $link['token']);
        ?>
      <tr>
        <td style="min-width:280px">
          <?php if ($link['note']): ?><div><b><?= h($link['note']) ?></b></div><?php endif; ?>
          <div class="copy-row"><input type="text" readonly value="<?= h($publicUrl) ?>" class="mono"><button type="button" class="btn btn-sm" data-copy>Копировать</button></div>
          <?php if ($link['access_code_hash']): ?><div class="small muted">с кодовым словом</div><?php endif; ?>
        </td>
        <td class="small">с <?= h(format_dt($link['starts_at'] ?: $link['created_at'])) ?><br>до <?= $link['expires_at'] ? h(format_dt($link['expires_at'])) : 'без срока' ?></td>
        <td><a href="<?= h(url('admin/issued.php?template=' . $tpl['id'] . '&link=' . $link['id'])) ?>"><?= (int) $link['issued_count'] ?></a><?= $link['max_uses'] ? ' из ' . (int) $link['max_uses'] : '' ?></td>
        <td><span class="badge badge--<?= $badge[0] ?>"><?= h($badge[1]) ?></span></td>
        <td>
          <div class="actions">
            <a class="btn btn-sm" href="<?= h($publicUrl) ?>" target="_blank" rel="noopener">Открыть</a>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
              <button class="btn btn-sm" name="action" value="extend">+7 дней</button>
              <button class="btn btn-sm" name="action" value="toggle"><?= $link['is_active'] ? 'Отключить' : 'Включить' ?></button>
              <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="Удалить ссылку? Слушатели больше не смогут по ней получить сертификат.">Удалить</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = btn.parentNode.querySelector('input');
    input.select();
    var done = function () { btn.textContent = 'Скопировано'; setTimeout(function () { btn.textContent = 'Копировать'; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(input.value).then(done);
    else { document.execCommand('copy'); done(); }
  });
});
document.querySelectorAll('[data-days]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var days = parseInt(btn.getAttribute('data-days'), 10);
    var input = document.getElementById('expiresAt');
    if (!days) { input.value = ''; return; }
    var d = new Date(Date.now() + days * 86400000);
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    input.value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T23:59';
  });
});
document.querySelectorAll('[data-confirm]').forEach(function (btn) {
  btn.addEventListener('click', function (e) { if (!confirm(btn.getAttribute('data-confirm'))) e.preventDefault(); });
});
</script>
<?php
page_end();
