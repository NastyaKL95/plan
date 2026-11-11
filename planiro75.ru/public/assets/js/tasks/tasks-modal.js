  function inferMayEditFallback(t) {
    var uid = currentUserId();
    if (!uid || !t) return false;
    if (String(t.author_id) === String(uid)) return true;
    if (t.assignee_id != null && String(t.assignee_id) === String(uid)) return true;
    return false;
  }

  function resolveMayEditFromTask(t) {
    if (!t) return false;
    if (Object.prototype.hasOwnProperty.call(t, 'may_edit')) {
      return !!t.may_edit;
    }
    return inferMayEditFallback(t);
  }

  function removeExtraTaskProjectOptions() {
    var sel = $('fProject');
    if (!sel) return;
    sel.querySelectorAll('option[data-planner-task-project-extra="1"]').forEach(function (o) {
      o.remove();
    });
  }

  /** Подпись проекта как в фильтре: «Подразделение — название», если есть данные из карточки задачи. */
  function taskProjectOptionLabelFromApi(t) {
    var dn = t && t.department_name ? String(t.department_name) + ' — ' : '';
    var pn = t && t.project_name ? String(t.project_name) : 'Проект';
    return dn + pn;
  }

  /** Если проект не попал в список (нет доступа к подразделению), добавляем опцию из ответа API, чтобы было видно название. */
  function ensureTaskProjectOptionFromApi(t) {
    var sel = $('fProject');
    if (!sel || !t) return;
    var pid = parseInt(t.project_id, 10);
    if (!pid) return;
    var i;
    for (i = 0; i < sel.options.length; i++) {
      if (parseInt(sel.options[i].value, 10) === pid) return;
    }
    var opt = document.createElement('option');
    opt.value = String(pid);
    opt.textContent = taskProjectOptionLabelFromApi(t);
    opt.setAttribute('data-planner-task-project-extra', '1');
    sel.appendChild(opt);
  }

  /** Редактировать проект может только постановщик (в режиме создания — доступно). */
  function setTaskProjectFieldReadOnly(task) {
    var sel = $('fProject');
    var btn = $('btnProjectPicker');
    var md = $('taskSplitModal');
    var mode = md ? md.getAttribute('data-task-mode') : '';
    if (mode === 'create') {
      if (sel) sel.disabled = false;
      if (btn) {
        btn.disabled = false;
        btn.removeAttribute('title');
        btn.classList.remove('is-disabled');
      }
      if (typeof window.bxCustomSelectSync === 'function' && sel) window.bxCustomSelectSync(sel);
      return;
    }
    var uid = currentUserId();
    var authorId = task ? parseInt(task.author_id, 10) : 0;
    var canEdit = uid > 0 && authorId > 0 && uid === authorId;
    if (sel) sel.disabled = !canEdit;
    if (btn) {
      btn.disabled = !canEdit;
      if (!canEdit) {
        btn.setAttribute('title', 'Менять проект может только постановщик задачи');
        btn.classList.add('is-disabled');
      } else {
        btn.removeAttribute('title');
        btn.classList.remove('is-disabled');
      }
    }
    if (typeof window.bxCustomSelectSync === 'function' && sel) window.bxCustomSelectSync(sel);
  }

  /**
   * Поля карточки: правка только если may_edit (API). Проект при may_edit — только у постановщика (setTaskProjectFieldReadOnly).
   * Режим create: всё включено, проект доступен будущему автору.
   */
  function applyTaskModalMayEdit(mayEdit, task) {
    taskModalMayEditCurrent = !!mayEdit;
    var md = $('taskSplitModal');
    if (md) md.setAttribute('data-task-readonly', mayEdit ? '' : '1');
    var mode = md ? md.getAttribute('data-task-mode') : '';
    if (mode === 'create') {
      ;['fTitle', 'fDesc', 'fAssignee', 'fDue', 'fStatus', 'fProject', 'fAssistants', 'fObservers', 'fWorkEdu', 'fWorkEduMethod', 'fWorkResearch', 'fWorkOrgMethod', 'fWorkPeriod', 'fWorkIndicator', 'fWorkHoursH1', 'fWorkHoursH2'].forEach(function (id) {
        var el = $(id);
        if (el) el.disabled = false;
      });
      var bp = $('btnPriorityToggle');
      if (bp) bp.disabled = false;
      ;['btnAssigneePicker', 'btnAssistantsPicker', 'btnObserversPicker', 'btnProjectPicker'].forEach(function (bid) {
        var b = $(bid);
        if (b) {
          b.disabled = false;
          b.classList.remove('is-disabled');
          if (bid !== 'btnProjectPicker') b.removeAttribute('title');
        }
      });
      setTaskProjectFieldReadOnly(task || { author_id: currentUserId() });
      if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
      return;
    }
    ;['fTitle', 'fDesc', 'fAssignee', 'fDue', 'fStatus', 'fAssistants', 'fObservers', 'fRemind', 'fRecurrence', 'fTransitionNote', 'fWorkEdu', 'fWorkEduMethod', 'fWorkResearch', 'fWorkOrgMethod', 'fWorkPeriod', 'fWorkIndicator', 'fWorkHoursH1', 'fWorkHoursH2'].forEach(function (id) {
      var el = $(id);
      if (el) el.disabled = !mayEdit;
    });
    var fp = $('fProject');
    if (fp) fp.disabled = !mayEdit;
    var visD = $('fVisibleDepartment');
    var visI = $('fVisibleInstitute');
    if (visD) visD.disabled = !mayEdit;
    if (visI) visI.disabled = !mayEdit;
    var btnPri = $('btnPriorityToggle');
    if (btnPri) btnPri.disabled = !mayEdit;
    ;['btnAssigneePicker', 'btnAssistantsPicker', 'btnObserversPicker'].forEach(function (bid) {
      var b = $(bid);
      if (b) {
        b.disabled = !mayEdit;
        if (!mayEdit) {
          b.classList.add('is-disabled');
          b.setAttribute('title', 'Нет прав на изменение');
        } else {
          b.classList.remove('is-disabled');
          b.removeAttribute('title');
        }
      }
    });
    var ftags = $('fTags');
    if (ftags) {
      ftags.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
        cb.disabled = !mayEdit;
      });
    }
    var footE = document.querySelector('.task-modal-footer-edit');
    if (footE) footE.hidden = !mayEdit;
    if (mayEdit && task) {
      setTaskProjectFieldReadOnly(task);
    } else {
      var bproj = $('btnProjectPicker');
      if (fp) fp.disabled = true;
      if (bproj) {
        bproj.disabled = true;
        bproj.classList.add('is-disabled');
        bproj.setAttribute('title', 'Нет прав на изменение');
      }
      if (typeof window.bxCustomSelectSync === 'function' && fp) window.bxCustomSelectSync(fp);
    }
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function applyTaskModalReadonlyDecorators() {
    var ok = taskModalMayEditCurrent;
    document.querySelectorAll('#linkList .link-del').forEach(function (b) {
      b.hidden = !ok;
    });
    document.querySelectorAll('#attachList .att-del').forEach(function (b) {
      b.hidden = !ok;
    });
    var lr = document.querySelector('#modalSectionLinks .links-row');
    if (lr) lr.hidden = !ok;
    var ar = document.querySelector('#modalSectionAttach .attach-row');
    if (ar) ar.hidden = !ok;
    var sta = document.querySelector('#modalSectionSubtasks .subtask-add');
    if (sta) sta.hidden = !ok;
    var str = document.querySelector('#modalSectionSubtasks .subtask-template-row');
    if (str) str.hidden = !ok;
    var sul = $('subtaskList');
    if (sul) {
      sul.querySelectorAll('.subtask-item > .subtask-check input[type=checkbox]').forEach(function (cb) {
        cb.disabled = !ok;
      });
      sul.querySelectorAll('.sub-del').forEach(function (b) {
        b.hidden = !ok;
      });
      sul.querySelectorAll('.subtask-assistants-trigger').forEach(function (b) {
        b.disabled = !ok;
      });
      sul.querySelectorAll('.subtask-assistants-select').forEach(function (s) {
        s.disabled = !ok;
      });
      sul.querySelectorAll('.subtask-assist-cb').forEach(function (cb) {
        cb.disabled = !ok;
      });
    }
  }
  function renderTagsPicker(selectedIds) {
    renderTagsPickerFor($('fTags'), selectedIds);
  }

  function renderTagsPickerFor(box, selectedIds) {
    if (!box) return;
    if (!tagsList.length) {
      box.innerHTML =
        '<p class="muted-inline">Меток пока нет. Создайте их в разделе «Метки» в боковом меню или выполните миграцию БД.</p>';
      return;
    }
    var set = {};
    (selectedIds || []).forEach(function (id) {
      set[String(id)] = true;
    });
    box.innerHTML = tagsList
      .map(function (tg) {
        var id = tg.id;
        var checked = set[String(id)] ? ' checked' : '';
        var c = tg.color && /^#[0-9A-Fa-f]{6}$/.test(tg.color) ? tg.color : '#64748b';
        return (
          '<label class="tag-chip"><input type="checkbox" value="' +
          id +
          '"' +
          checked +
          '> <span class="tag-chip-name" data-color="' +
          c +
          '">' +
          escapeHtml(tg.name) +
          '</span></label>'
        );
      })
      .join('');
    box.querySelectorAll('.tag-chip-name').forEach(function (sp) {
      var c = sp.getAttribute('data-color') || '#64748b';
      sp.style.borderLeft = '4px solid ' + c;
      sp.style.paddingLeft = '6px';
    });
    box.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
      cb.disabled = !taskModalMayEditCurrent;
    });
  }

  function selectedTagIdsFrom(box) {
    if (!box) return [];
    var ids = [];
    box.querySelectorAll('input[type=checkbox]:checked').forEach(function (cb) {
      ids.push(parseInt(cb.value, 10));
    });
    return ids;
  }

  function selectedTagIds() {
    return selectedTagIdsFrom($('fTags'));
  }

  function setTaskModalMode(mode) {
    var root = $('taskSplitModal');
    if (root) root.setAttribute('data-task-mode', mode);
  }

  function rememberTaskModalFocusBack() {
    var ae = document.activeElement;
    taskModalFocusBack = ae && ae !== document.body ? ae : null;
  }

  function restoreTaskModalFocusBack() {
    var target = taskModalFocusBack;
    taskModalFocusBack = null;
    if (target && document.contains(target) && !target.disabled && typeof target.focus === 'function') {
      try {
        target.focus({ preventScroll: true });
        return;
      } catch (e) {}
    }
    var fallback = $('btnCreate') || $('btnCreateMenu') || document.querySelector('.bx-topnav-notif');
    if (fallback && typeof fallback.focus === 'function') {
      try {
        fallback.focus({ preventScroll: true });
      } catch (e2) {}
    }
  }

  function closeTaskModal() {
    var ov = $('taskSplitOverlay');
    var md = $('taskSplitModal');
    closeParticipantPicker();
    closeChecklistTemplateMenu();
    closeChecklistTemplateDialog();
    if (md && document.activeElement && md.contains(document.activeElement)) {
      restoreTaskModalFocusBack();
    }
    if (md) md.setAttribute('aria-hidden', 'true');
    if (ov) ov.hidden = true;
    if (md) md.hidden = true;
    document.body.classList.remove('task-modal-open');
    createModalState.planLane = null;
    createModalState.myPlanStageId = null;
    createModalState.deadlineLane = null;
    createModalState.chatDrafts = [];
    clearOpenParamFromUrl();
  }

  function closeCreateTaskModal() {
    closeTaskModal();
  }

  function workFlag(id) {
    var el = $(id);
    return !!(el && el.checked);
  }

  function workText(id) {
    var el = $(id);
    return el ? (el.value || '').trim() : '';
  }

  function workHours(id) {
    var raw = workText(id).replace(',', '.');
    if (!raw) return null;
    var n = parseFloat(raw);
    if (!isFinite(n) || n < 0) return null;
    return Math.round(n * 100) / 100;
  }

  function appendWorkReport(body) {
    body.work_edu = workFlag('fWorkEdu');
    body.work_edu_method = workFlag('fWorkEduMethod');
    body.work_research = workFlag('fWorkResearch');
    body.work_org_method = workFlag('fWorkOrgMethod');
    body.work_period = workText('fWorkPeriod');
    body.work_indicator = workText('fWorkIndicator');
    body.work_hours_first = workHours('fWorkHoursH1');
    body.work_hours_second = workHours('fWorkHoursH2');
    return body;
  }

  function fillWorkReport(t) {
    t = t || {};
    function setCb(id, value) {
      var el = $(id);
      if (!el) return;
      el.checked = value === true || value === 1 || value === '1';
    }
    function setVal(id, value, asHours) {
      var el = $(id);
      if (!el) return;
      if (value == null || value === '') {
        el.value = '';
        return;
      }
      var text = String(value);
      if (asHours && /^\d+\.\d+$/.test(text)) {
        text = text.replace(/0+$/, '').replace(/\.$/, '');
      }
      el.value = asHours ? text.replace('.', ',') : text;
    }
    setCb('fWorkEdu', t.work_edu);
    setCb('fWorkEduMethod', t.work_edu_method);
    setCb('fWorkResearch', t.work_research);
    setCb('fWorkOrgMethod', t.work_org_method);
    setVal('fWorkPeriod', t.work_period || '', false);
    setVal('fWorkIndicator', t.work_indicator || '', false);
    setVal('fWorkHoursH1', t.work_hours_first != null && t.work_hours_first !== '' ? t.work_hours_first : '', true);
    setVal('fWorkHoursH2', t.work_hours_second != null && t.work_hours_second !== '' ? t.work_hours_second : '', true);
  }

  function openCreateTaskModal(opts) {
    rememberTaskModalFocusBack();
    opts = opts || {};
    var sid = opts.myPlanStageId != null ? parseInt(opts.myPlanStageId, 10) : 0;
    createModalState.myPlanStageId = sid > 0 ? sid : null;
    createModalState.planLane = createModalState.myPlanStageId ? null : opts.planLane || null;
    createModalState.deadlineLane = opts.deadlineLane || null;
    currentSubtaskParticipantOptions = [];

    var pid = defaultProjectIdForCreate();
    if (!pid) {
      notify('Нет доступных проектов. Создайте проект в подразделении.', 'error');
      return;
    }

    $('fTaskId').value = '';
    $('fTitle').value = '';
    $('fDesc').value = '';
    setPriorityUi('normal');

    var mp = $('fProject');
    if (mp) mp.value = String(pid);

    $('fAssignee').value = '';

    var st = 'new';
    var dueVal = '';

    if (createModalState.deadlineLane) {
      var patch = patchBodyForDeadlineLane(createModalState.deadlineLane, null);
      if (patch.status) st = patch.status;
      if (Object.prototype.hasOwnProperty.call(patch, 'due_date')) {
        dueVal = patch.due_date === null ? '' : dueToDatetimeLocal(patch.due_date);
      }
    }

    $('fStatus').value = st;
    $('fDue').value = dueVal;
    if ($('fVisibleDepartment')) $('fVisibleDepartment').checked = false;
    if ($('fVisibleInstitute')) $('fVisibleInstitute').checked = false;
    fillWorkReport({});

    var auth = $('taskModalAuthorDisplay');
    if (auth) auth.textContent = currentUserDisplayName();

    var sub = $('taskModalSubtitle');
    if (sub) sub.textContent = 'Чат — после создания задачи';

    createModalState.chatDrafts = [];

    setMultiSelected($('fAssistants'), []);
    setMultiSelected($('fObservers'), []);
    updateParticipantSummaries();

    document.querySelectorAll('.task-modal-extra').forEach(function (el) {
      el.hidden = true;
    });
    document.querySelectorAll('.task-modal-pill[data-target]').forEach(function (b) {
      b.classList.remove('is-active');
    });

    setTaskModalMode('create');
    applyTaskModalMayEdit(true, { author_id: currentUserId() });
    renderTagsPicker([]);

    var ncb = $('newCommentBody');
    if (ncb) {
      ncb.value = '';
      ncb.placeholder = 'Сообщение уйдёт в чат после «Создать задачу»';
      sizeChatCommentTextarea();
    }
    renderCreateChatFeed();

    $('taskSplitOverlay').hidden = false;
    $('taskSplitModal').hidden = false;
    $('taskSplitModal').setAttribute('aria-hidden', 'false');
    document.body.classList.add('task-modal-open');
    $('fTitle').focus();
  }

  function submitCreateTaskModal() {
    var pid = $('fProject') ? $('fProject').value : '';
    var title = ($('fTitle').value || '').trim();
    if (!pid) return notify('Выберите проект', 'error');
    if (!title) return notify('Укажите название задачи', 'error');

    var desc = ($('fDesc').value || '').trim();
    var body = {
      title: title,
      description: desc || null,
      status: $('fStatus').value,
      priority: $('fPriority').value,
      project_id: parseInt(pid, 10),
      assignee_id: $('fAssignee').value ? parseInt($('fAssignee').value, 10) : null,
      due_date: dueFromDatetimeLocal($('fDue').value || ''),
      tag_ids: selectedTagIds(),
      assistant_ids: selectedMulti($('fAssistants')),
      observer_ids: selectedMulti($('fObservers')),
      visible_department: $('fVisibleDepartment') ? !!$('fVisibleDepartment').checked : false,
      visible_institute: $('fVisibleInstitute') ? !!$('fVisibleInstitute').checked : false,
    };
    appendWorkReport(body);

    if (createModalState.myPlanStageId) {
      body.my_plan_stage_id = createModalState.myPlanStageId;
    } else if (createModalState.planLane) {
      body.my_plan_lane = createModalState.planLane;
    }

    api('/api/tasks.php', { method: 'POST', body: body })
      .then(function (res) {
        if (!res || res.id == null) {
          throw new Error('нет id в ответе');
        }
        var tid = res.id;
        var drafts = (createModalState.chatDrafts || []).slice();
        createModalState.chatDrafts = [];
        var chain = Promise.resolve();
        drafts.forEach(function (msg) {
          var t = (msg || '').trim();
          if (!t) return;
          chain = chain.then(function () {
            return api('/api/task_comments.php?task_id=' + tid, { method: 'POST', body: { body: t } });
          });
        });
        return chain
          .catch(function (err) {
            notify(
              'Задача создана, но часть сообщений в чат не отправилась: ' + (err && err.message ? err.message : ''),
              'error'
            );
          })
          .then(function () {
            return Promise.all([openDrawer(tid), loadTasks()]);
          });
      })
      .catch(function (e) {
        notify('Не удалось создать задачу: ' + (e.message || 'ошибка'), 'error');
      });
  }

  function renderLinks(links) {
    var el = $('linkList');
    if (!el) return;
    links = links || { outgoing: [], incoming: [] };
    var parts = [];
    (links.outgoing || []).forEach(function (L) {
      parts.push(
        '<div class="link-item">→ #' +
          L.linked_task_id +
          ' ' +
          escapeHtml(L.linked_title || '') +
          ' <span class="link-type">(' +
          escapeHtml(L.link_type) +
          ')</span> <button type="button" class="link-btn link-del" data-id="' +
          L.id +
          '">Удалить</button></div>'
      );
    });
    (links.incoming || []).forEach(function (L) {
      parts.push(
        '<div class="link-item">← #' +
          L.from_task_id +
          ' ' +
          escapeHtml(L.from_title || '') +
          ' <span class="link-type">(' +
          escapeHtml(L.link_type) +
          ')</span> <button type="button" class="link-btn link-del" data-id="' +
          L.id +
          '">Удалить</button></div>'
      );
    });
    el.innerHTML = parts.length ? parts.join('') : '<p class="muted-inline">Связей нет.</p>';
    el.querySelectorAll('.link-del').forEach(function (b) {
      b.addEventListener('click', function () {
        var lid = b.getAttribute('data-id');
        if (!lid) return;
        openAppInlineConfirm({
          title: 'Удалить связь?',
          message: 'Связь между задачами будет удалена.',
          confirmLabel: 'Удалить',
          danger: true,
        }).then(function (ok) {
          if (!ok) return;
          api('/api/task_link.php?id=' + lid, { method: 'DELETE' })
            .then(function () {
              return openDrawer($('fTaskId').value);
            })
            .catch(function (e) {
              notify('Ошибка: ' + (e.message || ''), 'error');
            });
        });
      });
    });
  }

  function renderAttachments(items) {
    var el = $('attachList');
    if (!el) return;
    if (!items || !items.length) {
      el.innerHTML = '<li class="muted-inline">Файлов пока нет.</li>';
      return;
    }
    el.innerHTML = items
      .map(function (a) {
        var href = apiUrl('/download.php?attachment=' + encodeURIComponent(a.id));
        return (
          '<li class="attach-item"><a href="' +
          href +
          '">' +
          escapeHtml(a.original_name || '') +
          '</a> <span class="attach-meta">' +
          (a.size_bytes || 0) +
          ' байт</span> <button type="button" class="link-btn att-del" data-id="' +
          a.id +
          '">Удалить</button></li>'
        );
      })
      .join('');
    el.querySelectorAll('.att-del').forEach(function (b) {
      b.addEventListener('click', function () {
        var aid = b.getAttribute('data-id');
        if (!aid) return;
        openAppInlineConfirm({
          title: 'Удалить файл?',
          message: 'Файл будет удалён из задачи без возможности восстановления.',
          confirmLabel: 'Удалить',
          danger: true,
        }).then(function (ok) {
          if (!ok) return;
          api('/api/task_attachment.php?id=' + aid, { method: 'DELETE' })
            .then(function () {
              return openDrawer($('fTaskId').value);
            })
            .catch(function (e) {
              notify('Ошибка: ' + (e.message || ''), 'error');
            });
        });
      });
    });
  }

  function openDrawer(id) {
    if (!id) return Promise.resolve();
    drawerChatState = {
      taskId: id,
      comments: [],
      history: [],
      hasMoreOlder: false,
      loadingOlder: false,
      oldestId: null,
    };
    syncLoadOlderChatUi();
    return Promise.all([
      api('/api/task.php?id=' + id),
      api(commentsApiUrl(id, 0, 80)).catch(function () {
        return { ok: true, items: [], has_more_older: false };
      }),
    ])
      .then(function (parts) {
        var data = parts[0];
        var pack = parts[1] || {};
        var comments = pack.items || [];
        drawerChatState.comments = comments;
        drawerChatState.hasMoreOlder = !!(pack.has_more_older === true || pack.has_more_older === 1);
        drawerChatState.oldestId = pack.oldest_id != null ? pack.oldest_id : null;
        var t = data.task;
        var mayEditResolved = resolveMayEditFromTask(t);
        taskModalMayEditCurrent = mayEditResolved;
        drawerChatState.history = (t && t.history) || [];
        currentSubtaskParticipantOptions = taskSubtaskParticipantPool(t);
        drawerInitialStatus = t.status || '';
        removeExtraTaskProjectOptions();
        $('fTaskId').value = t.id;
        $('fTitle').value = t.title;
        $('fDesc').value = t.description || '';
        $('fStatus').value = t.status;
        setPriorityUi(t.priority || 'normal');
        ensureTaskProjectOptionFromApi(t);
        $('fProject').value = t.project_id || '';
        $('fAssignee').value = t.assignee_id || '';
        $('fDue').value = dueToDatetimeLocal(t.due_date || '');
        if ($('fVisibleDepartment')) $('fVisibleDepartment').checked = !!parseInt(t.visible_department || 0, 10);
        if ($('fVisibleInstitute')) $('fVisibleInstitute').checked = !!parseInt(t.visible_institute || 0, 10);
        fillWorkReport(t);
        if ($('fRemind')) $('fRemind').value = t.remind_at || '';
        if ($('fRecurrence')) $('fRecurrence').value = t.recurrence || 'none';
        if ($('fTransitionNote')) $('fTransitionNote').value = t.transition_note || '';
        $('newCommentBody').value = '';
        var ncb = $('newCommentBody');
        if (ncb) {
          ncb.placeholder = 'Нажмите @ или + чтобы упомянуть человека, чат или AI';
          sizeChatCommentTextarea();
        }
        var au = $('taskModalAuthorDisplay');
        if (au) au.textContent = t.author_name || '—';
        var sub = $('taskModalSubtitle');
        if (sub) sub.textContent = 'Задача #' + t.id;
        renderTagsPicker((t.tags || []).map(function (x) {
          return x.id;
        }));
        fillParticipantSelects();
        var part = t.participants || {};
        setMultiSelected(
          $('fAssistants'),
          (part.assistants || []).map(function (x) {
            return x.id;
          })
        );
        setMultiSelected(
          $('fObservers'),
          (part.observers || []).map(function (x) {
            return x.id;
          })
        );
        updateParticipantSummaries();
        refreshMentionQuickPick(t);
        renderLinks(t.links);
        renderAttachments(t.attachments);
        renderSubtasks(t.subtasks || []);
        renderChatFeed(drawerChatState.comments, drawerChatState.history);
        syncLoadOlderChatUi();
        var tidMark = t.id;
        setTimeout(function () {
          if (!($('fTaskId') && String($('fTaskId').value) === String(tidMark))) return;
          api('/api/task_comment_reads.php', { method: 'POST', body: { task_id: tidMark } })
            .catch(function () {})
            .then(function () {
              if (!($('fTaskId') && String($('fTaskId').value) === String(tidMark))) return;
              var uid = currentUserId();
              if (uid > 0) {
                (drawerChatState.comments || []).forEach(function (c) {
                  if (!c || !Object.prototype.hasOwnProperty.call(c, 'is_read')) return;
                  if (parseInt(c.user_id, 10) !== uid) c.is_read = 1;
                });
              }
              renderChatFeed(drawerChatState.comments, drawerChatState.history);
            });
        }, 0);
        var secSubtasks = $('modalSectionSubtasks');
        if (secSubtasks) secSubtasks.hidden = false;
        setTaskModalMode('edit');
        applyTaskModalMayEdit(mayEditResolved, t);
        applyTaskModalReadonlyDecorators();
        rememberTaskModalFocusBack();
        $('taskSplitOverlay').hidden = false;
        $('taskSplitModal').hidden = false;
        $('taskSplitModal').setAttribute('aria-hidden', 'false');
        document.body.classList.add('task-modal-open');
        scrollChatToBottom();
      })
      .catch(function (e) {
        notify('Не удалось открыть задачу: ' + (e.message || 'ошибка'), 'error');
      });
  }

  function postComment() {
    var tid = $('fTaskId').value;
    var text = ($('newCommentBody').value || '').trim();
    if (!text) return;
    if (!tid) {
      var md = $('taskSplitModal');
      if (!md || md.getAttribute('data-task-mode') !== 'create') return;
      createModalState.chatDrafts.push(text);
      $('newCommentBody').value = '';
      sizeChatCommentTextarea();
      renderCreateChatFeed();
      return;
    }
    api('/api/task_comments.php?task_id=' + tid, { method: 'POST', body: { body: text } })
      .then(function () {
        $('newCommentBody').value = '';
        sizeChatCommentTextarea();
        return openDrawer(tid);
      })
      .catch(function (e) {
        notify('Не удалось отправить: ' + (e.message || ''), 'error');
      });
  }

  function closeDrawer() {
    closeTaskModal();
  }

  function subtaskOrderFromDom() {
    var ul = $('subtaskList');
    if (!ul) return [];
    var ids = [];
    ul.querySelectorAll('.subtask-item').forEach(function (li) {
      ids.push(parseInt(li.getAttribute('data-id'), 10));
    });
    return ids.filter(function (x) {
      return x > 0;
    });
  }

  function saveSubtaskOrder(taskId) {
    var order = subtaskOrderFromDom();
    if (order.length < 2) return Promise.resolve();
    return api('/api/subtasks.php?task_id=' + taskId, { method: 'PATCH', body: { order: order } }).catch(function () {});
  }

  function setupSubtaskDnD(ul) {
    ul.querySelectorAll('.subtask-item').forEach(function (li) {
      li.setAttribute('draggable', 'true');
      li.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/plain', li.getAttribute('data-id'));
        e.dataTransfer.effectAllowed = 'move';
        li.classList.add('subtask-dragging');
      });
      li.addEventListener('dragend', function () {
        li.classList.remove('subtask-dragging');
      });
      li.addEventListener('dragover', function (e) {
        e.preventDefault();
        var from = ul.querySelector('.subtask-dragging');
        if (!from || from === li) return;
        var rect = li.getBoundingClientRect();
        var before = e.clientY < rect.top + rect.height / 2;
        if (before) ul.insertBefore(from, li);
        else ul.insertBefore(from, li.nextSibling);
      });
    });
  }

  function subtaskAssistantSummaryFromIds(ids) {
    var pool =
      currentSubtaskParticipantOptions && currentSubtaskParticipantOptions.length
        ? currentSubtaskParticipantOptions
        : users;
    if (!ids || !ids.length) return 'Выбрать соисполнителей';
    var names = [];
    pool.forEach(function (u) {
      if (ids.indexOf(parseInt(u.id, 10)) >= 0) names.push(u.name || '');
    });
    if (!names.length) return 'Выбрать соисполнителей';
    if (names.length <= 2) return names.join(', ');
    return names[0] + ' +' + (names.length - 1);
  }

  function subtaskAssignedUsersFromIds(ids, pool) {
    var out = [];
    var set = {};
    (ids || []).forEach(function (id) {
      var v = parseInt(id, 10);
      if (v > 0) set[String(v)] = true;
    });
    (pool || []).forEach(function (u) {
      var uid = parseInt(u.id, 10);
      if (uid > 0 && set[String(uid)]) {
        out.push({ id: uid, name: u.name || '' });
      }
    });
    return out;
  }

  function subtaskAssistantsAvatarsHtml(ids, pool) {
    var assigned = subtaskAssignedUsersFromIds(ids, pool);
    var summary = subtaskAssistantSummaryFromIds(ids);
    var chips = assigned
      .slice(0, 3)
      .map(function (u) {
        var letter = (u.name || '?').trim().slice(0, 1).toUpperCase();
        return (
          '<span class="subtask-assist-avatar" title="' +
          escapeHtml(u.name || '') +
          '">' +
          escapeHtml(letter) +
          '</span>'
        );
      })
      .join('');
    if (assigned.length > 3) {
      chips +=
        '<span class="subtask-assist-avatar subtask-assist-avatar--more" title="' +
        escapeHtml(summary) +
        '">+' +
        (assigned.length - 3) +
        '</span>';
    }
    return chips;
  }

  function updateSubtaskAssistantsTrigger(wrap, ids, pool) {
    if (!wrap) return;
    var trigger = wrap.querySelector('.subtask-assistants-trigger');
    if (!trigger) return;
    var avatars = wrap.querySelector('.subtask-assistants-avatars');
    var summary = subtaskAssistantSummaryFromIds(ids);
    if (avatars) avatars.innerHTML = subtaskAssistantsAvatarsHtml(ids, pool);
    trigger.setAttribute('title', summary);
    trigger.setAttribute('aria-label', summary);
    wrap.classList.toggle('has-assignees', (ids || []).length > 0);
  }

  function taskSubtaskParticipantPool(task) {
    var out = [];
    var seen = {};
    if (!task) return out;
    function pushUser(id, name) {
      var uid = parseInt(id, 10);
      if (!(uid > 0)) return;
      var key = String(uid);
      if (seen[key]) return;
      seen[key] = true;
      out.push({ id: uid, name: name || '' });
    }
    pushUser(task.author_id, task.author_name || '');
    pushUser(task.assignee_id, task.assignee_name || '');
    var part = task.participants || {};
    (part.assistants || []).forEach(function (u) {
      pushUser(u.id, u.name || '');
    });
    (part.observers || []).forEach(function (u) {
      pushUser(u.id, u.name || '');
    });
    out.sort(function (a, b) {
      return String(a.name || '').localeCompare(String(b.name || ''), 'ru');
    });
    return out;
  }

  function closeSubtaskAssistantPopovers(exceptWrap) {
    var ul = $('subtaskList');
    if (!ul) return;
    ul.querySelectorAll('.subtask-assistants-wrap').forEach(function (w) {
      if (exceptWrap && w === exceptWrap) return;
      w.classList.remove('is-open');
    });
  }

  function renderSubtasks(items) {
    var ul = $('subtaskList');
    if (!ul) return;
    if (!items || !items.length) {
      ul.innerHTML =
        '<li class="subtask-empty-state">Чек-лист пока пуст. Добавьте первый пункт вручную или вставьте его из шаблона.</li>';
      updateChecklistTemplatePreview();
      return;
    }
    var availableUsers =
      currentSubtaskParticipantOptions && currentSubtaskParticipantOptions.length
        ? currentSubtaskParticipantOptions
        : users;
    ul.innerHTML = items
      .map(function (s) {
        var selectedSet = {};
        (s.assistants || []).forEach(function (a) {
          selectedSet[String(a.id)] = true;
        });
        var assistantsOpts = availableUsers
          .map(function (u) {
            return (
              '<option value="' +
              u.id +
              '"' +
              (selectedSet[String(u.id)] ? ' selected' : '') +
              '>' +
              escapeHtml(u.name || '') +
              '</option>'
            );
          })
          .join('');
        var selectedIds = Object.keys(selectedSet)
          .map(function (x) {
            return parseInt(x, 10);
          })
          .filter(function (x) {
            return x > 0;
          });
        var avatarsHtml = subtaskAssistantsAvatarsHtml(selectedIds, availableUsers);
        var summary = subtaskAssistantSummaryFromIds(selectedIds);
        var assistantsChecks = availableUsers
          .map(function (u) {
            return (
              '<label class="subtask-assist-opt"><input type="checkbox" class="subtask-assist-cb" value="' +
              u.id +
              '"' +
              (selectedSet[String(u.id)] ? ' checked' : '') +
              '> <span>' +
              escapeHtml(u.name || '') +
              '</span></label>'
            );
          })
          .join('');
        return (
          '<li class="subtask-item" data-id="' +
          s.id +
          '"><span class="subtask-grip" aria-hidden="true">⋮⋮</span><label class="subtask-check"><input type="checkbox" ' +
          (s.is_done == 1 || s.is_done === true ? 'checked' : '') +
          '><span class="subtask-title">' +
          escapeHtml(s.title) +
          '</span></label><div class="subtask-assistants-wrap' +
          (selectedIds.length ? ' has-assignees' : '') +
          '"><button type="button" class="subtask-assistants-trigger" title="' +
          escapeHtml(summary) +
          '" aria-label="' +
          escapeHtml(summary) +
          '"><span class="subtask-assistants-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5 1.34 3.5 3 3.5zm-8 0c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.67 0-8 1.34-8 4v1h11v-1c0-2.66-5.33-4-8-4zm8 0c-.29 0-.62.02-.97.05 1.33.97 1.97 2.17 1.97 3.45v1h7v-1c0-2.66-5.33-4-8-4z" fill="currentColor"/></svg></span></button><span class="subtask-assistants-avatars">' +
          avatarsHtml +
          '</span><select class="subtask-assistants-select" multiple size="3">' +
          assistantsOpts +
          '</select><div class="subtask-assistants-popover">' +
          assistantsChecks +
          '</div></div><button type="button" class="link-btn sub-del" aria-label="Удалить">×</button></li>'
        );
      })
      .join('');

    ul.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var li = cb.closest('.subtask-item');
        var sid = li.getAttribute('data-id');
        api('/api/subtask.php?id=' + sid, {
          method: 'PATCH',
          body: { is_done: cb.checked },
        }).catch(function () {
          cb.checked = !cb.checked;
        });
      });
    });
    ul.querySelectorAll('.sub-del').forEach(function (b) {
      b.addEventListener('click', function () {
        var li = b.closest('.subtask-item');
        var sid = li.getAttribute('data-id');
        openAppInlineConfirm({
          title: 'Удалить пункт чек-листа?',
          message: 'Пункт будет удалён из списка.',
          confirmLabel: 'Удалить',
          danger: true,
        }).then(function (ok) {
          if (!ok) return;
          api('/api/subtask.php?id=' + sid, { method: 'DELETE' }).then(function () {
            li.remove();
          });
        });
      });
    });
    ul.querySelectorAll('.subtask-assistants-select').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var li = sel.closest('.subtask-item');
        var sid = li ? li.getAttribute('data-id') : '';
        if (!sid) return;
        api('/api/subtask.php?id=' + sid, {
          method: 'PATCH',
          body: { assistant_ids: selectedMulti(sel) },
        }).catch(function (e) {
          notify('Не удалось сохранить соисполнителей: ' + (e.message || ''), 'error');
        });
      });
    });
    ul.querySelectorAll('.subtask-assistants-trigger').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var wrap = btn.closest('.subtask-assistants-wrap');
        if (!wrap) return;
        var willOpen = !wrap.classList.contains('is-open');
        closeSubtaskAssistantPopovers(willOpen ? wrap : null);
        wrap.classList.toggle('is-open', willOpen);
      });
    });
    ul.querySelectorAll('.subtask-assist-cb').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var wrap = cb.closest('.subtask-assistants-wrap');
        if (!wrap) return;
        var sel = wrap.querySelector('.subtask-assistants-select');
        if (!sel) return;
        var id = parseInt(cb.value, 10);
        for (var i = 0; i < sel.options.length; i++) {
          if (parseInt(sel.options[i].value, 10) === id) {
            sel.options[i].selected = !!cb.checked;
            break;
          }
        }
        var ids = selectedMulti(sel);
        updateSubtaskAssistantsTrigger(wrap, ids, availableUsers);
        sel.dispatchEvent(new Event('change'));
      });
    });
    setupSubtaskDnD(ul);
    updateChecklistTemplatePreview();
  }

  function saveTask() {
    var id = $('fTaskId').value;
    if (!$('fProject').value) return notify('Выберите проект', 'error');
    var newStatus = $('fStatus').value;
    if (newStatus === 'review' && drawerInitialStatus !== 'review') {
      var tn = ($('fTransitionNote') && $('fTransitionNote').value.trim()) || '';
      if (!tn) return notify('Укажите примечание к году работы организации при переводе на проверку.', 'error');
    }
    var body = {
      title: $('fTitle').value.trim(),
      description: $('fDesc').value,
      status: newStatus,
      priority: $('fPriority').value,
      project_id: parseInt($('fProject').value, 10),
      assignee_id: $('fAssignee').value ? parseInt($('fAssignee').value, 10) : null,
      due_date: dueFromDatetimeLocal($('fDue').value || ''),
      tag_ids: selectedTagIds(),
      visible_department: $('fVisibleDepartment') ? !!$('fVisibleDepartment').checked : false,
      visible_institute: $('fVisibleInstitute') ? !!$('fVisibleInstitute').checked : false,
    };
    appendWorkReport(body);
    if ($('fRemind')) body.remind_at = $('fRemind').value || null;
    if ($('fRecurrence')) body.recurrence = $('fRecurrence').value || 'none';
    if ($('fTransitionNote')) body.transition_note = $('fTransitionNote').value.trim() || null;
    body.assistant_ids = selectedMulti($('fAssistants'));
    body.observer_ids = selectedMulti($('fObservers'));
    if (!body.title) return notify('Укажите название', 'error');
    api('/api/task.php?id=' + id, { method: 'PATCH', body: body })
      .then(function () {
        closeDrawer();
        return loadTasks();
      })
      .catch(function (e) {
        notify('Не удалось сохранить: ' + (e.message || 'ошибка сети'), 'error');
      });
  }

  function defaultProjectIdForCreate() {
    var fp = $('filterProject');
    if (fp && fp.value) return parseInt(fp.value, 10);
    if (projects.length) return parseInt(projects[0].id, 10);
    return null;
  }

  function createTask() {
    openCreateTaskModal({});
  }

  function createTaskInLane(laneOrStage) {
    if (myPlanStagesFeature && laneOrStage != null && /^\d+$/.test(String(laneOrStage))) {
      openCreateTaskModal({ myPlanStageId: parseInt(laneOrStage, 10) });
      return;
    }
    var lane = String(laneOrStage || '');
    var valid = lane === 'unplanned' || lane === 'active' || lane === 'week';
    if (!valid) return;
    openCreateTaskModal({ planLane: lane });
  }

  function deleteTask() {
    var id = $('fTaskId').value;
    if (!id) return;
    openAppInlineConfirm({
      title: 'Удалить задачу?',
      message: 'Задача и все пункты чек-листа будут удалены без возможности восстановления.',
      confirmLabel: 'Удалить',
      danger: true,
    }).then(function (ok) {
      if (!ok) return;
      api('/api/task.php?id=' + id, { method: 'DELETE' }).then(function () {
        closeDrawer();
        loadTasks();
      });
    });
  }

  function addSubtask() {
    var id = $('fTaskId').value;
    var title = $('newSubtaskTitle').value.trim();
    if (!id || !title) return;
    api('/api/subtasks.php?task_id=' + id, { method: 'POST', body: { title: title } }).then(function () {
      $('newSubtaskTitle').value = '';
      return api('/api/task.php?id=' + id);
    }).then(function (data) {
      renderSubtasks(data.task.subtasks || []);
    });
  }

  function applyTemplate() {
    var id = $('fTaskId').value;
    var tpl = $('checklistTemplate') ? $('checklistTemplate').value : '';
    var mode = $('checklistTemplateMode') ? $('checklistTemplateMode').value : 'append';
    if (!id || !tpl) return notify('Выберите шаблон', 'error');
    var chain = Promise.resolve();
    if (mode === 'replace') {
      var ids = [];
      var ul = $('subtaskList');
      if (ul) {
        ul.querySelectorAll('.subtask-item').forEach(function (li) {
          var sid = parseInt(li.getAttribute('data-id'), 10);
          if (sid > 0) ids.push(sid);
        });
      }
      ids.forEach(function (sid) {
        chain = chain.then(function () {
          return api('/api/subtask.php?id=' + sid, { method: 'DELETE' });
        });
      });
    }
    chain
      .then(function () {
        return api('/api/subtasks.php?task_id=' + id, { method: 'POST', body: { template_id: parseInt(tpl, 10) } });
      })
      .then(function () {
        return api('/api/task.php?id=' + id);
      })
      .then(function (data) {
        renderSubtasks(data.task.subtasks || []);
        updateChecklistTemplatePreview();
      })
      .catch(function (e) {
        notify('Не удалось: ' + (e.message || ''), 'error');
      });
  }

  function checklistCurrentCountFromDom() {
    var ul = $('subtaskList');
    if (!ul) return 0;
    return ul.querySelectorAll('.subtask-item').length;
  }

  function updateChecklistTemplatePreview() {
    var pv = $('checklistTemplatePreview');
    if (!pv) return;
    var tplId = selectedChecklistTemplateId();
    if (!tplId) {
      pv.textContent = 'Выберите шаблон для предпросмотра.';
      return;
    }
    var mode = $('checklistTemplateMode') ? $('checklistTemplateMode').value : 'append';
    var tpl = null;
    for (var i = 0; i < checklistTemplates.length; i++) {
      if (parseInt(checklistTemplates[i].id, 10) === tplId) {
        tpl = checklistTemplates[i];
        break;
      }
    }
    var addN = tpl && Array.isArray(tpl.items) ? tpl.items.length : 0;
    var curN = checklistCurrentCountFromDom();
    if (mode === 'replace') {
      pv.textContent = 'Предпросмотр: текущих пунктов ' + curN + ', после замены будет ' + addN + '.';
    } else {
      pv.textContent = 'Предпросмотр: будет добавлено ' + addN + ' пунктов (итого ' + (curN + addN) + ').';
    }
  }

  function saveChecklistTemplate() {
    closeChecklistTemplateMenu();
    openChecklistTemplateDialog('save');
  }

  function renameChecklistTemplate() {
    closeChecklistTemplateMenu();
    openChecklistTemplateDialog('rename');
  }

  function deleteChecklistTemplate() {
    closeChecklistTemplateMenu();
    openChecklistTemplateDialog('delete');
  }

  function uploadAttachment() {
    var id = $('fTaskId').value;
    var inp = $('attachFile');
    if (!id || !inp || !inp.files || !inp.files[0]) return notify('Выберите файл', 'error');
    var fd = new FormData();
    fd.append('file', inp.files[0]);
    api('/api/task_attachments.php?task_id=' + encodeURIComponent(id), { method: 'POST', body: fd })
      .then(function () {
        inp.value = '';
        inp.dispatchEvent(new Event('change', { bubbles: true }));
        return openDrawer(id);
      })
      .catch(function (e) {
        notify('Загрузка не удалась: ' + (e.message || ''), 'error');
      });
  }

  function addLink() {
    var id = $('fTaskId').value;
    var lid = $('linkTaskId') ? parseInt($('linkTaskId').value, 10) : 0;
    var lt = $('linkType') ? $('linkType').value : 'relates';
    if (!id || lid <= 0) return notify('Укажите ID связанной задачи', 'error');
    api('/api/task_links.php?task_id=' + id, {
      method: 'POST',
      body: { linked_task_id: lid, link_type: lt },
    })
      .then(function () {
        if ($('linkTaskId')) $('linkTaskId').value = '';
        return openDrawer(id);
      })
      .catch(function (e) {
        notify('Не удалось добавить связь: ' + (e.message || ''), 'error');
      });
  }

  function refreshTemplatePick(items) {
    items = items || [];
    taskTemplates = items;
    var ttp = $('taskTemplatePick');
    if (ttp) {
      ttp.innerHTML =
        '<option value="">Шаблон…</option>' +
        items
          .map(function (tpl) {
            return '<option value="' + tpl.id + '">' + escapeHtml(tpl.name || '') + '</option>';
          })
          .join('');
      if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
    }
  }

  function bulkErrorRu(code) {
    return Core.humanizeApiError(code);
  }

  function applyBulkAction() {
    var action = $('bulkAction') ? $('bulkAction').value : '';
    var ids = selectedTaskIdsFromTable();
    if (!ids.length) {
      notify('Отметьте задачи галочками в таблице.', 'error');
      return;
    }
    var body = { task_ids: ids, action: action };
    if (action === 'set_status') {
      body.status = $('bulkStatus') ? $('bulkStatus').value : '';
      if (!body.status) return notify('Выберите статус', 'error');
    } else if (action === 'set_assignee') {
      var av = $('bulkAssignee') ? $('bulkAssignee').value : '';
      body.assignee_id = av ? parseInt(av, 10) : null;
    } else {
      return notify('Выберите действие в списке.', 'error');
    }
    var btn = $('btnBulkApply');
    if (btn) btn.disabled = true;
    setBulkProgress('Применение изменений...', true);
    api('/api/tasks_bulk.php', { method: 'POST', body: body })
      .then(function (res) {
        var okN = 0;
        if (res) {
          if (res.updated_count != null) {
            okN = parseInt(res.updated_count, 10) || 0;
          } else if (Array.isArray(res.updated)) {
            okN = res.updated.length;
          }
        }
        var fail = (res && res.failed) || [];
        var parts = [];
        if (okN > 0) {
          parts.push('Применено к ' + okN + ' задачам');
        }
        if (fail.length) {
          var sample = fail
            .slice(0, 5)
            .map(function (f) {
              return '#' + f.task_id + ': ' + bulkErrorRu(f.error);
            })
            .join('; ');
          parts.push('не удалось: ' + fail.length + ' (' + sample + (fail.length > 5 ? '…' : '') + ')');
        }
        if (okN > 0 && fail.length === 0) {
          notify('Массовое действие выполнено для ' + okN + ' задач.', 'success');
        } else if (okN > 0 && fail.length) {
          notify(parts.join('. '), 'error');
        } else if (fail.length) {
          notify(parts.join('. '), 'error');
        } else {
          notify('Изменений не внесено.', 'error');
        }
        var sa = $('taskSelectAll');
        if (sa) sa.checked = false;
        refreshBulkSelectionCount();
        return loadTasks();
      })
      .then(function () {
        updateBulkPreview();
      })
      .catch(function (e) {
        notify('Не выполнено: ' + (e.message || ''), 'error');
      })
      .then(function () {
        if (btn) btn.disabled = false;
        setBulkProgress('', false);
      });
  }

  function syncBulkActionUi() {
    var a = $('bulkAction');
    var bs = $('bulkStatus');
    var ba = $('bulkAssignee');
    if (!a || !bs || !ba) return;
    var v = a.value;
    bs.hidden = v !== 'set_status';
    ba.hidden = v !== 'set_assignee';
    updateBulkPreview();
  }

  function createFromSelectedTemplate() {
    var pid = $('taskTemplatePick') ? $('taskTemplatePick').value : '';
    var tid = pid ? parseInt(pid, 10) : 0;
    if (!tid) {
      notify('Выберите шаблон из списка.', 'error');
      return;
    }
    api('/api/tasks.php', { method: 'POST', body: { template_id: tid } })
      .then(function (res) {
        if (res && res.id != null) openDrawer(parseInt(res.id, 10));
        return loadTasks();
      })
      .catch(function (e) {
        notify('Не удалось создать: ' + (e.message || ''), 'error');
      });
  }

  function saveCurrentTaskAsTemplate() {
    var tid = $('fTaskId') ? $('fTaskId').value : '';
    var id = tid ? parseInt(tid, 10) : 0;
    if (!id) return;
    var titleHint = $('fTitle') ? $('fTitle').value.trim() || 'Шаблон' : 'Шаблон';
    openAppInlinePrompt({
      title: 'Название шаблона',
      message: 'Введите название нового шаблона задачи.',
      value: titleHint,
      maxlength: 120,
      placeholder: 'Название шаблона',
      confirmLabel: 'Сохранить',
    }).then(function (name) {
      if (name == null) return;
      var trimmed = (name || '').trim();
      if (!trimmed) return;
      api('/api/task_templates.php', {
        method: 'POST',
        body: { from_task_id: id, name: trimmed },
      })
        .then(function () {
          return api('/api/task_templates.php');
        })
        .then(function (d) {
          refreshTemplatePick(d.items || []);
          notify('Шаблон сохранён.', 'success');
        })
        .catch(function (e) {
          notify('Ошибка: ' + (e.message || ''), 'error');
        });
    });
  }

  function closeCreateTaskMenu() {
    var m = $('createTaskMenu');
    var b = $('btnCreateMenu');
    if (m) m.hidden = true;
    if (b) b.setAttribute('aria-expanded', 'false');
  }

  window.__plannerCloseCreateMenu = closeCreateTaskMenu;

  function toggleCreateTaskMenu() {
    if (typeof window.bxCustomSelectCloseAll === 'function') window.bxCustomSelectCloseAll();
    var m = $('createTaskMenu');
    var b = $('btnCreateMenu');
    if (!m || !b) return;
    var willOpen = !!m.hidden;
    m.hidden = !willOpen;
    b.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  }

  function openQuickTaskDialog() {
    closeCreateTaskMenu();
    var dlg = $('quickTaskDialog');
    var ov = $('quickTaskOverlay');
    if (!dlg || !ov) return;
    var qtp = $('quickTaskProject');
    var fp = $('filterProject');
    if (qtp && fp && fp.value) qtp.value = fp.value;
    if (typeof window.bxCustomSelectSync === 'function' && qtp) window.bxCustomSelectSync(qtp);
    var tit = $('quickTaskTitle');
    if (tit) {
      tit.value = '';
      tit.focus();
    }
    ov.hidden = false;
    dlg.hidden = false;
  }

  function closeQuickTaskDialog() {
    var dlg = $('quickTaskDialog');
    var ov = $('quickTaskOverlay');
    if (dlg) dlg.hidden = true;
    if (ov) ov.hidden = true;
  }

  function submitQuickTask() {
    var tit = $('quickTaskTitle');
    var qtp = $('quickTaskProject');
    var qta = $('quickTaskAssignee');
    var vis = $('quickTaskVisibleManagers');
    var v = tit && tit.value ? tit.value.trim() : '';
    if (!v) {
      notify('Введите заголовок', 'error');
      return;
    }
    if (!qtp || !qtp.value) {
      notify('Выберите проект', 'error');
      return;
    }
    var body = {
      title: v,
      project_id: parseInt(qtp.value, 10),
      is_quick_task: true,
      status: 'in_progress',
      quick_visible_managers: !!(vis && vis.checked),
    };
    if (qta && qta.value) body.assignee_id = parseInt(qta.value, 10);
    try {
      localStorage.setItem('planner_quick_default_project', String(qtp.value));
    } catch (eLs) {}
    api('/api/tasks.php', { method: 'POST', body: body })
      .then(function () {
        closeQuickTaskDialog();
        notify('Быстрая задача создана', 'success');
        return loadTasks();
      })
      .catch(function (e) {
        notify('Ошибка: ' + (e.message || ''), 'error');
      });
  }

