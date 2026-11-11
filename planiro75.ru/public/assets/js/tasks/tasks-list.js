  function loadTasks(opts) {
    opts = opts || {};
    var append = !!opts.append;
    if (tasksPagination.loading) return Promise.resolve();
    tasksPagination.loading = true;
    var offset = append ? tasksCache.length : 0;
    if (!append) {
      tasksCache = [];
    }
    var qs = buildTasksQueryString();
    if (qs.slice(-1) !== '&' && qs.slice(-1) !== '?') {
      qs += qs.indexOf('?') >= 0 ? '&' : '?';
    }
    qs += tasksListQuerySuffix(tasksPagination.limit, offset);
    return api(qs)
      .then(function (data) {
        var items = data.items || [];
        items.forEach(function (t) {
          if (!Array.isArray(t.gantt_predecessor_ids)) t.gantt_predecessor_ids = [];
        });
        if (append) {
          var seen = {};
          tasksCache.forEach(function (t) {
            seen[t.id] = true;
          });
          items.forEach(function (t) {
            if (!seen[t.id]) {
              tasksCache.push(t);
              seen[t.id] = true;
            }
          });
        } else {
          tasksCache = items;
        }
        tasksPagination.total = data.total != null ? data.total : tasksCache.length;
        tasksPagination.hasMore = data.has_more === true || data.has_more === 1;
        updateStatsFromResponse(data);
        setViewTabs();
        if (viewMode === 'list') renderTaskTable();
        else if (viewMode === 'plan') renderMyPlan();
        else if (viewMode === 'deadlines') renderDeadlinesBoard();
        else if (viewMode === 'calendar') renderCalendar();
        else if (viewMode === 'gantt') renderGantt();
        else renderKanban();
        updateTaskListTotal();
        updateTasksLoadMoreUi();
        setupTasksLoadMoreObserver();
        syncActiveFiltersChips();
        syncAdvMoreButtonLabel();
      })
      .finally(function () {
        tasksPagination.loading = false;
        updateTasksLoadMoreUi();
      });
  }

  function loadMoreTasks() {
    if (!tasksPagination.hasMore || tasksPagination.loading) return Promise.resolve();
    return loadTasks({ append: true });
  }

  function updateTasksLoadMoreUi() {
    var hint = $('tasksPaginationHint');
    if (hint) {
      hint.textContent = 'Показано ' + tasksCache.length + ' из ' + tasksPagination.total;
    }
    var foot = $('tasksPaginationFooter');
    if (foot) {
      foot.textContent = tasksCache.length + ' из ' + tasksPagination.total;
    }
    if (!tasksPagination.hasMore && tasksLoadMoreObserver) {
      tasksLoadMoreObserver.disconnect();
      tasksLoadMoreObserver = null;
    }
  }

  function setupTasksLoadMoreObserver() {
    if (tasksLoadMoreObserver) {
      tasksLoadMoreObserver.disconnect();
      tasksLoadMoreObserver = null;
    }
    if (!tasksPagination.hasMore) return;
    var sentinel = $('tasksLoadMoreSentinel');
    if (!sentinel || typeof IntersectionObserver === 'undefined') return;
    tasksLoadMoreObserver = new IntersectionObserver(
      function (entries) {
        for (var i = 0; i < entries.length; i++) {
          if (entries[i].isIntersecting) {
            loadMoreTasks();
            break;
          }
        }
      },
      { root: null, rootMargin: '240px', threshold: 0 }
    );
    tasksLoadMoreObserver.observe(sentinel);
  }

  function updateStatsFromResponse(data) {
    var s = data.stats || {};
    var o = $('statOverdue');
    if (o) o.textContent = String(s.overdue != null ? s.overdue : 0);
    var c = $('statComments');
    if (c) c.textContent = String(s.tasks_with_comments != null ? s.tasks_with_comments : 0);
    var w = $('statChatsWeek');
    if (w) w.textContent = String(s.chats_week != null ? s.chats_week : 0);
  }

  function renderTaskTable() {
    var tb = $('taskTableBody');
    if (!tb) return;
    tb.innerHTML = tasksCache
      .map(function (t) {
        return (
          '<tr class="task-row" data-id="' +
          t.id +
          '"><td class="task-table-check"><input type="checkbox" class="task-cb" data-id="' +
          t.id +
          '"></td><td class="task-table-title">' +
          taskTableTitleCellHtml(t) +
          '</td><td>' +
          taskTableActivityHtml(t) +
          '</td><td>' +
          taskTableDueHtml(t) +
          '</td><td>' +
          taskTableTextOrDash(t.author_name) +
          '</td><td>' +
          taskTableTextOrDash(t.assignee_name) +
          '</td><td class="task-table-muted">' +
          taskTableMutedTextHtml(t.assistants_line) +
          '</td><td>' +
          taskTableTextHtml(t.project_name) +
          '</td><td class="task-table-muted">' +
          taskTableMutedTextHtml(t.tag_line) +
          '</td><td>' +
          taskTableStatusHtml(t) +
          '</td></tr>'
        );
      })
      .join('');
    tb.querySelectorAll('.task-row').forEach(function (row) {
      row.addEventListener('click', function () {
        openDrawer(parseInt(row.getAttribute('data-id'), 10));
      });
    });
    tb.querySelectorAll('.task-cb').forEach(function (cb) {
      cb.addEventListener('click', function (ev) {
        ev.stopPropagation();
      });
      cb.addEventListener('change', function () {
        refreshBulkSelectionCount();
        updateBulkPreview();
      });
    });
    refreshBulkSelectionCount();
    var selAll = $('taskSelectAll');
    if (selAll) selAll.checked = false;
    updateBulkPreview();
  }

  function taskTableTextHtml(v) {
    return escapeHtml(v || '');
  }

  function taskTableTextOrDash(v) {
    return escapeHtml(v || '—');
  }

  function taskTableMutedTextHtml(v) {
    return escapeHtml(v || '');
  }

  function taskTableStatusHtml(t) {
    var st = LIST_STATUS_LABEL[t.status] || t.status;
    return escapeHtml(st);
  }

  function taskTableActivityHtml(t) {
    return t.updated_at ? escapeHtml(String(t.updated_at).replace('T', ' ').slice(0, 16)) : '—';
  }

  function taskTableDueHtml(t) {
    return t.due_date
      ? '<span class="task-due-pill' +
          (taskIsOverdue(t) ? ' task-due-pill--overdue' : '') +
          '">' +
          formatDueRu(t.due_date) +
          '</span>'
      : '—';
  }

  function taskTableTitleCellHtml(t) {
    var visibilityLine = visibilityPillsHtml(t);
    var qb = isQuickTaskItem(t)
      ? '<span class="task-quick-badge-inline" title="Быстрая задача">Быстрая</span> '
      : '';
    return (
      qb +
      escapeHtml(t.title || '') +
      (visibilityLine ? '<div class="task-table-visibility">' + visibilityLine + '</div>' : '')
    );
  }

  function selectedTaskIdsFromTable() {
    var tb = $('taskTableBody');
    if (!tb) return [];
    var ids = [];
    tb.querySelectorAll('.task-cb:checked').forEach(function (cb) {
      var id = parseInt(cb.getAttribute('data-id'), 10);
      if (id > 0) ids.push(id);
    });
    return ids;
  }

  function refreshBulkSelectionCount() {
    var n = selectedTaskIdsFromTable().length;
    var el = $('bulkSelectedCount');
    if (el) el.textContent = 'Выбрано: ' + n;
  }

  function setBulkProgress(text, shown) {
    var p = $('bulkProgress');
    if (!p) return;
    p.hidden = !shown;
    p.textContent = text || 'Применение...';
  }

  function updateBulkPreview() {
    var el = $('bulkPreview');
    if (!el) return;
    var action = $('bulkAction') ? $('bulkAction').value : '';
    var ids = selectedTaskIdsFromTable();
    if (!ids.length) {
      el.textContent = 'Выберите задачи для предпросмотра.';
      return;
    }
    if (action === 'set_status') {
      var st = $('bulkStatus') ? $('bulkStatus').value : '';
      if (!st) {
        el.textContent = 'Выберите новый статус для ' + ids.length + ' задач.';
        return;
      }
      var stRu = LIST_STATUS_LABEL[st] || st;
      el.textContent = 'Будет изменен статус у ' + ids.length + ' задач: ' + stRu + '.';
      return;
    }
    if (action === 'set_assignee') {
      var av = $('bulkAssignee') ? $('bulkAssignee').value : '';
      var asgText = 'Не назначен';
      if (av && $('bulkAssignee')) {
        var sel = $('bulkAssignee');
        asgText = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : asgText;
      }
      el.textContent = 'Будет назначен исполнитель у ' + ids.length + ' задач: ' + asgText + '.';
      return;
    }
    el.textContent = 'Выберите действие в списке.';
  }

  function escapeHtml(s) {
    return Core.escapeHtml(s);
  }

  function ensureNativeSelectWithoutBxDropdown(selectId) {
    var sel = $(selectId);
    if (!sel) return;
    sel.setAttribute('data-bx-dropdown-skip', '1');
    var wrap = sel.closest('.bx-dropdown-wrap');
    if (!wrap || !wrap.parentNode) return;
    wrap.parentNode.insertBefore(sel, wrap);
    wrap.remove();
  }

  function projectOptionLabel(p) {
    if (!p) return '';
    var dn = p.department_name ? p.department_name + ' — ' : '';
    return dn + (p.name || '');
  }

  function visibilityPillsHtml(t) {
    if (!t) return '';
    var out = '';
    if (parseInt(t.visible_department || 0, 10) === 1) {
      out += '<span class="pill pill-visibility pill-visibility--department">Структура</span>';
    }
    if (parseInt(t.visible_institute || 0, 10) === 1) {
      out += '<span class="pill pill-visibility pill-visibility--institute">Институт</span>';
    }
    return out;
  }

  function userPickerLabel(u) {
    var name = u.name || '';
    if (u.account_status === 'blocked' || u.is_blocked) {
      return name + ' (заблокирован)';
    }
    return name;
  }

  function fillParticipantSelects() {
    var opts = users
      .map(function (u) {
        return '<option value="' + u.id + '">' + escapeHtml(userPickerLabel(u)) + '</option>';
      })
      .join('');
    var fa = $('fAssistants');
    var fo = $('fObservers');
    if (fa) fa.innerHTML = opts;
    if (fo) fo.innerHTML = opts;
    updateParticipantSummaries();
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function participantSelectByRole(role) {
    if (role === 'assistants') return $('fAssistants');
    if (role === 'observers') return $('fObservers');
    if (role === 'assignee') return $('fAssignee');
    if (role === 'project') return $('fProject');
    return null;
  }

  function participantButtonByRole(role) {
    if (role === 'assistants') return $('btnAssistantsPicker');
    if (role === 'observers') return $('btnObserversPicker');
    if (role === 'assignee') return $('btnAssigneePicker');
    if (role === 'project') return $('btnProjectPicker');
    return null;
  }

  function positionParticipantPicker(role) {
    var pop = $('participantPicker');
    var anchor = participantButtonByRole(role);
    if (!pop || !anchor) return;
    var ar = anchor.getBoundingClientRect();
    var vw = window.innerWidth || document.documentElement.clientWidth || 0;
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;
    var desiredWidth = Math.max(320, Math.min(560, ar.width));
    var left = ar.left;
    if (left + desiredWidth > vw - 12) {
      left = Math.max(12, vw - desiredWidth - 12);
    }
    var top = ar.bottom + 8;
    var maxTop = vh - 140;
    if (top > maxTop) {
      top = Math.max(12, ar.top - 12);
      pop.style.transform = 'translateY(-100%)';
    } else {
      pop.style.transform = 'none';
    }
    pop.style.left = Math.round(left) + 'px';
    pop.style.top = Math.round(top) + 'px';
    pop.style.width = Math.round(desiredWidth) + 'px';
  }

  function participantTitleByRole(role) {
    if (role === 'assistants') return 'Соисполнители';
    if (role === 'observers') return 'Наблюдатели';
    if (role === 'assignee') return 'Исполнитель';
    if (role === 'project') return 'Проект';
    return 'Выбор';
  }

  function participantRoleIsSingle(role) {
    return role === 'assignee' || role === 'project';
  }

  function selectedParticipantItems(role) {
    var sel = participantSelectByRole(role);
    if (!sel) return [];
    var out = [];
    for (var i = 0; i < sel.options.length; i++) {
      var opt = sel.options[i];
      if (!opt.selected) continue;
      out.push({ id: parseInt(opt.value, 10), name: opt.textContent || '' });
    }
    return out;
  }

  function participantSummaryText(role) {
    if (role === 'assignee') {
      var assigneeSel = $('fAssignee');
      if (!assigneeSel || assigneeSel.selectedIndex < 0) return 'Не назначен';
      var assigneeOpt = assigneeSel.options[assigneeSel.selectedIndex];
      return assigneeOpt ? assigneeOpt.textContent || 'Не назначен' : 'Не назначен';
    }
    if (role === 'project') {
      var projectSel = $('fProject');
      if (!projectSel || projectSel.selectedIndex < 0) return 'Выбрать проект';
      var projectOpt = projectSel.options[projectSel.selectedIndex];
      return projectOpt ? projectOpt.textContent || 'Выбрать проект' : 'Выбрать проект';
    }
    var items = selectedParticipantItems(role);
    var base = role === 'assistants' ? 'Выбрать соисполнителей' : 'Выбрать наблюдателей';
    if (!items.length) return base;
    if (items.length <= 2) {
      return items
        .map(function (x) {
          return x.name;
        })
        .join(', ');
    }
    return items[0].name + ' +' + (items.length - 1);
  }

  function updateParticipantSummary(role) {
    var btn = participantButtonByRole(role);
    if (!btn) return;
    var text = participantSummaryText(role);
    btn.textContent = text;
    var isEmpty;
    if (role === 'assignee') {
      isEmpty = !($('fAssignee') && $('fAssignee').value);
    } else if (role === 'project') {
      isEmpty = !($('fProject') && $('fProject').value);
    } else {
      isEmpty = selectedParticipantItems(role).length === 0;
    }
    btn.classList.toggle('is-empty', isEmpty);
  }

  function updateParticipantSummaries() {
    updateParticipantSummary('assistants');
    updateParticipantSummary('observers');
    updateParticipantSummary('assignee');
    updateParticipantSummary('project');
  }

  function renderParticipantPicker() {
    var role = participantPickerState.role;
    var list = $('participantPickerList');
    var chips = $('participantPickerChips');
    if (!role || !list) return;
    var q = (participantPickerState.query || '').trim().toLowerCase();
    var selected = participantRoleIsSingle(role)
      ? (function () {
          var selSingle = participantSelectByRole(role);
          if (!selSingle || !selSingle.value) return [];
          var name = '';
          for (var si = 0; si < selSingle.options.length; si++) {
            if (selSingle.options[si].value === selSingle.value) {
              name = selSingle.options[si].textContent || '';
              break;
            }
          }
          return [{ id: parseInt(selSingle.value, 10), name: name }];
        })()
      : selectedParticipantItems(role);
    var selectedSet = {};
    selected.forEach(function (x) {
      selectedSet[String(x.id)] = true;
    });
    if (chips) {
      chips.innerHTML = selected
        .slice(0, 3)
        .map(function (x) {
          return '<span class="participant-picker-chip">' + escapeHtml(x.name || '') + '</span>';
        })
        .join('');
      if (selected.length > 3) {
        chips.innerHTML += '<span class="participant-picker-chip">+' + (selected.length - 3) + '</span>';
      }
    }
    var sourceList = role === 'project' ? projects : users;
    if (role === 'assignee') {
      sourceList = [{ id: '', name: 'Не назначен' }].concat(sourceList);
    }
    var filtered = sourceList.filter(function (u) {
      if (!q) return true;
      var sourceName = role === 'project' ? projectOptionLabel(u) : String(u.name || '');
      return sourceName.toLowerCase().indexOf(q) >= 0;
    });
    if (!filtered.length) {
      list.innerHTML = '<div class="participant-picker-empty">Ничего не найдено</div>';
      return;
    }
    list.innerHTML = filtered
      .map(function (u) {
        var valueRaw = u && Object.prototype.hasOwnProperty.call(u, 'id') ? String(u.id) : '';
        var checked = !!selectedSet[valueRaw];
        var displayName = role === 'project' ? projectOptionLabel(u) : String(u.name || '');
        var name = escapeHtml(displayName);
        var avatar = escapeHtml((displayName || '?').trim().slice(0, 1).toUpperCase());
        return (
          '<button type="button" class="participant-picker-item' +
          (checked ? ' is-selected' : '') +
          '" data-value="' +
          escapeHtml(valueRaw) +
          '"><span class="participant-picker-item-main"><span class="participant-picker-avatar">' +
          avatar +
          '</span><span class="participant-picker-name">' +
          name +
          '</span></span><span class="participant-picker-check">✓</span></button>'
        );
      })
      .join('');
    list.querySelectorAll('.participant-picker-item').forEach(function (item) {
      item.addEventListener('click', function () {
        var sel = participantSelectByRole(role);
        if (!sel) return;
        if (participantRoleIsSingle(role)) {
          var pickedValue = item.getAttribute('data-value');
          if (pickedValue == null) return;
          sel.value = String(pickedValue);
          updateParticipantSummary(role);
          closeParticipantPicker();
          return;
        }
        var id = parseInt(item.getAttribute('data-value'), 10);
        if (!id) return;
        for (var i = 0; i < sel.options.length; i++) {
          var opt = sel.options[i];
          if (parseInt(opt.value, 10) !== id) continue;
          opt.selected = !opt.selected;
          break;
        }
        updateParticipantSummary(role);
        renderParticipantPicker();
      });
    });
  }

  function openParticipantPicker(role) {
    var pb = participantButtonByRole(role);
    var ps = participantSelectByRole(role);
    if ((pb && pb.disabled) || (ps && ps.disabled)) return;
    var pop = $('participantPicker');
    var title = $('participantPickerTitle');
    var search = $('participantPickerSearch');
    if (!pop || !title || !search) return;
    participantPickerState.role = role;
    participantPickerState.query = '';
    title.textContent = participantTitleByRole(role);
    pop.hidden = false;
    pop.classList.add('is-open');
    var btnA = participantButtonByRole('assistants');
    var btnO = participantButtonByRole('observers');
    var btnAssignee = participantButtonByRole('assignee');
    var btnProject = participantButtonByRole('project');
    if (btnA) btnA.setAttribute('aria-expanded', role === 'assistants' ? 'true' : 'false');
    if (btnO) btnO.setAttribute('aria-expanded', role === 'observers' ? 'true' : 'false');
    if (btnAssignee) btnAssignee.setAttribute('aria-expanded', role === 'assignee' ? 'true' : 'false');
    if (btnProject) btnProject.setAttribute('aria-expanded', role === 'project' ? 'true' : 'false');
    var chips = $('participantPickerChips');
    if (chips) chips.style.display = participantRoleIsSingle(role) ? 'none' : '';
    var invite = $('participantPickerInvite');
    if (invite) invite.hidden = participantRoleIsSingle(role);
    var done = $('participantPickerDone');
    if (done) done.textContent = participantRoleIsSingle(role) ? 'Закрыть' : 'Готово';
    search.value = '';
    renderParticipantPicker();
    positionParticipantPicker(role);
    search.focus();
  }

  function closeParticipantPicker() {
    var pop = $('participantPicker');
    var btnA = participantButtonByRole('assistants');
    var btnO = participantButtonByRole('observers');
    var btnAssignee = participantButtonByRole('assignee');
    var btnProject = participantButtonByRole('project');
    if (pop) {
      pop.hidden = true;
      pop.classList.remove('is-open');
    }
    if (btnA) btnA.setAttribute('aria-expanded', 'false');
    if (btnO) btnO.setAttribute('aria-expanded', 'false');
    if (btnAssignee) btnAssignee.setAttribute('aria-expanded', 'false');
    if (btnProject) btnProject.setAttribute('aria-expanded', 'false');
    var chips = $('participantPickerChips');
    if (chips) chips.style.display = '';
    var invite = $('participantPickerInvite');
    if (invite) invite.hidden = false;
    var done = $('participantPickerDone');
    if (done) done.textContent = 'Готово';
    pop.style.left = '';
    pop.style.top = '';
    pop.style.width = '';
    pop.style.transform = '';
    participantPickerState.role = '';
    participantPickerState.query = '';
  }

  function parseDueMs(val) {
    if (!val) return NaN;
    var s = String(val).trim().replace(' ', 'T');
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) s += 'T23:59:59';
    var d = Date.parse(s);
    return isNaN(d) ? NaN : d;
  }

  function taskIsOverdue(t) {
    if (!t || !t.due_date) return false;
    if (t.status === 'done' || t.status === 'cancelled') return false;
    var ms = parseDueMs(t.due_date);
    return !isNaN(ms) && ms < Date.now();
  }

  function formatDueRu(val) {
    if (!val) return '';
    var s = String(val).replace('T', ' ');
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?/);
    if (!m) return escapeHtml(String(val));
    var months = [
      'янв',
      'фев',
      'мар',
      'апр',
      'мая',
      'июн',
      'июл',
      'авг',
      'сен',
      'окт',
      'ноя',
      'дек',
    ];
    var mi = parseInt(m[2], 10) - 1;
    var day = parseInt(m[3], 10);
    if (isNaN(day) || mi < 0 || mi > 11) return escapeHtml(String(val));
    var out = day + ' ' + months[mi];
    if (m[4]) out += ', ' + m[4] + ':' + m[5];
    return out;
  }

  function dueToDatetimeLocal(dbVal) {
    if (!dbVal) return '';
    var s = String(dbVal).replace('T', ' ');
    var m = s.match(/^(\d{4}-\d{2}-\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?/);
    if (!m) return '';
    if (m[2]) return m[1] + 'T' + m[2] + ':' + m[3];
    return m[1] + 'T12:00';
  }

  function dueFromDatetimeLocal(inputVal) {
    if (!inputVal) return null;
    var p = inputVal.split('T');
    if (p.length !== 2) return inputVal;
    var tm = p[1].split(':');
    var sec = tm.length >= 3 ? tm[2].slice(0, 2) : '00';
    return p[0] + ' ' + tm[0] + ':' + tm[1] + ':' + sec;
  }

  function updateTaskListTotal() {
    var el = $('taskListTotal');
    if (el) el.textContent = String(tasksPagination.total || tasksCache.length);
    var el2 = $('taskListShowCount');
    if (el2) el2.textContent = String(tasksCache.length);
  }

  function setupTaskCardCommonDnD(root) {
    root.querySelectorAll('.task-card').forEach(function (btn) {
      btn.setAttribute('draggable', 'true');
      btn.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/task-id', btn.getAttribute('data-id'));
        e.dataTransfer.effectAllowed = 'move';
        btn.classList.add('task-card-dragging');
      });
      btn.addEventListener('dragend', function () {
        btn.classList.remove('task-card-dragging');
        suppressCardClick = true;
        setTimeout(function () {
          suppressCardClick = false;
        }, 80);
      });
      btn.addEventListener('click', function () {
        if (suppressCardClick) return;
        openDrawer(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
    root.querySelectorAll('.task-quick-btn').forEach(function (btn) {
      function performQuick(ev) {
        ev.preventDefault();
        ev.stopPropagation();
        var tid = parseInt(btn.getAttribute('data-task-id'), 10);
        if (!tid) return;
        if (btn.getAttribute('data-quick') === 'status') {
          api('/api/task.php?id=' + tid, { method: 'PATCH', body: { status: 'in_progress' } })
            .then(loadTasks)
            .catch(function (e) {
              notify('Не удалось обновить статус: ' + (e.message || ''), 'error');
            });
          return;
        }
        if (btn.getAttribute('data-quick') === 'assign_me') {
          var uid = currentUserId();
          if (!uid) return;
          api('/api/task.php?id=' + tid, { method: 'PATCH', body: { assignee_id: uid } })
            .then(loadTasks)
            .catch(function (e) {
              notify('Не удалось назначить себя: ' + (e.message || ''), 'error');
            });
        }
      }
      btn.addEventListener('click', performQuick);
      btn.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        performQuick(ev);
      });
    });
  }

  function setupTaskDropLists(root, listSelector, cardSelector, attrName, onDrop) {
    root.querySelectorAll(listSelector).forEach(function (list) {
      list.addEventListener('dragover', function (e) {
        e.preventDefault();
        list.classList.add('kanban-list-hover');
      });
      list.addEventListener('dragleave', function () {
        list.classList.remove('kanban-list-hover');
      });
      list.addEventListener('drop', function (e) {
        e.preventDefault();
        list.classList.remove('kanban-list-hover');
        var tid = parseInt(e.dataTransfer.getData('text/task-id'), 10);
        var col = list.closest(cardSelector);
        var value = col ? col.getAttribute(attrName) : '';
        if (!tid || !value) return;
        onDrop(tid, value);
      });
    });
  }

  function setupKanbanDnD(root) {
    setupTaskDropLists(root, '.kanban-list', '.kanban-col', 'data-status', function (tid, newStatus) {
      api('/api/task.php?id=' + tid, { method: 'PATCH', body: { status: newStatus } })
        .then(loadTasks)
        .catch(function (err) {
          notify('Не удалось сменить статус: ' + (err.message || ''), 'error');
        });
    });
    setupTaskCardCommonDnD(root);
  }

  function renderKanban() {
    var root = $('kanban');
    if (!root) return;
    root.innerHTML = STATUS_ORDER.map(function (st) {
      var list = tasksCache.filter(function (t) {
        return t.status === st;
      });
      return (
        '<div class="kanban-col" data-status="' +
        st +
        '"><div class="kanban-col-head"><span>' +
        STATUS_LABEL[st] +
        '</span><span class="kanban-count">' +
        list.length +
        '</span></div><div class="kanban-list">' +
        (list.length ? '' : '<div class="task-list-empty">Пока нет задач в этой колонке.</div>') +
        list
          .map(function (t) {
            return (
              '<button type="button" class="task-card' +
              (taskIsOverdue(t) ? ' task-card--overdue' : '') +
              (isQuickTaskItem(t) ? ' task-card--quick' : '') +
              '" data-id="' +
              t.id +
              '">' +
              taskCardBodyHtml(t, assigneeRowHtml(t)) +
              '</button>'
            );
          })
          .join('') +
        '</div></div>'
      );
    }).join('');

    setupKanbanDnD(root);
  }

  function pad2(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function fmtSqlLocalDateTime(d) {
    return (
      d.getFullYear() +
      '-' +
      pad2(d.getMonth() + 1) +
      '-' +
      pad2(d.getDate()) +
      ' ' +
      pad2(d.getHours()) +
      ':' +
      pad2(d.getMinutes()) +
      ':00'
    );
  }

  function dueLocalDayStartMs(val) {
    var ms = parseDueMs(val);
    if (isNaN(ms)) return NaN;
    var d = new Date(ms);
    d.setHours(0, 0, 0, 0);
    return d.getTime();
  }

  function todayLocalDayStartMs() {
    var n = new Date();
    n.setHours(0, 0, 0, 0);
    return n.getTime();
  }

  function mondayOfWeekFromTs(ts) {
    var d = new Date(ts);
    var day = d.getDay();
    var diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);
    d.setHours(0, 0, 0, 0);
    return d.getTime();
  }

  function resolveDeadlineBucket(t) {
    if (t.status === 'done' || t.status === 'cancelled') return 'done';
    if (!t.due_date) return 'no_due';
    var dueMs = parseDueMs(t.due_date);
    if (isNaN(dueMs)) return 'no_due';
    if (dueMs < Date.now()) return 'overdue';
    var dueDay = dueLocalDayStartMs(t.due_date);
    var today = todayLocalDayStartMs();
    if (dueDay === today) return 'today';
    var mon0 = mondayOfWeekFromTs(today);
    var sun0 = mon0 + 6 * 86400000;
    if (dueDay >= mon0 && dueDay <= sun0 && dueDay !== today) return 'this_week';
    var mon1 = mon0 + 7 * 86400000;
    var sun1 = mon1 + 6 * 86400000;
    if (dueDay >= mon1 && dueDay <= sun1) return 'next_week';
    if (dueDay > sun1) return 'far';
    return 'this_week';
  }

  function suggestedDueThisWeek() {
    var now = Date.now();
    var monTs = mondayOfWeekFromTs(now);
    var fri = new Date(monTs);
    fri.setDate(fri.getDate() + 4);
    fri.setHours(18, 0, 0, 0);
    if (fri.getTime() > now) return fri;
    var tmr = new Date();
    tmr.setDate(tmr.getDate() + 1);
    tmr.setHours(18, 0, 0, 0);
    return tmr;
  }

  function subtaskProgressHtml(t) {
    return t.subtasks_done != null && t.subtasks_total
      ? '<div class="task-sub">' + t.subtasks_done + '/' + t.subtasks_total + ' чек-листа</div>'
      : '';
  }

  function taskDeptLineHtml(t) {
    return t.department_name ? '<div class="task-dept">' + escapeHtml(t.department_name) + '</div>' : '';
  }

  function taskTagLineHtml(t) {
    return t.tag_line ? '<div class="task-tags">' + escapeHtml(t.tag_line) + '</div>' : '';
  }

  function taskDueLineHtml(t) {
    return t.due_date
      ? '<div class="task-due-row"><span class="task-due-pill' +
          (taskIsOverdue(t) ? ' task-due-pill--overdue' : '') +
          '">' +
          formatDueRu(t.due_date) +
          '</span></div>'
      : '';
  }

  function taskProjectLineHtml(t) {
    return t.project_name
      ? '<div class="task-project-line"><svg class="task-folder-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path fill="currentColor" d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg><span>' +
          escapeHtml(t.project_name) +
          '</span></div>'
      : '';
  }

  function taskVisibilityRowHtml(t) {
    var pills = visibilityPillsHtml(t);
    return pills ? '<div class="task-visibility-row">' + pills + '</div>' : '';
  }

  function taskPriorityMetaHtml(t) {
    var p = String((t && t.priority) || 'normal');
    var important = p === 'high' || p === 'critical';
    if (!important) return '<div class="task-meta"></div>';
    var hint = 'Важная задача';
    var iconUrl = apiUrl('/assets/icons/flame-important.svg');
    return (
      '<div class="task-meta">' +
      '<span class="task-priority-icon ' +
      'is-important' +
      '" title="' +
      escapeHtml(hint) +
      '" aria-label="' +
      escapeHtml(hint) +
      '">' +
      '<img src="' +
      escapeHtml(iconUrl) +
      '" alt="" aria-hidden="true"></span>' +
      '</div>'
    );
  }

  function priorityIsImportantValue(v) {
    return v === 'high' || v === 'critical';
  }

  function setPriorityUi(value) {
    var p = value || 'normal';
    var f = $('fPriority');
    if (f) f.value = p;
    var btn = $('btnPriorityToggle');
    if (!btn) return;
    var important = priorityIsImportantValue(p);
    btn.classList.toggle('is-important', important);
    btn.classList.toggle('is-normal', !important);
    var icon = $('priorityToggleIcon');
    if (icon) {
      icon.src = apiUrl(important ? '/assets/icons/flame-important.svg' : '/assets/icons/flame-gray.svg');
    }
    var txt = important ? 'Приоритет: важная' : 'Приоритет: обычная';
    btn.setAttribute('aria-label', txt);
    btn.setAttribute('title', txt);
  }

  function togglePriorityUi() {
    var f = $('fPriority');
    if (!f) return;
    var next = priorityIsImportantValue(String(f.value || 'normal')) ? 'normal' : 'high';
    setPriorityUi(next);
  }

  function assigneeRowHtml(t) {
    return t.assignee_name
      ? '<div class="task-person-row"><span class="task-person-avatar" aria-hidden="true"></span><span class="task-person-name">' +
          escapeHtml(t.assignee_name) +
          '</span></div>'
      : '';
  }

  function planUsersRowHtml(t) {
    return (
      '<div class="task-plan-users" aria-hidden="true">' +
      '<span class="task-pu-dot" title="' +
      escapeHtml(t.author_name || '') +
      '"></span><span class="task-pu-arr">→</span><span class="task-pu-dot" title="' +
      escapeHtml(t.assignee_name || '') +
      '"></span></div>'
    );
  }

  function taskQuickActionsHtml(t) {
    return (
      '<div class="task-quick-row">' +
      '<span class="task-quick-btn" role="button" tabindex="0" data-quick="status" data-task-id="' +
      t.id +
      '">В работу</span>' +
      '</div>'
    );
  }

  function isQuickTaskItem(t) {
    return t && (t.is_quick_task == 1 || t.is_quick_task === '1' || t.is_quick_task === true);
  }

  function taskQuickBadgeHtml(t) {
    if (!isQuickTaskItem(t)) return '';
    return '<div class="task-quick-badge-row"><span class="task-quick-badge" title="Быстрая задача">Быстрая</span></div>';
  }

  function taskCardBodyHtml(t, peopleRowHtml) {
    return (
      taskQuickBadgeHtml(t) +
      taskDeptLineHtml(t) +
      '<div class="task-title">' +
      escapeHtml(t.title) +
      '</div>' +
      taskDueLineHtml(t) +
      taskProjectLineHtml(t) +
      (peopleRowHtml || '') +
      taskVisibilityRowHtml(t) +
      taskQuickActionsHtml(t) +
      taskPriorityMetaHtml(t) +
      taskTagLineHtml(t) +
      subtaskProgressHtml(t)
    );
  }

  function patchBodyForDeadlineLane(lane, task) {
    var body = {};
    if (lane === 'done') {
      body.status = 'done';
      return body;
    }
    if (task && (task.status === 'done' || task.status === 'cancelled')) {
      body.status = 'in_progress';
    }
    if (lane === 'no_due') {
      body.due_date = null;
      return body;
    }
    if (lane === 'overdue') {
      var y = new Date();
      y.setDate(y.getDate() - 1);
      y.setHours(23, 59, 0, 0);
      body.due_date = fmtSqlLocalDateTime(y);
      return body;
    }
    if (lane === 'today') {
      var td = new Date();
      td.setHours(18, 0, 0, 0);
      body.due_date = fmtSqlLocalDateTime(td);
      return body;
    }
    if (lane === 'this_week') {
      body.due_date = fmtSqlLocalDateTime(suggestedDueThisWeek());
      return body;
    }
    if (lane === 'next_week') {
      var mon0 = mondayOfWeekFromTs(Date.now());
      var sun0 = mon0 + 6 * 86400000;
      var nextMonTs = sun0 + 86400000;
      var nw = new Date(nextMonTs);
      nw.setHours(12, 0, 0, 0);
      body.due_date = fmtSqlLocalDateTime(nw);
      return body;
    }
    if (lane === 'far') {
      var far = new Date();
      far.setDate(far.getDate() + 21);
      far.setHours(12, 0, 0, 0);
      body.due_date = fmtSqlLocalDateTime(far);
      return body;
    }
    return body;
  }

  function deadlineSlaBadgeHtml(t) {
    if (!t.due_date || t.status === 'done' || t.status === 'cancelled') return '';
    var raw = String(t.due_date).replace(' ', 'T');
    var d = new Date(raw);
    if (isNaN(d.getTime())) return '';
    var diffMs = d.getTime() - Date.now();
    var hours = Math.round(diffMs / 3600000);
    if (hours < -24) {
      var days = Math.floor(-diffMs / 86400000);
      return '<span class="task-sla-badge task-sla-badge--overdue" title="SLA: просрочка">+' + days + 'д</span>';
    }
    if (hours < 0) {
      return '<span class="task-sla-badge task-sla-badge--overdue" title="SLA">' + hours + 'ч</span>';
    }
    if (hours < 24) {
      return '<span class="task-sla-badge task-sla-badge--hot" title="До дедлайна">' + hours + 'ч</span>';
    }
    var d2 = Math.ceil(diffMs / 86400000);
    return '<span class="task-sla-badge" title="До дедлайна">' + d2 + 'д</span>';
  }

  function deadlineTaskCardHtml(t) {
    return (
      '<button type="button" class="task-card task-card--deadline' +
      (taskIsOverdue(t) ? ' task-card--overdue' : '') +
      (isQuickTaskItem(t) ? ' task-card--quick' : '') +
      '" data-id="' +
      t.id +
      '">' +
      taskCardBodyHtml(t, planUsersRowHtml(t)) +
      '<div class="task-deadline-sla">' +
      deadlineSlaBadgeHtml(t) +
      '</div></button>'
    );
  }

  function setupDeadlinesDnD(root) {
    setupTaskDropLists(root, '.deadlines-col-list', '.deadlines-col', 'data-deadline-lane', function (tid, lane) {
      var task = null;
      for (var i = 0; i < tasksCache.length; i++) {
        if (tasksCache[i].id === tid) {
          task = tasksCache[i];
          break;
        }
      }
      var body = patchBodyForDeadlineLane(lane, task);
      api('/api/task.php?id=' + tid, { method: 'PATCH', body: body })
        .then(loadTasks)
        .catch(function (err) {
          notify('Не удалось обновить задачу: ' + (err.message || ''), 'error');
        });
    });
    setupTaskCardCommonDnD(root);
  }

  function renderDeadlinesBoard() {
    var root = $('taskDeadlinesWrap');
    if (!root) return;
    var lanes = ['overdue', 'today', 'this_week', 'next_week', 'no_due', 'far', 'done'];
    var laneHeadClass = {
      overdue: 'deadlines-col-head--overdue',
      today: 'deadlines-col-head--today',
      this_week: 'deadlines-col-head--this_week',
      next_week: 'deadlines-col-head--next_week',
      no_due: 'deadlines-col-head--no_due',
      far: 'deadlines-col-head--far',
      done: 'deadlines-col-head--done',
    };
    var laneTitle = {
      overdue: 'Просрочены',
      today: 'На сегодня',
      this_week: 'На этой неделе',
      next_week: 'На следующей неделе',
      no_due: 'Без срока',
      far: 'Больше двух недель',
      done: 'Завершены',
    };
    var urgent = tasksCache.filter(function (t) {
      if (t.status === 'done' || t.status === 'cancelled') return false;
      var b = resolveDeadlineBucket(t);
      return b === 'overdue' || b === 'today';
    });
    var strip =
      urgent.length > 0
        ? '<div class="deadlines-attention-bar" role="status"><strong>Требуют внимания сегодня</strong> — ' +
          urgent.length +
          ' ' +
          (urgent.length === 1 ? 'задача' : urgent.length < 5 ? 'задачи' : 'задач') +
          ' <span class="muted">(просрочено и с дедлайном на сегодня)</span></div>'
        : '';
    root.innerHTML =
      strip +
      '<div class="deadlines-kanban">' +
      lanes
        .map(function (lane) {
          var list = tasksCache.filter(function (t) {
            return resolveDeadlineBucket(t) === lane;
          });
          return (
            '<div class="deadlines-col" data-deadline-lane="' +
            lane +
            '"><div class="deadlines-col-head ' +
            laneHeadClass[lane] +
            '"><span class="deadlines-col-title">' +
            laneTitle[lane] +
            '</span><span class="deadlines-col-count">' +
            list.length +
            '</span></div><button type="button" class="deadlines-col-add" data-deadline-lane="' +
            lane +
            '" aria-label="Добавить в колонку">+</button><div class="kanban-list deadlines-col-list">' +
            (list.length ? '' : '<div class="task-list-empty">Нет задач в этой группе.</div>') +
            list.map(deadlineTaskCardHtml).join('') +
            '</div></div>'
          );
        })
        .join('') +
      '</div>';

    setupDeadlinesDnD(root);
    root.querySelectorAll('.deadlines-col-add').forEach(function (btn) {
      btn.addEventListener('click', function () {
        createTaskInDeadlineLane(btn.getAttribute('data-deadline-lane'));
      });
    });
  }

  function createTaskInDeadlineLane(lane) {
    var lanesOk =
      lane === 'overdue' ||
      lane === 'today' ||
      lane === 'this_week' ||
      lane === 'next_week' ||
      lane === 'no_due' ||
      lane === 'far' ||
      lane === 'done';
    if (!lanesOk) return;
    openCreateTaskModal({ deadlineLane: lane });
  }

  var MONTH_NAMES_CAL = [
    'Январь',
    'Февраль',
    'Март',
    'Апрель',
    'Май',
    'Июнь',
    'Июль',
    'Август',
    'Сентябрь',
    'Октябрь',
    'Ноябрь',
    'Декабрь',
  ];

  function calendarCellDateParts(val) {
    var ms = parseDueMs(val);
    if (isNaN(ms)) return null;
    var d = new Date(ms);
    return { y: d.getFullYear(), m: d.getMonth(), day: d.getDate() };
  }

  function tasksForCalendarDay(y, m, dayNum) {
    return tasksCache.filter(function (t) {
      if (!t.due_date) return false;
      if (t.status === 'done' || t.status === 'cancelled') return false;
      var p = calendarCellDateParts(t.due_date);
      return p && p.y === y && p.m === m && p.day === dayNum;
    });
  }

  function renderCalendar() {
    var grid = $('taskCalGrid');
    var title = $('taskCalMonthTitle');
    if (!grid) return;
    if (title) title.textContent = MONTH_NAMES_CAL[calendarMonth] + ' ' + calendarYear;
    var dim = new Date(calendarYear, calendarMonth + 1, 0).getDate();
    var first = new Date(calendarYear, calendarMonth, 1);
    var startOffset = (first.getDay() + 6) % 7;
    var totalCells = Math.ceil((startOffset + dim) / 7) * 7;
    var html = '';
    for (var i = 0; i < totalCells; i++) {
      if (i < startOffset || i >= startOffset + dim) {
        html += '<div class="task-cal-cell task-cal-cell--muted"></div>';
        continue;
      }
      var dayNum = i - startOffset + 1;
      var dayTasks = tasksForCalendarDay(calendarYear, calendarMonth, dayNum);
      var chips = dayTasks
        .map(function (t) {
          return (
            '<button type="button" class="task-cal-chip' +
            (taskIsOverdue(t) ? ' task-cal-chip--overdue' : '') +
            (isQuickTaskItem(t) ? ' task-cal-chip--quick' : '') +
            '" data-id="' +
            t.id +
            '">' +
            escapeHtml(t.title || '') +
            '</button>'
          );
        })
        .join('');
      html +=
        '<div class="task-cal-cell"><div class="task-cal-daynum">' +
        dayNum +
        '</div><div class="task-cal-chips">' +
        chips +
        '</div></div>';
    }
    grid.innerHTML = html;
    grid.querySelectorAll('.task-cal-chip').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openDrawer(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
  }

  function shiftCalendarMonth(delta) {
    calendarMonth += delta;
    if (calendarMonth > 11) {
      calendarMonth = 0;
      calendarYear++;
    }
    if (calendarMonth < 0) {
      calendarMonth = 11;
      calendarYear--;
    }
    renderCalendar();
  }

  function ganttDayStartMs(ms) {
    var d = new Date(ms);
    d.setHours(0, 0, 0, 0);
    return d.getTime();
  }

  function ganttParseMs(val) {
    if (!val) return NaN;
    var s = String(val).trim().replace(' ', 'T');
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) s += 'T12:00:00';
    var x = Date.parse(s);
    return isNaN(x) ? NaN : x;
  }

  function ganttTaskBarRange(t) {
    var createdRaw = ganttParseMs(t.created_at);
    if (isNaN(createdRaw)) return null;
    var startMs = ganttDayStartMs(createdRaw);
    var dueRaw = ganttParseMs(t.due_date);
    var endMs;
    if (!isNaN(dueRaw)) {
      endMs = ganttDayStartMs(dueRaw);
      if (endMs < startMs) endMs = startMs + 86400000;
    } else {
      if (t.status === 'done' || t.status === 'cancelled') {
        var upd = ganttParseMs(t.updated_at);
        endMs = !isNaN(upd) ? ganttDayStartMs(upd) : startMs + 86400000;
      } else {
        endMs = ganttDayStartMs(Date.now());
      }
      if (endMs < startMs) endMs = startMs + 86400000;
    }
    if (endMs <= startMs) endMs = startMs + 86400000;
    return { startMs: startMs, endMs: endMs };
  }

  function ganttBarClass(st) {
    if (st === 'done') return 'task-gantt-bar--done';
    if (st === 'cancelled') return 'task-gantt-bar--cancelled';
    if (st === 'review') return 'task-gantt-bar--review';
    if (st === 'in_progress') return 'task-gantt-bar--in_progress';
    return 'task-gantt-bar--new';
  }

  function ganttRectRelTo(child, container) {
    var cr = container.getBoundingClientRect();
    var br = child.getBoundingClientRect();
    return {
      left: br.left - cr.left + container.scrollLeft,
      top: br.top - cr.top + container.scrollTop,
      right: br.right - cr.left + container.scrollLeft,
      bottom: br.bottom - cr.top + container.scrollTop,
      width: br.width,
      height: br.height,
    };
  }

  function paintGanttDependencyLines(bodyEl, svgEl, idSet) {
    if (!bodyEl || !svgEl || !idSet) return;
    var w = bodyEl.scrollWidth;
    var h = bodyEl.scrollHeight;
    svgEl.setAttribute('width', String(w));
    svgEl.setAttribute('height', String(h));
    svgEl.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
    while (svgEl.firstChild) svgEl.removeChild(svgEl.firstChild);
    var rows = bodyEl.querySelectorAll('.task-gantt-row[data-task-id]');
    var barById = {};
    for (var i = 0; i < rows.length; i++) {
      var rid = parseInt(rows[i].getAttribute('data-task-id'), 10);
      var bar = rows[i].querySelector('.task-gantt-bar');
      if (!bar || isNaN(rid)) continue;
      barById[rid] = bar;
    }
    for (var k = 0; k < rows.length; k++) {
      var row = rows[k];
      var sid = parseInt(row.getAttribute('data-task-id'), 10);
      if (isNaN(sid)) continue;
      var succBar = barById[sid];
      if (!succBar) continue;
      var preds = row.getAttribute('data-gantt-preds');
      if (!preds) continue;
      var parts = preds.split(',').map(function (x) {
        return parseInt(x, 10);
      });
      var succR = ganttRectRelTo(succBar, bodyEl);
      var x2 = succR.left;
      var y2 = succR.top + succR.height / 2;
      for (var j = 0; j < parts.length; j++) {
        var pid = parts[j];
        if (!idSet[pid] || !barById[pid]) continue;
        var predBar = barById[pid];
        var predR = ganttRectRelTo(predBar, bodyEl);
        var x1 = predR.right;
        var y1 = predR.top + predR.height / 2;
        var dx = Math.max(24, Math.abs(x2 - x1) * 0.35);
        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute(
          'd',
          'M ' +
            x1 +
            ' ' +
            y1 +
            ' C ' +
            (x1 + dx) +
            ' ' +
            y1 +
            ', ' +
            (x2 - dx) +
            ' ' +
            y2 +
            ', ' +
            x2 +
            ' ' +
            y2
        );
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', '#64748b');
        path.setAttribute('stroke-width', '1.5');
        path.setAttribute('opacity', '0.72');
        svgEl.appendChild(path);
      }
    }
  }

  function renderGantt() {
    var host = $('taskGanttWrap');
    var inner = $('taskGanttInner');
    var lead = $('taskGanttLead');
    if (!host || !inner) return;
    var meta = [];
    tasksCache.forEach(function (t) {
      var r = ganttTaskBarRange(t);
      if (!r) return;
      meta.push({ t: t, r: r });
    });
    if (lead) {
      lead.textContent =
        'Полоса: от даты создания до срока. Если срока нет — до сегодня (активные) или до даты закрытия. Строки сгруппированы по проекту; линии — связи «блокирует» (предшественник → задача). Показано ' +
        meta.length +
        ' задач из ' +
        tasksCache.length +
        ' загруженных (' +
        tasksPagination.total +
        ' по фильтру). При необходимости нажмите «Загрузить ещё задачи».';
    }
    if (meta.length === 0) {
      inner.innerHTML =
        '<div class="task-gantt-empty">Нет задач с датой создания для диаграммы. Измените фильтры или подгрузите список.</div>';
      return;
    }
    var byProject = {};
    meta.forEach(function (item) {
      var key = item.t.project_name ? String(item.t.project_name) : '';
      var title = item.t.project_name || 'Без проекта';
      if (!byProject[key]) byProject[key] = { title: title, items: [] };
      byProject[key].items.push(item);
    });
    var groupKeys = Object.keys(byProject);
    groupKeys.sort(function (a, b) {
      return byProject[a].title.localeCompare(byProject[b].title, 'ru');
    });
    groupKeys.forEach(function (k) {
      byProject[k].items.sort(function (a, b) {
        return a.r.startMs - b.r.startMs || (a.t.id || 0) - (b.t.id || 0);
      });
    });
    var idSet = {};
    meta.forEach(function (m) {
      idSet[m.t.id] = true;
    });
    var minMs = meta[0].r.startMs;
    var maxMs = meta[0].r.endMs;
    for (var i = 1; i < meta.length; i++) {
      minMs = Math.min(minMs, meta[i].r.startMs);
      maxMs = Math.max(maxMs, meta[i].r.endMs);
    }
    var padMs = 5 * 86400000;
    minMs -= padMs;
    maxMs += padMs;
    var spanMs = maxMs - minMs;
    if (spanMs < 14 * 86400000) {
      spanMs = 14 * 86400000;
      maxMs = minMs + spanMs;
    }
    var pxPerDay = 20;
    var timelineW = Math.max(640, (spanMs / 86400000) * pxPerDay);
    var tickHtml = '';
    var step = spanMs > 120 * 86400000 ? 30 * 86400000 : 7 * 86400000;
    for (var tick = minMs; tick <= maxMs; tick += step) {
      var leftPct = ((tick - minMs) / spanMs) * 100;
      var lab = new Date(tick).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
      tickHtml +=
        '<div class="task-gantt-tick" style="left:' +
        leftPct +
        '%"><span class="task-gantt-tick-label">' +
        escapeHtml(lab) +
        '</span></div>';
    }
    var rowsHtml = '';
    for (var gi = 0; gi < groupKeys.length; gi++) {
      var gk = groupKeys[gi];
      var grp = byProject[gk];
      rowsHtml +=
        '<div class="task-gantt-group">' +
        '<div class="task-gantt-group-head">' +
        '<span class="task-gantt-group-head-label">' +
        escapeHtml(grp.title) +
        '</span><span class="task-gantt-group-head-rest"></span></div>';
      for (var ri = 0; ri < grp.items.length; ri++) {
        var item = grp.items[ri];
        var t = item.t;
        var r = item.r;
        var leftPct2 = ((r.startMs - minMs) / spanMs) * 100;
        var widthPct = ((r.endMs - r.startMs) / spanMs) * 100;
        widthPct = Math.max(widthPct, 0.35);
        var stLabel = LIST_STATUS_LABEL[t.status] || t.status;
        var tip = (t.title || 'Задача') + ' · ' + stLabel;
        if (t.project_name) tip += ' · ' + t.project_name;
        var preds = Array.isArray(t.gantt_predecessor_ids) ? t.gantt_predecessor_ids : [];
        var predAttr = preds.length ? ' data-gantt-preds="' + preds.join(',') + '"' : '';
        rowsHtml +=
          '<div class="task-gantt-row" data-task-id="' +
          t.id +
          '"' +
          predAttr +
          '>' +
          '<button type="button" class="task-gantt-row-title" data-id="' +
          t.id +
          '" title="' +
          escapeHtml(tip) +
          '">' +
          escapeHtml((t.title || 'Без названия').slice(0, 72) + ((t.title || '').length > 72 ? '…' : '')) +
          '</button>' +
          '<div class="task-gantt-track-wrap">' +
          '<div class="task-gantt-track" style="width:' +
          timelineW +
          'px">' +
          '<div class="task-gantt-bar ' +
          ganttBarClass(t.status) +
          '" style="left:' +
          leftPct2 +
          '%;width:' +
          widthPct +
          '%" title="' +
          escapeHtml(tip) +
          '"></div>' +
          '</div></div></div>';
      }
      rowsHtml += '</div>';
    }
    inner.innerHTML =
      '<div class="task-gantt-scale"><div class="task-gantt-scale-spacer"></div><div class="task-gantt-ticks" style="width:' +
      timelineW +
      'px">' +
      tickHtml +
      '</div></div>' +
      '<div class="task-gantt-body">' +
      '<svg class="task-gantt-deps" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"></svg>' +
      '<div class="task-gantt-groups">' +
      rowsHtml +
      '</div></div>';
    inner.querySelectorAll('.task-gantt-row-title').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openDrawer(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
    var bodyEl = inner.querySelector('.task-gantt-body');
    var svgEl = inner.querySelector('.task-gantt-deps');
    function paint() {
      paintGanttDependencyLines(bodyEl, svgEl, idSet);
    }
    requestAnimationFrame(function () {
      requestAnimationFrame(paint);
    });
  }

  function resolveMyPlanStageIdForTask(t) {
    if (!myPlanStagesFeature || !myPlanStages.length) {
      return null;
    }
    var sid = t.my_plan_stage_id != null && t.my_plan_stage_id !== '' ? parseInt(t.my_plan_stage_id, 10) : 0;
    if (sid > 0 && myPlanStages.some(function (s) { return parseInt(s.id, 10) === sid; })) {
      return sid;
    }
    return parseInt(myPlanStages[0].id, 10) || 0;
  }

  /**
   * Стадия «Мой план» из поля задачи (режим без таблиц стадий).
   */
  function resolveMyPlanLane(t) {
    var lane = t.my_plan_lane;
    if (lane === 'unplanned' || lane === 'active' || lane === 'week') return lane;
    return 'unplanned';
  }

  function taskVisibleInMyPlan(t) {
    var uid = currentUserId();
    if (!uid) return false;
    if (t.status === 'done' || t.status === 'cancelled') return false;
    var fr = $('filterRole');
    if (fr && fr.value) return true;
    return String(t.assignee_id) === String(uid) || String(t.author_id) === String(uid);
  }

  function planTaskCardHtml(t) {
    return (
      '<button type="button" class="task-card task-card--plan' +
      (taskIsOverdue(t) ? ' task-card--overdue' : '') +
      (isQuickTaskItem(t) ? ' task-card--quick' : '') +
      '" data-id="' +
      t.id +
      '">' +
      taskCardBodyHtml(t, planUsersRowHtml(t)) +
      '</button>'
    );
  }

  function setupPlanDnD(root) {
    if (myPlanStagesFeature && myPlanStages.length) {
      setupTaskDropLists(root, '.plan-col-list', '.plan-col', 'data-plan-stage-id', function (tid, newStageId) {
        var n = parseInt(newStageId, 10);
        if (!(n > 0)) return Promise.resolve();
        return api('/api/task.php?id=' + tid, { method: 'PATCH', body: { my_plan_stage_id: n } })
          .then(loadTasks)
          .catch(function (err) {
            notify('Не удалось переместить: ' + (err.message || ''), 'error');
          });
      });
    } else {
      setupTaskDropLists(root, '.plan-col-list', '.plan-col', 'data-plan-lane', function (tid, newLane) {
        return api('/api/task.php?id=' + tid, { method: 'PATCH', body: { my_plan_lane: newLane } })
          .then(loadTasks)
          .catch(function (err) {
            notify('Не удалось переместить (выполните sql/migration_my_plan.sql при ошибке): ' + (err.message || ''), 'error');
          });
      });
    }
    setupTaskCardCommonDnD(root);
  }

  function getThisWeekMondayLocal() {
    var now = new Date();
    var dow = now.getDay();
    var deltaMon = dow === 0 ? -6 : 1 - dow;
    return new Date(now.getFullYear(), now.getMonth(), now.getDate() + deltaMon);
  }

  /** Понедельник–воскресенье для заголовка колонки недели (локальное время). */
  function formatMondaySundayRangeRu(weekMonday) {
    var mon = weekMonday;
    var sun = new Date(mon.getFullYear(), mon.getMonth(), mon.getDate() + 6);
    function monthLong(d) {
      var s = d.toLocaleDateString('ru-RU', { month: 'long' });
      return String(s || '')
        .replace(/\s*г\.?\s*$/i, '')
        .replace(/\./g, '')
        .trim();
    }
    if (mon.getMonth() === sun.getMonth() && mon.getFullYear() === sun.getFullYear()) {
      return mon.getDate() + '–' + sun.getDate() + ' ' + monthLong(mon);
    }
    var o = { day: 'numeric', month: 'short' };
    return (
      mon
        .toLocaleDateString('ru-RU', o)
        .replace(/\./g, '')
        .trim() +
      ' — ' +
      sun
        .toLocaleDateString('ru-RU', o)
        .replace(/\./g, '')
        .trim()
    );
  }

  function formatMyPlanCalendarWeekRange() {
    return formatMondaySundayRangeRu(getThisWeekMondayLocal());
  }

  function formatMyPlanNextCalendarWeekRange() {
    var m = getThisWeekMondayLocal();
    var nextMon = new Date(m.getFullYear(), m.getMonth(), m.getDate() + 7);
    return formatMondaySundayRangeRu(nextMon);
  }

  function setupMyPlanColumnHeadEditors(planRoot) {
    if (!myPlanStagesFeature || !myPlanStages.length || !planRoot) return;
    planRoot.querySelectorAll('.plan-col-head-name').forEach(function (inp) {
      var sid = parseInt(inp.getAttribute('data-plan-stage-id'), 10);
      if (!(sid > 0)) return;
      inp.setAttribute('data-last-name', String(inp.value || '').trim());
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          inp.blur();
        }
      });
      inp.addEventListener('blur', function () {
        var committed = String(inp.getAttribute('data-last-name') || '').trim();
        var v = String(inp.value || '').trim();
        if (!v) {
          inp.value = committed;
          return;
        }
        if (v === committed) return;
        patchMyPlanStageField(sid, { name: v })
          .then(function () {
            inp.setAttribute('data-last-name', v);
          })
          .catch(function () {
            inp.value = committed;
          });
      });
    });
    planRoot.querySelectorAll('.plan-col-head-color').forEach(function (cInp) {
      var sid = parseInt(cInp.getAttribute('data-plan-stage-id'), 10);
      if (!(sid > 0)) return;
      var committed = String(cInp.getAttribute('data-last-color') || cInp.value || '#64748b');
      cInp.setAttribute('data-last-color', committed);
      cInp.addEventListener('change', function () {
        var v = String(cInp.value || '');
        var prev = String(cInp.getAttribute('data-last-color') || '#64748b');
        if (v === prev) return;
        var wrap = cInp.closest('.plan-col-head-color-wrap');
        var head = cInp.closest('.plan-col-head');
        if (wrap) wrap.style.backgroundColor = v;
        if (head) head.style.borderTopColor = v;
        patchMyPlanStageField(sid, { color: v })
          .then(function () {
            cInp.setAttribute('data-last-color', v);
          })
          .catch(function () {
            cInp.value = prev;
            if (wrap) wrap.style.backgroundColor = prev;
            if (head) head.style.borderTopColor = prev;
          });
      });
    });
  }

  function renderMyPlan() {
    var root = $('taskPlanWrap');
    if (!root) return;

    if (myPlanStagesFeature && myPlanStages.length) {
      var nCols = myPlanStages.length;
      root.innerHTML =
        '<div class="plan-kanban plan-kanban--stretch" style="--plan-cols:' +
        nCols +
        '">' +
        myPlanStages
          .map(function (stage) {
            var sid = parseInt(stage.id, 10);
            var list = tasksCache.filter(function (t) {
              return taskVisibleInMyPlan(t) && resolveMyPlanStageIdForTask(t) === sid;
            });
            var rawC = String(stage.color || '#64748b');
            if (!/^#[0-9A-Fa-f]{6}$/.test(rawC)) rawC = '#64748b';
            var col = rawC;
            var titleVal = escapeHtml(stage.name || 'Стадия');
            return (
              '<div class="plan-col" data-plan-stage-id="' +
              sid +
              '"><div class="plan-col-head plan-col-head--dynamic plan-col-head--editable" style="border-top:3px solid ' +
              col +
              '"><label class="plan-col-head-color-wrap" style="background-color:' +
              col +
              '" title="Цвет колонки"><input type="color" class="plan-col-head-color" data-plan-stage-id="' +
              sid +
              '" value="' +
              col +
              '" aria-label="Цвет колонки"/></label><input type="text" class="plan-col-head-name" data-plan-stage-id="' +
              sid +
              '" maxlength="120" spellcheck="false" value="' +
              titleVal +
              '" aria-label="Название колонки"/><span class="plan-col-count">' +
              list.length +
              '</span></div><button type="button" class="plan-col-add" data-plan-stage-id="' +
              sid +
              '" aria-label="' +
              escapeHtml('Добавить в «' + (stage.name || '') + '»') +
              '">+</button><div class="kanban-list plan-col-list">' +
              (list.length ? '' : '<div class="task-list-empty">Нет задач в этой колонке.</div>') +
              list.map(planTaskCardHtml).join('') +
              '</div></div>'
            );
          })
          .join('') +
        '</div>';
    } else {
      var TC = typeof window.__plannerTasksConstants === 'object' && window.__plannerTasksConstants ? window.__plannerTasksConstants : {};
      var lt = TC.PLAN_LANE_TITLE && typeof TC.PLAN_LANE_TITLE === 'object' ? TC.PLAN_LANE_TITLE : {};
      var lanes = ['unplanned', 'active', 'week'];
      var laneHeadClass = {
        unplanned: 'plan-col-head--blue',
        active: 'plan-col-head--lime',
        week: 'plan-col-head--cyan',
      };
      var thisWeekBase = lt.active != null ? String(lt.active) : 'Сделаю на этой неделе';
      var nextWeekBase = lt.week != null ? String(lt.week) : 'Сделаю на следующей неделе';
      var laneTitle = {
        unplanned: lt.unplanned != null ? String(lt.unplanned) : 'Не спланированы',
        active: thisWeekBase + ' · ' + formatMyPlanCalendarWeekRange(),
        week: nextWeekBase + ' · ' + formatMyPlanNextCalendarWeekRange(),
      };
      root.innerHTML =
        '<div class="plan-kanban plan-kanban--stretch" style="--plan-cols:3">' +
        lanes
          .map(function (lane) {
            var list = tasksCache.filter(function (t) {
              return taskVisibleInMyPlan(t) && resolveMyPlanLane(t) === lane;
            });
            return (
              '<div class="plan-col" data-plan-lane="' +
              lane +
              '"><div class="plan-col-head ' +
              laneHeadClass[lane] +
              '"><span class="plan-col-title">' +
              escapeHtml(laneTitle[lane]) +
              '</span><span class="plan-col-count">' +
              list.length +
              '</span></div><button type="button" class="plan-col-add" data-plan-lane="' +
              lane +
              '" aria-label="' +
              escapeHtml('Добавить в «' + laneTitle[lane] + '»') +
              '">+</button><div class="kanban-list plan-col-list">' +
              (list.length ? '' : '<div class="task-list-empty">Нет задач в этой колонке.</div>') +
              list.map(planTaskCardHtml).join('') +
              '</div></div>'
            );
          })
          .join('') +
        '</div>';
    }

    setupPlanDnD(root);
    root.querySelectorAll('.plan-col-add').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ds = btn.getAttribute('data-plan-stage-id');
        if (ds) {
          createTaskInLane(ds);
        } else {
          createTaskInLane(btn.getAttribute('data-plan-lane'));
        }
      });
    });
    setupMyPlanColumnHeadEditors(root);
  }
