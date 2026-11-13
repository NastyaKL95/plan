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

  function mayManage() {
    return !!window.__DEPT_ADMIN__;
  }

  var lastDeptItems = [];
  var deptUsers = [];

  function loadUsers() {
    return api('/api/users.php').then(function (d) {
      deptUsers = d.items || [];
    });
  }

  function userSelectOptions(excludeIds) {
    excludeIds = excludeIds || {};
    return deptUsers
      .filter(function (u) {
        return !excludeIds[String(u.id)];
      })
      .map(function (u) {
        return '<option value="' + u.id + '">' + escapeHtml(u.name) + '</option>';
      })
      .join('');
  }

  function render(items) {
    var list = $('deptList');
    var emptyEl = $('deptListEmpty');
    if (!list) return;
    if (!items || !items.length) {
      list.innerHTML = '';
      if (emptyEl) emptyEl.hidden = false;
      return;
    }
    if (emptyEl) emptyEl.hidden = true;
    var adm = mayManage();
    list.innerHTML = items
      .map(function (d) {
        var hid = parseInt(d.id, 10);
        var heads = Array.isArray(d.heads) ? d.heads : [];
        var headUsers = Array.isArray(d.head_users) ? d.head_users : [];
        var exclude = {};
        headUsers.forEach(function (h) {
          exclude[String(h.user_id)] = true;
        });
        var headsHtml = heads.length
          ? 'Руководители: ' + escapeHtml(heads.join(', '))
          : 'Руководители не назначены.';
        var chips =
          adm && headUsers.length
            ? headUsers
                .map(function (h) {
                  return (
                    '<span class="dept-head-chip">' +
                    escapeHtml(h.name) +
                    ' <button type="button" class="link-btn dept-head-remove" data-dept="' +
                    hid +
                    '" data-user="' +
                    h.user_id +
                    '" aria-label="Снять">×</button></span>'
                  );
                })
                .join(' ')
            : '';
        var headPick =
          adm && deptUsers.length
            ? '<div class="dept-heads-add">' +
              '<label class="visually-hidden" for="deptHeadPick' +
              hid +
              '">Добавить руководителя</label>' +
              '<select id="deptHeadPick' +
              hid +
              '" class="bx-select dept-head-pick">' +
              '<option value="">Выберите сотрудника…</option>' +
              userSelectOptions(exclude) +
              '</select> ' +
              '<button type="button" class="btn btn-secondary btn-sm dept-head-add" data-dept="' +
              hid +
              '">Добавить</button></div>'
            : '';
        var actions = adm
          ? '<div class="dept-card-actions">' +
            '<button type="button" class="btn btn-secondary btn-sm dept-btn-edit" data-id="' +
            hid +
            '">Изменить</button> ' +
            '<button type="button" class="btn btn-secondary btn-sm dept-btn-del" data-id="' +
            hid +
            '">Удалить</button>' +
            '</div>'
          : '';
        return (
          '<article class="dept-card" data-id="' +
          hid +
          '">' +
          '<div class="dept-card-head">' +
          '<h2>' +
          escapeHtml(d.name || '') +
          '</h2>' +
          '<span class="dept-sort-badge" title="Порядок сортировки">' +
          (d.sort_order != null ? escapeHtml(String(d.sort_order)) : '0') +
          '</span>' +
          '</div>' +
          '<p class="dept-heads">' +
          headsHtml +
          '</p>' +
          (adm ? '<div class="dept-head-chips">' + chips + '</div>' + headPick : '') +
          actions +
          '</article>'
        );
      })
      .join('');

    if (typeof window.bxCustomSelectInit === 'function') window.bxCustomSelectInit(list);

    if (adm) {
      list.querySelectorAll('.dept-btn-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
          openEdit(parseInt(btn.getAttribute('data-id'), 10));
        });
      });
      list.querySelectorAll('.dept-btn-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
          removeDept(parseInt(btn.getAttribute('data-id'), 10));
        });
      });
      list.querySelectorAll('.dept-head-add').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var did = parseInt(btn.getAttribute('data-dept'), 10);
          var sel = $('deptHeadPick' + did);
          var uid = sel && sel.value ? parseInt(sel.value, 10) : 0;
          if (!did || !uid) return;
          api('/api/department_heads.php', {
            method: 'POST',
            body: { department_id: did, user_id: uid },
          })
            .then(function () {
              return loadList();
            })
            .catch(function (e) {
              var m = e.message || '';
              if (m === 'duplicate') m = 'Уже назначен.';
              notify(m, 'error');
            });
        });
      });
      list.querySelectorAll('.dept-head-remove').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var did = parseInt(btn.getAttribute('data-dept'), 10);
          var uid = parseInt(btn.getAttribute('data-user'), 10);
          if (!did || !uid) return;
          api('/api/department_heads.php?department_id=' + did + '&user_id=' + uid, { method: 'DELETE' })
            .then(function () {
              return loadList();
            })
            .catch(function (e) {
              notify(e.message || '', 'error');
            });
        });
      });
    }
  }

  function loadList() {
    return api('/api/departments.php')
      .then(function (data) {
        lastDeptItems = data.items || [];
        render(lastDeptItems);
      })
      .catch(function () {
        lastDeptItems = [];
        render([]);
      });
  }

  function openEdit(id) {
    var d = null;
    for (var i = 0; i < lastDeptItems.length; i++) {
      if (parseInt(lastDeptItems[i].id, 10) === id) {
        d = lastDeptItems[i];
        break;
      }
    }
    if (!d) return;
    $('editDeptId').value = String(id);
    $('editDeptName').value = d.name || '';
    $('editDeptSort').value = d.sort_order != null ? String(d.sort_order) : '0';
    $('deptEditModal').showModal();
  }

  function removeDept(id) {
    if (!confirm('Удалить подразделение? Если к нему привязаны проекты, удаление будет запрещено.')) return;
    api('/api/department.php?id=' + id, { method: 'DELETE' })
      .then(function () {
        return loadList();
      })
      .catch(function (e) {
        var msg = e.message || '';
        if (msg === 'has_projects') {
          msg = 'Нельзя удалить: есть проекты этого подразделения. Перенесите или удалите проекты.';
        }
        notify(msg, 'error');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadUsers()
      .catch(function () {})
      .then(function () {
        return loadList();
      });

    var formCreate = $('formCreateDept');
    if (formCreate) {
      formCreate.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var msg = $('deptCreateMsg');
        var name = $('newDeptName').value.trim();
        var sortOrder = parseInt($('newDeptSort').value, 10);
        if (!name) return;
        if (isNaN(sortOrder) || sortOrder < 0) sortOrder = 0;
        api('/api/departments.php', {
          method: 'POST',
          body: { name: name, sort_order: sortOrder },
        })
          .then(function () {
            $('newDeptName').value = '';
            $('newDeptSort').value = '0';
            if (msg) {
              msg.textContent = 'Подразделение создано.';
              setTimeout(function () {
                msg.textContent = '';
              }, 3500);
            }
            return loadList();
          })
          .catch(function (e) {
            if (msg) msg.textContent = '';
            notify('Не создано: ' + (e.message || ''), 'error');
          });
      });
    }

    var formEdit = $('formEditDept');
    if (formEdit) {
      formEdit.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var id = parseInt($('editDeptId').value, 10);
        if (!id) return;
        var name = $('editDeptName').value.trim();
        var sortOrder = parseInt($('editDeptSort').value, 10);
        if (!name) return;
        if (isNaN(sortOrder) || sortOrder < 0) sortOrder = 0;
        api('/api/department.php?id=' + id, {
          method: 'PATCH',
          body: { name: name, sort_order: sortOrder },
        })
          .then(function () {
            $('deptEditModal').close();
            return loadList();
          })
          .catch(function (e) {
            notify('Не сохранено: ' + (e.message || ''), 'error');
          });
      });
    }
    var editCancel = $('editDeptCancel');
    if (editCancel) {
      editCancel.addEventListener('click', function () {
        $('deptEditModal').close();
      });
    }
  });
})();
