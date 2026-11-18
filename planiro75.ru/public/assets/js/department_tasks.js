(function () {
  var Core = window.PlannerCore;
  if (!Core) return;

  function $(id) {
    return Core.byId(id);
  }

  function escapeHtml(s) {
    return Core.escapeHtml(s);
  }

  function api(path) {
    return Core.api(path);
  }

  function tasksUrl(openId) {
    var base = Core.apiUrl('/tasks.php');
    if (!openId) return base;
    return base + (base.indexOf('?') >= 0 ? '&' : '?') + 'open=' + encodeURIComponent(String(openId));
  }

  var STATUS_RU = {
    new: 'Новая',
    in_progress: 'В работе',
    review: 'На проверке',
    done: 'Выполнена',
    cancelled: 'Отменена',
  };

  var allowedDeptIds = window.__PLANNER_DEPT_TASKS_FILTER_IDS__;
  if (allowedDeptIds != null && !Array.isArray(allowedDeptIds)) {
    allowedDeptIds = null;
  }

  var items = [];
  var total = 0;
  var hasMore = false;
  var loading = false;
  var limit = 50;
  var departments = [];
  var searchTimer = null;

  function fmtDue(s) {
    if (!s) return '—';
    var d = new Date(String(s).replace(' ', 'T'));
    if (!isFinite(d.getTime())) return escapeHtml(String(s));
    return d.toLocaleString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function buildQuery(offset) {
    var qs = '/api/tasks.php?list_scope=department&limit=' + limit + '&offset=' + (offset || 0);
    var q = $('deptTasksSearch') ? $('deptTasksSearch').value.trim() : '';
    var st = $('deptTasksStatus') ? $('deptTasksStatus').value : '';
    var dep = $('deptTasksDepartment') ? $('deptTasksDepartment').value : '';
    if (q) qs += '&q=' + encodeURIComponent(q);
    if (st) qs += '&status=' + encodeURIComponent(st);
    if (dep) qs += '&department_id=' + encodeURIComponent(dep);
    return qs;
  }

  function updatePager() {
    var pager = $('deptTasksPager');
    var stats = $('deptTasksStats');
    var btn = $('deptTasksLoadMoreBtn');
    if (pager) pager.textContent = 'Показано ' + items.length + ' из ' + total;
    if (stats) stats.textContent = total > 0 ? 'Всего: ' + total : '';
    if (btn) btn.hidden = !hasMore;
  }

  function renderTable() {
    var tb = $('deptTasksBody');
    if (!tb) return;
    if (!items.length) {
      tb.innerHTML = '<tr><td colspan="6" class="muted">Задач не найдено.</td></tr>';
      return;
    }
    tb.innerHTML = items
      .map(function (t) {
        var st = STATUS_RU[t.status] || t.status || '—';
        return (
          '<tr>' +
          '<td class="task-table-title"><a href="' +
          escapeHtml(tasksUrl(t.id)) +
          '">#' +
          escapeHtml(String(t.id)) +
          ' ' +
          escapeHtml(t.title || '') +
          '</a></td>' +
          '<td>' +
          escapeHtml(t.department_name || '—') +
          '</td>' +
          '<td class="task-table-muted">' +
          escapeHtml(t.project_name || '—') +
          '</td>' +
          '<td>' +
          escapeHtml(st) +
          '</td>' +
          '<td class="task-table-muted">' +
          escapeHtml(t.assignee_name || '—') +
          '</td>' +
          '<td class="task-table-muted">' +
          fmtDue(t.due_date) +
          '</td>' +
          '</tr>'
        );
      })
      .join('');
  }

  function loadTasks(append) {
    if (loading) return Promise.resolve();
    loading = true;
    var offset = append ? items.length : 0;
    return api(buildQuery(offset))
      .then(function (data) {
        var chunk = data.items || [];
        if (append) {
          var seen = {};
          items.forEach(function (t) {
            seen[t.id] = true;
          });
          chunk.forEach(function (t) {
            if (!seen[t.id]) {
              items.push(t);
              seen[t.id] = true;
            }
          });
        } else {
          items = chunk;
        }
        total = data.total != null ? data.total : items.length;
        hasMore = data.has_more === true || data.has_more === 1;
        renderTable();
        updatePager();
      })
      .catch(function (err) {
        if (!append && $('deptTasksBody')) {
          $('deptTasksBody').innerHTML =
            '<tr><td colspan="6" class="muted">Не удалось загрузить задачи.</td></tr>';
        }
        if (Core.notify) Core.notify((err && err.message) || 'Ошибка загрузки', { type: 'error' });
      })
      .finally(function () {
        loading = false;
      });
  }

  function fillDepartments() {
    var sel = $('deptTasksDepartment');
    if (!sel) return;
    return api('/api/departments.php').then(function (res) {
      departments = (res && res.items) || [];
      if (allowedDeptIds && allowedDeptIds.length) {
        var allow = {};
        allowedDeptIds.forEach(function (id) {
          allow[String(id)] = true;
        });
        departments = departments.filter(function (d) {
          return allow[String(d.id)];
        });
      }
      var opts = '<option value="">Все подразделения</option>';
      departments.forEach(function (d) {
        opts += '<option value="' + escapeHtml(String(d.id)) + '">' + escapeHtml(d.name || '') + '</option>';
      });
      sel.innerHTML = opts;
      if (typeof window.bxCustomSelectInit === 'function') window.bxCustomSelectInit(sel.parentElement || sel);
    });
  }

  function scheduleReload() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      loadTasks(false);
    }, 320);
  }

  function bindEvents() {
    var search = $('deptTasksSearch');
    var dep = $('deptTasksDepartment');
    var st = $('deptTasksStatus');
    var reset = $('deptTasksReset');
    var more = $('deptTasksLoadMoreBtn');
    if (search) {
      search.addEventListener('input', scheduleReload);
      search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          loadTasks(false);
        }
      });
    }
    if (dep) dep.addEventListener('change', function () {
      loadTasks(false);
    });
    if (st) st.addEventListener('change', function () {
      loadTasks(false);
    });
    if (reset) {
      reset.addEventListener('click', function () {
        if (search) search.value = '';
        if (dep) dep.value = '';
        if (st) st.value = '';
        if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
        loadTasks(false);
      });
    }
    if (more) {
      more.addEventListener('click', function () {
        loadTasks(true);
      });
    }
  }

  fillDepartments()
    .then(function () {
      bindEvents();
      return loadTasks(false);
    })
    .catch(function () {
      bindEvents();
      loadTasks(false);
    });
})();
