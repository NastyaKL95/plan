  function refreshMyPlanStagesFromServer() {
    return api('/api/my_plan_stages.php').then(function (r) {
      myPlanStages = (r && r.items) || [];
      myPlanStagesFeature = !!(r && r.feature);
      return myPlanStages;
    });
  }

  function openMyPlanStagesDialog() {
    var dlg = $('myPlanStagesDialog');
    if (!dlg || !myPlanStagesFeature) return;
    dlg.hidden = false;
    renderMyPlanStagesEditor();
    requestAnimationFrame(function () {
      var c = $('myPlanStagesClose');
      if (c && c.focus) c.focus();
    });
  }

  function closeMyPlanStagesDialog() {
    var dlg = $('myPlanStagesDialog');
    if (dlg) dlg.hidden = true;
  }

  function renderMyPlanStagesEditor() {
    var ul = $('myPlanStagesList');
    if (!ul) return;
    ul.innerHTML = myPlanStages
      .map(function (s) {
        var rawC = String(s.color || '#64748b');
        if (!/^#[0-9A-Fa-f]{6}$/.test(rawC)) rawC = '#64748b';
        return (
          '<li class="my-plan-stage-row" data-stage-id="' +
          parseInt(s.id, 10) +
          '">' +
          '<span class="my-plan-stage-move">' +
          '<button type="button" class="btn btn-secondary my-plan-stage-up" data-dir="-1" aria-label="Выше">↑</button>' +
          '<button type="button" class="btn btn-secondary my-plan-stage-down" data-dir="1" aria-label="Ниже">↓</button>' +
          '</span>' +
          '<input type="color" class="my-plan-stage-color" value="' +
          escapeHtml(rawC) +
          '" aria-label="Цвет"/>' +
          '<input type="text" class="input-block my-plan-stage-name" maxlength="120" value="' +
          escapeHtml(s.name || '') +
          '"/>' +
          '<button type="button" class="btn btn-danger-outline btn-sm my-plan-stage-del">Удалить</button>' +
          '</li>'
        );
      })
      .join('');
    ul.querySelectorAll('.my-plan-stage-name').forEach(function (inp) {
      inp.addEventListener('change', function () {
        var row = inp.closest('.my-plan-stage-row');
        patchMyPlanStageField(row, { name: inp.value.trim() }).catch(function () {});
      });
    });
    ul.querySelectorAll('.my-plan-stage-color').forEach(function (inp) {
      inp.addEventListener('change', function () {
        var row = inp.closest('.my-plan-stage-row');
        patchMyPlanStageField(row, { color: inp.value }).catch(function () {});
      });
    });
    ul.querySelectorAll('.my-plan-stage-up,.my-plan-stage-down').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var dir = parseInt(btn.getAttribute('data-dir'), 10);
        var row = btn.closest('.my-plan-stage-row');
        moveMyPlanStageRow(row, dir);
      });
    });
    ul.querySelectorAll('.my-plan-stage-del').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.my-plan-stage-row');
        deleteMyPlanStageRow(row);
      });
    });
  }

  function patchMyPlanStageField(rowOrId, fields) {
    var id = 0;
    if (typeof rowOrId === 'number') {
      id = rowOrId > 0 ? rowOrId : 0;
    } else if (rowOrId && typeof rowOrId.getAttribute === 'function') {
      id = parseInt(rowOrId.getAttribute('data-stage-id'), 10) || 0;
    }
    if (!(id > 0)) {
      return Promise.resolve();
    }
    var body = { id: id };
    if (fields.name != null) body.name = fields.name;
    if (fields.color != null) body.color = fields.color;
    return api('/api/my_plan_stages.php', { method: 'PATCH', body: body })
      .then(function () {
        return refreshMyPlanStagesFromServer();
      })
      .then(function () {
        renderMyPlanStagesEditor();
        if (viewMode === 'plan') renderMyPlan();
      })
      .catch(function (e) {
        notify('Не удалось сохранить стадию: ' + (e.message || ''), 'error');
        throw e;
      });
  }

  function moveMyPlanStageRow(row, delta) {
    var ul = $('myPlanStagesList');
    if (!ul || !row) return;
    var lis = Array.prototype.slice.call(ul.querySelectorAll('.my-plan-stage-row'));
    var i = lis.indexOf(row);
    if (i < 0) return;
    var j = i + delta;
    if (j < 0 || j >= lis.length) return;
    var a = lis[i];
    var b = lis[j];
    if (delta < 0) {
      ul.insertBefore(a, b);
    } else {
      ul.insertBefore(a, b.nextSibling);
    }
    saveMyPlanStagesOrder();
  }

  function saveMyPlanStagesOrder() {
    var ul = $('myPlanStagesList');
    if (!ul) return;
    var ids = Array.prototype.map.call(ul.querySelectorAll('.my-plan-stage-row'), function (r) {
      return parseInt(r.getAttribute('data-stage-id'), 10);
    });
    api('/api/my_plan_stages.php', { method: 'PATCH', body: { reorder: ids } })
      .then(function () {
        return refreshMyPlanStagesFromServer();
      })
      .then(function () {
        renderMyPlanStagesEditor();
        if (viewMode === 'plan') renderMyPlan();
      })
      .catch(function (e) {
        notify('Не удалось изменить порядок: ' + (e.message || ''), 'error');
      });
  }

  function deleteMyPlanStageRow(row) {
    var id = row ? parseInt(row.getAttribute('data-stage-id'), 10) : 0;
    if (!(id > 0)) return;
    openAppInlineConfirm({
      title: 'Удалить стадию?',
      message: 'Задачи в этой стадии перейдут в другую колонку.',
      confirmLabel: 'Удалить',
      danger: true,
    }).then(function (ok) {
      if (!ok) return;
      api('/api/my_plan_stages.php?id=' + encodeURIComponent(id), { method: 'DELETE' })
        .then(function () {
          return refreshMyPlanStagesFromServer();
        })
        .then(function () {
          closeMyPlanStagesDialog();
          return loadTasks();
        })
        .catch(function (e) {
          var msg = String((e && e.message) || '').toLowerCase();
          if (msg.indexOf('последн') !== -1 || msg.indexOf('last') !== -1) {
            notify('Нельзя удалить последнюю стадию.', 'error');
          } else {
            notify('Не удалось удалить: ' + (e.message || ''), 'error');
          }
        });
    });
  }

  function addMyPlanStage() {
    openAppInlinePrompt({
      title: 'Новая стадия',
      message: 'Название колонки «Мой план».',
      value: '',
      maxlength: 120,
      confirmLabel: 'Создать',
    }).then(function (name) {
      if (name == null) return;
      var nm = (name || '').trim();
      if (!nm) return;
      api('/api/my_plan_stages.php', { method: 'POST', body: { name: nm, color: '#64748b' } })
        .then(function () {
          return refreshMyPlanStagesFromServer();
        })
        .then(function () {
          renderMyPlanStagesEditor();
          if (viewMode === 'plan') renderMyPlan();
        })
        .catch(function (e) {
          notify('Не удалось создать: ' + (e.message || ''), 'error');
        });
    });
  }
