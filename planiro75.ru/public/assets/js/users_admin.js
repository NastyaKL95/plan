(function () {
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

  function escapeHtml(s) {
    return Core.escapeHtml(s);
  }

  function notify(message, type) {
    if (Core.notify) Core.notify(message, { type: type || 'info' });
    else window.alert(message);
  }

  var ROLE_LABEL = {
    member: 'Пользователь',
    admin: 'Администратор',
    head: 'Пользователь',
    owner: 'Администратор',
    rectorate: 'Пользователь',
  };

  var DEPT_ROLE_LABEL = {
    user: 'Сотрудник',
    manager: 'Руководитель организации',
    administrator: 'Руководитель подразделения',
    member: 'Сотрудник',
    head: 'Руководитель подразделения',
  };

  var STATUS_LABEL = {
    active: 'Активен',
    blocked: 'Заблокирован',
    pending_email: 'Ожидает email',
    pending_approval: 'Ожидает активации',
  };

  function normalizeDeptRole(role) {
    var r = String(role || 'user');
    if (r === 'head') return 'administrator';
    if (r === 'member') return 'user';
    return DEPT_ROLE_LABEL[r] ? r : 'user';
  }

  var users = [];
  var departments = [];
  var deptRoleRows = [];
  var registrationFlow = false;
  var usersFilterState = {
    q: '',
    role: '',
    departmentId: 0,
    accountStatus: '',
  };
  var usersPagerState = {
    page: 1,
    perPage: 20,
  };
  var usersSelectAllBound = false;

  var USER_TABLE_HEADERS = [
    { kind: 'check' },
    { label: 'Имя' },
    { label: 'Email' },
    { label: 'Роль на портале' },
    { label: 'Статус' },
    { label: 'Задач' },
    { label: 'Подразделения' },
    { label: '' },
  ];

  function unwrapBxSelect(select) {
    if (!select) return;
    var wrap = select.closest('.bx-dropdown-wrap');
    if (wrap && wrap.parentNode) {
      wrap.parentNode.insertBefore(select, wrap);
      wrap.remove();
    }
    select.classList.remove('bx-dropdown-native');
  }

  function initUsersPageSelects() {
    ['usersRoleFilter', 'usersStatusFilter', 'usersDeptFilter', 'usersPerPage', 'usersBulkAction'].forEach(function (id) {
      unwrapBxSelect($(id));
    });
    if (typeof window.bxCustomSelectInit !== 'function') return;
    var filters = document.querySelector('.users-filter-panel');
    if (filters) window.bxCustomSelectInit(filters);
    var bulkBar = $('usersBulkBar');
    if (bulkBar) window.bxCustomSelectInit(bulkBar);
    var pager = $('usersPager');
    if (pager) window.bxCustomSelectInit(pager);
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function renderTableHeader() {
    var table = $('usersTable');
    if (!table) return;
    var head = table.querySelector('thead');
    if (!head) return;
    head.id = 'usersTableHead';
    head.setAttribute('translate', 'no');
    head.className = 'notranslate';
    var cells = USER_TABLE_HEADERS.map(function (col) {
      if (col.kind === 'check') {
        return (
          '<th class="task-table-check"><input type="checkbox" id="usersSelectAll" aria-label="Выбрать все на странице"></th>'
        );
      }
      return '<th>' + escapeHtml(col.label) + '</th>';
    });
    head.innerHTML = '<tr>' + cells.join('') + '</tr>';
    if (!usersSelectAllBound) {
      usersSelectAllBound = true;
      bindUsersSelectAll();
    }
  }

  function bindUsersSelectAll() {
    document.addEventListener('change', function (ev) {
      var target = ev.target;
      if (!target || target.id !== 'usersSelectAll') return;
      var tb = $('usersTableBody');
      var on = !!target.checked;
      if (tb) {
        tb.querySelectorAll('.user-cb').forEach(function (cb) {
          cb.checked = on;
        });
      }
      target.indeterminate = false;
      refreshUsersBulkSelectionCount();
      updateUsersBulkPreview();
    });
  }

  function normalizeText(s) {
    return String(s || '')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  function userDepartmentIds(u) {
    var ids = [];
    var set = {};
    (u.department_roles || []).forEach(function (x) {
      var did = parseInt(x.department_id, 10) || 0;
      if (did > 0 && !set[did]) {
        set[did] = true;
        ids.push(did);
      }
    });
    var csv = String(u.department_ids || '').trim();
    if (csv) {
      csv.split(',').forEach(function (raw) {
        var did = parseInt(raw, 10) || 0;
        if (did > 0 && !set[did]) {
          set[did] = true;
          ids.push(did);
        }
      });
    }
    var one = parseInt(u.department_id, 10) || 0;
    if (one > 0 && !set[one]) ids.push(one);
    return ids;
  }

  function getFilteredUsers() {
    var q = normalizeText(usersFilterState.q);
    var role = usersFilterState.role || '';
    var depId = parseInt(usersFilterState.departmentId, 10) || 0;
    var accSt = usersFilterState.accountStatus || '';
    return users.filter(function (u) {
      if (accSt && String(u.account_status || 'active') !== accSt) return false;
      if (role && String(u.role || '') !== role) return false;
      if (depId > 0) {
        var ids = userDepartmentIds(u);
        if (ids.indexOf(depId) === -1) return false;
      }
      if (!q) return true;
      var hay = normalizeText(
        [u.name || '', u.email || '', u.department_names || '', u.department_name || '', ROLE_LABEL[u.role] || u.role || ''].join(' ')
      );
      return hay.indexOf(q) !== -1;
    });
  }

  function getPagerMeta(total) {
    var perPage = Math.max(1, parseInt(usersPagerState.perPage, 10) || 20);
    var pages = Math.max(1, Math.ceil(total / perPage));
    var page = Math.min(Math.max(1, parseInt(usersPagerState.page, 10) || 1), pages);
    usersPagerState.page = page;
    return { page: page, perPage: perPage, pages: pages };
  }

  function renderTable() {
    renderTableHeader();
    var tb = $('usersTableBody');
    if (!tb) return;
    var filtered = getFilteredUsers();
    var pager = getPagerMeta(filtered.length);
    var from = (pager.page - 1) * pager.perPage;
    var to = from + pager.perPage;
    var pageItems = filtered.slice(from, to);
    tb.innerHTML = pageItems
      .map(function (u) {
        var dn = u.department_names
          ? escapeHtml(u.department_names)
          : u.department_name
            ? escapeHtml(u.department_name)
            : '—';
        var rl = ROLE_LABEL[u.role] || u.role;
        var stKey = String(u.account_status || 'active');
        var st = escapeHtml(STATUS_LABEL[stKey] || u.account_status_label || 'Активен');
        var stClass =
          u.account_status === 'blocked'
            ? 'user-status user-status--blocked'
            : u.account_status === 'pending_approval'
              ? 'user-status user-status--warn'
              : u.account_status === 'pending_email'
                ? 'user-status user-status--muted'
                : 'user-status';
        return (
          '<tr data-id="' +
          u.id +
          '" data-status="' +
          escapeHtml(u.account_status || 'active') +
          '"><td class="task-table-check"><input type="checkbox" class="user-cb" data-id="' +
          u.id +
          '" aria-label="Выбрать пользователя"></td><td>' +
          escapeHtml(u.name) +
          '</td><td>' +
          escapeHtml(u.email) +
          '</td><td>' +
          escapeHtml(rl) +
          '</td><td><span class="' +
          stClass +
          '">' +
          st +
          '</span></td><td>' +
          (u.task_count != null ? String(u.task_count) : '—') +
          '</td><td>' +
          dn +
          '</td><td><button type="button" class="btn btn-secondary btn-sm user-edit-btn">Изменить</button></td></tr>'
        );
      })
      .join('');
    if (!pageItems.length) {
      tb.innerHTML =
        '<tr><td colspan="8" class="muted">Ничего не найдено. Измените параметры поиска или фильтры.</td></tr>';
    }
    tb.querySelectorAll('.user-cb').forEach(function (cb) {
      cb.addEventListener('change', function () {
        refreshUsersBulkSelectionCount();
        updateUsersBulkPreview();
        var sa = $('usersSelectAll');
        if (sa) {
          var all = tb.querySelectorAll('.user-cb');
          var checked = tb.querySelectorAll('.user-cb:checked');
          sa.checked = all.length > 0 && checked.length === all.length;
          sa.indeterminate = checked.length > 0 && checked.length < all.length;
        }
      });
    });
    tb.querySelectorAll('.user-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tr = btn.closest('tr');
        var id = tr ? parseInt(tr.getAttribute('data-id'), 10) : 0;
        openEdit(id);
      });
    });
    var stats = $('usersFilterStats');
    if (stats) {
      stats.textContent = 'Показано: ' + pageItems.length + ' из ' + filtered.length + ' (всего ' + users.length + ')';
    }
    var pageInfo = $('usersPageInfo');
    if (pageInfo) pageInfo.textContent = 'Страница ' + pager.page + ' из ' + pager.pages;
    var prev = $('usersPagePrev');
    if (prev) prev.disabled = pager.page <= 1;
    var next = $('usersPageNext');
    if (next) next.disabled = pager.page >= pager.pages;
    refreshUsersBulkSelectionCount();
    updateUsersBulkPreview();
    var selAll = $('usersSelectAll');
    if (selAll) {
      selAll.checked = false;
      selAll.indeterminate = false;
    }
  }

  function fillDepartmentFilterOptions() {
    var sel = $('usersDeptFilter');
    if (!sel) return;
    var current = sel.value || '';
    sel.innerHTML =
      '<option value="">Все подразделения</option>' +
      departments
        .map(function (d) {
          return '<option value="' + d.id + '">' + escapeHtml(d.name) + '</option>';
        })
        .join('');
    if (current) sel.value = current;
    if (typeof window.bxCustomSelectSync === 'function') window.bxCustomSelectSync(sel);
    else if (typeof window.bxCustomSelectInit === 'function') window.bxCustomSelectInit(sel.parentElement || sel);
  }

  function fillStatusFilterOptions() {
    var sel = $('usersStatusFilter');
    if (!sel) return;
    if (typeof window.bxCustomSelectSync === 'function') window.bxCustomSelectSync(sel);
  }

  function bindFilters() {
    var search = $('usersSearch');
    var role = $('usersRoleFilter');
    var dep = $('usersDeptFilter');
    var acc = $('usersStatusFilter');
    var reset = $('usersFilterReset');
    if (search) {
      search.addEventListener('input', function () {
        usersFilterState.q = search.value || '';
        usersPagerState.page = 1;
        renderTable();
      });
    }
    if (role) {
      role.addEventListener('change', function () {
        usersFilterState.role = role.value || '';
        usersPagerState.page = 1;
        renderTable();
      });
    }
    if (dep) {
      dep.addEventListener('change', function () {
        usersFilterState.departmentId = parseInt(dep.value, 10) || 0;
        usersPagerState.page = 1;
        renderTable();
      });
    }
    if (acc) {
      acc.addEventListener('change', function () {
        usersFilterState.accountStatus = acc.value || '';
        usersPagerState.page = 1;
        renderTable();
      });
    }
    if (reset) {
      reset.addEventListener('click', function () {
        usersFilterState.q = '';
        usersFilterState.role = '';
        usersFilterState.departmentId = 0;
        usersFilterState.accountStatus = '';
        usersPagerState.page = 1;
        if (search) search.value = '';
        if (role) role.value = '';
        if (dep) dep.value = '';
        if (acc) acc.value = '';
        if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
        renderTable();
      });
    }
  }

  function bindPager() {
    var prev = $('usersPagePrev');
    var next = $('usersPageNext');
    var perPage = $('usersPerPage');
    if (prev) {
      prev.addEventListener('click', function () {
        usersPagerState.page = Math.max(1, usersPagerState.page - 1);
        renderTable();
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        usersPagerState.page = usersPagerState.page + 1;
        renderTable();
      });
    }
    if (perPage) {
      perPage.addEventListener('change', function () {
        usersPagerState.perPage = Math.max(1, parseInt(perPage.value, 10) || 20);
        usersPagerState.page = 1;
        if (typeof window.bxCustomSelectSync === 'function') window.bxCustomSelectSync(perPage);
        renderTable();
      });
    }
  }

  function renderDeptRoleRows() {
    var host = $('editUserDeptRoles');
    if (!host) return;
    if (!deptRoleRows.length) {
      host.innerHTML = '<p class="muted">Подразделения не выбраны.</p>';
      return;
    }
    host.innerHTML = deptRoleRows
      .map(function (r, idx) {
        var depOpts =
          '<option value="">— выберите подразделение —</option>' +
          departments
            .map(function (d) {
              var sel = String(d.id) === String(r.department_id || '') ? ' selected' : '';
              return '<option value="' + d.id + '"' + sel + '>' + escapeHtml(d.name) + '</option>';
            })
            .join('');
        var nr = normalizeDeptRole(r.role);
        var roleOpts =
          '<option value="user"' + (nr === 'user' ? ' selected' : '') + '>Сотрудник</option>' +
          '<option value="administrator"' + (nr === 'administrator' ? ' selected' : '') + '>Руководитель подразделения</option>' +
          '<option value="manager"' + (nr === 'manager' ? ' selected' : '') + '>Руководитель организации</option>';
        return (
          '<div class="user-dept-role-row" data-idx="' +
          idx +
          '">' +
          '<label class="user-dept-role-field-label">Подразделение</label>' +
          '<select class="input-block user-dept-role-dept" data-bx-dropdown-skip="1" title="Подразделение">' +
          depOpts +
          '</select>' +
          '<p class="user-dept-role-dept-hint" hidden></p>' +
          '<div class="user-dept-role-row-bottom">' +
          '<div class="user-dept-role-role-wrap">' +
          '<label class="user-dept-role-field-label">Роль в подразделении</label>' +
          '<select class="input-block user-dept-role-role" data-bx-dropdown-skip="1">' +
          roleOpts +
          '</select>' +
          '</div>' +
          '<button type="button" class="btn btn-secondary btn-sm user-dept-role-del">Удалить</button>' +
          '</div>' +
          '</div>'
        );
      })
      .join('');
    if (typeof window.bxCustomSelectInit === 'function') {
      window.bxCustomSelectInit(host);
    }
    host.querySelectorAll('.user-dept-role-dept').forEach(function (depSel) {
      function syncDeptTitle() {
        var opt = depSel.options[depSel.selectedIndex];
        var label = opt ? (opt.textContent || '').trim() : '';
        var hasValue = !!(depSel.value && depSel.value !== '0');
        depSel.title = label;
        var hint = depSel.parentElement
          ? depSel.parentElement.querySelector('.user-dept-role-dept-hint')
          : null;
        if (hint) {
          if (hasValue && label) {
            hint.textContent = label;
            hint.hidden = false;
          } else {
            hint.textContent = '';
            hint.hidden = true;
          }
        }
      }
      syncDeptTitle();
      depSel.addEventListener('change', syncDeptTitle);
    });
    host.querySelectorAll('.user-dept-role-row').forEach(function (rowEl) {
      var idx = parseInt(rowEl.getAttribute('data-idx'), 10) || 0;
      var dep = rowEl.querySelector('.user-dept-role-dept');
      var role = rowEl.querySelector('.user-dept-role-role');
      var del = rowEl.querySelector('.user-dept-role-del');
      if (dep) {
        dep.addEventListener('change', function () {
          if (!deptRoleRows[idx]) return;
          deptRoleRows[idx].department_id = dep.value ? parseInt(dep.value, 10) : 0;
        });
      }
      if (role) {
        role.addEventListener('change', function () {
          if (!deptRoleRows[idx]) return;
          deptRoleRows[idx].role = normalizeDeptRole(role.value);
        });
      }
      if (del) {
        del.addEventListener('click', function () {
          deptRoleRows.splice(idx, 1);
          renderDeptRoleRows();
        });
      }
    });
  }

  function openEdit(id) {
    var u = null;
    for (var i = 0; i < users.length; i++) {
      if (parseInt(users[i].id, 10) === id) {
        u = users[i];
        break;
      }
    }
    if (!u) return;
    $('editUserId').value = String(u.id);
    $('editUserName').value = u.name || '';
    $('editUserRole').value = u.role || 'member';
    deptRoleRows = ((u.department_roles || []).map(function (x) {
      return {
        department_id: parseInt(x.department_id, 10) || 0,
        role: normalizeDeptRole(x.role),
      };
    })).filter(function (x) {
      return x.department_id > 0;
    });
    renderDeptRoleRows();
    var pending = registrationFlow && u.account_status === 'pending_approval';
    var blocked = u.account_status === 'blocked';
    var canBlock = u.account_status !== 'blocked';
    var banner = $('editUserPendingBanner');
    var blockedBanner = $('editUserBlockedBanner');
    var btnApprove = $('userEditApprove');
    var btnBlock = $('userEditBlock');
    var btnUnblock = $('userEditUnblock');
    var btnDelete = $('userEditDelete');
    var canDelete = !!u.can_delete;
    if (banner) banner.hidden = !pending;
    if (blockedBanner) blockedBanner.hidden = !blocked;
    if (btnApprove) btnApprove.hidden = !pending;
    var isSelf = typeof window.__PLANNER_USER_ID__ === 'number' && window.__PLANNER_USER_ID__ === id;
    if (btnBlock) btnBlock.hidden = !canBlock || isSelf;
    if (btnUnblock) btnUnblock.hidden = !blocked;
    if (btnDelete) {
      btnDelete.hidden = !canDelete;
      btnDelete.title = canDelete ? 'Удалить пользователя без задач' : '';
    }
    var dangerHost = $('userEditDangerActions');
    if (dangerHost) {
      var showDanger =
        (btnBlock && !btnBlock.hidden) ||
        (btnUnblock && !btnUnblock.hidden) ||
        (btnDelete && !btnDelete.hidden);
      dangerHost.hidden = !showDanger;
    }
    var modal = $('userEditModal');
    if (modal) {
      modal.classList.toggle('user-edit-modal--pending', !!pending);
    }
    var btnSave = $('userEditSave');
    if (btnSave) {
      btnSave.classList.toggle('btn-secondary', !!pending);
      btnSave.classList.toggle('btn-primary', !pending);
    }
    if (typeof window.bxCustomSelectSync === 'function') {
      window.bxCustomSelectSync($('editUserRole'));
    }
    $('userEditModal').showModal();
  }

  function buildUserPatchBody() {
    var body = {
      name: $('editUserName').value.trim(),
      role: $('editUserRole').value,
    };
    var dedup = {};
    var rows = [];
    deptRoleRows.forEach(function (r) {
      var did = parseInt(r.department_id, 10) || 0;
      if (did <= 0 || dedup[did]) return;
      dedup[did] = true;
      rows.push({ department_id: did, role: normalizeDeptRole(r.role) });
    });
    body.department_roles = rows;
    return body;
  }

  var USER_API_ERRORS = {
    cannot_block_self: 'Нельзя заблокировать свою учётную запись.',
    already_blocked: 'Пользователь уже заблокирован.',
    not_blocked: 'Пользователь не заблокирован.',
    invalid_state: 'Действие недоступно для текущего статуса (например, «Активировать» только для «Ожидает активации»).',
    migration_required: 'Выполните миграцию sql/migration_user_blocked_v1.sql на сервере.',
    cannot_delete_self: 'Нельзя удалить свою учётную запись.',
    has_tasks: 'Нельзя удалить: у пользователя есть задачи.',
    last_admin: 'Нельзя удалить последнего администратора организации.',
    department_required: 'Не назначено подразделение.',
    email_not_verified: 'Email не подтверждён.',
    approve_failed: 'Не удалось активировать.',
    not_found: 'Пользователь не найден.',
    bad_response: 'Сервер вернул некорректный ответ. Проверьте, что загружен файл public/api/users_bulk.php.',
    bad_action: 'Неизвестное действие.',
    no_users: 'Не выбраны пользователи.',
  };

  function currentUserId() {
    return typeof window.__PLANNER_USER_ID__ === 'number' ? window.__PLANNER_USER_ID__ : 0;
  }

  function findUser(id) {
    for (var i = 0; i < users.length; i++) {
      if (parseInt(users[i].id, 10) === id) return users[i];
    }
    return null;
  }

  function countBulkEligible(action, ids) {
    var selfId = currentUserId();
    var n = 0;
    ids.forEach(function (id) {
      var u = findUser(id);
      if (!u) return;
      var st = String(u.account_status || 'active');
      if (action === 'block') {
        if (st !== 'blocked' && id !== selfId) n++;
      } else if (action === 'unblock') {
        if (st === 'blocked') n++;
      } else if (action === 'delete') {
        if (u.can_delete) n++;
      } else if (action === 'approve') {
        if (st === 'pending_approval') n++;
      }
    });
    return n;
  }

  function selectedUserIdsFromTable() {
    var tb = $('usersTableBody');
    if (!tb) return [];
    var ids = [];
    tb.querySelectorAll('.user-cb:checked').forEach(function (cb) {
      var id = parseInt(cb.getAttribute('data-id'), 10);
      if (id > 0) ids.push(id);
    });
    return ids;
  }

  function refreshUsersBulkSelectionCount() {
    var n = selectedUserIdsFromTable().length;
    var el = $('usersBulkSelectedCount');
    if (el) el.textContent = 'Выбрано: ' + n;
  }

  function setUsersBulkProgress(text, shown) {
    var p = $('usersBulkProgress');
    if (!p) return;
    p.hidden = !shown;
    p.textContent = text || 'Применение...';
  }

  function bulkActionLabel(action) {
    var map = {
      block: 'заблокированы',
      unblock: 'разблокированы',
      delete: 'удалены',
      approve: 'активированы',
    };
    return map[action] || action;
  }

  function updateUsersBulkPreview() {
    var el = $('usersBulkPreview');
    if (!el) return;
    var action = $('usersBulkAction') ? $('usersBulkAction').value : '';
    var ids = selectedUserIdsFromTable();
    if (!ids.length) {
      el.textContent = 'Отметьте пользователей и выберите действие.';
      return;
    }
    if (!action) {
      el.textContent = 'Выберите действие для ' + ids.length + ' пользователей.';
      return;
    }
    var eligible = countBulkEligible(action, ids);
    if (eligible === 0) {
      el.textContent = 'Для выбранного действия не подходит ни один из ' + ids.length + ' пользователей.';
      return;
    }
    if (eligible < ids.length) {
      el.textContent =
        'Подходит ' + eligible + ' из ' + ids.length + ': будут ' + bulkActionLabel(action) + '.';
      return;
    }
    el.textContent = 'Будут ' + bulkActionLabel(action) + ' ' + ids.length + ' пользователей.';
  }

  function syncUsersBulkActionUi() {
    var approveOpt = $('usersBulkActionApprove');
    if (approveOpt) approveOpt.hidden = !registrationFlow;
    if (typeof window.bxCustomSelectSync === 'function') window.bxCustomSelectSync($('usersBulkAction'));
    updateUsersBulkPreview();
  }

  function applyUsersBulkAction() {
    var action = $('usersBulkAction') ? $('usersBulkAction').value : '';
    var ids = selectedUserIdsFromTable();
    if (!ids.length) {
      notify('Отметьте пользователей галочками в таблице.', 'error');
      return;
    }
    if (!action) {
      notify('Выберите действие в списке.', 'error');
      return;
    }
    var eligible = countBulkEligible(action, ids);
    if (eligible === 0) {
      notify('Для этого действия не подходит ни один из выбранных пользователей.', 'error');
      return;
    }
    var confirmText = '';
    if (action === 'block') {
      confirmText =
        'Заблокировать ' +
        eligible +
        (eligible < ids.length ? ' из ' + ids.length : '') +
        ' пользователей? Они не смогут войти в систему.';
    } else if (action === 'unblock') {
      confirmText =
        'Разблокировать ' +
        eligible +
        (eligible < ids.length ? ' из ' + ids.length : '') +
        ' пользователей?';
    } else if (action === 'delete') {
      confirmText =
        'Удалить ' +
        eligible +
        (eligible < ids.length ? ' из ' + ids.length : '') +
        ' пользователей безвозвратно?\n\nУдаление возможно только без задач.';
    } else if (action === 'approve') {
      confirmText =
        'Активировать доступ для ' +
        eligible +
        (eligible < ids.length ? ' из ' + ids.length : '') +
        ' пользователей?\n\nНужны назначенные роль и подразделение.';
    }
    if (confirmText && !window.confirm(confirmText)) return;

    var btn = $('btnUsersBulkApply');
    if (btn) btn.disabled = true;
    setUsersBulkProgress('Применение изменений...', true);
    api('/api/users_bulk.php', { method: 'POST', body: { user_ids: ids, action: action } }, USER_API_ERRORS)
      .then(function (res) {
        var okN = 0;
        if (res) {
          if (res.updated_count != null) okN = parseInt(res.updated_count, 10) || 0;
          else if (Array.isArray(res.updated)) okN = res.updated.length;
        }
        var fail = (res && res.failed) || [];
        if (okN > 0 && fail.length === 0) {
          notify('Массовое действие выполнено для ' + okN + ' пользователей.', 'success');
        } else if (okN > 0 && fail.length) {
          var sample = fail
            .slice(0, 5)
            .map(function (f) {
              return '#' + f.user_id + ': ' + (USER_API_ERRORS[f.error] || f.error);
            })
            .join('; ');
          notify(
            'Применено: ' + okN + '. Не удалось: ' + fail.length + ' (' + sample + (fail.length > 5 ? '…' : '') + ')',
            'error'
          );
        } else if (fail.length) {
          var sampleOnly = fail
            .slice(0, 5)
            .map(function (f) {
              return '#' + f.user_id + ': ' + (USER_API_ERRORS[f.error] || f.error);
            })
            .join('; ');
          notify('Не удалось выполнить действие (' + sampleOnly + (fail.length > 5 ? '…' : '') + ')', 'error');
        } else {
          notify('Изменений не внесено.', 'error');
        }
        var sa = $('usersSelectAll');
        if (sa) sa.checked = false;
        refreshUsersBulkSelectionCount();
        return loadAll();
      })
      .then(function () {
        updateUsersBulkPreview();
      })
      .catch(function (e) {
        notify('Не выполнено: ' + (e.message || ''), 'error');
      })
      .then(function () {
        if (btn) btn.disabled = false;
        setUsersBulkProgress('', false);
      });
  }

  function saveUser(id, extra) {
    var body = buildUserPatchBody();
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        body[k] = extra[k];
      });
    }
    return api('/api/user.php?id=' + id, { method: 'PATCH', body: body }, USER_API_ERRORS);
  }

  function loadAll() {
    return Promise.all([api('/api/users.php'), api('/api/departments.php')])
      .then(function (res) {
        users = res[0].items || [];
        registrationFlow = !!res[0].registration_flow;
        departments = res[1].items || [];
        usersPagerState.page = 1;
        fillDepartmentFilterOptions();
        fillStatusFilterOptions();
        syncUsersBulkActionUi();
        renderTable();
      })
      .catch(function (e) {
        notify('Загрузка: ' + (e.message || ''), 'error');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    renderTableHeader();
    initUsersPageSelects();
    bindFilters();
    bindPager();
    loadAll();
    var bulkAct = $('usersBulkAction');
    if (bulkAct) bulkAct.addEventListener('change', syncUsersBulkActionUi);
    var btnBulk = $('btnUsersBulkApply');
    if (btnBulk) btnBulk.addEventListener('click', applyUsersBulkAction);
    $('userEditForm').addEventListener('submit', function (ev) {
      ev.preventDefault();
      var id = parseInt($('editUserId').value, 10);
      if (!id) return;
      saveUser(id)
        .then(function () {
          $('userEditModal').close();
          return loadAll();
        })
        .catch(function (e) {
          notify('Ошибка: ' + (e.message || ''), 'error');
        });
    });
    var btnApprove = $('userEditApprove');
    if (btnApprove) {
      btnApprove.addEventListener('click', function () {
        var id = parseInt($('editUserId').value, 10);
        if (!id) return;
        saveUser(id, { approve: true })
          .then(function () {
            notify('Доступ пользователя активирован', 'success');
            $('userEditModal').close();
            return loadAll();
          })
          .catch(function (e) {
            notify(e.message || 'Не удалось активировать', 'error');
          });
      });
    }
    function setBlocked(id, blocked) {
      var msg = blocked
        ? 'Заблокировать пользователя? Он не сможет войти в систему. Задачи и роли в них сохранятся.'
        : 'Разблокировать пользователя и восстановить вход?';
      if (!window.confirm(msg)) return;
      api('/api/user.php?id=' + id, { method: 'PATCH', body: { blocked: blocked } }, USER_API_ERRORS)
        .then(function () {
          notify(blocked ? 'Пользователь заблокирован' : 'Пользователь разблокирован', 'success');
          $('userEditModal').close();
          return loadAll();
        })
        .catch(function (e) {
          notify(e.message || 'Не удалось изменить статус', 'error');
        });
    }
    var btnBlock = $('userEditBlock');
    if (btnBlock) {
      btnBlock.addEventListener('click', function () {
        var id = parseInt($('editUserId').value, 10);
        if (!id) return;
        setBlocked(id, true);
      });
    }
    var btnUnblock = $('userEditUnblock');
    if (btnUnblock) {
      btnUnblock.addEventListener('click', function () {
        var id = parseInt($('editUserId').value, 10);
        if (!id) return;
        setBlocked(id, false);
      });
    }
    var btnDelete = $('userEditDelete');
    if (btnDelete) {
      btnDelete.addEventListener('click', function () {
        var id = parseInt($('editUserId').value, 10);
        if (!id) return;
        var u = null;
        for (var i = 0; i < users.length; i++) {
          if (parseInt(users[i].id, 10) === id) {
            u = users[i];
            break;
          }
        }
        if (!u || !u.can_delete) return;
        var label = (u.name || u.email || '').trim() || 'пользователя';
        if (
          !window.confirm(
            'Удалить «' +
              label +
              '» безвозвратно?\n\nДопустимо только если у него нет задач. Профиль и связи в системе будут удалены.'
          )
        ) {
          return;
        }
        api('/api/user.php?id=' + id, { method: 'DELETE' }, USER_API_ERRORS)
          .then(function () {
            notify('Пользователь удалён', 'success');
            $('userEditModal').close();
            return loadAll();
          })
          .catch(function (e) {
            notify(e.message || 'Не удалось удалить', 'error');
          });
      });
    }
    $('userEditCancel').addEventListener('click', function () {
      $('userEditModal').close();
    });
    var btnAdd = $('btnAddUserDeptRole');
    if (btnAdd) {
      btnAdd.addEventListener('click', function () {
        deptRoleRows.push({ department_id: 0, role: 'user' });
        renderDeptRoleRows();
      });
    }
  });
})();
