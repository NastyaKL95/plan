<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
$reportsScopeLevel = planner_reports_visibility_level($user);
$reportsScopeLead = match ($reportsScopeLevel) {
    'org' => 'Просрочки, «пробуксовка» и быстрые задачи по всей организации.',
    'department' => 'Просрочки, «пробуксовка» и быстрые задачи по вашему подразделению.',
    default => 'Просрочки и «пробуксовка» по вашим задачам.',
};
layout_start($user, 'Отчёты', 'reports');
?>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Отчёты</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Отчёты</h1>
  <p class="page-lead"><?= htmlspecialchars($reportsScopeLead, ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="report-export-bar">
  <?php if ($reportsScopeLevel !== 'self'): ?>
  <a class="btn btn-secondary" href="<?= htmlspecialchars(planner_url('/reports_export.php?mode=departments'), ENT_QUOTES, 'UTF-8') ?>">Скачать Excel — по подразделениям</a>
  <?php endif; ?>
  <a class="btn btn-secondary" href="<?= htmlspecialchars(planner_url('/reports_export.php?mode=tasks'), ENT_QUOTES, 'UTF-8') ?>">Скачать Excel — список задач</a>
  <a class="btn btn-primary" href="<?= htmlspecialchars(planner_url('/reports_efficiency.php'), ENT_QUOTES, 'UTF-8') ?>">Эффективность сотрудников и отделов</a>
</div>

<div class="reports-grid">
  <section class="report-card report-card--wide" id="quickTasksReportSection" hidden>
    <h2>Мелкие быстрые задачи по датам</h2>
    <p class="muted report-lead-stuck">За последние 14 дней, в вашей зоне видимости. Сотрудники отмечают такие карточки кнопкой «Быстро» на странице задач.</p>
    <div id="quickTasksByDayWrap" class="report-quick-tasks-wrap"></div>
  </section>
  <section class="report-card report-card--wide">
    <h2>«Пробуксовка» задач</h2>
    <p class="muted report-lead-stuck">Активные задачи без смены статуса дольше 14 дней (после миграции «status_changed_at» считается по входу в текущий статус; до миграции — по последнему обновлению записи).</p>
    <div id="stuckTasksTable" class="report-stuck-wrap"></div>
  </section>
</div>

<p class="report-overdue" id="overdueSummary"></p>

<script>
(function () {
  var root = typeof window.__PLANNER_ROOT__ === 'string' ? window.__PLANNER_ROOT__ : '';
  function apiUrl(p) {
    if (!p.startsWith('/')) p = '/' + p;
    return root + p;
  }
  function tasksDrillUrl(query) {
    return apiUrl('/tasks.php?' + query);
  }
  fetch(apiUrl('/api/reports_data.php'), { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) throw new Error(d.error || 'err');
      var el = document.getElementById('overdueSummary');
      if (el) {
        var n = d.overdue_total || 0;
        el.innerHTML =
          'Просрочено (срок прошёл, задача не закрыта): <a href="' +
          tasksDrillUrl('report_overdue=1') +
          '">' +
          n +
          '</a> — открыть список';
      }
      var stuck = d.stuck_tasks || [];
      var stEl = document.getElementById('stuckTasksTable');
      if (stEl && !document.getElementById('reportStuckDrill')) {
        var hint = document.createElement('p');
        hint.id = 'reportStuckDrill';
        hint.className = 'muted report-drill-hint';
        hint.innerHTML =
          '<a href="' + tasksDrillUrl('report_stuck=1') + '">Все задачи с «пробуксовкой» (как в отчёте)</a>';
        stEl.parentNode.insertBefore(hint, stEl);
      }
      var qsec = document.getElementById('quickTasksReportSection');
      var qwrap = document.getElementById('quickTasksByDayWrap');
      var qd = d.quick_tasks_by_day || [];
      if (qsec && qwrap) {
        if (qd.length) {
          qsec.hidden = false;
          var statusRuQ = { new: 'Новая', in_progress: 'В работе', review: 'На проверке', done: 'Выполнена', cancelled: 'Отменена' };
          function escQ(s) {
            return String(s || '')
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;');
          }
          qwrap.innerHTML = qd
            .map(function (day) {
              var rows = (day.items || [])
                .map(function (it) {
                  var st = statusRuQ[it.status] || it.status;
                  return (
                    '<tr><td><a href="' +
                    tasksDrillUrl('open=' + encodeURIComponent(String(it.id))) +
                    '">#' +
                    escQ(it.id) +
                    ' ' +
                    escQ(it.title) +
                    '</a></td><td>' +
                    escQ(st) +
                    '</td><td>' +
                    escQ(it.assignee_name || '—') +
                    '</td><td>' +
                    escQ(it.project_name || '—') +
                    '</td><td class="muted">' +
                    escQ(String(it.created_at || '').replace('T', ' ').slice(0, 16)) +
                    '</td></tr>'
                  );
                })
                .join('');
              return (
                '<div class="report-quick-day"><h3 class="report-quick-day-title">' +
                escQ(day.date) +
                '</h3><table class="task-table report-quick-table"><thead><tr><th>Задача</th><th>Статус</th><th>Исполнитель</th><th>Проект</th><th>Создана</th></tr></thead><tbody>' +
                rows +
                '</tbody></table><p class="muted report-drill-hint"><a href="' +
                tasksDrillUrl('task_kind=quick&report_active=1') +
                '">Все мелкие активные в списке задач</a></p></div>'
              );
            })
            .join('');
        } else {
          qsec.hidden = true;
        }
      }
      if (stEl) {
        function esc(s) {
          return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
        }
        var statusRu = { new: 'Новая', in_progress: 'В работе', review: 'На проверке', done: 'Выполнена', cancelled: 'Отменена' };
        if (!stuck.length) {
          stEl.innerHTML = '<p class="muted">Нет задач в этом срезе.</p>';
        } else {
          stEl.innerHTML =
            '<table class="task-table report-stuck-table"><thead><tr><th>Задача</th><th>Статус</th><th>Исполнитель</th><th>Дней без обновления</th></tr></thead><tbody>' +
            stuck
              .map(function (r) {
                var st = statusRu[r.status] || r.status;
                return (
                  '<tr><td><a href="' +
                  apiUrl('/tasks.php?open=' + encodeURIComponent(r.id)) +
                  '">#' +
                  r.id +
                  ' ' +
                  esc(r.title) +
                  '</a></td><td>' +
                  esc(st) +
                  '</td><td>' +
                  esc(r.assignee_name || '—') +
                  '</td><td>' +
                  esc(String(r.days_in_state != null ? r.days_in_state : '—')) +
                  '</td></tr>'
                );
              })
              .join('') +
            '</tbody></table>';
        }
      }
    })
    .catch(function () {
      document.getElementById('overdueSummary').textContent = 'Не удалось загрузить данные.';
    });
})();
</script>
<?php layout_end(); ?>
