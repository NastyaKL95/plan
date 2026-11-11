<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
layout_start($user, 'Проекты', 'projects');
?>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Проекты</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Проекты</h1>
  <div class="page-actions">
    <button type="button" class="btn btn-secondary" id="btnNewPortfolio">Новый портфель</button>
    <button type="button" class="btn btn-primary" id="btnNewProject">Новый проект</button>
  </div>
</div>

<div class="projects-grid" id="projectsGrid"></div>

<dialog class="modal" id="projectModal">
  <form method="dialog" class="modal-form" id="projectForm">
    <h2 class="modal-title" id="projectModalTitle">Проект</h2>
    <input type="hidden" id="projId" value="">
    <label class="field-label" for="projDept">Подразделение</label>
    <select id="projDept" class="input-block" required></select>
    <label class="field-label" for="projName">Название</label>
    <input type="text" id="projName" required maxlength="255" class="input-block">
    <label class="field-label" for="projDesc">Описание</label>
    <textarea id="projDesc" class="input-block" rows="3"></textarea>
    <label class="field-label" for="projColor">Цвет</label>
    <input type="color" id="projColor" value="#2563eb" class="input-block color-input">
    <label class="field-label" for="projPortfolios">Портфели</label>
    <select id="projPortfolios" class="input-block" multiple size="6"></select>
    <p class="muted proj-delete-hint" id="projDeleteHint" hidden></p>
    <div class="modal-actions modal-actions--split">
      <button type="button" class="btn btn-danger-outline" id="projDelete" hidden>Удалить проект</button>
      <div class="modal-actions-right">
        <button type="button" class="btn btn-secondary" id="projCancel">Отмена</button>
        <button type="submit" class="btn btn-primary">Сохранить</button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="modal" id="portfolioModal">
  <form method="dialog" class="modal-form" id="portfolioForm">
    <h2 class="modal-title" id="portfolioModalTitle">Портфель</h2>
    <input type="hidden" id="portfolioId" value="">
    <label class="field-label" for="portfolioName">Название</label>
    <input type="text" id="portfolioName" required maxlength="255" class="input-block">
    <label class="field-label" for="portfolioDesc">Описание</label>
    <textarea id="portfolioDesc" class="input-block" rows="3"></textarea>
    <label class="field-label" for="portfolioColor">Цвет</label>
    <input type="color" id="portfolioColor" value="#0ea5e9" class="input-block color-input">
    <label class="field-label" for="portfolioParent">Родительский портфель</label>
    <select id="portfolioParent" class="input-block">
      <option value="">Без родителя</option>
    </select>
    <label class="field-label" for="portfolioProjects">Проекты в портфеле</label>
    <select id="portfolioProjects" class="input-block" multiple size="6"></select>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" id="portfolioCancel">Отмена</button>
      <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
  </form>
</dialog>

<script src="<?= htmlspecialchars(planner_url('/assets/js/projects.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php layout_end(); ?>
