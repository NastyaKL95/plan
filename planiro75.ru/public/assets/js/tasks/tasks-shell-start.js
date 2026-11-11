(function () {
  var TC = typeof window.__plannerTasksConstants === 'object' && window.__plannerTasksConstants ? window.__plannerTasksConstants : {};
  var STATUS_ORDER = TC.STATUS_ORDER || ['new', 'in_progress', 'review', 'done', 'cancelled'];
  var STATUS_LABEL = TC.STATUS_LABEL || {
    new: 'Новые',
    in_progress: 'В работе',
    review: 'Проверка',
    done: 'Готово',
    cancelled: 'Отмена',
  };
  var LIST_STATUS_LABEL = TC.LIST_STATUS_LABEL || {
    new: 'Новая',
    in_progress: 'В работе',
    review: 'На проверке',
    done: 'Выполнена',
    cancelled: 'Отменена',
  };
  var projects = [];
  var users = [];
  var departmentsCache = [];
  var tagsList = [];
  var checklistTemplates = [];
  var taskTemplates = [];
  var tasksCache = [];
  var tasksPagination = { total: 0, limit: 80, hasMore: false, loading: false };
  var tasksLoadMoreObserver = null;
  /** Согласовано с task.may_edit: чек-листы, вложения, связи. */
  var taskModalMayEditCurrent = true;
  var drawerChatState = {
    taskId: 0,
    comments: [],
    history: [],
    hasMoreOlder: false,
    loadingOlder: false,
    oldestId: null,
  };
  var skipAutoDefaultView = !!(projectParam || qParam || openTaskParam);
  var currentSubtaskParticipantOptions = [];
  var checklistTemplateDialogState = { mode: '', templateId: 0, taskId: 0 };
  var checklistTemplateDialogFocusBack = null;
  var checklistTemplateMenuFocusBack = null;
  var appInlineDialogResolver = null;
  var appInlineDialogMode = '';
  var appInlineDialogFocusBack = null;
  var participantPickerState = { role: '', query: '' };
  var viewMode = 'board';
  var qParam = new URLSearchParams(window.location.search).get('q') || '';
  var openTaskParam = new URLSearchParams(window.location.search).get('open') || '';
  var projectParam = new URLSearchParams(window.location.search).get('project_id') || '';
  var myPlanStages = [];
  var myPlanStagesFeature = false;
  var drawerInitialStatus = '';
  var suppressCardClick = false;
  var calInit = new Date();
  var calendarYear = calInit.getFullYear();
  var calendarMonth = calInit.getMonth();
  var createModalState = { planLane: null, myPlanStageId: null, deadlineLane: null, chatDrafts: [] };
  var taskModalFocusBack = null;

  try {
    var savedView = localStorage.getItem('planner_tasks_view');
    if (savedView === 'list') viewMode = 'list';
    else if (savedView === 'plan') viewMode = 'plan';
    else if (savedView === 'deadlines') viewMode = 'deadlines';
    else if (savedView === 'calendar') viewMode = 'calendar';
    else if (savedView === 'gantt') viewMode = 'gantt';
    else viewMode = 'board';
  } catch (eView) {}

  function setViewTabs() {
    var kb = $('kanban');
    var lw = $('taskListWrap');
    var pw = $('taskPlanWrap');
    var dw = $('taskDeadlinesWrap');
    var cw = $('taskCalendarWrap');
    var gw = $('taskGanttWrap');
    var tabBoard = $('tabViewBoard');
    var tabList = $('tabViewList');
    var tabPlan = $('tabViewPlan');
    var tabDeadlines = $('tabViewDeadlines');
    var tabCalendar = $('tabViewCalendar');
    var tabGantt = $('tabViewGantt');
    var isList = viewMode === 'list';
    var isPlan = viewMode === 'plan';
    var isDeadlines = viewMode === 'deadlines';
    var isCalendar = viewMode === 'calendar';
    var isGantt = viewMode === 'gantt';
    var isBoard = !isList && !isPlan && !isDeadlines && !isCalendar && !isGantt;
    if (kb) kb.hidden = !isBoard;
    if (pw) pw.hidden = !isPlan;
    if (dw) dw.hidden = !isDeadlines;
    if (cw) cw.hidden = !isCalendar;
    if (gw) gw.hidden = !isGantt;
    if (lw) lw.hidden = !isList;
    if (tabBoard) {
      tabBoard.classList.toggle('is-active', isBoard);
      tabBoard.setAttribute('aria-selected', isBoard ? 'true' : 'false');
    }
    if (tabList) {
      tabList.classList.toggle('is-active', isList);
      tabList.setAttribute('aria-selected', isList ? 'true' : 'false');
    }
    if (tabPlan) {
      tabPlan.classList.toggle('is-active', isPlan);
      tabPlan.setAttribute('aria-selected', isPlan ? 'true' : 'false');
    }
    if (tabDeadlines) {
      tabDeadlines.classList.toggle('is-active', isDeadlines);
      tabDeadlines.setAttribute('aria-selected', isDeadlines ? 'true' : 'false');
    }
    if (tabCalendar) {
      tabCalendar.classList.toggle('is-active', isCalendar);
      tabCalendar.setAttribute('aria-selected', isCalendar ? 'true' : 'false');
    }
    if (tabGantt) {
      tabGantt.classList.toggle('is-active', isGantt);
      tabGantt.setAttribute('aria-selected', isGantt ? 'true' : 'false');
    }
    var mpt = $('myPlanToolbar');
    if (mpt) mpt.hidden = !(isPlan && myPlanStagesFeature);
    var bb = $('bulkBar');
    if (bb) bb.hidden = !isList;
    var th = $('toolbarHint');
    if (th) {
      if (isList) {
        th.textContent = 'Отметьте задачи и примените массовое действие.';
      } else if (isPlan) {
        th.textContent =
          'Мой план (как в Битрикс24): стадии только у вас; перенос карточки не меняет срок. Без стадии задача в «Не спланированы».';
      } else if (isDeadlines) {
        th.textContent = 'Перетащите карточку — срок или статус обновятся по колонке';
      } else if (isCalendar) {
        th.textContent = 'Задачи с дедлайном в выбранном месяце; нажмите на название в ячейке';
      } else if (isGantt) {
        th.textContent =
          'Диаграмма Ганта по текущему списку: от создания до срока (без срока — до сегодня или закрытия). Клик по названию — карточка';
      } else {
        th.textContent = 'Перетащите карточку в колонку статуса';
      }
    }
  }

  function selectedMulti(sel) {
    if (!sel) return [];
    var o = [];
    for (var i = 0; i < sel.options.length; i++) {
      if (sel.options[i].selected) {
        var v = parseInt(sel.options[i].value, 10);
        if (v > 0) o.push(v);
      }
    }
    return o;
  }

  function setMultiSelected(sel, ids) {
    if (!sel || !ids) return;
    var set = {};
    ids.forEach(function (id) {
      set[String(id)] = true;
    });
    for (var i = 0; i < sel.options.length; i++) {
      var v = sel.options[i].value;
      sel.options[i].selected = !!set[v];
    }
  }

  function participantLabelById(id) {
    var n = parseInt(id, 10);
    if (!(n > 0)) return '';
    for (var i = 0; i < users.length; i++) {
      if (parseInt(users[i].id, 10) === n) return users[i].name || '';
    }
    return '';
  }

  function resolveParticipantIdFromSearchInput() {
    var hidden = $('filterAdvParticipant');
    var input = $('filterAdvParticipantSearch');
    if (!hidden || !input) return '';
    var raw = (input.value || '').trim();
    if (!raw) {
      hidden.value = '';
      return '';
    }
    var fromIdPattern = raw.match(/#(\d+)\s*$/);
    if (fromIdPattern) {
      hidden.value = fromIdPattern[1];
      return hidden.value;
    }
    for (var i = 0; i < users.length; i++) {
      if ((users[i].name || '').toLowerCase() === raw.toLowerCase()) {
        hidden.value = String(users[i].id);
        return hidden.value;
      }
    }
    hidden.value = '';
    return '';
  }

  function setParticipantSearchById(id) {
    var hidden = $('filterAdvParticipant');
    var input = $('filterAdvParticipantSearch');
    if (hidden) hidden.value = id ? String(id) : '';
    if (!input) return;
    if (!id) {
      input.value = '';
      return;
    }
    var label = participantLabelById(id);
    input.value = label ? label + ' #' + String(id) : String(id);
  }

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

  function clearOpenParamFromUrl() {
    try {
      var u = new URL(window.location.href);
      if (!u.searchParams.has('open')) return;
      u.searchParams.delete('open');
      var next = u.pathname + (u.search ? u.search : '') + (u.hash ? u.hash : '');
      window.history.replaceState({}, '', next);
      openTaskParam = '';
    } catch (eUrl) {}
  }

  function notify(message, type) {
    if (Core.notify) Core.notify(message, { type: type || 'info' });
    else window.alert(message);
  }
  function currentUserId() {
    return typeof window.__PLANNER_USER_ID__ === 'number' ? window.__PLANNER_USER_ID__ : 0;
  }

  function currentUserDisplayName() {
    return typeof window.__PLANNER_USER_NAME__ === 'string' ? window.__PLANNER_USER_NAME__ : '';
  }

  function userPickerLabel(u) {
    var name = u.name || '';
    if (u.account_status === 'blocked' || u.is_blocked) {
      return name + ' (заблокирован)';
    }
    return name;
  }


  function setModalDensity(mode) {
    var card = document.querySelector('.task-modal-card');
    if (!card) return;
    var compact = mode === 'compact';
    card.classList.toggle('task-modal-card--compact', compact);
    var btnComfort = $('btnDensityComfort');
    var btnCompact = $('btnDensityCompact');
    if (btnComfort) btnComfort.classList.toggle('is-active', !compact);
    if (btnCompact) btnCompact.classList.toggle('is-active', compact);
    try {
      localStorage.setItem('planner_modal_density', compact ? 'compact' : 'comfortable');
    } catch (e1) {}
  }

  function initModalDensityControls() {
    var saved = '';
    try {
      saved = localStorage.getItem('planner_modal_density') || '';
    } catch (e2) {}
    setModalDensity(saved === 'compact' ? 'compact' : 'comfortable');
    var btnComfort = $('btnDensityComfort');
    var btnCompact = $('btnDensityCompact');
    if (btnComfort) {
      btnComfort.addEventListener('click', function () {
        setModalDensity('comfortable');
      });
    }
    if (btnCompact) {
      btnCompact.addEventListener('click', function () {
        setModalDensity('compact');
      });
    }
  }

  function loadMeta() {
    return Promise.all([
      api('/api/projects.php'),
      api('/api/users.php'),
      api('/api/tags.php').catch(function () {
        return { ok: true, items: [] };
      }),
      api('/api/checklist_templates.php').catch(function () {
        return { ok: true, items: [] };
      }),
      api('/api/task_templates.php').catch(function () {
        return { ok: true, items: [] };
      }),
      api('/api/departments.php').catch(function () {
        return { ok: true, items: [] };
      }),
      api('/api/my_plan_stages.php').catch(function () {
        return { ok: true, items: [], feature: false };
      }),
    ]).then(function (res) {
      projects = res[0].items || [];
      users = res[1].items || [];
      tagsList = res[2].items || [];
      checklistTemplates = res[3].items || [];
      taskTemplates = res[4].items || [];
      departmentsCache = res[5].items || [];
      var mps = res[6];
      myPlanStages = (mps && mps.items) || [];
      myPlanStagesFeature = !!(mps && mps.feature);
      var fp = $('filterProject');
      var fProj = $('fProject');
      function projLabel(p) {
        var dn = p.department_name ? p.department_name + ' — ' : '';
        return dn + p.name;
      }
      if (fp) {
        fp.innerHTML =
          '<option value="">Все проекты</option>' +
          projects
            .map(function (p) {
              return '<option value="' + p.id + '">' + escapeHtml(projLabel(p)) + '</option>';
            })
            .join('');
        if (projectParam) {
          var hasProject = projects.some(function (p) {
            return String(p.id) === String(projectParam);
          });
          if (hasProject) fp.value = String(projectParam);
        }
      }
      if (fProj) {
        fProj.innerHTML = projects
          .map(function (p) {
            return '<option value="' + p.id + '">' + escapeHtml(projLabel(p)) + '</option>';
          })
          .join('');
      }
      refillAssigneeOptions();
      fillParticipantSelects();
      refreshTemplatePick(taskTemplates);
      var bulkAsg = $('bulkAssignee');
      if (bulkAsg) {
        bulkAsg.innerHTML =
          '<option value="">Не назначен</option>' +
          assignableUsers()
            .map(function (u) {
              return '<option value="' + u.id + '">' + escapeHtml(userPickerLabel(u)) + '</option>';
            })
            .join('');
      }
      var faa = $('filterAdvAssignee');
      if (faa) {
        faa.innerHTML =
          '<option value="">Любой</option>' +
          users
            .map(function (u) {
              return '<option value="' + u.id + '">' + escapeHtml(userPickerLabel(u)) + '</option>';
            })
            .join('');
      }
      var fapList = $('filterAdvParticipantList');
      if (fapList) {
        fapList.innerHTML = users
          .map(function (u) {
            return '<option value="' + escapeHtml((u.name || '') + ' #' + String(u.id)) + '"></option>';
          })
          .join('');
      }
      var ftg = $('filterAdvTags');
      if (ftg) {
        ftg.innerHTML =
          '<option value="">Любая</option>' +
          tagsList
            .map(function (tg) {
              return '<option value="' + tg.id + '">' + escapeHtml(tg.name || '') + '</option>';
            })
            .join('');
      }
      var svd = $('savedViewDept');
      if (svd) {
        svd.innerHTML =
          '<option value="">Вся организация</option>' +
          departmentsCache
            .map(function (d) {
              return '<option value="' + d.id + '">' + escapeHtml(d.name || '') + '</option>';
            })
            .join('');
      }
      var fdep = $('filterDepartment');
      if (fdep) {
        fdep.innerHTML =
          '<option value="">— любой —</option>' +
          departmentsCache
            .map(function (d) {
              return '<option value="' + d.id + '">' + escapeHtml(d.name || '') + '</option>';
            })
            .join('');
      }
      syncSavedViewScopeUi();
      var qtp = $('quickTaskProject');
      if (qtp && projects.length) {
        qtp.innerHTML = projects
          .map(function (p) {
            return '<option value="' + p.id + '">' + escapeHtml(projLabel(p)) + '</option>';
          })
          .join('');
        try {
          var sdp = localStorage.getItem('planner_quick_default_project');
          if (sdp && projects.some(function (p) {
            return String(p.id) === sdp;
          })) {
            qtp.value = sdp;
          } else if (fProj && fProj.value) {
            qtp.value = fProj.value;
          } else {
            qtp.value = String(projects[0].id);
          }
        } catch (eQtp) {
          qtp.value = String(projects[0].id);
        }
      }
      var qta = $('quickTaskAssignee');
      var quickUsers = assignableUsers();
      if (qta && quickUsers.length) {
        qta.innerHTML = quickUsers
          .map(function (u) {
            return '<option value="' + u.id + '">' + escapeHtml(u.name) + '</option>';
          })
          .join('');
        var myId = currentUserId();
        if (myId) qta.value = String(myId);
      }
      var ct = $('checklistTemplate');
      if (ct) renderChecklistTemplatePick();
      if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
      return refreshSavedViewsPick().then(function (viewItems) {
        syncUiFromUrlDrillParams();
        if (!skipAutoDefaultView && viewItems && viewItems.length) {
          var def = null;
          for (var vi = 0; vi < viewItems.length; vi++) {
            if (parseInt(viewItems[vi].is_default, 10) === 1) {
              def = viewItems[vi];
              break;
            }
          }
          if (def && def.filters_json != null) {
            try {
              var filt0 = typeof def.filters_json === 'string' ? JSON.parse(def.filters_json) : def.filters_json;
              if (filt0 && typeof filt0 === 'object') {
                applyFiltersFromObject(filt0);
              }
            } catch (eDef0) {}
          }
        }
        return viewItems;
      });
    });
  }

  function renderChecklistTemplatePick() {
    var ct = $('checklistTemplate');
    if (!ct) return;
    ct.innerHTML =
      '<option value="">Шаблон чек-листа…</option>' +
      checklistTemplates
        .map(function (tpl) {
          return '<option value="' + tpl.id + '">' + escapeHtml(tpl.name) + '</option>';
        })
        .join('');
    updateChecklistTemplatePreview();
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function refreshChecklistTemplates() {
    return api('/api/checklist_templates.php').then(function (res) {
      checklistTemplates = (res && res.items) || [];
      renderChecklistTemplatePick();
      return checklistTemplates;
    });
  }

  function selectedChecklistTemplateId() {
    var ct = $('checklistTemplate');
    if (!ct || !ct.value) return 0;
    var id = parseInt(ct.value, 10);
    return id > 0 ? id : 0;
  }

  function closeChecklistTemplateDialog() {
    var box = $('checklistTemplateDialog');
    if (box) {
      box.hidden = true;
      box.classList.remove('is-open');
    }
    checklistTemplateDialogState = { mode: '', templateId: 0, taskId: 0 };
    if (checklistTemplateDialogFocusBack && checklistTemplateDialogFocusBack.focus) checklistTemplateDialogFocusBack.focus();
    checklistTemplateDialogFocusBack = null;
  }

  function closeChecklistTemplateMenu() {
    var menu = $('checklistTemplateMenu');
    var btn = $('btnChecklistTemplateMenu');
    if (menu) {
      menu.hidden = true;
      menu.classList.remove('is-open');
    }
    if (btn) btn.setAttribute('aria-expanded', 'false');
    if (checklistTemplateMenuFocusBack && checklistTemplateMenuFocusBack.focus) checklistTemplateMenuFocusBack.focus();
    checklistTemplateMenuFocusBack = null;
  }

  function toggleChecklistTemplateMenu() {
    var menu = $('checklistTemplateMenu');
    var btn = $('btnChecklistTemplateMenu');
    if (!menu || !btn) return;
    var willOpen = !!menu.hidden;
    menu.hidden = !willOpen;
    menu.classList.toggle('is-open', willOpen);
    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    if (willOpen) {
      checklistTemplateMenuFocusBack = document.activeElement;
      var first = menu.querySelector('.checklist-template-menu-item');
      if (first && first.focus) first.focus();
    } else {
      checklistTemplateMenuFocusBack = null;
    }
  }

  function openChecklistTemplateDialog(mode) {
    var box = $('checklistTemplateDialog');
    var title = $('checklistTemplateDialogTitle');
    var text = $('checklistTemplateDialogText');
    var input = $('checklistTemplateDialogInput');
    var submit = $('checklistTemplateDialogSubmit');
    if (!box || !title || !text || !input || !submit) return;
    checklistTemplateDialogFocusBack = document.activeElement;
    var taskId = parseInt(($('fTaskId') && $('fTaskId').value) || '0', 10);
    var tplId = selectedChecklistTemplateId();
    var ct = $('checklistTemplate');
    var currentName = '';
    if (ct && ct.selectedIndex >= 0) currentName = (ct.options[ct.selectedIndex].textContent || '').trim();

    if (mode === 'save') {
      var hint = $('fTitle') ? $('fTitle').value.trim() || 'Чек-лист' : 'Чек-лист';
      title.textContent = 'Сохранить шаблон';
      text.textContent =
        taskId > 0
          ? 'Введите название нового шаблона чек-листа.'
          : 'Сначала сохраните задачу, затем можно сохранить чек-лист как шаблон.';
      input.hidden = false;
      input.value = hint;
      submit.textContent = 'Сохранить';
      checklistTemplateDialogState = { mode: mode, templateId: 0, taskId: taskId };
    } else if (mode === 'rename') {
      title.textContent = 'Переименовать шаблон';
      text.textContent =
        tplId > 0
          ? 'Введите новое название выбранного шаблона.'
          : 'Сначала выберите шаблон в списке выше.';
      input.hidden = false;
      input.value = currentName || 'Шаблон чек-листа';
      submit.textContent = 'Сохранить';
      checklistTemplateDialogState = { mode: mode, templateId: tplId, taskId: 0 };
    } else if (mode === 'delete') {
      title.textContent = 'Удалить шаблон';
      text.textContent =
        tplId > 0
          ? 'Удалить шаблон "' + currentName + '"? Это действие нельзя отменить.'
          : 'Сначала выберите шаблон в списке выше.';
      input.hidden = true;
      input.value = '';
      submit.textContent = 'Удалить';
      checklistTemplateDialogState = { mode: mode, templateId: tplId, taskId: 0 };
    } else {
      return;
    }
    box.hidden = false;
    box.classList.add('is-open');
    var canSubmit =
      (mode === 'save' && taskId > 0) ||
      (mode === 'rename' && tplId > 0) ||
      (mode === 'delete' && tplId > 0);
    submit.disabled = !canSubmit;
    if (!input.hidden) {
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);
    } else {
      submit.focus();
    }
  }

  function submitChecklistTemplateDialog() {
    var mode = checklistTemplateDialogState.mode;
    if (!mode) return;
    var input = $('checklistTemplateDialogInput');
    var rawName = input ? input.value : '';
    var name = (rawName || '').trim();
    if ((mode === 'save' || mode === 'rename') && !name) {
      return notify('Укажите название шаблона.', 'error');
    }
    if (mode === 'save') {
      api('/api/checklist_templates.php', {
        method: 'POST',
        body: { from_task_id: checklistTemplateDialogState.taskId, name: name },
      })
        .then(function () {
          return refreshChecklistTemplates();
        })
        .then(function () {
          var ct = $('checklistTemplate');
          if (ct) {
            for (var i = 0; i < ct.options.length; i++) {
              if ((ct.options[i].textContent || '').trim() === name) {
                ct.selectedIndex = i;
                break;
              }
            }
          }
          closeChecklistTemplateDialog();
        })
        .catch(function (e) {
          notify('Не удалось сохранить шаблон: ' + (e.message || 'ошибка'), 'error');
        });
      return;
    }
    if (mode === 'rename') {
      var rid = checklistTemplateDialogState.templateId;
      api('/api/checklist_templates.php?id=' + rid, { method: 'PATCH', body: { name: name } })
        .then(function () {
          return refreshChecklistTemplates();
        })
        .then(function () {
          var sel = $('checklistTemplate');
          if (sel) {
            for (var i = 0; i < sel.options.length; i++) {
              if (parseInt(sel.options[i].value, 10) === rid) {
                sel.selectedIndex = i;
                break;
              }
            }
          }
          closeChecklistTemplateDialog();
        })
        .catch(function (e) {
          notify('Не удалось переименовать шаблон: ' + (e.message || 'ошибка'), 'error');
        });
      return;
    }
    if (mode === 'delete') {
      api('/api/checklist_templates.php?id=' + checklistTemplateDialogState.templateId, { method: 'DELETE' })
        .then(function () {
          return refreshChecklistTemplates();
        })
        .then(function () {
          var sel = $('checklistTemplate');
          if (sel) sel.value = '';
          closeChecklistTemplateDialog();
        })
        .catch(function (e) {
          notify('Не удалось удалить шаблон: ' + (e.message || ''), 'error');
        });
    }
  }

  function cancelAppInlineDialog() {
    if (!appInlineDialogResolver) return;
    closeAppInlineDialog(appInlineDialogMode === 'prompt' ? null : false);
  }

  function closeAppInlineDialog(result) {
    var box = $('appInlineDialog');
    var card = box ? box.querySelector('.app-inline-dialog-card') : null;
    var textEl = $('appInlineDialogText');
    var input = $('appInlineDialogInput');
    if (card) {
      card.classList.remove('is-open');
    }
    if (box) {
      box.hidden = true;
      box.setAttribute('aria-hidden', 'true');
    }
    if (textEl) textEl.textContent = '';
    if (input) input.value = '';
    appInlineDialogMode = '';
    var fn = appInlineDialogResolver;
    appInlineDialogResolver = null;
    if (appInlineDialogFocusBack && appInlineDialogFocusBack.focus) {
      try {
        appInlineDialogFocusBack.focus();
      } catch (eA) {}
    }
    appInlineDialogFocusBack = null;
    if (fn) fn(result);
  }

  function submitAppInlineDialog() {
    if (!appInlineDialogResolver) return;
    if (appInlineDialogMode === 'confirm') {
      closeAppInlineDialog(true);
      return;
    }
    if (appInlineDialogMode === 'prompt') {
      var input = $('appInlineDialogInput');
      closeAppInlineDialog(input ? input.value : '');
    }
  }

  function openAppInlineDialog(cfg) {
    return new Promise(function (resolve) {
      if (appInlineDialogResolver) {
        var prev = appInlineDialogResolver;
        appInlineDialogResolver = null;
        prev(cfg.mode === 'prompt' ? null : false);
      }
      appInlineDialogResolver = resolve;
      appInlineDialogMode = cfg.mode;
      appInlineDialogFocusBack = document.activeElement;

      var box = $('appInlineDialog');
      var card = box ? box.querySelector('.app-inline-dialog-card') : null;
      var titleEl = $('appInlineDialogTitle');
      var textEl = $('appInlineDialogText');
      var input = $('appInlineDialogInput');
      var submitBtn = $('appInlineDialogConfirm');
      if (!box || !titleEl || !textEl || !input || !submitBtn) {
        appInlineDialogResolver = null;
        appInlineDialogMode = '';
        resolve(cfg.mode === 'prompt' ? null : false);
        return;
      }

      titleEl.textContent = cfg.title || '';
      var msg = (cfg.message || '').trim();
      textEl.textContent = msg;
      textEl.hidden = !msg;

      var isPrompt = cfg.mode === 'prompt';
      input.hidden = !isPrompt;
      if (isPrompt) {
        input.value = cfg.value != null ? String(cfg.value) : '';
        input.maxLength = cfg.maxlength != null ? cfg.maxlength : 16000;
        input.placeholder = cfg.placeholder || '';
      } else {
        input.value = '';
      }

      submitBtn.textContent = cfg.confirmLabel || 'OK';
      submitBtn.className = cfg.danger ? 'btn btn-danger-outline' : 'btn btn-primary';

      box.hidden = false;
      box.setAttribute('aria-hidden', 'false');
      if (card) {
        card.classList.add('is-open');
      }

      requestAnimationFrame(function () {
        if (isPrompt) {
          input.focus();
          try {
            input.setSelectionRange(input.value.length, input.value.length);
          } catch (eB) {}
        } else {
          submitBtn.focus();
        }
      });
    });
  }

  function openAppInlineConfirm(opts) {
    opts = opts || {};
    return openAppInlineDialog({
      mode: 'confirm',
      title: opts.title || 'Подтвердите',
      message: opts.message || '',
      confirmLabel: opts.confirmLabel || 'OK',
      danger: !!opts.danger,
    });
  }

  function openAppInlinePrompt(opts) {
    opts = opts || {};
    return openAppInlineDialog({
      mode: 'prompt',
      title: opts.title || '',
      message: opts.message || '',
      value: opts.value != null ? opts.value : '',
      placeholder: opts.placeholder || '',
      maxlength: opts.maxlength != null ? opts.maxlength : 16000,
      confirmLabel: opts.confirmLabel || 'OK',
      danger: false,
    });
  }
