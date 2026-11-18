(function () {
  var badge = document.getElementById('bxNotifBadge');
  var Core = window.PlannerCore;
  if (!badge || !Core || !Core.api) return;

  var baseInterval = 90000;
  var hiddenInterval = 180000;
  var bc = null;
  try {
    if (typeof BroadcastChannel !== 'undefined') {
      bc = new BroadcastChannel('planner-notifications');
    }
  } catch (e) {
    bc = null;
  }

  function show(data) {
    var n = data && typeof data.badge_total === 'number' ? data.badge_total : 0;
    var overdue = data && data.overdue_assignee_count > 0;
    badge.textContent = n > 99 ? '99+' : String(n);
    badge.hidden = n <= 0;
    badge.classList.toggle('bx-rail-badge--hot', !!overdue);
    var link = badge.closest('a');
    if (link) {
      var parts = [];
      if (data && data.unread_comment_count) parts.push('непрочитанных: ' + data.unread_comment_count);
      if (overdue) parts.push('просрочено задач: ' + data.overdue_assignee_count);
      link.title = 'Уведомления' + (parts.length ? ' (' + parts.join(', ') + ')' : '');
    }
  }

  function fetchCount() {
    Core.api('/api/notifications.php?badge_only=1', { method: 'GET' })
      .then(show)
      .catch(function () {});
  }

  var timer = null;
  function scheduleNext() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(function () {
      fetchCount();
      scheduleNext();
    }, document.hidden ? hiddenInterval : baseInterval);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) fetchCount();
    scheduleNext();
  });

  // Страница уведомлений сообщает об отметке «прочитано» — обновляем значок здесь и в других вкладках.
  window.addEventListener('planner:notifications-changed', function () {
    fetchCount();
    if (bc) {
      try {
        bc.postMessage({ type: 'refresh' });
      } catch (e) {}
    }
  });
  if (bc) {
    bc.onmessage = function (ev) {
      if (ev && ev.data && ev.data.type === 'refresh' && !document.hidden) fetchCount();
    };
  }

  fetchCount();
  scheduleNext();
})();
