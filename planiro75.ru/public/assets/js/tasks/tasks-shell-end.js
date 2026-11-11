  document.addEventListener('DOMContentLoaded', function () {
    initModalDensityControls();
    ensureNativeSelectWithoutBxDropdown('fAssignee');
    ensureNativeSelectWithoutBxDropdown('fProject');

    if ($('tasksAdvSearch') && qParam) $('tasksAdvSearch').value = qParam;

    Promise.all([loadMeta(), loadTasks()]).then(function () {
      var oidOpen = parseInt(openTaskParam, 10);
      if (oidOpen) {
        openDrawer(oidOpen);
        clearOpenParamFromUrl();
      }
    });

    if ($('filterProject')) $('filterProject').addEventListener('change', loadTasks);
    if ($('filterDepartment')) $('filterDepartment').addEventListener('change', loadTasks);
    if ($('filterTaskKind')) $('filterTaskKind').addEventListener('change', loadTasks);
    if ($('btnCreateMenu')) {
      $('btnCreateMenu').addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        toggleCreateTaskMenu();
      });
    }
    if ($('menuItemQuickTask')) {
      $('menuItemQuickTask').addEventListener('click', function (ev) {
        ev.preventDefault();
        openQuickTaskDialog();
      });
    }
    document.addEventListener('click', function (ev) {
      var wrap = document.querySelector('.bx-create-split');
      if (!wrap || !ev.target) return;
      if (wrap.contains(ev.target)) return;
      closeCreateTaskMenu();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') closeCreateTaskMenu();
    });
    if ($('quickTaskCancel')) $('quickTaskCancel').addEventListener('click', closeQuickTaskDialog);
    if ($('quickTaskOverlay')) $('quickTaskOverlay').addEventListener('click', closeQuickTaskDialog);
    if ($('quickTaskSubmit')) $('quickTaskSubmit').addEventListener('click', submitQuickTask);
    if ($('mentionQuickPick')) {
      $('mentionQuickPick').addEventListener('change', function () {
        var sel = $('mentionQuickPick');
        var ta = $('newCommentBody');
        if (!sel || !ta || !sel.value) return;
        var id = sel.value;
        ta.value = (ta.value || '') + '@[user:' + id + '] ';
        sel.value = '';
        if (typeof window.bxCustomSelectSync === 'function') window.bxCustomSelectSync(sel);
        ta.focus();
        sizeChatCommentTextarea();
      });
    }
    if ($('newCommentBody')) {
      $('newCommentBody').addEventListener('input', sizeChatCommentTextarea);
      sizeChatCommentTextarea();
    }
    if ($('filterRole')) $('filterRole').addEventListener('change', loadTasks);
    if ($('filterVisibility')) $('filterVisibility').addEventListener('change', loadTasks);
    if ($('btnApplyAdvSearch')) $('btnApplyAdvSearch').addEventListener('click', loadTasks);
    if ($('btnResetAdvSearch')) {
      $('btnResetAdvSearch').addEventListener('click', function () {
        resetAdvancedFiltersOnly();
        loadTasks();
      });
    }
    if ($('tasksAdvSearch')) {
      var gsEl = $('tasksAdvSearch');
      var gsTmo = null;
      gsEl.addEventListener('input', function () {
        if (gsTmo) clearTimeout(gsTmo);
        gsTmo = setTimeout(function () {
          loadTasks();
        }, 400);
      });
      gsEl.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          if (gsTmo) clearTimeout(gsTmo);
          loadTasks();
        }
      });
    }

    // Автоприменение расширенных фильтров (без кнопки) + чипсы активных фильтров
    (function () {
      var tmo = null;
      function kick() {
        if (tmo) clearTimeout(tmo);
        tmo = setTimeout(function () {
          loadTasks();
        }, 250);
      }
      var ids = [
        'filterAdvAssignee',
        'filterAdvParticipantSearch',
        'filterAdvTags',
        'filterDueFrom',
        'filterDueTo',
        'filterCommentsMode',
        'filterAttachmentsMode',
        'filterDepartment',
        'filterTaskKind',
        'filterIncludeArchivedDone',
      ];
      ids.forEach(function (id) {
        var el = $(id);
        if (!el) return;
        el.addEventListener('change', kick);
        if (el.tagName === 'INPUT') el.addEventListener('input', kick);
      });
      syncActiveFiltersChips();
      syncAdvMoreButtonLabel();
    })();

    (function () {
      var det = $('advSearchDetails');
      var btn = $('btnAdvMore');
      if (!det || !btn) return;
      try {
        var saved = localStorage.getItem('planner_adv_more') || '';
        if (saved === '1') det.classList.add('is-more-open');
      } catch (eS) {}
      btn.setAttribute('aria-expanded', det.classList.contains('is-more-open') ? 'true' : 'false');
      btn.addEventListener('click', function () {
        var on = !det.classList.contains('is-more-open');
        if (on) det.classList.add('is-more-open');
        else det.classList.remove('is-more-open');
        btn.setAttribute('aria-expanded', on ? 'true' : 'false');
        try {
          localStorage.setItem('planner_adv_more', on ? '1' : '0');
        } catch (eW) {}
      });
      syncAdvMoreButtonLabel();
    })();

    if ($('btnClearOneAdvFilter')) {
      $('btnClearOneAdvFilter').addEventListener('click', function () {
        resetAdvancedFiltersOnly();
        loadTasks();
      });
    }
    if ($('btnClearAllFilters')) {
      $('btnClearAllFilters').addEventListener('click', function () {
        applyFiltersFromObject({
          project_id: '',
          role_scope: '',
          visibility_scope: '',
          q: '',
          assignee_id: '',
          participant_user_id: '',
          tag_ids: [],
          due_from: '',
          due_to: '',
          comments_mode: '',
          attachments_mode: '',
          department_id: '',
          task_kind: '',
          include_archived_done: 0,
        });
        loadTasks();
      });
    }
    if ($('btnSaveView')) {
      $('btnSaveView').addEventListener('click', function () {
        openAppInlinePrompt({
          title: 'Сохранить представление',
          message: 'Введите название. Сохраняются текущие фильтры и строка поиска.',
          value: '',
          maxlength: 120,
          confirmLabel: 'Сохранить',
        }).then(function (name) {
          if (name == null) return;
          var nm = (name || '').trim();
          if (!nm) return;
          var body = {
            name: nm,
            scope: 'personal',
            filters: collectCurrentFiltersObject(),
          };
          var sc = $('savedViewScope');
          if (sc && sc.value === 'team') {
            body.scope = 'team';
            if (userMayPickViewDepartment()) {
              var dep = $('savedViewDept');
              if (dep && dep.value !== '') {
                body.department_id = parseInt(dep.value, 10);
              } else {
                body.department_id = null;
              }
            }
          }
          api('/api/task_saved_views.php', {
            method: 'POST',
            body: body,
          })
            .then(function (res) {
              notify('Представление сохранено.', 'success');
              var newId = res && res.id ? String(res.id) : '';
              if ($('savedViewSearch')) $('savedViewSearch').value = '';
              return refreshSavedViewsPick().then(function () {
                if (newId && $('savedViewPick')) $('savedViewPick').value = newId;
              });
            })
            .catch(function (e) {
              notify('Не удалось сохранить: ' + (e.message || ''), 'error');
            });
        });
      });
    }
    if ($('savedViewScope')) {
      $('savedViewScope').addEventListener('change', syncSavedViewScopeUi);
      syncSavedViewScopeUi();
    }
    if ($('btnDeleteView')) {
      $('btnDeleteView').addEventListener('click', function () {
        var sid = $('savedViewPick') ? $('savedViewPick').value : '';
        if (!sid) {
          notify('Выберите представление в списке.', 'error');
          return;
        }
        openAppInlineConfirm({
          title: 'Удалить представление?',
          message: 'Удалить это сохранение фильтров?',
          confirmLabel: 'Удалить',
          danger: true,
        }).then(function (ok) {
          if (!ok) return;
          api('/api/task_saved_views.php?id=' + encodeURIComponent(sid), { method: 'DELETE' })
            .then(function () {
              notify('Удалено.', 'success');
              if ($('savedViewPick')) $('savedViewPick').value = '';
              return refreshSavedViewsPick();
            })
            .catch(function (e) {
              notify('Ошибка: ' + (e.message || ''), 'error');
            });
        });
      });
    }
    if ($('btnLoadMoreTasks')) {
      $('btnLoadMoreTasks').addEventListener('click', function () {
        loadMoreTasks();
      });
    }
    if ($('btnLoadOlderComments')) {
      $('btnLoadOlderComments').addEventListener('click', function () {
        var tid = drawerChatState.taskId;
        if (!tid || !drawerChatState.hasMoreOlder || drawerChatState.loadingOlder || !drawerChatState.oldestId) {
          return;
        }
        drawerChatState.loadingOlder = true;
        syncLoadOlderChatUi();
        var sc = $('taskModalChatScroll');
        var prevH = sc ? sc.scrollHeight : 0;
        var prevTop = sc ? sc.scrollTop : 0;
        api(commentsApiUrl(tid, drawerChatState.oldestId, 80))
          .then(function (pack) {
            var older = (pack && pack.items) || [];
            drawerChatState.comments = older.concat(drawerChatState.comments);
            drawerChatState.hasMoreOlder = !!(pack && (pack.has_more_older === true || pack.has_more_older === 1));
            var mn = null;
            drawerChatState.comments.forEach(function (c) {
              var x = parseInt(c.id, 10);
              if (x > 0 && (mn === null || x < mn)) {
                mn = x;
              }
            });
            drawerChatState.oldestId = mn;
            renderChatFeed(drawerChatState.comments, drawerChatState.history);
            if (sc) {
              var newH = sc.scrollHeight;
              sc.scrollTop = newH - prevH + prevTop;
            }
          })
          .catch(function () {
            notify('Не удалось загрузить сообщения.', 'error');
          })
          .then(function () {
            drawerChatState.loadingOlder = false;
            syncLoadOlderChatUi();
          });
      });
    }
    if ($('btnUpdateSavedView')) {
      $('btnUpdateSavedView').addEventListener('click', function () {
        var sid = $('savedViewPick') ? $('savedViewPick').value : '';
        if (!sid) {
          notify('Выберите представление в списке.', 'error');
          return;
        }
        api('/api/task_saved_views.php', {
          method: 'PATCH',
          body: { id: parseInt(sid, 10), filters: collectCurrentFiltersObject() },
        })
          .then(function () {
            notify('Представление обновлено.', 'success');
            return refreshSavedViewsPick();
          })
          .catch(function (e) {
            notify('Не удалось обновить: ' + (e.message || ''), 'error');
          });
      });
    }
    if ($('btnDuplicateSavedView')) {
      $('btnDuplicateSavedView').addEventListener('click', function () {
        var sid = $('savedViewPick') ? $('savedViewPick').value : '';
        if (!sid) {
          notify('Выберите представление для копирования.', 'error');
          return;
        }
        openAppInlinePrompt({
          title: 'Дублировать представление',
          message: 'Название новой копии.',
          value: 'Копия',
          maxlength: 120,
          confirmLabel: 'Создать',
        }).then(function (name) {
          if (name == null) return;
          var nm = (name || '').trim();
          if (!nm) return;
          var body = {
            name: nm,
            scope: 'personal',
            from_view_id: parseInt(sid, 10),
          };
          var sc = $('savedViewScope');
          if (sc && sc.value === 'team') {
            body.scope = 'team';
            if (userMayPickViewDepartment()) {
              var dep = $('savedViewDept');
              if (dep && dep.value !== '') {
                body.department_id = parseInt(dep.value, 10);
              } else {
                body.department_id = null;
              }
            }
          }
          api('/api/task_saved_views.php', { method: 'POST', body: body })
            .then(function (res) {
              notify('Копия создана.', 'success');
              return refreshSavedViewsPick().then(function () {
                if (res && res.id && $('savedViewPick')) {
                  $('savedViewPick').value = String(res.id);
                }
              });
            })
            .catch(function (e) {
              notify('Ошибка: ' + (e.message || ''), 'error');
            });
        });
      });
    }
    if ($('btnDefaultSavedView')) {
      $('btnDefaultSavedView').addEventListener('click', function () {
        var sid = $('savedViewPick') ? $('savedViewPick').value : '';
        if (!sid) {
          notify('Выберите представление.', 'error');
          return;
        }
        api('/api/task_saved_views.php', {
          method: 'PATCH',
          body: { id: parseInt(sid, 10), make_default: true },
        })
          .then(function () {
            setSavedViewDefaultLocalId(sid);
            notify('Это представление будет открываться по умолчанию.', 'success');
            return refreshSavedViewsPick();
          })
          .catch(function (e) {
            var msg = String((e && e.message) || '').toLowerCase();
            if (msg.indexOf('миграц') !== -1 || msg.indexOf('migration') !== -1) {
              setSavedViewDefaultLocalId(sid);
              notify('Сохранено локально: это представление будет по умолчанию в этом браузере.', 'success');
              refreshSavedViewsPick();
              return;
            }
            notify('Не удалось: ' + (e.message || ''), 'error');
          });
      });
    }
    if ($('savedViewPick')) {
      $('savedViewPick').addEventListener('change', function () {
        var id = $('savedViewPick').value;
        if (!id) return;
        var raw = $('savedViewPick').dataset.viewsJson;
        var items = [];
        try {
          items = raw ? JSON.parse(raw) : [];
        } catch (eSv) {
          items = [];
        }
        var found = null;
        for (var i = 0; i < items.length; i++) {
          if (String(items[i].id) === String(id)) {
            found = items[i];
            break;
          }
        }
        if (!found || found.filters_json == null) return;
        var filt = {};
        try {
          filt = JSON.parse(found.filters_json);
        } catch (eJ) {
          return;
        }
        applyFiltersFromObject(filt);
        loadTasks();
      });
    }

    if ($('savedViewSearch')) {
      var svTmo = null;
      $('savedViewSearch').addEventListener('input', function () {
        var q = $('savedViewSearch').value || '';
        var keep = $('savedViewPick') ? $('savedViewPick').value : '';
        if (svTmo) clearTimeout(svTmo);
        svTmo = setTimeout(function () {
          renderSavedViewsPickFiltered(q, keep);
        }, 120);
      });
      $('savedViewSearch').addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
          ev.preventDefault();
          $('savedViewSearch').value = '';
          renderSavedViewsPickFiltered('', $('savedViewPick') ? $('savedViewPick').value : '');
        }
      });
    }

    if ($('btnRenameSavedView')) {
      $('btnRenameSavedView').addEventListener('click', function () {
        var sid = $('savedViewPick') ? $('savedViewPick').value : '';
        if (!sid) {
          notify('Выберите представление.', 'error');
          return;
        }
        var items = getSavedViewsCache();
        var cur = null;
        for (var i = 0; i < items.length; i++) {
          if (String(items[i].id) === String(sid)) {
            cur = items[i];
            break;
          }
        }
        openAppInlinePrompt({
          title: 'Переименовать представление',
          message: 'Введите новое название.',
          value: cur && cur.name ? String(cur.name) : '',
          maxlength: 120,
          confirmLabel: 'Сохранить',
        }).then(function (name) {
          if (name == null) return;
          var nm = (name || '').trim();
          if (!nm) return;
          api('/api/task_saved_views.php', { method: 'PATCH', body: { id: parseInt(sid, 10), name: nm } })
            .then(function () {
              notify('Переименовано.', 'success');
              return refreshSavedViewsPick();
            })
            .catch(function (e) {
              notify('Не удалось: ' + (e.message || ''), 'error');
            });
        });
      });
    }

    var tabBoard = $('tabViewBoard');
    var tabList = $('tabViewList');
    if (tabBoard) {
      tabBoard.addEventListener('click', function () {
        viewMode = 'board';
        try {
          localStorage.setItem('planner_tasks_view', 'board');
        } catch (e1) {}
        setViewTabs();
        renderKanban();
      });
    }
    if (tabList) {
      tabList.addEventListener('click', function () {
        viewMode = 'list';
        try {
          localStorage.setItem('planner_tasks_view', 'list');
        } catch (e2) {}
        setViewTabs();
        renderTaskTable();
      });
    }
    var tabPlan = $('tabViewPlan');
    if (tabPlan) {
      tabPlan.addEventListener('click', function () {
        viewMode = 'plan';
        try {
          localStorage.setItem('planner_tasks_view', 'plan');
        } catch (e3) {}
        setViewTabs();
        renderMyPlan();
      });
    }
    var tabDeadlines = $('tabViewDeadlines');
    if (tabDeadlines) {
      tabDeadlines.addEventListener('click', function () {
        viewMode = 'deadlines';
        try {
          localStorage.setItem('planner_tasks_view', 'deadlines');
        } catch (e4) {}
        setViewTabs();
        renderDeadlinesBoard();
      });
    }
    var tabCalendar = $('tabViewCalendar');
    if (tabCalendar) {
      tabCalendar.addEventListener('click', function () {
        viewMode = 'calendar';
        try {
          localStorage.setItem('planner_tasks_view', 'calendar');
        } catch (eCal) {}
        setViewTabs();
        renderCalendar();
      });
    }
    var tabGantt = $('tabViewGantt');
    if (tabGantt) {
      tabGantt.addEventListener('click', function () {
        viewMode = 'gantt';
        try {
          localStorage.setItem('planner_tasks_view', 'gantt');
        } catch (eGantt) {}
        setViewTabs();
        renderGantt();
      });
    }
    if ($('taskCalPrev')) $('taskCalPrev').addEventListener('click', function () {
      shiftCalendarMonth(-1);
    });
    if ($('taskCalNext')) $('taskCalNext').addEventListener('click', function () {
      shiftCalendarMonth(1);
    });

    var selAllHdr = $('taskSelectAll');
    if (selAllHdr) {
      selAllHdr.addEventListener('change', function () {
        var tb = $('taskTableBody');
        var on = !!selAllHdr.checked;
        if (tb) {
          tb.querySelectorAll('.task-cb').forEach(function (cb) {
            cb.checked = on;
          });
        }
        refreshBulkSelectionCount();
        updateBulkPreview();
      });
    }

    var bulkAct = $('bulkAction');
    if (bulkAct) bulkAct.addEventListener('change', syncBulkActionUi);
    if ($('bulkStatus')) $('bulkStatus').addEventListener('change', updateBulkPreview);
    if ($('bulkAssignee')) $('bulkAssignee').addEventListener('change', updateBulkPreview);
    if ($('btnBulkApply')) $('btnBulkApply').addEventListener('click', applyBulkAction);
    if ($('btnCreateFromTemplate')) $('btnCreateFromTemplate').addEventListener('click', createFromSelectedTemplate);
    if ($('btnSaveTemplate')) $('btnSaveTemplate').addEventListener('click', saveCurrentTaskAsTemplate);
    if ($('btnPriorityToggle')) $('btnPriorityToggle').addEventListener('click', togglePriorityUi);

    if ($('btnNewTask')) $('btnNewTask').addEventListener('click', createTask);

    var ovrCreate = $('taskSplitOverlay');
    if (ovrCreate) {
      ovrCreate.addEventListener('click', closeCreateTaskModal);
    }
    if ($('btnModalCreateTask')) $('btnModalCreateTask').addEventListener('click', submitCreateTaskModal);
    if ($('btnModalCreateCancel')) $('btnModalCreateCancel').addEventListener('click', closeCreateTaskModal);
    if ($('taskSplitModalCloseX')) $('taskSplitModalCloseX').addEventListener('click', closeCreateTaskModal);
    if ($('btnAssistantsPicker')) {
      $('btnAssistantsPicker').addEventListener('click', function () {
        openParticipantPicker('assistants');
      });
    }
    if ($('btnAssigneePicker')) {
      $('btnAssigneePicker').addEventListener('click', function () {
        openParticipantPicker('assignee');
      });
    }
    if ($('btnProjectPicker')) {
      $('btnProjectPicker').addEventListener('click', function () {
        openParticipantPicker('project');
      });
    }
    if ($('btnObserversPicker')) {
      $('btnObserversPicker').addEventListener('click', function () {
        openParticipantPicker('observers');
      });
    }
    if ($('fAssignee')) {
      $('fAssignee').addEventListener('change', function () {
        updateParticipantSummary('assignee');
      });
    }
    if ($('fProject')) {
      $('fProject').addEventListener('change', function () {
        updateParticipantSummary('project');
      });
    }
    if ($('participantPickerClose')) $('participantPickerClose').addEventListener('click', closeParticipantPicker);
    if ($('participantPickerDone')) $('participantPickerDone').addEventListener('click', closeParticipantPicker);
    if ($('participantPickerSearch')) {
      $('participantPickerSearch').addEventListener('input', function (ev) {
        participantPickerState.query = ev.target.value || '';
        renderParticipantPicker();
      });
    }
    if ($('participantPickerInvite')) {
      $('participantPickerInvite').addEventListener('click', function () {
        notify('Функция приглашения будет добавлена отдельно.', 'info');
      });
    }

    document.querySelectorAll('.task-modal-pill[data-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tid = btn.getAttribute('data-target');
        var sec = tid ? $(tid) : null;
        if (!sec) return;
        var isThisOpen = !sec.hidden;
        document.querySelectorAll('.task-modal-extra').forEach(function (el) {
          if (el.id === 'modalSectionSubtasks') return;
          el.hidden = true;
        });
        document.querySelectorAll('.task-modal-pill[data-target]').forEach(function (b) {
          b.classList.remove('is-active');
        });
        if (isThisOpen) return;
        sec.hidden = false;
        btn.classList.add('is-active');
      });
    });

    document.addEventListener('mousedown', function (ev) {
      var pop = $('participantPicker');
      if (!pop || pop.hidden || !pop.classList.contains('is-open')) return;
      var trgA = $('btnAssistantsPicker');
      var trgO = $('btnObserversPicker');
      var trgAssignee = $('btnAssigneePicker');
      var trgProject = $('btnProjectPicker');
      var target = ev.target;
      if (pop.contains(target)) return;
      if (trgA && trgA.contains(target)) return;
      if (trgO && trgO.contains(target)) return;
      if (trgAssignee && trgAssignee.contains(target)) return;
      if (trgProject && trgProject.contains(target)) return;
      closeParticipantPicker();
    });

    window.addEventListener('resize', function () {
      if (!participantPickerState.role) return;
      var pop = $('participantPicker');
      if (!pop || pop.hidden) return;
      positionParticipantPicker(participantPickerState.role);
    });
    var modalLeft = document.querySelector('.task-modal-left');
    if (modalLeft) {
      modalLeft.addEventListener(
        'scroll',
        function () {
          if (!participantPickerState.role) return;
          var pop = $('participantPicker');
          if (!pop || pop.hidden) return;
          positionParticipantPicker(participantPickerState.role);
        },
        { passive: true }
      );
    }

    document.addEventListener('mousedown', function (ev) {
      var target = ev.target;
      if (!(target.closest && target.closest('.subtask-assistants-wrap'))) {
        closeSubtaskAssistantPopovers(null);
      }
      if (!(target.closest && target.closest('.checklist-template-menu-wrap'))) {
        closeChecklistTemplateMenu();
      }
      var ctd = $('checklistTemplateDialog');
      if (ctd && !ctd.hidden && !(target.closest && target.closest('#checklistTemplateDialog'))) {
        if (!(target.closest && target.closest('#btnSaveChecklistTemplate'))) {
          if (!(target.closest && target.closest('#btnRenameChecklistTemplate'))) {
            if (!(target.closest && target.closest('#btnDeleteChecklistTemplate'))) {
              closeChecklistTemplateDialog();
            }
          }
        }
      }
      var aid = $('appInlineDialog');
      if (aid && !aid.hidden && target.closest && target.closest('.app-inline-dialog-backdrop')) {
        cancelAppInlineDialog();
      }
      if (target.closest && target.closest('.comment-actions')) return;
      closeCommentMenus();
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Escape') return;
      if ($('appInlineDialog') && !$('appInlineDialog').hidden) {
        ev.preventDefault();
        cancelAppInlineDialog();
        return;
      }
      var mpsdEsc = $('myPlanStagesDialog');
      if (mpsdEsc && !mpsdEsc.hidden) {
        ev.preventDefault();
        closeMyPlanStagesDialog();
        return;
      }
      if (!($('taskSplitModal') && !$('taskSplitModal').hidden)) return;
      if ($('checklistTemplateMenu') && !$('checklistTemplateMenu').hidden) {
        ev.preventDefault();
        closeChecklistTemplateMenu();
        return;
      }
      closeSubtaskAssistantPopovers(null);
      closeCommentMenus();
      var pop = $('participantPicker');
      if (pop && !pop.hidden) {
        ev.preventDefault();
        closeParticipantPicker();
        return;
      }
      var md = $('taskSplitModal');
      if (md && !md.hidden) {
        ev.preventDefault();
        if ($('checklistTemplateDialog') && !$('checklistTemplateDialog').hidden) {
          closeChecklistTemplateDialog();
          return;
        }
        closeCreateTaskModal();
      }
    });
    $('btnSaveTask').addEventListener('click', saveTask);
    $('btnDeleteTask').addEventListener('click', deleteTask);
    $('btnAddSubtask').addEventListener('click', addSubtask);
    if ($('btnPostComment')) $('btnPostComment').addEventListener('click', postComment);
    if ($('btnApplyTemplate')) $('btnApplyTemplate').addEventListener('click', applyTemplate);
    if ($('checklistTemplate')) $('checklistTemplate').addEventListener('change', updateChecklistTemplatePreview);
    if ($('checklistTemplateMode')) $('checklistTemplateMode').addEventListener('change', updateChecklistTemplatePreview);
    if ($('btnSaveChecklistTemplate')) $('btnSaveChecklistTemplate').addEventListener('click', saveChecklistTemplate);
    if ($('btnRenameChecklistTemplate')) $('btnRenameChecklistTemplate').addEventListener('click', renameChecklistTemplate);
    if ($('btnDeleteChecklistTemplate')) $('btnDeleteChecklistTemplate').addEventListener('click', deleteChecklistTemplate);
    if ($('btnChecklistTemplateMenu')) $('btnChecklistTemplateMenu').addEventListener('click', toggleChecklistTemplateMenu);
    if ($('checklistTemplateDialogCancel')) $('checklistTemplateDialogCancel').addEventListener('click', closeChecklistTemplateDialog);
    if ($('checklistTemplateDialogSubmit')) $('checklistTemplateDialogSubmit').addEventListener('click', submitChecklistTemplateDialog);
    if ($('checklistTemplateDialogInput')) {
      $('checklistTemplateDialogInput').addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        ev.preventDefault();
        submitChecklistTemplateDialog();
      });
    }
    if ($('appInlineDialogCancel')) $('appInlineDialogCancel').addEventListener('click', cancelAppInlineDialog);
    if ($('appInlineDialogConfirm')) $('appInlineDialogConfirm').addEventListener('click', submitAppInlineDialog);
    if ($('appInlineDialogInput')) {
      $('appInlineDialogInput').addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        ev.preventDefault();
        submitAppInlineDialog();
      });
    }
    if ($('btnUploadAttach')) $('btnUploadAttach').addEventListener('click', uploadAttachment);
    if ($('btnAddLink')) $('btnAddLink').addEventListener('click', addLink);

    if ($('btnMyPlanAllStages')) {
      $('btnMyPlanAllStages').addEventListener('click', openMyPlanStagesDialog);
    }
    if ($('myPlanStagesClose')) {
      $('myPlanStagesClose').addEventListener('click', closeMyPlanStagesDialog);
    }
    if ($('myPlanStagesAdd')) {
      $('myPlanStagesAdd').addEventListener('click', addMyPlanStage);
    }
    var mpsd = $('myPlanStagesDialog');
    if (mpsd) {
      mpsd.addEventListener('click', function (ev) {
        if (ev.target && ev.target.classList && ev.target.classList.contains('my-plan-stages-dialog__backdrop')) {
          closeMyPlanStagesDialog();
        }
      });
    }

    var sul = $('subtaskList');
    if (sul) {
      sul.addEventListener('dragover', function (e) {
        e.preventDefault();
      });
      sul.addEventListener('drop', function (e) {
        e.preventDefault();
        var tid = $('fTaskId').value;
        if (tid) saveSubtaskOrder(tid);
      });
    }
    updateBulkPreview();
    updateChecklistTemplatePreview();
  });
})();
