<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

$admin = require_admin();
$tpl = cert_template((int) ($_GET['id'] ?? 0));
if (!$tpl) {
    flash('Шаблон не найден.', 'error');
    redirect('admin/index.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $next = cert_template_from_post($tpl, $_POST);
    try {
        $upload = cert_uploaded_background($_FILES['bg'] ?? null);
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
        redirect('admin/template.php?id=' . $tpl['id']);
    }
    $bgFile = $tpl['bg_file'];
    $bgW = $tpl['bg_width'];
    $bgH = $tpl['bg_height'];
    if ($upload !== null) {
        $name = bin2hex(random_bytes(16)) . '.jpg';
        if (!move_uploaded_file($upload['tmp'], CERT_STORAGE . '/backgrounds/' . $name)) {
            flash('Не удалось сохранить фон: проверьте права на папку storage/backgrounds.', 'error');
            redirect('admin/template.php?id=' . $tpl['id']);
        }
        $old = cert_background_path($tpl);
        if ($old !== null) {
            @unlink($old);
        }
        [$bgFile, $bgW, $bgH] = [$name, $upload['w'], $upload['h']];
    }
    if ($next['inputs'] === []) {
        flash('Нужно хотя бы одно поле, которое заполняет слушатель.', 'error');
        redirect('admin/template.php?id=' . $tpl['id']);
    }
    db()->prepare(
        'UPDATE cert_templates SET title = ?, bg_file = ?, bg_width = ?, bg_height = ?, inputs_json = ?, fields_json = ?,
                number_prefix = ?, number_digits = ? WHERE id = ?'
    )->execute([
        $next['title'], $bgFile, $bgW, $bgH,
        json_encode($next['inputs'], JSON_UNESCAPED_UNICODE),
        json_encode($next['fields'], JSON_UNESCAPED_UNICODE),
        $next['number_prefix'], $next['number_digits'], $tpl['id'],
    ]);
    flash('Шаблон сохранён.');
    redirect('admin/template.php?id=' . $tpl['id']);
}

[$pageW, $pageH] = cert_page_size($tpl);
$fontOptions = [];
foreach (cert_fonts() as $key => $f) {
    $fontOptions[] = ['key' => $key, 'label' => $f['label'], 'family' => $f['family'], 'weight' => $f['weight'], 'style' => $f['style']];
}
$editorData = [
    'inputs' => $tpl['inputs'],
    'fields' => $tpl['fields'],
    'page' => ['w' => $pageW, 'h' => $pageH],
    'bg' => $tpl['bg_file'] ? url('admin/bg.php?id=' . $tpl['id'] . '&v=' . strtotime((string) $tpl['updated_at'])) : '',
    'fonts' => $fontOptions,
    'system' => cert_system_placeholders(),
    'samples' => cert_sample_values($tpl),
    'sampleSystem' => [
        'number' => cert_format_number($tpl, (int) $tpl['next_number']),
        'date' => date('d.m.Y'),
        'date_long' => cert_date_long(date('Y-m-d')),
        'code' => 'PRVW7K3M9Q',
        'verify_url' => absolute_url('verify.php?code=PRVW7K3M9Q'),
        'title' => $tpl['title'],
    ],
];

page_start($tpl['title'], $admin, 'templates');
template_tabs($tpl, 'design');
?>
<form method="post" enctype="multipart/form-data" id="tplForm">
  <?= csrf_field() ?>
  <input type="hidden" name="inputs_json" id="inputsJson">
  <input type="hidden" name="fields_json" id="fieldsJson">
  <div class="editor">
    <div class="editor-stage-wrap">
      <div class="editor-stage" id="stage"></div>
      <p class="muted small">Перетаскивайте надписи мышью, стрелки на клавиатуре сдвигают выбранную точнее (с Shift — сильнее). Точка — место привязки строки. Если текст длиннее области, он уменьшается, а при нескольких строках переносится: точный вид — в предпросмотре PDF.</p>
    </div>

    <div class="editor-side">
      <div class="card">
        <label class="field"><span class="field-label">Название шаблона</span>
          <input type="text" name="title" value="<?= h($tpl['title']) ?>" maxlength="255" required></label>
        <label class="field" style="margin-bottom:0"><span class="field-label">Фон сертификата</span>
          <input type="file" name="bg" id="bgFile" accept="image/jpeg,image/png,image/webp">
          <span class="field-hint">JPG или PNG. Для печати лучше 3508×2480 точек (А4, 300 dpi). Картинка переводится в JPG прямо в браузере.</span>
        </label>
      </div>

      <div class="card">
        <h2>Надписи на сертификате</h2>
        <ul class="el-list" id="elList"></ul>
        <button type="button" class="btn btn-sm" id="btnAddEl">+ Надпись</button>
        <div id="elProps" hidden style="margin-top:14px;border-top:1px solid var(--border);padding-top:14px">
          <label class="field"><span class="field-label">Текст</span>
            <textarea id="pText" rows="2"></textarea>
            <span class="field-hint">Подставляется при выдаче:</span>
            <span class="ph-chips" id="phChips"></span>
          </label>
          <div class="grid-2">
            <label class="field"><span class="field-label">Шрифт</span><select id="pFont"></select></label>
            <label class="field"><span class="field-label">Размер, пт</span><input type="number" id="pSize" min="4" max="160" step="0.5"></label>
            <label class="field"><span class="field-label">Цвет</span><input type="color" id="pColor"></label>
            <label class="field"><span class="field-label">Выравнивание</span>
              <select id="pAlign"><option value="left">по левому краю</option><option value="center">по центру</option><option value="right">по правому краю</option></select></label>
            <label class="field"><span class="field-label">Ширина области, %</span><input type="number" id="pMaxw" min="5" max="100" step="1"></label>
            <label class="field"><span class="field-label">Строк не больше</span><input type="number" id="pLines" min="1" max="6" step="1"></label>
            <label class="field"><span class="field-label">X, %</span><input type="number" id="pX" min="0" max="100" step="0.1"></label>
            <label class="field"><span class="field-label">Y, %</span><input type="number" id="pY" min="0" max="100" step="0.1"></label>
          </div>
          <div class="actions">
            <button type="button" class="btn btn-sm" id="btnCenterEl">По центру листа</button>
            <button type="button" class="btn btn-sm btn-danger" id="btnDelEl">Удалить надпись</button>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>Что заполняет слушатель</h2>
        <div class="input-rows" id="inputRows"></div>
        <button type="button" class="btn btn-sm" id="btnAddInput">+ Поле</button>
      </div>

      <div class="card">
        <h2>Нумерация</h2>
        <div class="grid-2">
          <label class="field"><span class="field-label">Начало номера</span>
            <input type="text" name="number_prefix" id="numPrefix" value="<?= h($tpl['number_prefix']) ?>" maxlength="40"></label>
          <label class="field"><span class="field-label">Цифр в номере</span>
            <input type="number" name="number_digits" id="numDigits" value="<?= (int) $tpl['number_digits'] ?>" min="1" max="10"></label>
        </div>
        <p class="muted small" style="margin:0">Следующий номер: <b id="numSample"><?= h(cert_format_number($tpl, (int) $tpl['next_number'])) ?></b></p>
      </div>
    </div>
  </div>
  <div class="save-bar">
    <button type="submit" class="btn" formaction="<?= h(url('admin/preview.php?id=' . $tpl['id'])) ?>" formtarget="_blank">Предпросмотр PDF</button>
    <button type="submit" class="btn btn-primary">Сохранить</button>
  </div>
</form>
<script>window.CERT_EDITOR = <?= json_encode($editorData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.CERT_NEXT_NUMBER = <?= (int) $tpl['next_number'] ?>;</script>
<script src="<?= h(url('assets/editor.js') . '?v=' . (string) (@filemtime(CERT_PUBLIC . '/assets/editor.js') ?: 1)) ?>"></script>
<?php
page_end();
