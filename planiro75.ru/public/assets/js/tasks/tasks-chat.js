  function parseFeedTs(createdAt) {
    if (!createdAt) return 0;
    var s = String(createdAt).replace(' ', 'T');
    var t = Date.parse(s);
    return isNaN(t) ? 0 : t;
  }

  /** Только часы:минуты, как в мессенджерах (без даты в подписи). */
  function formatChatMessageTime(createdAt) {
    if (!createdAt) return '';
    var raw = String(createdAt);
    var d = new Date(raw.replace(' ', 'T'));
    if (isNaN(d.getTime())) {
      d = new Date(raw);
    }
    if (isNaN(d.getTime())) {
      var sp = raw.split(/[\sT]/);
      if (sp.length >= 2) {
        var tpart = sp[1];
        var mm = tpart.match(/^(\d{1,2}):(\d{2})/);
        if (mm) {
          var hh = parseInt(mm[1], 10);
          var mi = parseInt(mm[2], 10);
          return (hh < 10 ? '0' : '') + hh + ':' + (mi < 10 ? '0' : '') + mi;
        }
      }
      return '';
    }
    var h = d.getHours();
    var m = d.getMinutes();
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
  }

  function chatMessageDatetimeAttr(createdAt) {
    if (!createdAt) return '';
    var raw = String(createdAt);
    var d = new Date(raw.replace(' ', 'T'));
    if (isNaN(d.getTime())) {
      d = new Date(raw);
    }
    if (isNaN(d.getTime())) return '';
    try {
      return d.toISOString();
    } catch (eIso) {
      return '';
    }
  }

  function parseCreatedDate(createdAt) {
    if (!createdAt) return null;
    var raw = String(createdAt);
    var d = new Date(raw.replace(' ', 'T'));
    if (isNaN(d.getTime())) {
      d = new Date(raw);
    }
    return isNaN(d.getTime()) ? null : d;
  }

  /** Календарный день в локальном времени, для группировки. */
  function chatDayKeyFromCreated(createdAt) {
    var d = parseCreatedDate(createdAt);
    if (!d) return '';
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }

  /** Подпись разделителя: «Вторник, 28 апреля». */
  function formatChatDateSeparatorLabel(createdAt) {
    var d = parseCreatedDate(createdAt);
    if (!d) return '';
    try {
      var s = d.toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' });
      if (!s) return '';
      s = String(s)
        .replace(/\s*20\d{2}\s*г\.?/gi, '')
        .replace(/\s+/g, ' ')
        .trim();
      if (!s) return '';
      return s.charAt(0).toUpperCase() + s.slice(1);
    } catch (eLab) {
      return '';
    }
  }

  function renderChatDateSeparatorHtml(createdAt) {
    var label = formatChatDateSeparatorLabel(createdAt);
    if (!label) return '';
    return (
      '<div class="chat-day-sep" role="separator" aria-label="' +
      escapeHtml(label) +
      '"><span class="chat-day-sep__pill">' +
      escapeHtml(label) +
      '</span></div>'
    );
  }

  function closeCommentMenus() {
    document.querySelectorAll('.comment-actions-menu').forEach(function (menu) {
      menu.hidden = true;
    });
  }

  /** Совпадает с min-height / max-height в .task-modal-comment-input (task-modal.css). */
  var CHAT_COMMENT_TEXTAREA_MIN_PX = 48;
  var CHAT_COMMENT_TEXTAREA_MAX_PX = 110;

  function sizeChatCommentTextarea() {
    var ta = $('newCommentBody');
    if (!ta) return;
    ta.style.overflowY = 'hidden';
    ta.style.height = 'auto';
    var sh = ta.scrollHeight;
    var capped = Math.min(Math.max(sh, CHAT_COMMENT_TEXTAREA_MIN_PX), CHAT_COMMENT_TEXTAREA_MAX_PX);
    ta.style.height = capped + 'px';
    ta.style.overflowY = sh > CHAT_COMMENT_TEXTAREA_MAX_PX ? 'auto' : 'hidden';
  }

  function startReplyToComment(commentId, userName, body) {
    var input = $('newCommentBody');
    if (!input) return;
    var intro = 'Ответ #' + commentId + ' ' + (userName || 'пользователь') + ':\n> ' + String(body || '').replace(/\n/g, '\n> ');
    var prev = (input.value || '').trim();
    input.value = (prev ? prev + '\n\n' : '') + intro + '\n\n';
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);
    sizeChatCommentTextarea();
  }

  function decodeMenuAttr(val) {
    try {
      return decodeURIComponent(val || '');
    } catch (e) {
      return val || '';
    }
  }

  function formatCommentBodyForDisplay(raw, mentions) {
    var map = {};
    (mentions || []).forEach(function (m) {
      if (m && m.user_id != null) {
        map[String(m.user_id)] = m.name || '';
      }
    });
    var s = escapeHtml(raw || '');
    s = s.replace(/@\[user:(\d+)\]/g, function (_, id) {
      var n = map[id] || 'user ' + id;
      return '<span class="comment-mention">@' + escapeHtml(n) + '</span>';
    });
    return s;
  }

  function refreshMentionQuickPick(t) {
    var sel = $('mentionQuickPick');
    if (!sel) return;
    var pool = [];
    function addUser(id, name) {
      var x = parseInt(id, 10) || 0;
      var me = currentUserId();
      if (!x || (me && x === me)) return;
      for (var i = 0; i < pool.length; i++) {
        if (pool[i].id === x) return;
      }
      pool.push({ id: x, name: (name || '').trim() || 'user ' + x });
    }
    if (t) {
      addUser(t.author_id, t.author_name);
      addUser(t.assignee_id, t.assignee_name);
      var part = t.participants || {};
      (part.assistants || []).forEach(function (u) {
        addUser(u.id, u.name);
      });
      (part.observers || []).forEach(function (u) {
        addUser(u.id, u.name);
      });
    }
    sel.innerHTML =
      '<option value="">Упомянуть участника…</option>' +
      pool
        .map(function (u) {
          return '<option value="' + u.id + '">' + escapeHtml(u.name) + '</option>';
        })
        .join('');
    if (typeof window.bxCustomSelectSync === 'function') window.bxCustomSelectSync(sel);
  }

  function commentsApiUrl(taskId, beforeId, limit) {
    var lim = limit != null ? limit : 80;
    var u = '/api/task_comments.php?task_id=' + encodeURIComponent(String(taskId)) + '&limit=' + encodeURIComponent(String(lim));
    if (beforeId) {
      u += '&before_id=' + encodeURIComponent(String(beforeId));
    }
    return u;
  }

  function syncLoadOlderChatUi() {
    var b = $('btnLoadOlderComments');
    if (!b) return;
    b.hidden = !drawerChatState.hasMoreOlder;
    b.disabled = drawerChatState.loadingOlder;
  }

  function scrollChatToBottom() {
    var sc = $('taskModalChatScroll');
    if (!sc) return;
    // Повторяем на следующем кадре, чтобы поймать перерасчёт высоты после рендера/открытия модалки.
    var apply = function () {
      sc.scrollTop = sc.scrollHeight;
    };
    apply();
    if (typeof requestAnimationFrame === 'function') {
      requestAnimationFrame(apply);
    } else {
      setTimeout(apply, 0);
    }
  }

  function renderChatFeed(comments, history) {
    var el = $('commentList');
    if (!el) return;
    comments = comments || [];
    history = history || [];
    var uid = currentUserId();
    var rows = [];
    comments.forEach(function (c) {
      rows.push({
        kind: 'comment',
        ts: parseFeedTs(c.created_at),
        sortId: parseInt(c.id, 10) || 0,
        data: c,
      });
    });
    history.forEach(function (h) {
      var hm = String((h && h.message) || '').trim();
      if (hm.indexOf('Комментарий:') === 0) return;
      rows.push({
        kind: 'history',
        ts: parseFeedTs(h.created_at),
        sortId: parseInt(h.id, 10) || 0,
        data: h,
      });
    });
    rows.sort(function (a, b) {
      if (a.ts !== b.ts) return a.ts - b.ts;
      if (a.sortId !== b.sortId) return a.sortId - b.sortId;
      return a.kind === b.kind ? 0 : a.kind === 'comment' ? -1 : 1;
    });

    if (!rows.length) {
      el.innerHTML = '<p class="comment-empty">Пока нет комментариев и записей журнала.</p>';
      return;
    }

    function buildChatFeedRowHtml(row) {
      if (row.kind === 'history') {
        var h = row.data;
        var hIso = chatMessageDatetimeAttr(h.created_at);
        var hClock = formatChatMessageTime(h.created_at);
        return (
          '<div class="chat-feed-event"><div class="chat-feed-event-meta">' +
          escapeHtml(h.user_name || 'Система') +
          ' <span class="chat-feed-event-sep" aria-hidden="true">·</span> <time class="chat-feed-event-time" datetime="' +
          escapeHtml(hIso || '') +
          '">' +
          escapeHtml(hClock || '') +
          '</time></div><div class="chat-feed-event-body">' +
          escapeHtml(h.message || '') +
          '</div></div>'
        );
      }
      var c = row.data;
      var mine = uid && parseInt(c.user_id, 10) === uid;
      var receiptState = c.receipt_state;
      if (receiptState === undefined || receiptState === null || receiptState === '') {
        receiptState = mine ? 0 : null;
      } else {
        receiptState = Number(receiptState);
        if (isNaN(receiptState)) receiptState = mine ? 0 : null;
      }
      var outgoingUnread = mine && receiptState === 0;
      var outgoingPartial = mine && receiptState === 1;
      var outgoingReadAll = mine && receiptState === 2;
      var readBy = Array.isArray(c.receipt_read_by) ? c.receipt_read_by : [];
      var checksTitle = 'Не прочитано';
      if (outgoingReadAll || outgoingPartial) {
        checksTitle = readBy.length ? 'Прочитали: ' + readBy.join(', ') : outgoingReadAll ? 'Прочитано всеми' : 'Прочитано';
      }
      var menuItems =
        '<button type="button" class="comment-action-item" data-act="reply" data-id="' +
        c.id +
        '" data-user="' +
        encodeURIComponent(String(c.user_name || '')) +
        '" data-body="' +
        encodeURIComponent(String(c.body || '')) +
        '">Ответить</button>' +
        (mine
          ? '<button type="button" class="comment-action-item" data-act="edit" data-id="' +
            c.id +
            '" data-body="' +
            encodeURIComponent(String(c.body || '')) +
            '">Редактировать</button><button type="button" class="comment-action-item is-danger" data-act="delete" data-id="' +
            c.id +
            '">Удалить</button>'
          : '');
      var menuBtn =
        '<div class="comment-actions"><button type="button" class="comment-actions-toggle" data-id="' +
        c.id +
        '" aria-label="Действия">⋯</button><div class="comment-actions-menu" data-menu-id="' +
        c.id +
        '" hidden>' +
        menuItems +
        '</div></div>';
      var isoAttr = chatMessageDatetimeAttr(c.created_at);
      var clockStr = formatChatMessageTime(c.created_at);
      var headRow =
        '<div class="comment-msg-head' +
        (mine ? ' comment-msg-head--out' : '') +
        '">' +
        (mine
          ? '<span class="comment-msg-head-spacer" aria-hidden="true"></span>'
          : '<span class="comment-author">' + escapeHtml(c.user_name || '') + '</span>') +
        menuBtn +
        '</div>';
      return (
        '<article class="comment-item ' +
        (mine ? 'comment-item--mine' : 'comment-item--other') +
        (outgoingPartial ? ' comment-item--read-partial' : '') +
        (outgoingReadAll ? ' comment-item--read' : '') +
        (outgoingUnread ? ' comment-item--unread' : '') +
        '" data-id="' +
        c.id +
        '">' +
        headRow +
        '<div class="comment-body">' +
        formatCommentBodyForDisplay(c.body, c.mentions) +
        '</div>' +
        '<div class="comment-msg-footer">' +
        '<time class="comment-msg-time" datetime="' +
        escapeHtml(isoAttr || '') +
        '">' +
        escapeHtml(clockStr || '') +
        '</time>' +
        (mine
          ? '<span class="comment-read-checks ' +
            (outgoingReadAll ? 'is-read' : outgoingPartial ? 'is-partial' : 'is-unread') +
            '" title="' +
            escapeHtml(checksTitle) +
            '">' +
            (outgoingUnread ? '✓' : '✓✓') +
            '</span>'
          : '') +
        '</div></article>'
      );
    }

    var lastDayKey = null;
    var htmlChunks = [];
    rows.forEach(function (row) {
      var createdSrc = row.kind === 'comment' ? row.data.created_at : row.data.created_at;
      var dk = chatDayKeyFromCreated(createdSrc);
      if (dk) {
        if (dk !== lastDayKey) {
          var sep = renderChatDateSeparatorHtml(createdSrc);
          if (sep) htmlChunks.push(sep);
          lastDayKey = dk;
        }
      }
      htmlChunks.push(buildChatFeedRowHtml(row));
    });
    el.innerHTML = htmlChunks.join('');

    el.querySelectorAll('.comment-actions-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cid = btn.getAttribute('data-id');
        var menu = cid ? el.querySelector('.comment-actions-menu[data-menu-id="' + cid + '"]') : null;
        if (!menu) return;
        var willOpen = menu.hidden;
        closeCommentMenus();
        menu.hidden = !willOpen;
      });
    });

    el.querySelectorAll('.comment-action-item').forEach(function (item) {
      item.addEventListener('click', function () {
        var act = item.getAttribute('data-act');
        var cid = item.getAttribute('data-id');
        if (!cid) return;
        if (act === 'reply') {
          closeCommentMenus();
          startReplyToComment(
            cid,
            decodeMenuAttr(item.getAttribute('data-user') || ''),
            decodeMenuAttr(item.getAttribute('data-body') || '')
          );
          return;
        }
        if (act === 'edit') {
          var current = decodeMenuAttr(item.getAttribute('data-body') || '');
          closeCommentMenus();
          openAppInlinePrompt({
            title: 'Изменить комментарий',
            message: 'Введите новый текст сообщения.',
            value: current,
            maxlength: 16000,
            confirmLabel: 'Сохранить',
          }).then(function (next) {
            if (next == null) return;
            var text = (next || '').trim();
            if (!text) {
              notify('Текст комментария не может быть пустым.', 'error');
              return;
            }
            api('/api/task_comment.php?id=' + cid, { method: 'PATCH', body: { body: text } })
              .then(function () {
                return openDrawer($('fTaskId').value);
              })
              .catch(function (e) {
                notify('Не удалось изменить: ' + (e.message || ''), 'error');
              });
          });
          return;
        }
        if (act === 'delete') {
          closeCommentMenus();
          openAppInlineConfirm({
            title: 'Удалить комментарий?',
            message: 'Сообщение будет удалено без возможности восстановления.',
            confirmLabel: 'Удалить',
            danger: true,
          }).then(function (ok) {
            if (!ok) return;
            api('/api/task_comment.php?id=' + cid, { method: 'DELETE' })
              .then(function () {
                return openDrawer($('fTaskId').value);
              })
              .catch(function (e) {
                notify('Не удалось удалить: ' + (e.message || ''), 'error');
              });
          });
        }
      });
    });
  }

  function renderCreateChatFeed() {
    var el = $('commentList');
    if (!el) return;
    var hint =
      '<p class="task-modal-system-msg task-modal-system-msg--feed-hint">Задача ещё не создана. Ниже можно написать сообщения — они отправятся в чат после нажатия «Создать задачу».</p>';
    var drafts = createModalState.chatDrafts || [];
    var draftBlocks = drafts
      .map(function (body, i) {
        return (
          '<article class="comment-item comment-item--draft" data-draft-index="' +
          i +
          '"><div class="comment-msg-head"><span class="comment-author">' +
          escapeHtml(currentUserDisplayName() || 'Вы') +
          '</span><span class="comment-draft-badge">к отправке</span></div><div class="comment-body">' +
          escapeHtml(body) +
          '</div></article>'
        );
      })
      .join('');
    if (!drafts.length) {
      el.innerHTML = hint;
      return;
    }
    el.innerHTML = hint + draftBlocks;
  }
