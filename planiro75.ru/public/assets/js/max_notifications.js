(function () {
  var Core = window.PlannerCore;
  if (!Core) return;

  var EVENT_LABELS = {
    task_created: 'Создание задачи',
    task_assigned: 'Назначение исполнителя',
    task_status_changed: 'Смена статуса',
    task_due_changed: 'Смена срока',
    task_comment_mention: 'Упоминание в комментарии',
    task_participant_added: 'Соисполнитель',
    direct_message: 'Прямое сообщение',
    sla_due_2h: 'SLA: срок через 2 часа',
    sla_overdue_24h: 'SLA: просрочка',
    sla_stale_days: 'SLA: нет движения',
    digest_summary: 'Дайджест',
  };
  var STATUS_LABELS = {
    pending: 'Ожидает',
    sent: 'Отправлено',
    failed: 'Ошибка',
    cancelled: 'Отменено',
  };

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    return Core.escapeHtml(s);
  }

  function eventLabel(code) {
    var key = String(code || '');
    return EVENT_LABELS[key] || (key !== '' ? key : 'Событие');
  }

  function statusHtml(status) {
    var key = String(status || '');
    var label = STATUS_LABELS[key] || key || '—';
    var cls = 'max-status max-status--' + (STATUS_LABELS[key] ? key : 'cancelled');
    return '<span class="' + cls + '">' + escapeHtml(label) + '</span>';
  }

  function taskCell(taskId) {
    var id = parseInt(taskId, 10) || 0;
    if (!id) return '—';
    return '<a href="' + Core.apiUrl('/tasks.php?open=' + id) + '">#' + id + '</a>';
  }

  function actionsHtml(item) {
    var id = parseInt(item.id, 10) || 0;
    var status = String(item.status || '');
    var html = '<div class="max-note-actions">';
    if (status === 'pending') {
      html += '<button type="button" class="btn btn-secondary btn-sm" data-max-action="cancel" data-id="' + id + '">Отменить</button>';
    } else if (status === 'failed' || status === 'cancelled') {
      html += '<button type="button" class="btn btn-secondary btn-sm" data-max-action="retry" data-id="' + id + '">Отправить снова</button>';
    } else if (status === 'sent') {
      html += '<button type="button" class="btn btn-secondary btn-sm" data-max-action="resend" data-id="' + id + '">Повторить</button>';
    }
    html += '</div>';
    return html;
  }

  var lastTotal = 0;
  var selected = {};
  var digestSelected = {};

  function selectedIds(map) {
    return Object.keys(map).filter(function (id) { return map[id]; }).map(function (id) { return parseInt(id, 10); });
  }

  function refreshBulk() {
    var ids = selectedIds(selected);
    var count = $('mxBulkCount');
    if (count) count.textContent = ids.length ? 'Выбрано: ' + ids.length : 'Ничего не выбрано';
    var retry = $('mxBulkRetry');
    var del = $('mxBulkDelete');
    if (retry) retry.disabled = ids.length === 0;
    if (del) del.disabled = ids.length === 0;
    var all = $('mxNotesCheckAll');
    var boxes = document.querySelectorAll('#mxNotesBody input[data-max-check]');
    if (all) {
      var on = 0;
      boxes.forEach(function (box) { if (box.checked) on += 1; });
      all.checked = boxes.length > 0 && on === boxes.length;
      all.indeterminate = on > 0 && on < boxes.length;
    }
  }

  function refreshDigestBulk() {
    var ids = selectedIds(digestSelected);
    var count = $('mxDigestCount');
    if (count) count.textContent = ids.length ? 'Выбрано: ' + ids.length : 'Ничего не выбрано';
    var btn = $('mxDigestDelete');
    if (btn) btn.disabled = ids.length === 0;
    var all = $('mxDigestCheckAll');
    var boxes = document.querySelectorAll('#mxDigestBody input[data-digest-check]');
    if (all) {
      var on = 0;
      boxes.forEach(function (box) { if (box.checked) on += 1; });
      all.checked = boxes.length > 0 && on === boxes.length;
      all.indeterminate = on > 0 && on < boxes.length;
    }
  }

  function currentFilter() {
    return {
      status: $('mxNotesStatus') ? $('mxNotesStatus').value : 'except_sent',
      event_type: $('mxNotesEvent') ? $('mxNotesEvent').value : '',
      user_q: $('mxNotesUser') ? $('mxNotesUser').value.trim() : '',
    };
  }

  function renderItems(items) {
    var body = $('mxNotesBody');
    if (!body) return;
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="8" class="muted">Уведомлений нет.</td></tr>';
      refreshBulk();
      return;
    }
    body.innerHTML = items.map(function (it) {
      var who = String(it.user_name || '') || String(it.user_email || '');
      var err = String(it.last_error || '');
      var text = String(it.text || '');
      var id = parseInt(it.id, 10) || 0;
      if (err && String(it.status || '') === 'failed') {
        text = text !== '' ? text + '\nОшибка: ' + err : err;
      }
      return '<tr>' +
        '<td class="max-check-col"><input type="checkbox" data-max-check="' + id + '" aria-label="Выбрать уведомление ' + id + '"' + (selected[id] ? ' checked' : '') + '></td>' +
        '<td>' + escapeHtml(it.created_at || '') + '</td>' +
        '<td>' + statusHtml(it.status) + '</td>' +
        '<td>' + escapeHtml(eventLabel(it.event_type)) + '</td>' +
        '<td>' + escapeHtml(who || '—') + '</td>' +
        '<td>' + taskCell(it.task_id) + '</td>' +
        '<td class="max-note-text">' + escapeHtml(text || '—') + '</td>' +
        '<td>' + actionsHtml(it) + '</td>' +
        '</tr>';
    }).join('');
    refreshBulk();
  }

  function renderDigest(items) {
    var body = $('mxDigestBody');
    if (!body) return;
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="7" class="muted">Дайджест пуст.</td></tr>';
      refreshDigestBulk();
      return;
    }
    body.innerHTML = items.map(function (it) {
      var who = String(it.user_name || '') || String(it.user_email || '');
      var id = parseInt(it.id, 10) || 0;
      return '<tr>' +
        '<td class="max-check-col"><input type="checkbox" data-digest-check="' + id + '" aria-label="Выбрать сообщение дайджеста ' + id + '"' + (digestSelected[id] ? ' checked' : '') + '></td>' +
        '<td>' + escapeHtml(it.created_at || '') + '</td>' +
        '<td><span class="max-status max-status--digest">' + escapeHtml(eventLabel(it.event_type)) + '</span></td>' +
        '<td>' + escapeHtml(who || '—') + '</td>' +
        '<td>' + taskCell(it.task_id) + '</td>' +
        '<td class="max-note-text">' + escapeHtml(it.text || '') + '</td>' +
        '<td><button type="button" class="btn btn-secondary btn-sm" data-max-digest="' + id + '">Убрать</button></td>' +
        '</tr>';
    }).join('');
    refreshDigestBulk();
  }

  function load() {
    var hint = $('mxNotesHint');
    if (hint) hint.textContent = 'Загрузка…';
    selected = {};
    digestSelected = {};
    var filter = currentFilter();
    var params = new URLSearchParams();
    if (filter.status) params.set('status', filter.status);
    if (filter.event_type) params.set('event_type', filter.event_type);
    if (filter.user_q) params.set('user_q', filter.user_q);
    params.set('limit', '200');
    Core.api('/api/max_queue_list.php?' + params.toString()).then(function (res) {
      var items = (res && res.items) || [];
      var total = (res && res.total) || 0;
      lastTotal = total;
      renderItems(items);
      renderDigest((res && res.digest) || []);
      if (hint) {
        var hidden = filter.status === 'except_sent' ? ' Успешно отправленные скрыты.' : '';
        hint.textContent = (total > items.length
          ? 'Показаны последние ' + items.length + ' из ' + total + '.'
          : 'Всего: ' + total + '.') + hidden;
      }
    }).catch(function (err) {
      if (hint) hint.textContent = err.message || 'Не удалось загрузить уведомления';
    });
  }

  function bulk(body) {
    return Core.api('/api/max_queue_item.php', {
      method: 'POST',
      body: body,
    }, {
      forbidden: 'Недостаточно прав',
      bad_action: 'Неизвестное действие',
      bad_id: 'Не выбраны уведомления',
    }).then(function (res) {
      var n = res && res.affected != null ? res.affected : 0;
      if (Core.notify) Core.notify('Готово: ' + n, { type: 'success' });
      load();
    }).catch(function (err) {
      if (Core.notify) Core.notify(err.message || 'Не удалось изменить уведомления', { type: 'error' });
    });
  }

  function apply(source, id, action) {
    Core.api('/api/max_queue_item.php', {
      method: 'POST',
      body: { source: source, id: id, action: action },
    }, {
      forbidden: 'Недостаточно прав',
      not_found: 'Уведомление уже изменено или удалено',
      bad_status: 'Это действие сейчас недоступно',
      bad_action: 'Неизвестное действие',
    }).then(load).catch(function (err) {
      if (Core.notify) Core.notify(err.message || 'Не удалось изменить уведомление', { type: 'error' });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var reload = $('btnMaxNotesReload');
    if (reload) reload.addEventListener('click', load);
    ['mxNotesStatus', 'mxNotesEvent'].forEach(function (id) {
      var el = $(id);
      if (el) el.addEventListener('change', load);
    });
    var user = $('mxNotesUser');
    if (user) {
      user.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          load();
        }
      });
    }
    var notesAll = $('mxNotesCheckAll');
    if (notesAll) {
      notesAll.addEventListener('change', function () {
        document.querySelectorAll('#mxNotesBody input[data-max-check]').forEach(function (box) {
          box.checked = notesAll.checked;
          var rowId = parseInt(box.getAttribute('data-max-check') || '0', 10);
          if (rowId) selected[rowId] = notesAll.checked;
        });
        refreshBulk();
      });
    }
    var digestAll = $('mxDigestCheckAll');
    if (digestAll) {
      digestAll.addEventListener('change', function () {
        document.querySelectorAll('#mxDigestBody input[data-digest-check]').forEach(function (box) {
          box.checked = digestAll.checked;
          var rowId = parseInt(box.getAttribute('data-digest-check') || '0', 10);
          if (rowId) digestSelected[rowId] = digestAll.checked;
        });
        refreshDigestBulk();
      });
    }
    document.addEventListener('change', function (ev) {
      var box = ev.target;
      if (!box || !box.getAttribute) return;
      var noteId = parseInt(box.getAttribute('data-max-check') || '0', 10);
      if (noteId) {
        selected[noteId] = box.checked;
        refreshBulk();
        return;
      }
      var digestId = parseInt(box.getAttribute('data-digest-check') || '0', 10);
      if (digestId) {
        digestSelected[digestId] = box.checked;
        refreshDigestBulk();
      }
    });
    var retryBtn = $('mxBulkRetry');
    var deleteBtn = $('mxBulkDelete');
    if (retryBtn) {
      retryBtn.addEventListener('click', function () {
        var ids = selectedIds(selected);
        if (!ids.length) return;
        bulk({ source: 'event', action: 'retry', ids: ids });
      });
    }
    if (deleteBtn) {
      deleteBtn.addEventListener('click', function () {
        var ids = selectedIds(selected);
        if (!ids.length) return;
        if (!window.confirm('Удалить выбранные уведомления: ' + ids.length + '?')) return;
        bulk({ source: 'event', action: 'delete', ids: ids });
      });
    }
    var filterRetry = $('mxBulkFilterRetry');
    var filterDelete = $('mxBulkFilterDelete');
    if (filterRetry) {
      filterRetry.addEventListener('click', function () {
        if (!lastTotal) return;
        if (!window.confirm('Снова поставить в очередь все уведомления по фильтру, до 5000? Сейчас в фильтре: ' + lastTotal + '.')) return;
        var filter = currentFilter();
        bulk({
          source: 'event',
          action: 'retry',
          scope: 'filter',
          status: filter.status,
          event_type: filter.event_type,
          user_q: filter.user_q,
        });
      });
    }
    if (filterDelete) {
      filterDelete.addEventListener('click', function () {
        if (!lastTotal) return;
        if (!window.confirm('Удалить все уведомления по фильтру, до 5000? Сейчас в фильтре: ' + lastTotal + '.')) return;
        var filter = currentFilter();
        bulk({
          source: 'event',
          action: 'delete',
          scope: 'filter',
          status: filter.status,
          event_type: filter.event_type,
          user_q: filter.user_q,
        });
      });
    }
    var digestDelete = $('mxDigestDelete');
    var digestDeleteAll = $('mxDigestDeleteAll');
    if (digestDelete) {
      digestDelete.addEventListener('click', function () {
        var ids = selectedIds(digestSelected);
        if (!ids.length) return;
        if (!window.confirm('Убрать выбранные сообщения из дайджеста: ' + ids.length + '?')) return;
        bulk({ source: 'digest', action: 'delete', ids: ids });
      });
    }
    if (digestDeleteAll) {
      digestDeleteAll.addEventListener('click', function () {
        if (!window.confirm('Убрать из дайджеста все ожидающие сообщения?')) return;
        bulk({ source: 'digest', action: 'delete', scope: 'filter' });
      });
    }
    document.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('[data-max-action], [data-max-digest]') : null;
      if (!btn) return;
      var digestId = parseInt(btn.getAttribute('data-max-digest') || '0', 10);
      if (digestId) {
        apply('digest', digestId, 'cancel');
        return;
      }
      var id = parseInt(btn.getAttribute('data-id') || '0', 10);
      var action = btn.getAttribute('data-max-action') || '';
      if (id && action) apply('event', id, action);
    });
    load();
  });
})();
