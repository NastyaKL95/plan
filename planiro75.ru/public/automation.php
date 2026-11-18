<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
$may_manage_automation = in_array($user['role'] ?? '', ['admin', 'owner'], true);

layout_start($user, 'Автоматизация', 'automation');
?>
<script>window.__PLANNER_USER_ROLE__=<?= json_encode($user['role'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;</script>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Автоматизация</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Автоматизация (роботы)</h1>
</div>

<?php if (!$may_manage_automation): ?>
<p class="muted">Раздел доступен только администратору организации и ректорату.</p>
<?php else: ?>

<section class="bx-automation-panel" id="automationPanel">
  <p class="bx-automation-desc muted">При переходе задачи в указанный статус выполняются действия: назначить исполнителя и/или добавить комментарий (от вашего имени). Правило с пустым проектом действует для всех проектов.</p>
  <div class="bx-automation-table-wrap">
    <table class="task-table bx-automation-rules-table" aria-label="Правила">
      <thead>
        <tr>
          <th>Проект</th>
          <th>Статус (триггер)</th>
          <th>Назначить исполнителя</th>
          <th>Комментарий</th>
          <th>Порядок</th>
          <th>Вкл.</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="automationRulesBody"></tbody>
    </table>
  </div>
  <form class="bx-automation-form" id="formAutomationRule" action="#" method="post">
    <div class="bx-automation-form-row">
      <label class="field-label-inline" for="autoProject">Проект</label>
      <select id="autoProject" class="bx-select">
        <option value="">Все проекты</option>
      </select>
      <label class="field-label-inline" for="autoTrigger">Триггер</label>
      <select id="autoTrigger" class="bx-select" required>
        <option value="new">Новая</option>
        <option value="in_progress">В работе</option>
        <option value="review">На проверке</option>
        <option value="done">Выполнена</option>
        <option value="cancelled">Отменена</option>
      </select>
      <label class="field-label-inline" for="autoAssignee">Исполнитель</label>
      <select id="autoAssignee" class="bx-select">
        <option value="">— не менять —</option>
      </select>
    </div>
    <div class="bx-automation-form-row">
      <label class="field-label-inline" for="autoComment">Текст комментария</label>
      <input type="text" id="autoComment" class="input-inline" style="flex:1;min-width:220px" maxlength="8000" placeholder="Необязательно">
      <label class="field-label-inline" for="autoSort">Порядок</label>
      <input type="number" id="autoSort" class="input-inline" min="0" value="0" style="width:72px">
      <button type="submit" class="btn btn-primary" id="btnAddAutomationRule">Добавить правило</button>
    </div>
  </form>
  <p class="tag-create-msg" id="automationMsg" role="status"></p>
</section>

<script src="<?= htmlspecialchars(planner_url('/assets/js/automation_page.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>

<?php layout_end(); ?>
