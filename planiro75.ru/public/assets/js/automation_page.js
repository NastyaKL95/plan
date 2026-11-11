(function () {
  var Core = window.PlannerCore;
  function $(id) {
    return Core.byId(id);
  }

  function apiUrl(path) {
    return Core.apiUrl(path);
  }

  function api(path, opts) {
    return Core.api(path, opts);
  }

  function escapeHtml(s) {
    return Core.escapeHtml(s);
  }

  function notify(message, type) {
    if (Core.notify) Core.notify(message, { type: type || 'info' });
    else window.alert(message);
  }

  function automationSetMsg(text, kind) {
    var el = $('automationMsg');
    if (!el) return;
    el.textContent = text || '';
    el.classList.remove('is-error', 'is-ok');
    if (kind === 'error') el.classList.add('is-error');
    if (kind === 'ok') el.classList.add('is-ok');
  }

  function renderAutomationRules(items) {
    var tbody = $('automationRulesBody');
    if (!tbody) return;
    var trigRu = {
      new: 'Новая',
      in_progress: 'В работе',
      review: 'На проверке',
      done: 'Выполнена',
      cancelled: 'Отменена',
    };
    tbody.innerHTML = (items || [])
      .map(function (r) {
        var pid = r.id;
        return (
          '<tr data-rule-id="' +
          pid +
          '"><td>' +
          escapeHtml(r.project_name || 'Все проекты') +
          '</td><td>' +
          escapeHtml(trigRu[r.trigger_status] || r.trigger_status) +
          '</td><td>' +
          escapeHtml(r.action_assignee_name || '—') +
          '</td><td class="task-table-muted">' +
          escapeHtml((r.action_comment || '').slice(0, 80)) +
          (r.action_comment && r.action_comment.length > 80 ? '…' : '') +
          '</td><td>' +
          escapeHtml(String(r.sort_order != null ? r.sort_order : 0)) +
          '</td><td><input type="checkbox" class="auto-enabled" data-id="' +
          pid +
          '" ' +
          (parseInt(r.enabled, 10) === 1 ? 'checked' : '') +
          '></td><td><button type="button" class="btn btn-secondary btn-sm auto-del" data-id="' +
          pid +
          '">Удалить</button></td></tr>'
        );
      })
      .join('');
    tbody.querySelectorAll('.auto-enabled').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var id = parseInt(cb.getAttribute('data-id'), 10);
        if (!id) return;
        api('/api/task_automation.php?id=' + id, {
          method: 'PATCH',
          body: { enabled: cb.checked },
        }).catch(function (e) {
          notify('Не удалось: ' + (e.message || ''), 'error');
          cb.checked = !cb.checked;
        });
      });
    });
    tbody.querySelectorAll('.auto-del').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        if (!id || !confirm('Удалить правило?')) return;
        api('/api/task_automation.php?id=' + id, { method: 'DELETE' })
          .then(function () {
            return api('/api/task_automation.php');
          })
          .then(function (d) {
            renderAutomationRules(d.items || []);
          })
          .catch(function (e) {
            notify('Ошибка: ' + (e.message || ''), 'error');
          });
      });
    });
  }

  function projLabel(p) {
    var dn = p.department_name ? p.department_name + ' — ' : '';
    return dn + p.name;
  }

  function fillAutomationSelects(projects, users) {
    projects = projects || [];
    users = users || [];
    var autoProj = $('autoProject');
    if (autoProj) {
      var meId = String(window.__PLANNER_USER_ID__ || '');
      var myDeptIds = [];
      users.forEach(function (u) {
        if (String(u.id) !== meId) return;
        (u.department_ids || []).forEach(function (id) {
          var n = parseInt(id, 10);
          if (n > 0 && myDeptIds.indexOf(n) < 0) myDeptIds.push(n);
        });
        var primaryDept = parseInt(u.department_id, 10);
        if (primaryDept > 0 && myDeptIds.indexOf(primaryDept) < 0) myDeptIds.push(primaryDept);
      });
      var deptProjects = [];
      var otherProjects = [];
      projects.forEach(function (p) {
        var did = parseInt(p.department_id, 10);
        if (did > 0 && myDeptIds.indexOf(did) >= 0) deptProjects.push(p);
        else otherProjects.push(p);
      });
      autoProj.innerHTML =
        '<option value="">Все проекты</option>' +
        deptProjects.concat(otherProjects)
          .map(function (p) {
            return '<option value="' + p.id + '">' + escapeHtml(projLabel(p)) + '</option>';
          })
          .join('');
    }
    var autoAsg = $('autoAssignee');
    if (autoAsg) {
      var me = String(window.__PLANNER_USER_ID__ || '');
      var mine = [];
      users.forEach(function (u) {
        if (String(u.id) !== me) return;
        (u.department_ids || []).forEach(function (id) {
          var n = parseInt(id, 10);
          if (n > 0 && mine.indexOf(n) < 0) mine.push(n);
        });
        var primary = parseInt(u.department_id, 10);
        if (primary > 0 && mine.indexOf(primary) < 0) mine.push(primary);
      });
      function shares(u) {
        var ids = [];
        (u.department_ids || []).forEach(function (id) {
          var n = parseInt(id, 10);
          if (n > 0) ids.push(n);
        });
        var primary = parseInt(u.department_id, 10);
        if (primary > 0) ids.push(primary);
        for (var i = 0; i < ids.length; i++) {
          if (mine.indexOf(ids[i]) >= 0) return true;
        }
        return false;
      }
      autoAsg.innerHTML =
        '<option value="">— не менять —</option>' +
        users
          .filter(function (u) {
            return !(u && (u.account_status === 'blocked' || u.is_blocked));
          })
          .slice()
          .sort(function (a, b) {
            var ra = String(a.id) === me ? 0 : shares(a) ? 1 : 2;
            var rb = String(b.id) === me ? 0 : shares(b) ? 1 : 2;
            if (ra !== rb) return ra - rb;
            return String(a.name || '').localeCompare(String(b.name || ''), 'ru');
          })
          .map(function (u) {
            return '<option value="' + u.id + '">' + escapeHtml(u.name) + '</option>';
          })
          .join('');
    }
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function loadAutomationRules() {
    var panel = $('automationPanel');
    if (!panel) return;
    api('/api/task_automation.php')
      .then(function (d) {
        renderAutomationRules(d.items || []);
      })
      .catch(function () {});
  }

  function submitAutomationRule(e) {
    if (e && e.preventDefault) e.preventDefault();
    var trig = $('autoTrigger') ? $('autoTrigger').value : '';
    var assignRaw = $('autoAssignee') ? $('autoAssignee').value : '';
    var comment = ($('autoComment') && $('autoComment').value.trim()) || '';
    var assignId = assignRaw ? parseInt(assignRaw, 10) : 0;
    var projRaw = $('autoProject') ? $('autoProject').value : '';
    var sortOrder = $('autoSort') ? parseInt($('autoSort').value, 10) || 0 : 0;
    if (!assignId && !comment) {
      automationSetMsg('Укажите исполнителя или текст комментария.', 'error');
      return;
    }
    var body = {
      trigger_status: trig,
      sort_order: sortOrder,
      enabled: true,
    };
    if (assignId > 0) body.action_assignee_id = assignId;
    if (comment) body.action_comment = comment;
    if (projRaw) body.project_id = parseInt(projRaw, 10);
    automationSetMsg('Сохранение…', '');
    api('/api/task_automation.php', { method: 'POST', body: body })
      .then(function () {
        if ($('autoComment')) $('autoComment').value = '';
        automationSetMsg('Правило добавлено.', 'ok');
        return api('/api/task_automation.php');
      })
      .then(function (d) {
        renderAutomationRules(d.items || []);
      })
      .catch(function (err) {
        var m = err.message || '';
        automationSetMsg(m === 'need_action' ? 'Нужно действие.' : 'Ошибка: ' + m, 'error');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!$('automationPanel')) return;
    Promise.all([api('/api/projects.php'), api('/api/users.php')])
      .then(function (res) {
        fillAutomationSelects(res[0].items || [], res[1].items || []);
        loadAutomationRules();
      })
      .catch(function () {});
    var formAuto = $('formAutomationRule');
    if (formAuto) formAuto.addEventListener('submit', submitAutomationRule);
  });
})();
