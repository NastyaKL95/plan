<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/reports_efficiency.php';

$user = require_login();
$reportsScopeLevel = planner_reports_efficiency_level($user);
$reportsScopeLead = match ($reportsScopeLevel) {
    'org' => 'Сводка по всей организации за выбранный период.',
    'department' => 'Сводка по вашему подразделению за выбранный период.',
    default => 'Персональная эффективность: только ваши задачи за выбранный период.',
};
layout_start($user, 'Эффективность', 'reports');
?>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span>
  <a href="<?= htmlspecialchars(planner_url('/reports.php'), ENT_QUOTES, 'UTF-8') ?>">Отчёты</a><span class="bc-sep">/</span><span>Эффективность</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Эффективность сотрудников и подразделений</h1>
  <p class="page-lead"><?= htmlspecialchars($reportsScopeLead, ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="eff-toolbar">
  <label class="eff-period">
    <span class="muted">Период</span>
    <select id="effPeriod" class="bx-select">
      <option value="7d">7 дней</option>
      <option value="30d" selected>30 дней</option>
      <option value="90d">90 дней</option>
    </select>
  </label>
  <a class="btn btn-secondary" id="effCsvEmp" href="<?= htmlspecialchars(planner_url('/reports_efficiency_export.php?mode=employees&period=30d'), ENT_QUOTES, 'UTF-8') ?>"><?= $reportsScopeLevel === 'self' ? 'CSV мои показатели' : 'CSV сотрудники' ?></a>
  <?php if ($reportsScopeLevel !== 'self'): ?>
  <a class="btn btn-secondary" id="effCsvDep" href="<?= htmlspecialchars(planner_url('/reports_efficiency_export.php?mode=departments&period=30d'), ENT_QUOTES, 'UTF-8') ?>">CSV подразделения</a>
  <?php endif; ?>
</div>

<div class="eff-kpi-grid" id="effKpiGrid">
  <article class="eff-kpi-card"><div class="eff-kpi-title">Создано</div><div class="eff-kpi-value" id="kpiCreated">0</div></article>
  <article class="eff-kpi-card"><div class="eff-kpi-title">Закрыто</div><div class="eff-kpi-value" id="kpiDone">0</div></article>
  <article class="eff-kpi-card"><div class="eff-kpi-title">В срок</div><div class="eff-kpi-value" id="kpiOnTime">0%</div></article>
  <article class="eff-kpi-card"><div class="eff-kpi-title">Активные</div><div class="eff-kpi-value" id="kpiActive">0</div></article>
  <article class="eff-kpi-card"><div class="eff-kpi-title">Просроченные активные</div><div class="eff-kpi-value" id="kpiOverdue">0</div></article>
  <article class="eff-kpi-card"><div class="eff-kpi-title">Среднее закрытие</div><div class="eff-kpi-value" id="kpiAvgClose">0 ч</div></article>
</div>

<div class="reports-grid">
  <section class="report-card report-card--wide">
    <h2>Недельный тренд</h2>
    <p class="muted report-chart-meta">Создано и закрыто по неделям выбранного периода.</p>
    <div class="report-chart-scroll">
      <div class="report-chart-host-inner" style="height:260px">
        <canvas id="effTrendChart" aria-label="Тренд создано и закрыто"></canvas>
      </div>
    </div>
  </section>
  <section class="report-card report-card--wide">
    <h2><?= $reportsScopeLevel === 'self' ? 'Мои показатели' : 'По сотрудникам' ?></h2>
    <div class="report-stuck-wrap" id="effByEmployees"></div>
  </section>
  <?php if ($reportsScopeLevel !== 'self'): ?>
  <section class="report-card report-card--wide" id="effDepartmentsSection">
    <h2>По подразделениям</h2>
    <div class="report-stuck-wrap" id="effByDepartments"></div>
  </section>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
  var root = typeof window.__PLANNER_ROOT__ === 'string' ? window.__PLANNER_ROOT__ : '';
  function apiUrl(p) { return root + (p.charAt(0) === '/' ? p : '/' + p); }
  function tasksUrl(q) { return apiUrl('/tasks.php?' + q); }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function num(v) { var n = Number(v); return isNaN(n) ? 0 : n; }
  function riskClass(score, overdue, active) {
    var overRate = active > 0 ? (overdue / active) * 100 : 0;
    if (score < 45 || overRate >= 45) return 'eff-risk eff-risk--high';
    if (score < 70 || overRate >= 25) return 'eff-risk eff-risk--mid';
    return 'eff-risk eff-risk--low';
  }
  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }
  function period() {
    var el = document.getElementById('effPeriod');
    return el ? (el.value || '30d') : '30d';
  }
  function setExportLinks() {
    var p = encodeURIComponent(period());
    var e = document.getElementById('effCsvEmp');
    var d = document.getElementById('effCsvDep');
    if (e) e.href = apiUrl('/reports_efficiency_export.php?mode=employees&period=' + p);
    if (d) d.href = apiUrl('/reports_efficiency_export.php?mode=departments&period=' + p);
  }

  function renderEmployees(rows) {
    var host = document.getElementById('effByEmployees');
    if (!host) return;
    if (!rows || !rows.length) {
      host.innerHTML = '<p class="muted">Нет данных.</p>';
      return;
    }
    host.innerHTML = '<table class="task-table"><thead><tr>' +
      '<th>Сотрудник</th><th>Отдел</th><th>Создано</th><th>Закрыто</th><th>В срок</th><th>Активные</th><th>Просроченные</th><th>Сред. закрытие</th><th>Индекс</th>' +
      '</tr></thead><tbody>' +
      rows.map(function (r) {
        var sid = r.user_id != null && r.user_id !== '' ? String(r.user_id) : '';
        var q = sid ? ('report_active=1&assignee_id=' + encodeURIComponent(sid)) : 'report_active=1&assignee_empty=1';
        var score = num(r.score);
        var active = num(r.active_total);
        var over = num(r.overdue_active);
        return '<tr class="eff-row-link" data-href="' + esc(tasksUrl(q)) + '">' +
          '<td>' + esc(r.user_name || 'Без исполнителя') + '</td>' +
          '<td>' + esc(r.department_name || '—') + '</td>' +
          '<td>' + num(r.created_total) + '</td>' +
          '<td>' + num(r.done_total) + '</td>' +
          '<td>' + num(r.on_time_rate).toFixed(1) + '%</td>' +
          '<td>' + num(r.active_total) + '</td>' +
          '<td>' + num(r.overdue_active) + '</td>' +
          '<td>' + (r.avg_close_hours == null ? '—' : num(r.avg_close_hours).toFixed(1) + ' ч') + '</td>' +
          '<td><span class="' + riskClass(score, over, active) + '">' + score.toFixed(1) + '</span></td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';
    host.querySelectorAll('.eff-row-link').forEach(function (tr) {
      tr.addEventListener('click', function () {
        var h = tr.getAttribute('data-href');
        if (h) window.location.href = h;
      });
    });
  }

  function renderDepartments(rows) {
    var host = document.getElementById('effByDepartments');
    if (!host) return;
    if (!rows || !rows.length) {
      host.innerHTML = '<p class="muted">Нет данных.</p>';
      return;
    }
    host.innerHTML = '<table class="task-table"><thead><tr>' +
      '<th>Подразделение</th><th>Создано</th><th>Закрыто</th><th>В срок</th><th>Активные</th><th>Просроченные</th><th>Без исполнителя</th><th>Без срока</th><th>Индекс</th>' +
      '</tr></thead><tbody>' +
      rows.map(function (r) {
        var q = 'department_id=' + encodeURIComponent(String(r.department_id || '')) + '&report_active=1';
        var score = num(r.score);
        var active = num(r.active_total);
        var over = num(r.overdue_active);
        return '<tr class="eff-row-link" data-href="' + esc(tasksUrl(q)) + '">' +
          '<td>' + esc(r.department_name || '—') + '</td>' +
          '<td>' + num(r.created_total) + '</td>' +
          '<td>' + num(r.done_total) + '</td>' +
          '<td>' + num(r.on_time_rate).toFixed(1) + '%</td>' +
          '<td>' + num(r.active_total) + '</td>' +
          '<td>' + num(r.overdue_active) + '</td>' +
          '<td>' + num(r.without_assignee) + '</td>' +
          '<td>' + num(r.without_due) + '</td>' +
          '<td><span class="' + riskClass(score, over, active) + '">' + score.toFixed(1) + '</span></td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';
    host.querySelectorAll('.eff-row-link').forEach(function (tr) {
      tr.addEventListener('click', function () {
        var h = tr.getAttribute('data-href');
        if (h) window.location.href = h;
      });
    });
  }

  var trendChart = null;
  function renderTrend(rows) {
    var c = document.getElementById('effTrendChart');
    if (!c || typeof Chart === 'undefined') return;
    var labels = (rows || []).map(function (r) { return String(r.week_label || ''); });
    var created = (rows || []).map(function (r) { return num(r.created_total); });
    var done = (rows || []).map(function (r) { return num(r.done_total); });
    if (trendChart && typeof trendChart.destroy === 'function') trendChart.destroy();
    trendChart = new Chart(c, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          { label: 'Создано', data: created, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.14)', tension: 0.28, fill: false },
          { label: 'Закрыто', data: done, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.14)', tension: 0.28, fill: false },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
      },
    });
  }

  function load() {
    setExportLinks();
    fetch(apiUrl('/api/reports_efficiency.php?period=' + encodeURIComponent(period())), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) throw new Error('load_failed');
        var k = d.kpi || {};
        setText('kpiCreated', String(num(k.created_total)));
        setText('kpiDone', String(num(k.done_total)));
        setText('kpiOnTime', num(k.on_time_rate).toFixed(1) + '%');
        setText('kpiActive', String(num(k.active_total)));
        setText('kpiOverdue', String(num(k.overdue_active)));
        setText('kpiAvgClose', num(k.avg_close_hours).toFixed(1) + ' ч');
        renderEmployees(d.employees || []);
        renderDepartments(d.departments || []);
        renderTrend(d.trend || []);
      })
      .catch(function () {
        var h1 = document.getElementById('effByEmployees');
        if (h1) h1.innerHTML = '<p class="muted">Не удалось загрузить отчёт.</p>';
        var h2 = document.getElementById('effByDepartments');
        if (h2) h2.innerHTML = '<p class="muted">Не удалось загрузить отчёт.</p>';
      });
  }

  var p = document.getElementById('effPeriod');
  if (p) p.addEventListener('change', load);
  load();
})();
</script>
<?php layout_end(); ?>

