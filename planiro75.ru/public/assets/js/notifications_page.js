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

  function taskUrl(id) {
    var root = typeof window.__PLANNER_ROOT__ === 'string' ? window.__PLANNER_ROOT__ : '';
    return root + '/tasks.php?open=' + id;
  }

  function render(data) {
    var list = $('notifCommentList');
    var empty = $('notifCommentEmpty');
    var items = data.items || [];
    var unread = items.filter(function (x) {
      return !x.read;
    });
    if (!list) return;
    if (!unread.length) {
      list.innerHTML = '';
      if (empty) empty.hidden = false;
    } else {
      if (empty) empty.hidden = true;
      list.innerHTML = unread
        .map(function (n) {
          var tid = n.task_id ? parseInt(n.task_id, 10) : 0;
          var link =
            tid > 0
              ? '<a href="' +
                taskUrl(tid) +
                '" class="notif-task-link">Открыть задачу</a> · <button type="button" class="link-btn notif-read-btn" data-id="' +
                n.id +
                '">Прочитано</button>'
              : '';
          return (
            '<li class="notif-item"><div class="notif-title">' +
            escapeHtml(n.title || '') +
            '</div><div class="notif-meta muted">' +
            escapeHtml(n.created_at || '') +
            '</div><div class="notif-actions">' +
            link +
            '</div></li>'
          );
        })
        .join('');
      list.querySelectorAll('.notif-read-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = parseInt(btn.getAttribute('data-id'), 10);
          api('/api/notifications.php', { method: 'PATCH', body: { id: id } })
            .then(load)
            .catch(function (e) {
              notify(e.message || '', 'error');
            });
        });
      });
    }

    var dueList = $('notifDueList');
    var dueEmpty = $('notifDueEmpty');
    var dues = data.due_alerts || [];
    if (!dueList) return;
    if (!dues.length) {
      dueList.innerHTML = '';
      if (dueEmpty) dueEmpty.hidden = false;
    } else {
      if (dueEmpty) dueEmpty.hidden = true;
      dueList.innerHTML = dues
        .map(function (d) {
          var cls = d.level === 'overdue' ? 'notif-due--bad' : 'notif-due--soon';
          return (
            '<li class="notif-item ' +
            cls +
            '"><a href="' +
            taskUrl(d.task_id) +
            '">' +
            escapeHtml(d.title || '') +
            '</a> · ' +
            escapeHtml(d.due_date || '') +
            ' · ' +
            (d.level === 'overdue' ? 'просрочено' : 'скоро') +
            '</li>'
          );
        })
        .join('');
    }
  }

  function load() {
    return fetch(apiUrl('/api/notifications.php'), { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || 'err');
        render(d);
      })
      .catch(function () {
        $('notifCommentList').innerHTML = '<li class="muted">Не удалось загрузить.</li>';
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    load();
    var b = $('btnMarkAllRead');
    if (b) {
      b.addEventListener('click', function () {
        api('/api/notifications.php', { method: 'POST', body: { mark_all: true } })
          .then(load)
          .catch(function (e) {
            notify(e.message || '', 'error');
          });
      });
    }
  });
})();
