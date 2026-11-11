<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
$mayManageTags = user_may_manage_tags($user);
layout_start($user, 'Метки', 'tags');
?>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Метки</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Метки организации</h1>
</div>

<?php if ($mayManageTags): ?>
<section class="tag-create-panel bx-tag-panel" aria-labelledby="tagCreateHeading">
  <h2 id="tagCreateHeading" class="tag-create-heading visually-hidden">Создание метки</h2>
  <p class="tag-create-desc">Метки видны во всех проектах. Создайте тему, тип документа, метку срочности и выберите их в карточке задачи.</p>
  <form class="tag-create-form" id="formCreateTag" action="#" method="post">
    <label class="visually-hidden" for="newTagName">Название метки</label>
    <input type="text" id="newTagName" class="input-inline tag-create-name" maxlength="80" placeholder="Название метки" autocomplete="off">
    <label class="field-label-inline" for="newTagColor">Цвет</label>
    <input type="color" id="newTagColor" class="input-color" value="#64748b" title="Цвет на канбане и в списке" aria-label="Цвет метки">
    <button type="submit" class="btn btn-secondary" id="btnCreateTag">Создать метку</button>
  </form>
  <p class="tag-create-msg" id="tagCreateMsg" role="status" aria-live="polite"></p>
</section>
<?php else: ?>
<p class="auth-hint">Создавать метки может только администратор. Вы можете выбирать существующие метки в карточках задач.</p>
<?php endif; ?>

<script src="<?= htmlspecialchars(planner_url('/assets/js/tags_page.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php layout_end(); ?>
