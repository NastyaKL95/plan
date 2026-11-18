<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/documents.php';

$user = require_login();
planner_documents_ensure_schema();
$mayManage = user_may_manage_documents($user);
$types = planner_document_types();

$deptSt = db()->prepare('SELECT id, name FROM departments WHERE organization_id = ? ORDER BY sort_order, name');
$deptSt->execute([(int) $user['organization_id']]);
$departments = $deptSt->fetchAll(PDO::FETCH_ASSOC);

$e = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$pageConfig = [
    'types' => $types,
    'mayManage' => $mayManage,
    'extensions' => planner_document_extensions(),
    'maxBytes' => PLANNER_DOCUMENT_MAX_BYTES,
];

layout_start($user, 'Документы', 'documents');
?>
<section class="docs-page">
  <div class="bx-main-card">
    <header class="docs-head">
      <div>
        <h1 class="bx-page-title">Документы</h1>
        <p class="docs-sub">Инструкции, положения, регламенты и приказы организации</p>
      </div>
      <?php if ($mayManage): ?>
      <button type="button" class="btn btn-primary" id="btnDocAdd">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Добавить документ
      </button>
      <?php endif; ?>
    </header>

    <div class="docs-search">
      <svg class="docs-search-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="search" id="docSearch" placeholder="Поиск по названию, номеру, описанию и тексту документа" autocomplete="off" aria-label="Поиск документов">
      <kbd class="docs-kbd" title="Нажмите /, чтобы начать поиск">/</kbd>
    </div>

    <nav class="docs-types" id="docTypes" aria-label="Вид документа">
      <button type="button" class="docs-type is-active" data-type=""><span>Все</span><b data-count="">0</b></button>
      <?php foreach ($types as $key => $t): ?>
      <button type="button" class="docs-type" data-type="<?= $e($key) ?>" style="--doc-c: <?= $e($t['color']) ?>">
        <i class="docs-type-dot" aria-hidden="true"></i><span><?= $e($t['plural']) ?></span><b data-count="<?= $e($key) ?>">0</b>
      </button>
      <?php endforeach; ?>
    </nav>

    <div class="docs-filters">
      <select id="docDept" class="bx-select" aria-label="Подразделение">
        <option value="">Все подразделения</option>
        <?php foreach ($departments as $d): ?>
        <option value="<?= (int) $d['id'] ?>"><?= $e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="docYear" class="bx-select" aria-label="Год документа">
        <option value="">Все годы</option>
      </select>
      <div class="docs-seg" role="group" aria-label="Статус" id="docStatus">
        <button type="button" data-status="active" class="is-active">Действующие</button>
        <button type="button" data-status="inactive">Утратили силу</button>
        <button type="button" data-status="all">Все</button>
      </div>
      <select id="docSort" class="bx-select" aria-label="Сортировка">
        <option value="date_desc">Сначала новые</option>
        <option value="date_asc">Сначала старые</option>
        <option value="title">По названию</option>
        <option value="recent">Недавно изменённые</option>
      </select>
      <button type="button" class="docs-reset" id="docReset" hidden>Сбросить фильтры</button>
    </div>

    <p class="docs-summary" id="docSummary" aria-live="polite"></p>
    <div class="docs-list" id="docList" aria-busy="true"></div>
  </div>
</section>

<?php if ($mayManage): ?>
<dialog class="modal docs-modal" id="docModal">
  <form method="dialog" class="modal-form" id="docForm" novalidate>
    <h2 class="modal-title" id="docModalTitle">Новый документ</h2>
    <input type="hidden" id="docId" value="">

    <label class="docs-drop" id="docDrop">
      <input type="file" id="docFile" name="file" accept="<?= $e('.' . implode(',.', planner_document_extensions())) ?>">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6M9 15l3-3 3 3"/></svg>
      <span class="docs-drop-text" id="docDropText">Перетащите файл сюда или <u>выберите на компьютере</u></span>
      <span class="docs-drop-hint" id="docDropHint">PDF, Word, Excel, PowerPoint, картинки — до <?= (int) (PLANNER_DOCUMENT_MAX_BYTES / 1024 / 1024) ?> МБ. По тексту Word-файлов работает поиск.</span>
    </label>

    <span class="field-label">Вид документа</span>
    <div class="docs-type-pick" id="docTypePick" role="radiogroup" aria-label="Вид документа">
      <?php foreach ($types as $key => $t): ?>
      <label style="--doc-c: <?= $e($t['color']) ?>"><input type="radio" name="doc_type" value="<?= $e($key) ?>"><span><?= $e($t['label']) ?></span></label>
      <?php endforeach; ?>
    </div>

    <label class="field-label" for="docTitle">Название</label>
    <textarea id="docTitle" name="title" class="input-block docs-title-input" rows="2" maxlength="500" required placeholder="Например: Положение о порядке проведения курсов повышения квалификации"></textarea>

    <div class="docs-form-row">
      <div>
        <label class="field-label" for="docNumber">Номер</label>
        <input type="text" id="docNumber" name="doc_number" class="input-block" maxlength="60" placeholder="№ 45-од">
      </div>
      <div>
        <label class="field-label" for="docDate">Дата</label>
        <input type="date" id="docDate" name="doc_date" class="input-block">
      </div>
    </div>

    <label class="field-label" for="docDeptInput">Подразделение</label>
    <select id="docDeptInput" name="department_id" class="input-block">
      <option value="">Для всей организации</option>
      <?php foreach ($departments as $d): ?>
      <option value="<?= (int) $d['id'] ?>"><?= $e($d['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="field-label" for="docDesc">Кратко о документе</label>
    <textarea id="docDesc" name="description" class="input-block" rows="3" maxlength="5000" placeholder="О чём документ и кому он нужен"></textarea>

    <div class="docs-checks">
      <label class="docs-check"><input type="checkbox" id="docActive" name="is_active" value="1" checked> Действующий</label>
      <label class="docs-check"><input type="checkbox" id="docPinned" name="is_pinned" value="1"> Закрепить вверху списка</label>
    </div>
    <p class="docs-form-error" id="docFormError" role="alert" hidden></p>

    <div class="modal-actions modal-actions--split">
      <button type="button" class="btn btn-danger-outline" id="docDelete" hidden>Удалить</button>
      <div class="modal-actions-right">
        <button type="button" class="btn btn-secondary" id="docCancel">Отмена</button>
        <button type="submit" class="btn btn-primary" id="docSave">Сохранить</button>
      </div>
    </div>
  </form>
</dialog>
<?php endif; ?>

<script>window.__DOCS__ = <?= json_encode($pageConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $e(planner_url('/assets/js/documents.js') . '?v=' . (string) (@filemtime(__DIR__ . '/assets/js/documents.js') ?: 1)) ?>"></script>
<?php layout_end(); ?>
