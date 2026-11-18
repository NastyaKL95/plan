(function () {
  var Core = window.PlannerCore;
  if (!Core) return;

  function $(id) {
    return Core.byId(id);
  }

  function notify(message, type) {
    if (Core.notify) Core.notify(message, { type: type || 'info' });
    else window.alert(message);
  }

  function roleRu(role) {
    var map = {
      admin: 'Администратор',
      member: 'Пользователь',
      head: 'Пользователь',
      rectorate: 'Пользователь',
      owner: 'Администратор',
    };
    return map[String(role || '')] || 'Пользователь';
  }

  function normalizeDeptRole(role) {
    var r = String(role || 'user');
    if (r === 'head') return 'administrator';
    if (r === 'member') return 'user';
    return r === 'manager' || r === 'administrator' ? r : 'user';
  }

  function deptRoleRu(role) {
    var map = {
      user: 'Сотрудник',
      manager: 'Руководитель организации',
      administrator: 'Руководитель подразделения',
    };
    return map[normalizeDeptRole(role)] || 'Сотрудник';
  }

  function avatarLetter(name) {
    var s = String(name || '').trim();
    if (!s) return 'U';
    return s.charAt(0).toUpperCase();
  }

  var profileDeptRows = [];
  var profileDeptOptions = [];
  var profileMayAssignDeptRoles = false;

  function renderProfileDeptRows() {
    var host = $('profileDeptRoles');
    if (!host) return;
    if (!profileDeptRows.length) {
      host.innerHTML = '<p class="muted">Подразделения не выбраны.</p>';
      return;
    }
    host.innerHTML = profileDeptRows
      .map(function (r, idx) {
        var depOpts =
          '<option value="">— выберите подразделение —</option>' +
          profileDeptOptions
            .map(function (d) {
              var sel = String(d.id) === String(r.department_id || '') ? ' selected' : '';
              return '<option value="' + d.id + '"' + sel + '>' + String(d.name || '') + '</option>';
            })
            .join('');
        var nr = normalizeDeptRole(r.role);
        var roleOpts = '<option value="user"' + (nr === 'user' ? ' selected' : '') + '>Сотрудник</option>';
        if (profileMayAssignDeptRoles) {
          roleOpts +=
            '<option value="administrator"' + (nr === 'administrator' ? ' selected' : '') + '>Руководитель подразделения</option>' +
            '<option value="manager"' + (nr === 'manager' ? ' selected' : '') + '>Руководитель организации</option>';
        } else if (nr === 'manager' || nr === 'administrator') {
          roleOpts += '<option value="' + nr + '" selected>' + deptRoleRu(nr) + '</option>';
        }
        return (
          '<div class="user-dept-role-row" data-idx="' +
          idx +
          '">' +
          '<select class="input-block profile-dept-role-dept">' +
          depOpts +
          '</select>' +
          '<select class="input-block profile-dept-role-role">' +
          roleOpts +
          '</select>' +
          '<button type="button" class="btn btn-secondary btn-sm profile-dept-role-del">Удалить</button>' +
          '</div>'
        );
      })
      .join('');
    if (typeof window.bxCustomSelectInit === 'function') window.bxCustomSelectInit(host);
    host.querySelectorAll('.user-dept-role-row').forEach(function (rowEl) {
      var idx = parseInt(rowEl.getAttribute('data-idx'), 10) || 0;
      var dep = rowEl.querySelector('.profile-dept-role-dept');
      var role = rowEl.querySelector('.profile-dept-role-role');
      var del = rowEl.querySelector('.profile-dept-role-del');
      if (dep) {
        dep.addEventListener('change', function () {
          if (!profileDeptRows[idx]) return;
          profileDeptRows[idx].department_id = dep.value ? parseInt(dep.value, 10) : 0;
        });
      }
      if (role) {
        role.addEventListener('change', function () {
          if (!profileDeptRows[idx]) return;
          profileDeptRows[idx].role = normalizeDeptRole(role.value);
        });
      }
      if (del) {
        del.addEventListener('click', function () {
          profileDeptRows.splice(idx, 1);
          renderProfileDeptRows();
        });
      }
    });
  }

  function openProfileModal() {
    var modal = $('profileModal');
    if (!modal) return;
    Core.api('/api/me.php')
      .then(function (res) {
        var it = (res && res.item) || {};
        profileDeptOptions = (res && res.department_options) || [];
        profileMayAssignDeptRoles = !!(res && (res.may_assign_department_roles || res.may_assign_head_role));
        if ($('profileName')) $('profileName').value = it.name || '';
        if ($('profileEmail')) $('profileEmail').value = it.email || '';
        if ($('profileMaxChatId')) $('profileMaxChatId').value = it.max_chat_id || '';
        if ($('profileNewPassword')) $('profileNewPassword').value = '';
        if ($('profileNewPassword2')) $('profileNewPassword2').value = '';
        profileDeptRows = ((it.department_roles || []).map(function (x) {
          return {
            department_id: parseInt(x.department_id, 10) || 0,
            role: normalizeDeptRole(x.role),
          };
        })).filter(function (x) {
          return x.department_id > 0;
        });
        renderProfileDeptRows();
        var meta = roleRu(it.role);
        if (Array.isArray(it.department_roles) && it.department_roles.length) {
          var parts = String(it.department_names || '')
            .split(',')
            .map(function (x) {
              return x.trim();
            })
            .filter(function (x) {
              return !!x;
            });
          if (parts.length) {
            meta += ' · ' + parts.join(', ');
          } else if (it.department_name) {
            meta += ' · ' + it.department_name;
          }
        } else if (it.department_names) meta += ' · ' + it.department_names;
        else if (it.department_name) meta += ' · ' + it.department_name;
        if ($('profileMeta')) $('profileMeta').textContent = meta;
        var orgBlock = $('profileOrgYearBlock');
        var orgEditRow = $('profileOrgYearEditRow');
        var orgDisp = $('profileOrgYearDisplay');
        var orgInp = $('profileOrgYear');
        if (orgBlock && res.organization_work_year != null && res.organization_work_year !== '') {
          orgBlock.hidden = false;
          if (orgDisp) orgDisp.textContent = String(res.organization_work_year);
          if (orgInp) orgInp.value = String(res.organization_work_year);
          if (orgEditRow) orgEditRow.hidden = !res.may_edit_organization_work_year;
        } else if (orgBlock) {
          orgBlock.hidden = true;
        }
        if (typeof modal.showModal === 'function') modal.showModal();
        else modal.setAttribute('open', 'open');
      })
      .catch(function (e) {
        notify('Не удалось открыть профиль: ' + (e.message || ''), 'error');
      });
  }

  function openProfileModalFromMenu() {
    closeUserMenu();
    openProfileModal();
  }

  function userMenuEls() {
    return {
      wrap: $('bxUserMenu'),
      btn: $('btnProfileMenuTop'),
      panel: $('bxUserMenuPanel'),
    };
  }

  function isUserMenuOpen() {
    var x = userMenuEls();
    return !!(x.panel && !x.panel.hidden);
  }

  function openUserMenu() {
    var x = userMenuEls();
    if (!x.btn || !x.panel) return;
    x.panel.hidden = false;
    x.btn.setAttribute('aria-expanded', 'true');
  }

  function closeUserMenu() {
    var x = userMenuEls();
    if (!x.btn || !x.panel) return;
    x.panel.hidden = true;
    x.btn.setAttribute('aria-expanded', 'false');
  }

  function toggleUserMenu() {
    if (isUserMenuOpen()) closeUserMenu();
    else openUserMenu();
  }

  function closeProfileModal() {
    var modal = $('profileModal');
    if (!modal) return;
    if (typeof modal.close === 'function') modal.close();
    else modal.removeAttribute('open');
  }

  function submitProfile(ev) {
    ev.preventDefault();
    var name = $('profileName') ? $('profileName').value.trim() : '';
    var p1 = $('profileNewPassword') ? $('profileNewPassword').value : '';
    var p2 = $('profileNewPassword2') ? $('profileNewPassword2').value : '';
    if (!name) return notify('Введите имя', 'error');
    if (p1 || p2) {
      if (p1.length < 6) return notify('Пароль должен быть не короче 6 символов', 'error');
      if (p1 !== p2) return notify('Пароли не совпадают', 'error');
    }
    var body = { name: name };
    if ($('profileMaxChatId')) body.max_chat_id = $('profileMaxChatId').value.trim();
    if (p1) body.new_password = p1;
    var dedup = {};
    var rows = [];
    profileDeptRows.forEach(function (r) {
      var did = parseInt(r.department_id, 10) || 0;
      if (did <= 0 || dedup[did]) return;
      dedup[did] = true;
      rows.push({ department_id: did, role: normalizeDeptRole(r.role) });
    });
    body.department_roles = rows;
    Core.api('/api/me.php', { method: 'PATCH', body: body }, {
      bad_name: 'Некорректное имя',
      bad_password: 'Некорректный пароль',
      bad_department: 'Некорректное подразделение',
      bad_role: 'Некорректная роль',
      bad_max_chat_id: 'Некорректный ID чата MAX',
      schema_max_chat_id_missing: 'Нужно выполнить миграцию БД для поля MAX chat_id',
      head_role_forbidden: 'Роль руководителя может назначить только администратор/ректорат',
      no_fields: 'Нет изменений',
      error: 'Ошибка сохранения',
    })
      .then(function () {
        var topAvatar = $('topUserAvatar');
        if (topAvatar) {
          topAvatar.textContent = avatarLetter(name);
          topAvatar.setAttribute('title', name);
        }
        var topBtn = $('btnProfileOpenTop');
        if (topBtn) topBtn.setAttribute('title', name);
        closeProfileModal();
        notify('Профиль обновлен', 'success');
      })
      .catch(function (e) {
        notify('Не удалось сохранить: ' + (e.message || ''), 'error');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var menuBtn = $('btnProfileMenuTop');
    var openTop = $('btnProfileOpenTop');
    var openRail = $('btnProfileOpenRail');
    var cancel = $('profileCancel');
    var btnAdd = $('btnAddProfileDeptRole');
    var form = $('profileForm');
    if (menuBtn) {
      menuBtn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        toggleUserMenu();
      });
    }
    if (openTop) openTop.addEventListener('click', openProfileModalFromMenu);
    if (openRail) openRail.addEventListener('click', openProfileModal);
    if (cancel) cancel.addEventListener('click', closeProfileModal);
    if (btnAdd) {
      btnAdd.addEventListener('click', function () {
        profileDeptRows.push({ department_id: 0, role: 'user' });
        renderProfileDeptRows();
      });
    }
    if (form) form.addEventListener('submit', submitProfile);
    var orgSave = $('profileOrgYearSave');
    if (orgSave) {
      orgSave.addEventListener('click', function () {
        var inp = $('profileOrgYear');
        if (!inp) return;
        var y = parseInt(inp.value, 10);
        if (y < 1990 || y > 2100) return notify('Укажите год от 1990 до 2100', 'error');
        Core.api(
          '/api/organization.php',
          { method: 'PATCH', body: { work_year: y } },
          { forbidden: 'Недостаточно прав', bad_year: 'Некорректный год', schema: 'Сначала выполните миграцию БД (work_year)', error: 'Не удалось сохранить' }
        )
          .then(function (r) {
            var v = r && r.work_year != null ? String(r.work_year) : String(y);
            var badge = $('bxOrgYearBadge');
            if (badge) badge.textContent = v;
            var disp = $('profileOrgYearDisplay');
            if (disp) disp.textContent = v;
            notify('Год работы организации обновлён', 'success');
          })
          .catch(function (e) {
            notify(e.message || 'Ошибка', 'error');
          });
      });
    }

    // Закрывать меню профиля по клику вне и по Escape
    document.addEventListener('click', function (ev) {
      var x = userMenuEls();
      if (!x.wrap || !x.panel || x.panel.hidden) return;
      if (!ev.target) return;
      if (x.wrap.contains(ev.target)) return;
      closeUserMenu();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') closeUserMenu();
    });
  });

  // Экспорт для страницы профиля
  window.PlannerProfile = window.PlannerProfile || {};
  window.PlannerProfile.openModal = openProfileModal;
})();

