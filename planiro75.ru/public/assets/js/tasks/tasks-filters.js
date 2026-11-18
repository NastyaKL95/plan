  function tasksListQuerySuffix(limit, offset) {
    var lim = limit != null ? limit : tasksPagination.limit;
    var off = offset != null ? offset : 0;
    var sep = '&';
    return 'limit=' + encodeURIComponent(String(lim)) + '&offset=' + encodeURIComponent(String(off));
  }

  function buildTasksQueryString() {
    var qs = '/api/tasks.php?';
    var pid = $('filterProject') ? $('filterProject').value : '';
    if (!pid && projectParam) pid = String(projectParam);
    if (pid) qs += 'project_id=' + encodeURIComponent(pid) + '&';
    var fr = $('filterRole');
    if (fr && fr.value) qs += 'role_scope=' + encodeURIComponent(fr.value) + '&';
    var fv = $('filterVisibility');
    if (fv && fv.value) qs += 'visibility_scope=' + encodeURIComponent(fv.value) + '&';
    var gs = $('tasksAdvSearch');
    var sq = gs && gs.value.trim() ? gs.value.trim() : qParam;
    if (sq) qs += 'q=' + encodeURIComponent(sq) + '&';
    var fa = $('filterAdvAssignee');
    if (fa && fa.value) qs += 'assignee_id=' + encodeURIComponent(fa.value) + '&';
    var participantId = resolveParticipantIdFromSearchInput();
    if (participantId) qs += 'participant_user_id=' + encodeURIComponent(participantId) + '&';
    var ft = $('filterAdvTags');
    if (ft && ft.selectedOptions && ft.selectedOptions.length) {
      var tids = [];
      for (var i = 0; i < ft.selectedOptions.length; i++) {
        var tid = parseInt(ft.selectedOptions[i].value, 10);
        if (tid > 0) tids.push(String(tid));
      }
      if (tids.length) qs += 'tag_ids=' + encodeURIComponent(tids.join(',')) + '&';
    }
    var df0 = $('filterDueFrom');
    if (df0 && df0.value) qs += 'due_from=' + encodeURIComponent(df0.value) + '&';
    var dt0 = $('filterDueTo');
    if (dt0 && dt0.value) qs += 'due_to=' + encodeURIComponent(dt0.value) + '&';
    var ctm = $('filterCommentsMode');
    if (ctm && ctm.value !== '') qs += 'has_comments=' + encodeURIComponent(ctm.value) + '&';
    var atm = $('filterAttachmentsMode');
    if (atm && atm.value !== '') qs += 'has_attachments=' + encodeURIComponent(atm.value) + '&';
    var fdep = $('filterDepartment');
    if (fdep && fdep.value) qs += 'department_id=' + encodeURIComponent(fdep.value) + '&';
    var ftk = $('filterTaskKind');
    if (ftk && ftk.value) qs += 'task_kind=' + encodeURIComponent(ftk.value) + '&';
    var archDone = $('filterIncludeArchivedDone');
    if (archDone && archDone.checked) qs += 'include_archived_done=1&';
    return appendTasksDrillFromBrowserUrl(qs);
  }

  function appendTasksDrillFromBrowserUrl(qs) {
    try {
      var sp = new URLSearchParams(window.location.search);
      var keys = [
        'status',
        'department_id',
        'report_active',
        'report_overdue',
        'report_stuck',
        'assignee_id',
        'assignee_empty',
        'task_kind',
        'include_archived_done',
      ];
      keys.forEach(function (k) {
        if (!sp.has(k)) return;
        var v = sp.get(k);
        if (v === null || v === '') return;
        var encK = encodeURIComponent(k) + '=';
        if (qs.indexOf(encK) !== -1) return;
        qs += encK + encodeURIComponent(v) + '&';
      });
    } catch (eDrill) {}
    return qs;
  }

  function syncUiFromUrlDrillParams() {
    try {
      var sp = new URLSearchParams(window.location.search);
      var dep = sp.get('department_id');
      if (dep && $('filterDepartment')) $('filterDepartment').value = dep;
      var tk = sp.get('task_kind');
      if (tk && $('filterTaskKind')) $('filterTaskKind').value = tk;
    } catch (eS) {}
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function collectCurrentFiltersObject() {
    var ft = $('filterAdvTags');
    var tagIds = [];
    if (ft && ft.selectedOptions) {
      for (var i = 0; i < ft.selectedOptions.length; i++) {
        var tid = parseInt(ft.selectedOptions[i].value, 10);
        if (tid > 0) tagIds.push(tid);
      }
    }
    var gs = $('tasksAdvSearch');
    return {
      project_id: $('filterProject') ? $('filterProject').value || '' : '',
      role_scope: $('filterRole') ? $('filterRole').value || '' : '',
      visibility_scope: $('filterVisibility') ? $('filterVisibility').value || '' : '',
      q: gs && gs.value.trim() ? gs.value.trim() : '',
      assignee_id: $('filterAdvAssignee') ? $('filterAdvAssignee').value || '' : '',
      participant_user_id: resolveParticipantIdFromSearchInput(),
      tag_ids: tagIds.filter(function (x) {
        return x > 0;
      }),
      due_from: $('filterDueFrom') ? $('filterDueFrom').value || '' : '',
      due_to: $('filterDueTo') ? $('filterDueTo').value || '' : '',
      comments_mode: $('filterCommentsMode') ? $('filterCommentsMode').value || '' : '',
      attachments_mode: $('filterAttachmentsMode') ? $('filterAttachmentsMode').value || '' : '',
      department_id: $('filterDepartment') ? $('filterDepartment').value || '' : '',
      task_kind: $('filterTaskKind') ? $('filterTaskKind').value || '' : '',
      include_archived_done:
        $('filterIncludeArchivedDone') && $('filterIncludeArchivedDone').checked ? 1 : 0,
    };
  }

  function applyFiltersFromObject(f) {
    f = f || {};
    if ($('filterProject') && f.project_id != null) $('filterProject').value = String(f.project_id);
    if ($('filterRole') && f.role_scope != null) $('filterRole').value = String(f.role_scope || '');
    if ($('filterVisibility') && f.visibility_scope != null) $('filterVisibility').value = String(f.visibility_scope || '');
    if ($('tasksAdvSearch') && f.q != null) $('tasksAdvSearch').value = String(f.q || '');
    if ($('filterAdvAssignee') && f.assignee_id != null) $('filterAdvAssignee').value = String(f.assignee_id || '');
    if (f.participant_user_id != null) setParticipantSearchById(String(f.participant_user_id || ''));
    var ft = $('filterAdvTags');
    if (ft) {
      if (f.tag_ids && f.tag_ids.length > 0) {
        ft.value = String(f.tag_ids[0] || '');
      } else if (Array.isArray(f.tag_ids)) {
        ft.value = '';
      }
    }
    if ($('filterDueFrom') && f.due_from != null) $('filterDueFrom').value = String(f.due_from || '');
    if ($('filterDueTo') && f.due_to != null) $('filterDueTo').value = String(f.due_to || '');
    if ($('filterCommentsMode')) {
      if (f.comments_mode != null && f.comments_mode !== '') {
        $('filterCommentsMode').value = String(f.comments_mode);
      } else if (f.has_comments === 1 || f.has_comments === '1' || f.has_comments === true) {
        $('filterCommentsMode').value = '1';
      } else if (f.has_comments === 0 || f.has_comments === '0' || f.has_comments === false) {
        $('filterCommentsMode').value = '0';
      } else {
        $('filterCommentsMode').value = '';
      }
    }
    if ($('filterAttachmentsMode')) {
      if (f.attachments_mode != null && f.attachments_mode !== '') {
        $('filterAttachmentsMode').value = String(f.attachments_mode);
      } else if (f.has_attachments === 1 || f.has_attachments === '1' || f.has_attachments === true) {
        $('filterAttachmentsMode').value = '1';
      } else if (f.has_attachments === 0 || f.has_attachments === '0' || f.has_attachments === false) {
        $('filterAttachmentsMode').value = '0';
      } else {
        $('filterAttachmentsMode').value = '';
      }
    }
    if ($('filterDepartment') && f.department_id != null) {
      $('filterDepartment').value = String(f.department_id || '');
    }
    if ($('filterTaskKind') && f.task_kind != null) {
      $('filterTaskKind').value = String(f.task_kind || '');
    }
    if ($('filterIncludeArchivedDone')) {
      $('filterIncludeArchivedDone').checked = !!(
        f.include_archived_done === 1 ||
        f.include_archived_done === true ||
        f.include_archived_done === '1'
      );
    }
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function resetAdvancedFiltersOnly() {
    if ($('filterAdvAssignee')) $('filterAdvAssignee').value = '';
    setParticipantSearchById('');
    var ft = $('filterAdvTags');
    if (ft) {
      ft.value = '';
    }
    if ($('filterDueFrom')) $('filterDueFrom').value = '';
    if ($('filterDueTo')) $('filterDueTo').value = '';
    if ($('filterCommentsMode')) $('filterCommentsMode').value = '';
    if ($('filterAttachmentsMode')) $('filterAttachmentsMode').value = '';
    if ($('filterDepartment')) $('filterDepartment').value = '';
    if ($('filterTaskKind')) $('filterTaskKind').value = '';
    if ($('filterIncludeArchivedDone')) $('filterIncludeArchivedDone').checked = false;
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function userMayPickViewDepartment() {
    var r = typeof window.__PLANNER_USER_ROLE__ === 'string' ? window.__PLANNER_USER_ROLE__ : '';
    if (r === 'admin' || r === 'owner') return true;
    return window.__PLANNER_USER_HAS_MANAGER__ === true;
  }

  function getSavedViewDefaultLocalId() {
    try {
      return localStorage.getItem('planner_saved_view_default_id') || '';
    } catch (eSvGet) {
      return '';
    }
  }

  function setSavedViewDefaultLocalId(id) {
    try {
      if (id) localStorage.setItem('planner_saved_view_default_id', String(id));
      else localStorage.removeItem('planner_saved_view_default_id');
    } catch (eSvSet) {}
  }

  function syncSavedViewScopeUi() {
    var scope = $('savedViewScope');
    var wrap = $('savedViewDeptWrap');
    if (!scope || !wrap) return;
    var team = scope.value === 'team';
    wrap.hidden = !(team && userMayPickViewDepartment());
  }

  function savedViewOptionLabel(v) {
    var base = v.name || '';
    if (parseInt(v.is_default, 10) === 1) {
      base = '★ ' + base;
    }
    if (v.scope === 'team') {
      var did = v.department_id != null && v.department_id !== '' ? parseInt(v.department_id, 10) : 0;
      if (!did) {
        return base + ' · командное (вся орг.)';
      }
      for (var i = 0; i < departmentsCache.length; i++) {
        if (parseInt(departmentsCache[i].id, 10) === did) {
          return base + ' · ' + (departmentsCache[i].name || 'отдел');
        }
      }
      return base + ' · командное';
    }
    return base + ' · личное';
  }

  function getSavedViewsCache() {
    var sel = $('savedViewPick');
    if (!sel) return [];
    var raw = sel.dataset.viewsJson;
    var items = [];
    try {
      items = raw ? JSON.parse(raw) : [];
    } catch (eSv) {
      items = [];
    }
    return Array.isArray(items) ? items : [];
  }

  function renderSavedViewsPickFiltered(query, keepSelectedId) {
    var sel = $('savedViewPick');
    if (!sel) return;
    var q = (query || '').trim().toLowerCase();
    var items = getSavedViewsCache();
    var filtered = items;
    if (q) {
      filtered = items.filter(function (v) {
        var lab = savedViewOptionLabel(v) || '';
        return lab.toLowerCase().indexOf(q) !== -1;
      });
    }
    sel.innerHTML =
      '<option value="">— не выбрано —</option>' +
      filtered
        .map(function (v) {
          return '<option value="' + v.id + '">' + escapeHtml(savedViewOptionLabel(v)) + '</option>';
        })
        .join('');
    if (keepSelectedId) {
      sel.value = String(keepSelectedId);
    }
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function refreshSavedViewsPick() {
    var sel = $('savedViewPick');
    if (!sel) return Promise.resolve();
    return api('/api/task_saved_views.php')
      .catch(function () {
        return { ok: true, items: [] };
      })
      .then(function (d) {
        var items = (d && d.items) || [];
        var hasServerDefault = false;
        for (var i = 0; i < items.length; i++) {
          if (parseInt(items[i].is_default, 10) === 1) {
            hasServerDefault = true;
            break;
          }
        }
        if (!hasServerDefault) {
          var localDefaultId = getSavedViewDefaultLocalId();
          if (localDefaultId) {
            for (var j = 0; j < items.length; j++) {
              if (String(items[j].id) === String(localDefaultId)) {
                items[j].is_default = 1;
                break;
              }
            }
          }
        }
        sel.dataset.viewsJson = JSON.stringify(items);
        var q = $('savedViewSearch') ? $('savedViewSearch').value : '';
        var keep = sel.value || '';
        renderSavedViewsPickFiltered(q, keep);
        return items;
      });
  }

  function syncActiveFiltersChips() {
    var row = $('tasksActiveFiltersRow');
    var host = $('tasksActiveFilters');
    if (!row || !host) return;
    var f = collectCurrentFiltersObject();
    var chips = [];
    function addChip(key, label, clearFn) {
      chips.push({ key: key, label: label, clearFn: clearFn });
    }
    if (f.project_id) {
      var projLab = '';
      var fp = $('filterProject');
      if (fp && fp.options) {
        for (var i = 0; i < fp.options.length; i++) {
          if (fp.options[i].value === String(f.project_id)) {
            projLab = fp.options[i].textContent || '';
            break;
          }
        }
      }
      addChip('project_id', 'Проект: ' + (projLab || f.project_id), function () {
        if ($('filterProject')) $('filterProject').value = '';
      });
    }
    if (f.role_scope) {
      addChip('role_scope', 'Роль: ' + f.role_scope, function () {
        if ($('filterRole')) $('filterRole').value = '';
      });
    }
    if (f.visibility_scope) {
      addChip('visibility_scope', 'Видимость: ' + f.visibility_scope, function () {
        if ($('filterVisibility')) $('filterVisibility').value = '';
      });
    }
    if (f.q) {
      addChip('q', 'Поиск: ' + f.q, function () {
        if ($('tasksAdvSearch')) $('tasksAdvSearch').value = '';
      });
    }
    if (f.assignee_id) {
      var aLab = '';
      var fa = $('filterAdvAssignee');
      if (fa && fa.options) {
        for (var j = 0; j < fa.options.length; j++) {
          if (fa.options[j].value === String(f.assignee_id)) {
            aLab = fa.options[j].textContent || '';
            break;
          }
        }
      }
      addChip('assignee_id', 'Исполнитель: ' + (aLab || f.assignee_id), function () {
        if ($('filterAdvAssignee')) $('filterAdvAssignee').value = '';
      });
    }
    if (f.participant_user_id) {
      var pLab = participantLabelById(f.participant_user_id);
      addChip('participant_user_id', 'Участник: ' + (pLab || f.participant_user_id), function () {
        setParticipantSearchById('');
      });
    }
    if (Array.isArray(f.tag_ids) && f.tag_ids.length) {
      addChip('tag_ids', 'Метки: ' + f.tag_ids.join(', '), function () {
        var ft = $('filterAdvTags');
        if (!ft) return;
        ft.value = '';
      });
    }
    if (f.due_from) {
      addChip('due_from', 'Срок с: ' + f.due_from, function () {
        if ($('filterDueFrom')) $('filterDueFrom').value = '';
      });
    }
    if (f.due_to) {
      addChip('due_to', 'Срок по: ' + f.due_to, function () {
        if ($('filterDueTo')) $('filterDueTo').value = '';
      });
    }
    if (f.comments_mode !== '') {
      addChip('comments_mode', f.comments_mode === '1' ? 'С комментариями' : 'Без комментариев', function () {
        if ($('filterCommentsMode')) $('filterCommentsMode').value = '';
      });
    }
    if (f.attachments_mode !== '') {
      addChip('attachments_mode', f.attachments_mode === '1' ? 'С вложениями' : 'Без вложений', function () {
        if ($('filterAttachmentsMode')) $('filterAttachmentsMode').value = '';
      });
    }
    if (f.department_id) {
      var dLab = '';
      var fd = $('filterDepartment');
      if (fd && fd.options) {
        for (var qd = 0; qd < fd.options.length; qd++) {
          if (fd.options[qd].value === String(f.department_id)) {
            dLab = fd.options[qd].textContent || '';
            break;
          }
        }
      }
      addChip('department_id', 'Подразделение: ' + (dLab || f.department_id), function () {
        if ($('filterDepartment')) $('filterDepartment').value = '';
      });
    }
    if (f.task_kind) {
      addChip('task_kind', 'Тип: ' + f.task_kind, function () {
        if ($('filterTaskKind')) $('filterTaskKind').value = '';
      });
    }
    if (f.include_archived_done) {
      addChip('include_archived_done', 'Закрытые: все', function () {
        if ($('filterIncludeArchivedDone')) $('filterIncludeArchivedDone').checked = false;
      });
    }

    row.hidden = chips.length === 0;
    $('btnClearOneAdvFilter').hidden = !(
      f.assignee_id ||
      f.participant_user_id ||
      (Array.isArray(f.tag_ids) && f.tag_ids.length) ||
      f.due_from ||
      f.due_to ||
      f.comments_mode !== '' ||
      f.attachments_mode !== '' ||
      f.department_id ||
      f.task_kind ||
      f.include_archived_done
    );

    host.innerHTML = chips
      .map(function (c) {
        return (
          '<span class="bx-filter-tag" data-key="' +
          escapeHtml(c.key) +
          '">' +
          escapeHtml(c.label) +
          ' <button type="button" class="bx-filter-tag-x" aria-label="Убрать фильтр">×</button></span>'
        );
      })
      .join('');
    host.querySelectorAll('.bx-filter-tag').forEach(function (el) {
      var key = el.getAttribute('data-key') || '';
      var btn = el.querySelector('.bx-filter-tag-x');
      if (!btn) return;
      btn.addEventListener('click', function () {
        for (var i = 0; i < chips.length; i++) {
          if (chips[i].key === key && typeof chips[i].clearFn === 'function') {
            chips[i].clearFn();
            break;
          }
        }
        if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
        loadTasks();
      });
    });
  }

  function countExtraActiveFilters(f) {
    f = f || collectCurrentFiltersObject();
    var c = 0;
    if (f.participant_user_id) c++;
    if (f.department_id) c++;
    if (Array.isArray(f.tag_ids) && f.tag_ids.length) c++;
    if (f.comments_mode !== '') c++;
    if (f.attachments_mode !== '') c++;
    if (f.task_kind) c++;
    if (f.include_archived_done) c++;
    return c;
  }

  function syncAdvMoreButtonLabel() {
    var btn = $('btnAdvMore');
    if (!btn) return;
    var f = collectCurrentFiltersObject();
    var n = countExtraActiveFilters(f);
    var base = 'Ещё фильтры';
    btn.textContent = n > 0 ? base + ' · ' + n : base;
  }

