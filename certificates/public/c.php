<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/app.php';

$token = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($_GET['t'] ?? ''))) ?? '';
$link = null;
if ($token !== '') {
    $st = db()->prepare('SELECT * FROM cert_links WHERE token = ?');
    $st->execute([$token]);
    $link = $st->fetch() ?: null;
}
$tpl = $link ? cert_template((int) $link['template_id']) : null;
$self = 'c.php?t=' . $token;

function public_message(string $title, string $text, string $kind = 'warn'): void
{
    page_start($title);
    echo '<div class="card public-card"><div class="result-icon result-icon--' . h($kind) . '">' . ($kind === 'bad' ? '✕' : '!') . '</div>'
        . '<h1 class="public-title">' . h($title) . '</h1><p class="lead">' . h($text) . '</p>'
        . '<p class="muted small">Если это ошибка, напишите организатору мероприятия.</p></div>';
    page_end();
    exit;
}

if (!$link || !$tpl) {
    http_response_code(404);
    public_message('Ссылка не найдена', 'Проверьте, что ссылка скопирована полностью.', 'bad');
}

$issuedCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['code'] ?? '')) ?? '');
if ($issuedCode !== '') {
    $issued = cert_issued_by_code($issuedCode);
    if (!$issued || (int) $issued['template_id'] !== (int) $tpl['id']) {
        redirect($self);
    }
    $f = flash();
    page_start('Сертификат готов');
    $name = cert_holder_name($tpl, $issued['values']);
    ?>
    <div class="card public-card">
      <div class="result-icon">✓</div>
      <h1 class="public-title">Сертификат готов</h1>
      <?php if ($f): ?><p class="lead"><?= h($f['text']) ?></p><?php endif; ?>
      <div class="kv">
        <div><span>Кому</span><b><?= h($name) ?></b></div>
        <div><span>Мероприятие</span><b><?= h($tpl['title']) ?></b></div>
        <div><span>Номер</span><b><?= h($issued['number']) ?></b></div>
        <div><span>Код проверки</span><b class="mono"><?= h($issued['code']) ?></b></div>
      </div>
      <div class="actions" style="justify-content:center">
        <a class="btn btn-primary btn-lg" href="<?= h(url('download.php?code=' . $issued['code'])) ?>">Скачать PDF</a>
        <a class="btn btn-lg" href="<?= h(url('download.php?code=' . $issued['code'] . '&inline=1')) ?>" target="_blank" rel="noopener">Открыть</a>
      </div>
      <p class="muted small">Сохраните код проверки: по нему сертификат можно скачать ещё раз и проверить его подлинность на странице
        <a href="<?= h(url('verify.php?code=' . $issued['code'])) ?>">проверки</a>.</p>
    </div>
    <?php
    page_end();
    exit;
}

$issuedCount = (int) db()->query('SELECT COUNT(*) FROM cert_issued WHERE link_id = ' . (int) $link['id'])->fetchColumn();
$problem = cert_link_problem($link, $issuedCount);
if ($problem !== null) {
    public_message('Получить сертификат нельзя', $problem);
}

