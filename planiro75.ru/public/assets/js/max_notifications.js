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

  function renderItems(items) {
    var body = $('mxNotesBody');
    if (!body) return;
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="7" class="muted">Уведомлений нет.</td></tr>';
      return;
    }
    body.innerHTML = items.map(function (it) {
      var who = String(it.user_name || '') || String(it.user_email || '');
      var err = String(it.last_error || '');
      var text = String(it.text || '');
      if (err && String(it.status || '') === 'failed') {
        text = text !== '' ? text + '\nОшибка: ' + err : err;
      }
      return '<tr>' +
        '<td>' + escapeHtml(it.created_at || '') + '</td>' +
        '<td>' + statusHtml(it.status) + '</td>' +
        '<td>' + escapeHtml(eventLabel(it.event_type)) + '</td>' +
        '<td>' + escapeHtml(who || '—') + '</td>' +
        '<td>' + taskCell(it.task_id) + '</td>' +
        '<td class="max-note-text">' + escapeHtml(text || '—') + '</td>' +
        '<td>' + actionsHtml(it) + '</td>' +
        '</tr>';
    }).join('');
  }

  function renderDigest(items) {
    var body = $('mxDigestBody');
    if (!body) return;
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="6" class="muted">Дайджест пуст.</td></tr>';
      return;
    }
    body.innerHTML = items.map(function (it) {
      var who = String(it.user_name || '') || String(it.user_email || '');
      var id = parseInt(it.id, 10) || 0;
      return '<tr>' +
        '<td>' + escapeHtml(it.created_at || '') + '</td>' +
        '<td><span class="max-status max-status--digest">' + escapeHtml(eventLabel(it.event_type)) + '</span></td>' +
        '<td>' + escapeHtml(who || '—') + '</td>' +
        '<td>' + taskCell(it.task_id) + '</td>' +
        '<td class="max-note-text">' + escapeHtml(it.text || '') + '</td>' +
        '<td><button type="button" class="btn btn-secondary btn-sm" data-max-digest="' + id + '">Убрать</button></td>' +
        '</tr>';
    }).join('');
  }

  function load() {
    var hint = $('mxNotesHint');
    if (hint) hint.textContent = 'Загрузка…';
    var params = new URLSearchParams();
    var status = $('mxNotesStatus') ? $('mxNotesStatus').value : '';
    var eventType = $('mxNotesEvent') ? $('mxNotesEvent').value : '';
    var userQ = $('mxNotesUser') ? $('mxNotesUser').value.trim() : '';
    if (status) params.set('status', status);
    if (eventType) params.set('event_type', eventType);
    if (userQ) params.set('user_q', userQ);
    params.set('limit', '200');
    Core.api('/api/max_queue_list.php?' + params.toString()).then(function (res) {
      var items = (res && res.items) || [];
      var total = (res && res.total) || 0;
      renderItems(items);
      renderDigest((res && res.digest) || []);
      if (hint) {
        hint.textContent = total > items.length
          ? 'Показаны последние ' + items.length + ' из ' + total + '.'
          : 'Всего: ' + total + '.';
      }
    }).catch(function (err) {
      if (hint) hint.textContent = err.message || 'Не удалось загрузить уведомления';
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
