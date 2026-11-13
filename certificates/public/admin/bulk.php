<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

$admin = require_admin();
$tpl = cert_template((int) ($_GET['template'] ?? 0));
if (!$tpl) {
    flash('Шаблон не найден.', 'error');
    redirect('admin/index.php');
}
$tid = (int) $tpl['id'];
$self = 'admin/bulk.php?template=' . $tid;
$state = $_SESSION['bulk'][$tid] ?? null;
$result = $_SESSION['bulk_result'][$tid] ?? null;

if (isset($_GET['zip'])) {
    $ids = array_map('intval', $result['ids'] ?? []);
    if (!$ids) {
        flash('Архив больше недоступен — скачайте его из реестра.', 'error');
        redirect($self);
    }
    $st = db()->prepare(
        'SELECT i.*, l.note AS link_note FROM cert_issued i LEFT JOIN cert_links l ON l.id = i.link_id
         WHERE i.template_id = ? AND i.id IN (' . implode(',', $ids) . ') ORDER BY i.id'
    );
    $st->execute([$tid]);
    cert_stream_zip($tpl, $st, 'Сертификаты — ' . $tpl['title'] . ' — ' . date('d.m.Y'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'reset') {
        unset($_SESSION['bulk'][$tid], $_SESSION['bulk_result'][$tid]);
    } elseif ($action === 'upload') {
        try {
            $rows = bulk_parse_input($_FILES['list'] ?? null, (string) ($_POST['pasted'] ?? ''));
            $guess = bulk_guess_mapping($tpl, $rows);
            $_SESSION['bulk'][$tid] = ['rows' => $rows, 'header' => $guess['header'], 'map' => $guess['map']];
            unset($_SESSION['bulk_result'][$tid]);
        } catch (RuntimeException $e) {
            flash($e->getMessage(), 'error');
        }
    } elseif (($action === 'map' || $action === 'issue') && $state) {
        $map = [];
        foreach ($tpl['inputs'] as $input) {
            $cols = [];
            foreach ((array) ($_POST['map'][$input['key']] ?? []) as $col) {
                if ($col !== '' && is_numeric($col)) {
                    $cols[] = (int) $col;
                }
            }
            $map[$input['key']] = array_values(array_unique($cols));
        }
        $state['map'] = $map;
        $state['header'] = !empty($_POST['header']);
        $_SESSION['bulk'][$tid] = $state;

        if ($action === 'issue') {
            @set_time_limit(0);
            $ids = [];
            $new = 0;
            $skipped = [];
            $dataRows = $state['header'] ? array_slice($state['rows'], 1, null, true) : $state['rows'];
            foreach ($dataRows as $i => $row) {
                [$values, $errors] = cert_collect_values($tpl, ['v' => bulk_row_values($tpl, $row, $map)]);
                if ($errors) {
                    $labels = array_map(static fn($k) => current(array_filter($tpl['inputs'], static fn($in) => $in['key'] === $k))['label'] ?? $k, array_keys($errors));
                    $skipped[] = [$i + 1, 'не заполнено: ' . implode(', ', $labels)];
                    continue;
                }
                [$issued, $isNew] = cert_issue($tpl, null, $values, null);
                if (!isset($ids[(int) $issued['id']])) {
                    $ids[(int) $issued['id']] = true;
                    $new += $isNew ? 1 : 0;
                }
            }
            $_SESSION['bulk_result'][$tid] = [
                'ids' => array_keys($ids),
                'new' => $new,
                'existing' => count($ids) - $new,
                'skipped' => $skipped,
            ];
            unset($_SESSION['bulk'][$tid]);
        }
    }
    redirect($self);
}

page_start('Выдача по списку — ' . $tpl['title'], $admin, 'templates');
template_tabs($tpl, 'bulk');
if (!$tpl['bg_file']) {
    echo '<div class="flash flash--error">У шаблона ещё нет фона — сертификаты будут на белом листе. Загрузите фон на вкладке «Макет».</div>';
}

if ($result):
    $total = count($result['ids']);
    ?>
  <div class="card">
    <h2>Готово</h2>
    <div class="stats">
      <div class="stat stat--ok"><b><?= (int) $result['new'] ?></b><span>выдано новых</span></div>
      <div class="stat"><b><?= (int) $result['existing'] ?></b><span>уже были выданы раньше</span></div>
      <div class="stat<?= $result['skipped'] ? ' stat--warn' : '' ?>"><b><?= count($result['skipped']) ?></b><span>строк пропущено</span></div>
    </div>
    <?php if ($result['skipped']): ?>
      <details style="margin:12px 0"><summary>Какие строки пропущены</summary>
        <ul class="small"><?php foreach (array_slice($result['skipped'], 0, 200) as [$line, $why]): ?><li>Строка <?= (int) $line ?>: <?= h($why) ?></li><?php endforeach; ?></ul>
      </details>
    <?php endif; ?>
    <div class="actions">
      <?php if ($total): ?>
        <a class="btn btn-primary btn-lg" href="<?= h(url($self . '&zip=1')) ?>">Скачать архив PDF (<?= $total ?>)</a>
      <?php endif; ?>
      <a class="btn btn-lg" href="<?= h(url('admin/issued.php?template=' . $tid)) ?>">Открыть реестр</a>
      <form method="post" class="inline-form"><?= csrf_field() ?><button class="btn btn-lg" name="action" value="reset">Загрузить другой список</button></form>
    </div>
    <p class="muted small">В архиве — PDF каждого сертификата и реестр в CSV для Excel. Архив можно скачать и позже: в реестре кнопка «Архив PDF».</p>
  </div>
<?php elseif (!$state): ?>
  <div class="card">
    <h2>Список участников</h2>
    <p class="muted">Загрузите таблицу Excel (.xlsx) или CSV либо скопируйте строки из Excel и вставьте ниже. Столбцы можно назвать как угодно — на следующем шаге вы укажете, где ФИО, а где организация. ФИО может быть одним столбцом или тремя: «Фамилия», «Имя», «Отчество».</p>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <label class="field"><span class="field-label">Файл</span>
        <input type="file" name="list" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"></label>
      <label class="field"><span class="field-label">…или вставьте строки из таблицы</span>
        <textarea name="pasted" rows="8" placeholder="Иванова Анна Сергеевна&#9;МБОУ «СОШ № 1» г. Читы&#10;Петров Иван Ильич&#9;МБОУ «СОШ № 2» г. Читы"></textarea></label>
      <button class="btn btn-primary">Дальше</button>
    </form>
  </div>
<?php else:
    $rows = $state['rows'];
    $map = $state['map'];
    $colCount = max(array_map('count', $rows));
    $dataRows = $state['header'] ? array_slice($rows, 1, null, true) : $rows;
    $prepared = [];
    $ok = 0;
    $hashes = [];
    foreach ($dataRows as $i => $row) {
        [$values, $errors] = cert_collect_values($tpl, ['v' => bulk_row_values($tpl, $row, $map)]);
        $prepared[] = [$i + 1, $values, $errors];
        if (!$errors) {
            $ok++;
            $hashes[cert_dedupe_hash($tpl, $values)] = true;
        }
    }
    $already = 0;
    if ($hashes) {
        foreach (array_chunk(array_keys($hashes), 500) as $chunk) {
            $st = db()->prepare('SELECT COUNT(*) FROM cert_issued WHERE template_id = ? AND dedupe_hash IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $st->execute(array_merge([$tid], $chunk));
            $already += (int) $st->fetchColumn();
        }
    }
    $repeats = $ok - count($hashes);
    $bad = count($prepared) - $ok;
    $shown = array_slice($prepared, 0, 15);
    foreach ($prepared as $p) {
        if ($p[2] && !in_array($p, $shown, true) && count($shown) < 40) {
            $shown[] = $p;
        }
    }
    $sample = static function (int $col) use ($rows, $state): string {
        $v = (string) ($rows[$state['header'] ? 1 : 0][$col] ?? $rows[0][$col] ?? '');

        return mb_strimwidth($v, 0, 28, '…');
    };
    ?>
  <form method="post" class="card" id="mapForm">
    <?= csrf_field() ?>
    <div class="page-head" style="margin-bottom:8px"><h2 style="margin:0">Какие столбцы куда</h2>
      <button class="btn btn-sm" name="action" value="reset" formnovalidate>Загрузить другой список</button></div>
    <label class="check" style="margin-bottom:14px"><input type="checkbox" name="header" value="1" <?= $state['header'] ? 'checked' : '' ?> data-auto>
      <span>Первая строка — заголовки<?php if ($state['header']): ?> <span class="muted small">(<?= h(implode(' · ', array_slice($rows[0], 0, 8))) ?>)</span><?php endif; ?></span></label>
    <div>
    <?php foreach ($tpl['inputs'] as $input): $cols = $map[$input['key']] ?? []; ?>
      <div class="field"><span class="field-label"><?= h($input['label']) ?><?= $input['required'] ? ' <span class="req">*</span>' : '' ?></span>
        <div class="actions">
        <?php for ($slot = 0; $slot < 3; $slot++): $sel = $cols[$slot] ?? null; ?>
          <?php if ($slot > 0 && $sel === null && ($slot > count($cols))) { continue; } ?>
          <?php if ($slot > 0): ?><span class="muted">+</span><?php endif; ?>
          <select name="map[<?= h($input['key']) ?>][]" data-auto style="max-width:240px">
            <option value=""><?= $slot === 0 ? '— не брать —' : '—' ?></option>
            <?php for ($c = 0; $c < $colCount; $c++): ?>
              <option value="<?= $c ?>" <?= $sel === $c ? 'selected' : '' ?>><?= h(bulk_column_name($c) . ($state['header'] && ($rows[0][$c] ?? '') !== '' ? ': ' . mb_strimwidth($rows[0][$c], 0, 24, '…') : '') . ' — ' . $sample($c)) ?></option>
            <?php endfor; ?>
          </select>
        <?php endfor; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <p class="muted small" style="margin-top:0">Чтобы собрать поле из нескольких столбцов (например, Фамилия + Имя + Отчество), выберите столбец в дополнительном списке справа.</p>

    <h2>Проверка перед выдачей</h2>
    <p>
      В списке <b><?= count($prepared) ?></b> <?= plural_ru(count($prepared), 'строка', 'строки', 'строк') ?>:
      готово к выдаче <b><?= $ok ?></b><?php if ($bad): ?>, <span style="color:var(--danger)">с ошибками <b><?= $bad ?></b> (будут пропущены)</span><?php endif; ?>.
      <?php if ($repeats > 0): ?><br><span class="muted">Повторов в списке: <?= $repeats ?> — на каждого человека будет один сертификат.</span><?php endif; ?>
      <?php if ($already > 0): ?><br><span class="muted">Уже получали сертификат: <?= $already ?> — новые номера им не присваиваются, в архив попадут выданные раньше.</span><?php endif; ?>
    </p>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Строка</th><?php foreach ($tpl['inputs'] as $input): ?><th><?= h($input['label']) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($shown as [$line, $values, $errors]): ?>
        <tr<?= $errors ? ' style="background:#fff5f5"' : '' ?>>
          <td class="muted"><?= (int) $line ?></td>
          <?php foreach ($tpl['inputs'] as $input): ?>
            <td><?= isset($errors[$input['key']]) ? '<span style="color:var(--danger)">не заполнено</span>' : h($values[$input['key']]) ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (count($prepared) > count($shown)): ?><p class="muted small">Показаны первые строки и все строки с ошибками.</p><?php endif; ?>
    <div class="actions" style="margin-top:14px">
      <button class="btn btn-primary btn-lg" name="action" value="issue" <?= $ok ? '' : 'disabled' ?>
        onclick="this.textContent='Выдаём…';">Выдать <?= $ok ?> <?= plural_ru($ok, 'сертификат', 'сертификата', 'сертификатов') ?></button>
      <noscript><button class="btn" name="action" value="map">Обновить проверку</button></noscript>
    </div>
  </form>
  <script>
  document.querySelectorAll('#mapForm [data-auto]').forEach(function (el) {
    el.addEventListener('change', function () {
      var form = document.getElementById('mapForm');
      var a = document.createElement('input');
      a.type = 'hidden'; a.name = 'action'; a.value = 'map';
      form.appendChild(a);
      form.submit();
    });
  });
  </script>
<?php endif;
page_end();