$values = [];
$errors = [];
$formError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    [$values, $errors] = cert_collect_values($tpl, $_POST);

    $attemptsKey = 'code_attempts_' . $link['id'];
    $attempts = $_SESSION[$attemptsKey] ?? ['n' => 0, 'until' => 0];
    if ((string) ($_POST['website'] ?? '') !== '') {
        $formError = 'Не удалось отправить форму.';
    } elseif ($link['access_code_hash']) {
        if ($attempts['until'] > time()) {
            $formError = 'Слишком много неверных попыток. Подождите минуту.';
        } elseif (!password_verify(mb_strtolower(trim((string) ($_POST['access_code'] ?? ''))), (string) $link['access_code_hash'])) {
            $attempts['n']++;
            if ($attempts['n'] >= 5) {
                $attempts = ['n' => 0, 'until' => time() + 60];
            }
            $_SESSION[$attemptsKey] = $attempts;
            $errors['access_code'] = 'Неверное кодовое слово. Уточните его у организатора.';
        } else {
            unset($_SESSION[$attemptsKey]);
        }
    }
    if ($formError === '' && empty($_POST['confirm'])) {
        $errors['confirm'] = 'Подтвердите, что данные введены верно.';
    }

    if ($formError === '' && !$errors) {
        $limit = (int) cfg('max_per_ip_hour');
        $st = db()->prepare('SELECT COUNT(*) FROM cert_issued WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR');
        $st->execute([client_ip()]);
        if ($limit > 0 && (int) $st->fetchColumn() >= $limit) {
            $formError = 'С этого адреса выдано слишком много сертификатов за час. Попробуйте позже.';
        } else {
            try {
                [$row, $isNew] = cert_issue($tpl, $link, $values, client_ip());
                flash($isNew ? 'Проверьте данные и скачайте файл.' : 'Сертификат с такими данными уже выдавался — вот он.');
                redirect($self . '&code=' . $row['code']);
            } catch (RuntimeException $e) {
                $formError = $e->getMessage();
            }
        }
    }
}

page_start($tpl['title']);
?>
<div class="card public-card">
  <p class="public-sub">Получение сертификата</p>
  <h1 class="public-title"><?= h($tpl['title']) ?></h1>
  <?php if ($link['note']): ?><p class="muted"><?= h($link['note']) ?></p><?php endif; ?>
  <?php if ($formError !== ''): ?><div class="flash flash--error"><?= h($formError) ?></div><?php endif; ?>

  <form method="post" novalidate style="text-align:left">
    <?= csrf_field() ?>
    <div style="position:absolute;left:-10000px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
    <?php foreach ($tpl['inputs'] as $input): $k = $input['key']; ?>
      <label class="field"><span class="field-label"><?= h($input['label']) ?><?= $input['required'] ? ' <span class="req">*</span>' : '' ?></span>
        <input type="text" name="v[<?= h($k) ?>]" value="<?= h($values[$k] ?? '') ?>" maxlength="<?= (int) $input['maxlen'] ?>"
          <?= $input['required'] ? 'required' : '' ?> <?= $k === 'fio' ? 'autocomplete="name"' : ($k === 'org' ? 'autocomplete="organization"' : '') ?>>
        <?php if (isset($errors[$k])): ?><span class="field-hint" style="color:var(--danger)"><?= h($errors[$k]) ?></span>
        <?php elseif ($input['hint'] !== ''): ?><span class="field-hint"><?= h($input['hint']) ?></span><?php endif; ?>
      </label>
    <?php endforeach; ?>
    <?php if ($link['access_code_hash']): ?>
      <label class="field"><span class="field-label">Кодовое слово <span class="req">*</span></span>
        <input type="text" name="access_code" autocomplete="off" required>
        <?php if (isset($errors['access_code'])): ?><span class="field-hint" style="color:var(--danger)"><?= h($errors['access_code']) ?></span>
        <?php else: ?><span class="field-hint">Его сообщил организатор.</span><?php endif; ?>
      </label>
    <?php endif; ?>
    <label class="check" style="margin:4px 0 16px">
      <input type="checkbox" name="confirm" value="1" <?= !empty($_POST['confirm']) ? 'checked' : '' ?>>
      <span>Данные написаны верно — в сертификате они будут именно так<?php if (isset($errors['confirm'])): ?><br><span class="small" style="color:var(--danger)"><?= h($errors['confirm']) ?></span><?php endif; ?></span>
    </label>
    <button type="submit" class="btn btn-primary btn-lg btn-block">Получить сертификат</button>
  </form>
  <?php if ($link['expires_at']): ?>
    <p class="footer-note">Ссылка действует до <?= h(format_dt($link['expires_at'])) ?></p>
  <?php endif; ?>
</div>
<?php
page_end();
