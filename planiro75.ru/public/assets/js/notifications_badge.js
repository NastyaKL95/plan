(function () {
  var badge = document.getElementById('notificationsBadge');
  if (!badge) return;

  var baseInterval = 90000;
  var hiddenInterval = 180000;
  var channelName = 'planner-notifications';
  var bc = null;
  try {
    if (typeof BroadcastChannel !== 'undefined') {
      bc = new BroadcastChannel(channelName);
    }
  } catch (e) {
    bc = null;
  }

  function fetchCount() {
    if (typeof Core === 'undefined' || !Core.api) return;
    Core.api('/api/notifications.php?count_only=1', { method: 'GET' })
      .then(function (data) {
        var n = data && typeof data.unread === 'number' ? data.unread : 0;
        if (n > 0) {
          badge.textContent = n > 99 ? '99+' : String(n);
          badge.classList.remove('is-hidden');
        } else {
          badge.textContent = '';
          badge.classList.add('is-hidden');
        }
      })
      .catch(function () {});
  }

  function broadcastRefresh() {
    if (!bc) return;
    try {
      bc.postMessage({ type: 'refresh' });
    } catch (e) {}
  }

  var timer = null;
  function scheduleNext() {
    if (timer) clearTimeout(timer);
    var ms = document.hidden ? hiddenInterval : baseInterval;
    timer = setTimeout(function () {
      fetchCount();
      scheduleNext();
    }, ms);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
      fetchCount();
      broadcastRefresh();
    }
    scheduleNext();
  });

  if (bc) {
    bc.onmessage = function (ev) {
      if (ev && ev.data && ev.data.type === 'refresh' && !document.hidden) {
        fetchCount();
      }
    };
  }

  fetchCount();
  scheduleNext();
})();
