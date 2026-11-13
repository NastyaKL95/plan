<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/app.php';

$admin = current_admin();
$code = trim((string) ($_GET['code'] ?? ''));
$issued = $code !== '' ? cert_issued_by_code($code) : null;
$tpl = $issued ? cert_template((int) $issued['template_id']) : null;

page_start('Проверка сертификата', $admin, 'verify');
?>
<div class="card public-card">
  <p class="public-sub">Проверка подлинности</p>
  <h1 class="public-title">Проверить сертификат</h1>
  <form method="get" class="copy-row" style="margin:12px 0 4px">
    <input type="text" name="code" value="<?= h($code) ?>" placeholder="Код с сертификата, например K7M2Q9XA4D" class="mono" maxlength="20" required autofocus>
    <button type="submit" class="btn btn-primary">Проверить</button>
  </form>

  <?php if ($code !== '' && (!$issued || !$tpl)): ?>
    <div class="result-icon result-icon--bad" style="margin-top:20px">✕</div>
    <p class="lead">Сертификат с кодом <b class="mono"><?= h(strtoupper($code)) ?></b> не найден.</p>
  <?php elseif ($issued && $tpl): ?>
    <?php if ($issued['revoked_at']): ?>
      <div class="result-icon result-icon--bad" style="margin-top:20px">✕</div>
      <p class="lead"><b>Сертификат аннулирован</b> <?= h(format_dt($issued['revoked_at'])) ?></p>
    <?php else: ?>
      <div class="result-icon" style="margin-top:20px">✓</div>
      <p class="lead"><b>Сертификат действителен</b></p>
    <?php endif; ?>
    <div class="kv">
      <?php foreach ($tpl['inputs'] as $input): if (($issued['values'][$input['key']] ?? '') === '') { continue; } ?>
        <div><span><?= h($input['label']) ?></span><b><?= h($issued['values'][$input['key']]) ?></b></div>
      <?php endforeach; ?>
      <div><span>Мероприятие</span><b><?= h($tpl['title']) ?></b></div>
      <div><span>Номер</span><b><?= h($issued['number']) ?></b></div>
      <div><span>Дата выдачи</span><b><?= h(date('d.m.Y', strtotime((string) $issued['issued_on']))) ?></b></div>
    </div>
    <?php if (!$issued['revoked_at']): ?>
      <a class="btn" href="<?= h(url('download.php?code=' . $issued['code'] . '&inline=1')) ?>" target="_blank" rel="noopener">Открыть сертификат</a>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
page_end();
