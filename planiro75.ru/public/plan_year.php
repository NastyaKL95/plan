<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
if (!function_exists('user_may_access_org_plan_console') || !user_may_access_org_plan_console($user)) {
    http_response_code(403);
    layout_start($user, 'Доступ запрещён', 'dashboard');
    echo '<div class="page-head"><h1 class="page-title">Доступ запрещён</h1><p class="muted">Раздел «План года» и импорт Excel доступны только пользователям с ролью «Администратор».</p></div>';
    layout_end();
    exit;
}

$curYear = (int) ($user['organization_work_year'] ?? (int) date('Y'));
layout_start($user, 'План года', 'plan_year');
?>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>План года</span>
</nav>

<div class="page-head">
  <h1 class="page-title">План года</h1>
  <p class="muted page-subline">Смена года работы организации и массовая загрузка задач из Excel для выбранного года. Проекты с таким же названием должны уже существовать в системе для соответствующего года работы организации.</p>
</div>

<div class="content-stack">
  <section class="panel plan-year-panel">
    <h2 class="panel-title">Год работы организации</h2>
    <p class="muted">Текущий год в интерфейсе: <strong id="planYearCurrent"><?= (int) $curYear ?></strong>. После смены года в списках отображаются задачи и проекты выбранного года работы организации.</p>
    <div class="plan-year-row">
      <label class="field-label" for="planYearInput">Новый год работы организации (год)</label>
      <div class="plan-year-actions">
        <input type="number" id="planYearInput" class="input-block" min="1990" max="2100" step="1" value="<?= (int) $curYear ?>" inputmode="numeric">
        <button type="button" class="btn btn-primary" id="planYearSave">Применить</button>
      </div>
    </div>
  </section>

  <section class="panel plan-year-panel">
    <h2 class="panel-title">Импорт задач из Excel (.xlsx)</h2>
    <p class="muted">Колонки можно задать заголовками в первой строке (см. шаблон) или использовать порядок по умолчанию: задача, проект, подразделение, срок, email исполнителя. В колонке «Срок» — дата (31.12.2026, Excel «Дата»); время подставится из настройки организации (профиль администратора или MAX: /settings due_time 18:00). Выгрузка года — в том же формате; повторный импорт создаёт новые задачи.</p>
    <div class="plan-year-template-actions">
      <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars(planner_url('/api/plan_import.php?template=1'), ENT_QUOTES, 'UTF-8') ?>">Скачать пустой шаблон</a>
      <a class="btn btn-secondary btn-sm" id="planExportLink" href="<?= htmlspecialchars(planner_url('/api/plan_import.php?export=1&work_year=' . $curYear), ENT_QUOTES, 'UTF-8') ?>">Выгрузить все задачи года</a>
    </div>
    <label class="field-label" for="planImportYear">Год работы организации, в который добавить задачи</label>
    <input type="number" id="planImportYear" class="input-block plan-year-input-narrow" min="1990" max="2100" step="1" value="<?= (int) $curYear ?>" inputmode="numeric">
    <label class="field-label" for="planImportFile">Файл .xlsx</label>
    <div class="bx-file-field bx-file-field--block">
      <input type="file" id="planImportFile" class="bx-file-field__native" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
      <div class="bx-file-field__face" aria-hidden="true">
        <span class="bx-file-field__btn">Выбрать файл</span>
        <span class="bx-file-field__name" data-bx-file-name data-bx-file-placeholder="Файл не выбран">Файл не выбран</span>
      </div>
    </div>
    <div class="plan-year-import-actions">
      <button type="button" class="btn btn-primary" id="planImportBtn">Загрузить и импортировать</button>
    </div>
    <div id="planImportResult" class="plan-year-import-result" hidden></div>
  </section>
</div>

<script src="<?= htmlspecialchars(planner_url('/assets/js/plan_year.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php
layout_end();
