<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
layout_start($user, 'Уведомления', 'dashboard');
?>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Уведомления</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Уведомления</h1>
  <button type="button" class="btn btn-secondary" id="btnMarkAllRead">Отметить все прочитанными</button>
</div>

<section class="panel notif-section">
  <h2 class="panel-title">Комментарии к задачам</h2>
  <ul class="notif-list" id="notifCommentList"></ul>
  <p class="muted" id="notifCommentEmpty" hidden>Нет непрочитанных уведомлений.</p>
</section>

<section class="panel notif-section">
  <h2 class="panel-title">Сроки (вы исполнитель)</h2>
  <p class="muted notif-hint">Просроченные и ближайшие 72 часа.</p>
  <ul class="notif-list" id="notifDueList"></ul>
  <p class="muted" id="notifDueEmpty" hidden>Нет таких задач.</p>
</section>

<script src="<?= htmlspecialchars(planner_url('/assets/js/notifications_page.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php layout_end(); ?>
