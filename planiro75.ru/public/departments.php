<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
$mayManage = user_may_manage_departments($user);

layout_start($user, 'Подразделения', 'departments');
?>
<script>window.__DEPT_ADMIN__=<?= $mayManage ? 'true' : 'false' ?>;</script>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Подразделения</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Структурные подразделения</h1>
</div>

<p class="dept-intro">
  У каждого подразделения — свои проекты и задачи. <strong>Руководитель</strong> видит все проекты закреплённых за ним подразделений.
  <strong>Администратор</strong> видит все проекты института. Список подразделений соответствует структуре на <a href="https://irozk.ru/sveden/struct" target="_blank" rel="noopener">irozk.ru</a>. Руководителей можно назначить из списка пользователей (несколько человек на отдел).
</p>

<?php if ($mayManage): ?>
<section class="dept-create-panel">
  <h2 class="dept-create-heading">Новое подразделение</h2>
  <form class="dept-create-form" id="formCreateDept" action="#" method="post">
    <input type="text" id="newDeptName" class="input-inline" required maxlength="255" placeholder="Полное название" autocomplete="off" aria-label="Название">
    <label class="visually-hidden" for="newDeptSort">Порядок в списке</label>
    <input type="number" id="newDeptSort" class="input-inline" min="0" value="0" style="width:100px" title="Порядок сортировки" aria-label="Порядок сортировки">
    <button type="submit" class="btn btn-primary">Создать</button>
  </form>
  <p class="dept-create-msg" id="deptCreateMsg" role="status" aria-live="polite"></p>
</section>
<?php endif; ?>

<div class="dept-list" id="deptList"></div>
<p class="muted dept-empty-msg" id="deptListEmpty" hidden>Список подразделений пуст.</p>

<?php if ($mayManage): ?>
<dialog class="modal" id="deptEditModal">
  <form method="dialog" class="modal-form" id="formEditDept">
    <h2 class="modal-title">Подразделение</h2>
    <input type="hidden" id="editDeptId">
    <label class="field-label" for="editDeptName">Название</label>
    <input type="text" id="editDeptName" required maxlength="255" class="input-block">
    <label class="field-label" for="editDeptSort">Порядок сортировки</label>
    <input type="number" id="editDeptSort" min="0" class="input-block">
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" id="editDeptCancel">Отмена</button>
      <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<script src="<?= htmlspecialchars(planner_url('/assets/js/departments.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php layout_end(); ?>
