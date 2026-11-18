(function () {
  var Core = window.PlannerCore;

  function apiUrl(path) {
    return Core.apiUrl(path);
  }

  function statusCards(stats) {
    var b = stats.by_status || {};
    var defs = [
      { key: 'new', label: 'Новые', sub: 'Входящие', color: 'kpi-grey' },
      { key: 'in_progress', label: 'В работе', sub: 'Активные', color: 'kpi-blue' },
      { key: 'review', label: 'На проверке', sub: 'Согласование', color: 'kpi-purple' },
      { key: 'done', label: 'Выполнено', sub: 'Закрыты', color: 'kpi-green' },
      { key: 'cancelled', label: 'Отменено', sub: 'Архив', color: 'kpi-pink' },
    ];
    var t = stats.totals || {};
    return defs
      .map(function (d) {
        var n = b[d.key] || 0;
        return (
          '<article class="kpi-card ' +
          d.color +
          '"><div class="kpi-label">' +
          d.label +
          '</div><div class="kpi-value">' +
          n +
          '</div><div class="kpi-meta"><span>Всего задач: ' +
          (t.tasks || 0) +
          '</span><span>Сегодня: ' +
          (d.key === 'done' ? t.today_done || 0 : t.today_created || 0) +
          '</span></div></article>'
        );
      })
      .join('');
  }

  function miniStats(t) {
    return [
      { label: 'Задач', val: t.tasks },
      { label: 'Проектов', val: t.projects },
      { label: 'Участников', val: t.members },
      { label: 'Создано сегодня', val: t.today_created },
      { label: 'Закрыто сегодня', val: t.today_done },
    ]
      .map(function (x) {
        return (
          '<div class="mini-card"><div class="mini-val">' +
          x.val +
          '</div><div class="mini-lbl">' +
          x.label +
          '</div></div>'
        );
      })
      .join('');
  }

  function dashDeptApiUrl() {
    return apiUrl('/api/dashboard_departments.php?per_page=100');
  }

  function fmtDateRu(s) {
    if (!s) return '—';
    var d = new Date(String(s).replace(' ', 'T'));
    if (!isFinite(d.getTime())) return escapeHtml(String(s));
    return d.toLocaleString('ru-RU', {
      day: 'numeric',
      month: 'long',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function analyticsClass(text) {
    var s = String(text || '').toLowerCase();
    if (s.indexOf('отлич') !== -1) return 'is-good';
    if (s.indexOf('норм') !== -1) return 'is-mid';
    return 'is-bad';
  }

  function usersGhostIcons(count) {
    var n = Math.max(0, parseInt(count, 10) || 0);
    if (n <= 0) return '<span class="dash-users-empty">нет задач</span>';
    var shown = Math.min(3, n);
    var icon = '<span class="dash-user-ghost" aria-hidden="true"></span>';
    return '<span class="dash-users-pack">' + new Array(shown + 1).join(icon) + '</span><span class="dash-users-count">' + n + '</span>';
  }

  function effShape(percent) {
    var p = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
    var cls = 'is-red';
    if (p === 0) cls = 'is-zero';
    else if (p > 70) cls = 'is-green';
    else if (p > 40) cls = 'is-orange';
    return (
      '<span class="dash-eff-shape ' +
      cls +
      '"><span class="dash-eff-shape__fill" style="width:' +
      p +
      '%"></span></span><strong class="dash-eff-value ' +
      cls +
      '">' +
      p +
      '%</strong>'
    );
  }

  function loadDepartmentsReport() {
    fetch(dashDeptApiUrl(), { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        var body = document.getElementById('dashDeptTableBody');
        if (!body || !data || !data.ok) return;
        var pg = data.pagination || {};
        var total = parseInt(pg.total, 10) || 0;
        var items = data.items || [];
        if (!items.length) {
          body.innerHTML =
            '<tr><td colspan="6"><span class="muted">Нет данных по подразделениям.</span></td></tr>';
        } else {
          body.innerHTML = items
            .map(function (x) {
              return (
                '<tr><td><span class="dash-row-grip" aria-hidden="true">≡</span><a class="dash-dept-name" href="' +
                apiUrl('/tasks.php?department_id=' + encodeURIComponent(String(x.department_id)) + '&report_active=1') +
                '">' +
                escapeHtml(x.department_name || '') +
                '</a><div class="dash-dept-sub">Всего задач: ' +
                escapeHtml(String(x.total_count || 0)) +
                '</div></td><td>' +
                escapeHtml(fmtDateRu(x.last_activity_at)) +
                '</td><td>' +
                usersGhostIcons(x.active_count) +
                '</td><td><div class="dash-eff">' +
                effShape(x.effectiveness) +
                '</div></td><td>' +
                usersGhostIcons(x.done_count) +
                '</td><td><span class="dash-analytics ' +
                analyticsClass(x.analytics) +
                '">' +
                '<span class="dash-analytics-ico" aria-hidden="true">↗</span> ' +
                escapeHtml(x.analytics || '') +
                '</span></td></tr>'
              );
            })
            .join('');
        }
        var totalEl = document.getElementById('dashDeptTotal');
        if (totalEl) totalEl.textContent = 'Всего: ' + total;
      })
      .catch(function () {});
  }

  function load() {
    fetch(apiUrl('/api/stats.php'), { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) return;
        var grid = document.getElementById('kpiGrid');
        if (grid) grid.innerHTML = statusCards(data);
        var ms = document.getElementById('miniStats');
        if (ms) ms.innerHTML = miniStats(data.totals || {});
        var pb = document.getElementById('projectBreakdown');
        if (pb) {
          pb.innerHTML = (data.by_project || [])
            .map(function (p) {
              return (
                '<li><span class="dt-name">' +
                escapeHtml(p.name) +
                '</span><span class="dt-count">' +
                p.c +
                '</span></li>'
              );
            })
            .join('');
        }
      })
      .catch(function () {});

    fetch(apiUrl('/api/activity.php?limit=12'), { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        var feed = document.getElementById('activityFeed');
        if (!feed || !data.ok) return;
        feed.innerHTML = (data.items || [])
          .map(function (a) {
            return (
              '<div class="act-item"><div class="act-msg">' +
              escapeHtml(a.message) +
              '</div><div class="act-meta"><span>' +
              escapeHtml(a.user_name || 'Система') +
              '</span> · <time>' +
              escapeHtml(a.created_at) +
              '</time></div></div>'
            );
          })
          .join('');
      })
      .catch(function () {});
  }

  function escapeHtml(s) {
    return Core.escapeHtml(s);
  }

  window.PlannerDashboard = {
    init: function () {
      load();
      loadDepartmentsReport();
      var ban = document.getElementById('dashBanner');
      var banT = document.getElementById('dashBannerText');
      if (ban && banT) {
        ban.hidden = false;
        banT.textContent =
          'Проекты привязаны к структурным подразделениям. Администратор и руководитель организации видят все проекты; сотрудник — только свои подразделения.';
      }
    },
  };
})();
