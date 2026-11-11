<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
layout_start($user, 'Обзор', 'dashboard');
?>
<div class="banner-info" id="dashBanner" hidden>
  <span id="dashBannerText"></span>
</div>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Обзор</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Панель организации</h1>
  <p class="muted page-subline">План на <strong><?= (int) ($user['organization_work_year'] ?? (int) date('Y')) ?></strong> год: в списках задач и проектов показываются только они. Смена года в профиле (ректорат/админ) открывает новый контур плана.</p>
</div>

<div class="overview-grid">
  <section class="panel panel--hero">
    <h2 class="panel-title">Ключевые показатели</h2>
    <div class="kpi-grid" id="kpiGrid"></div>
  </section>
  <section class="panel panel--hero">
    <h2 class="panel-title">Общие показатели</h2>
    <div class="mini-stats mini-stats--hero" id="miniStats"></div>
  </section>
</div>

<section class="panel dash-dept-report">
  <div class="dash-dept-report__head">
    <h2 class="panel-title">Сводка по подразделениям</h2>
    <span class="muted" id="dashDeptTotal">Всего: 0</span>
  </div>
  <div class="dash-dept-report__table-wrap">
    <table class="task-table dash-dept-table">
      <thead>
        <tr>
          <th>Название</th>
          <th>Активность</th>
          <th>В работе</th>
          <th>Эффективность (-14/+7)</th>
          <th>Завершены</th>
          <th>Аналитика</th>
        </tr>
      </thead>
      <tbody id="dashDeptTableBody"></tbody>
    </table>
  </div>
</section>

<div class="overview-bottom">
  <div class="panel">
    <h2 class="panel-title">По проектам</h2>
    <ul class="doc-types" id="projectBreakdown"></ul>
  </div>
  <div class="panel">
    <h2 class="panel-title">Последние действия</h2>
    <div class="activity-feed" id="activityFeed"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.PlannerDashboard) window.PlannerDashboard.init();
});
</script>
<script src="<?= htmlspecialchars(planner_url('/assets/js/dashboard.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php layout_end(); ?>
