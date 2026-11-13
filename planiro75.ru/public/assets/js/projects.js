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

  var departments = [];
  var portfolios = [];
  var projects = [];
  var collapsedPortfolioIds = {};
  var canDeletePortfolios = false;
  var canCreateProjects = false;

  function fillDeptSelect() {
    var sel = $('projDept');
    if (!sel) return;
    sel.innerHTML = (departments || [])
      .map(function (d) {
        return '<option value="' + d.id + '">' + escapeHtml(d.name) + '</option>';
      })
      .join('');
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function fillPortfolioProjectSelect() {
    var sel = $('portfolioProjects');
    if (!sel) return;
    sel.innerHTML = projects
      .map(function (p) {
        return '<option value="' + p.id + '">' + escapeHtml(p.name) + '</option>';
      })
      .join('');
  }

  function getPortfolioDescendants(rootId) {
    var out = {};
    var children = {};
    portfolios.forEach(function (pf) {
      var key = String(pf.parent_portfolio_id || '');
      if (!children[key]) children[key] = [];
      children[key].push(parseInt(pf.id, 10));
    });
    function walk(id) {
      var arr = children[String(id)] || [];
      arr.forEach(function (x) {
        if (out[x]) return;
        out[x] = true;
        walk(x);
      });
    }
    walk(rootId);
    return out;
  }

  function fillPortfolioParentSelect(editId) {
    var sel = $('portfolioParent');
    if (!sel) return;
    var blocked = editId ? getPortfolioDescendants(editId) : {};
    sel.innerHTML =
      '<option value="">Без родителя</option>' +
      portfolios
        .map(function (p) {
          if (editId && parseInt(p.id, 10) === editId) return '';
          if (blocked[parseInt(p.id, 10)]) return '';
          return '<option value="' + p.id + '">' + escapeHtml(p.name) + '</option>';
        })
        .join('');
    if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
  }

  function render(items) {
    var grid = $('projectsGrid');
    var mapPortfolioProjects = {};
    items.forEach(function (p) {
      (p.portfolio_ids || []).forEach(function (pfid) {
        if (!mapPortfolioProjects[pfid]) mapPortfolioProjects[pfid] = [];
        mapPortfolioProjects[pfid].push(p);
      });
    });
    var children = {};
    portfolios.forEach(function (pf) {
      var parent = pf.parent_portfolio_id ? String(pf.parent_portfolio_id) : 'root';
      if (!children[parent]) children[parent] = [];
      children[parent].push(pf);
    });

    function projectCard(p, sourcePortfolioId) {
      var src = parseInt(sourcePortfolioId || 0, 10) || 0;
      var dept = p.department_name ? '<div class="project-dept">' + escapeHtml(p.department_name) + '</div>' : '';
      return (
        '<article class="project-card' +
        (src ? ' project-card--in-portfolio' : '') +
        '" style="--accent:' +
        escapeHtml(p.color) +
        '" data-project-id="' +
        p.id +
        '" data-source-portfolio-id="' +
        src +
        '" draggable="true"><div class="project-dot"></div>' +
        dept +
        '<h3>' +
        escapeHtml(p.name) +
        '</h3><p>' +
        escapeHtml(p.description || '') +
        '</p>' +
        '<a class="project-open-tasks-btn" data-tip="Открыть задачи" href="' +
        apiUrl('/tasks.php?project_id=' + encodeURIComponent(p.id)) +
        '" title="Открыть задачи проекта" aria-label="Открыть задачи проекта"><svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path d="M3 5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v2H3V5z" fill="currentColor"></path><path d="M3 10h18l-2 9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2l-2-9z" fill="currentColor"></path></svg></a>' +
        '<button type="button" class="project-edit-btn project-edit-icon-btn" data-tip="Редактировать проект" data-id="' +
        p.id +
        '" title="Редактировать проект" aria-label="Редактировать проект"><svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path d="M3 17.25V21h3.75L18.81 8.94l-3.75-3.75L3 17.25z" fill="currentColor"></path><path d="M20.71 7.04a1.003 1.003 0 000-1.42L18.37 3.29a1.003 1.003 0 00-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.83z" fill="currentColor"></path></svg></button>' +
        (src
          ? '<button type="button" class="portfolio-project-unlink link-btn" data-project-id="' +
            p.id +
            '" data-source-portfolio-id="' +
            src +
            '" title="Убрать из портфеля">×</button>'
          : '') +
        '</article>'
      );
    }

    function renderPortfolioNode(pf) {
      var childPortfolios = children[String(pf.id)] || [];
      var ownProjects = mapPortfolioProjects[pf.id] || [];
      var isCollapsed = !!collapsedPortfolioIds[pf.id];
      var hasChildren = childPortfolios.length > 0;
      return (
        '<section class="portfolio-node' +
        (isCollapsed ? ' is-collapsed' : '') +
        '" style="--accent:' +
        escapeHtml(pf.color || '#0ea5e9') +
        '" data-portfolio-id="' +
        pf.id +
        '"><div class="portfolio-head"><div class="portfolio-head-main">' +
        (hasChildren
          ? '<button type="button" class="portfolio-collapse" data-id="' +
            pf.id +
            '" title="Свернуть/развернуть">' +
            (isCollapsed ? '+' : '-') +
            '</button>'
          : '') +
        '<span class="portfolio-dot"></span><h3 class="portfolio-title">' +
        escapeHtml(pf.name) +
        '</h3></div><div class="portfolio-actions"><button type="button" class="portfolio-menu-toggle" data-id="' +
        pf.id +
        '" aria-label="Действия">⋯</button><div class="portfolio-menu" data-menu-id="' +
        pf.id +
        '" hidden><button type="button" class="portfolio-menu-item portfolio-edit" data-id="' +
        pf.id +
        '">Изменить</button>' +
        (canCreateProjects
          ? '<button type="button" class="portfolio-menu-item portfolio-add-child" data-id="' +
            pf.id +
            '">Добавить дочерний</button>'
          : '') +
        (canDeletePortfolios
          ? '<button type="button" class="portfolio-menu-item is-danger portfolio-delete" data-id="' +
            pf.id +
            '">Удалить</button>'
          : '') +
        '</div></div></div><p class="portfolio-desc">' +
        escapeHtml(pf.description || '') +
        '</p><div class="portfolio-body"' +
        (isCollapsed ? ' hidden' : '') +
        '>' +
        (ownProjects.length
          ? '<div class="portfolio-projects dropzone" data-portfolio-id="' +
            pf.id +
            '">' +
            ownProjects
              .map(function (p) {
                return projectCard(p, pf.id);
              })
              .join('') +
            '</div>'
          : '<div class="portfolio-projects dropzone" data-portfolio-id="' + pf.id + '"><p class="muted">Проекты не добавлены</p></div>') +
        (childPortfolios.length
          ? '<div class="portfolio-children">' + childPortfolios.map(renderPortfolioNode).join('') + '</div>'
          : '') +
        '</div></section>'
      );
    }

    var roots = children.root || [];
    var assigned = {};
    Object.keys(mapPortfolioProjects).forEach(function (k) {
      (mapPortfolioProjects[k] || []).forEach(function (p) {
        assigned[p.id] = true;
      });
    });
    var unassigned = items.filter(function (p) {
      return !assigned[p.id];
    });
    var html = '<div class="portfolio-main">';
    if (roots.length) {
      html += '<div class="portfolio-tree">' + roots.map(renderPortfolioNode).join('') + '</div>';
    } else {
      html += canCreateProjects
        ? '<p class="muted">Пока нет портфелей. Создайте первый портфель кнопкой сверху.</p>'
        : '<p class="muted">Пока нет портфелей.</p>';
    }
    html += '<section class="portfolio-unassigned"><h3>Проекты без портфеля</h3>';
    html +=
      '<div class="projects-grid dropzone-unassigned">' +
      (unassigned.length
        ? unassigned
            .map(function (p) {
              return projectCard(p, 0);
            })
            .join('')
        : '<p class="muted">Нет</p>') +
      '</div>';
    html += '</section></div>';
    grid.innerHTML = html;

    bindProjectUiEvents(grid);
    setupPortfolioDnD(grid);
  }

  function closePortfolioMenus() {
    document.querySelectorAll('.portfolio-menu').forEach(function (menu) {
      menu.hidden = true;
    });
  }

  function bindProjectUiEvents(grid) {
    grid.querySelectorAll('.portfolio-collapse').forEach(function (btn) {
      if (btn.classList.contains('portfolio-collapse--ghost')) return;
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        if (!id) return;
        collapsedPortfolioIds[id] = !collapsedPortfolioIds[id];
        render(projects);
      });
    });
    grid.querySelectorAll('.portfolio-menu-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        var menu = id ? grid.querySelector('.portfolio-menu[data-menu-id="' + id + '"]') : null;
        if (!menu) return;
        var open = menu.hidden;
        closePortfolioMenus();
        menu.hidden = !open;
      });
    });
    grid.querySelectorAll('.portfolio-edit').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closePortfolioMenus();
        openPortfolioEdit(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
    grid.querySelectorAll('.portfolio-add-child').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closePortfolioMenus();
        openPortfolioCreate(parseInt(btn.getAttribute('data-id'), 10) || 0);
      });
    });
    grid.querySelectorAll('.portfolio-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closePortfolioMenus();
        var id = parseInt(btn.getAttribute('data-id'), 10);
        if (!id || !confirm('Удалить портфель?')) return;
        api('/api/portfolios.php?id=' + id, { method: 'DELETE' })
          .then(load)
          .catch(function (err) {
            notify('Не удалось удалить портфель: ' + (err.message || ''), 'error');
          });
      });
    });
    grid.querySelectorAll('.portfolio-project-unlink').forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var pid = parseInt(btn.getAttribute('data-project-id'), 10);
        var src = parseInt(btn.getAttribute('data-source-portfolio-id'), 10);
        if (!pid || !src) return;
        api('/api/portfolio_project.php', {
          method: 'POST',
          body: { mode: 'detach', project_id: pid, source_portfolio_id: src },
        })
          .then(load)
          .catch(function (err) {
            notify('Не удалось убрать проект из портфеля: ' + (err.message || ''), 'error');
          });
      });
    });
    grid.querySelectorAll('.project-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        openProjectEdit(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
  }

  function setupPortfolioDnD(root) {
    root.querySelectorAll('.project-card[data-project-id]').forEach(function (card) {
      card.addEventListener('dragstart', function (ev) {
        ev.dataTransfer.effectAllowed = 'move';
        ev.dataTransfer.setData(
          'text/plain',
          JSON.stringify({
            projectId: parseInt(card.getAttribute('data-project-id'), 10),
            sourcePortfolioId: parseInt(card.getAttribute('data-source-portfolio-id') || '0', 10) || 0,
          })
        );
      });
    });
    root.querySelectorAll('.portfolio-projects.dropzone').forEach(function (zone) {
      zone.addEventListener('dragover', function (ev) {
        ev.preventDefault();
        zone.classList.add('is-over');
      });
      zone.addEventListener('dragleave', function () {
        zone.classList.remove('is-over');
      });
      zone.addEventListener('drop', function (ev) {
        ev.preventDefault();
        zone.classList.remove('is-over');
        var raw = ev.dataTransfer.getData('text/plain');
        if (!raw) return;
        var payload;
        try {
          payload = JSON.parse(raw);
        } catch (e) {
          return;
        }
        var projectId = parseInt(payload.projectId, 10);
        var src = parseInt(payload.sourcePortfolioId || 0, 10) || 0;
        var target = parseInt(zone.getAttribute('data-portfolio-id') || '0', 10);
        if (!projectId || !target) return;
        if (src === target) return;
        api('/api/portfolio_project.php', {
          method: 'POST',
          body: {
            mode: src ? 'move' : 'attach',
            project_id: projectId,
            source_portfolio_id: src || null,
            target_portfolio_id: target,
          },
        })
          .then(load)
          .catch(function (err) {
            notify('Не удалось перенести проект: ' + (err.message || ''), 'error');
          });
      });
    });
    var unassigned = root.querySelector('.dropzone-unassigned');
    if (unassigned) {
      unassigned.addEventListener('dragover', function (ev) {
        ev.preventDefault();
        unassigned.classList.add('is-over');
      });
      unassigned.addEventListener('dragleave', function () {
        unassigned.classList.remove('is-over');
      });
      unassigned.addEventListener('drop', function (ev) {
        ev.preventDefault();
        unassigned.classList.remove('is-over');
        var raw = ev.dataTransfer.getData('text/plain');
        if (!raw) return;
        var payload;
        try {
          payload = JSON.parse(raw);
        } catch (e) {
          return;
        }
        var projectId = parseInt(payload.projectId, 10);
        var src = parseInt(payload.sourcePortfolioId || 0, 10) || 0;
        if (!projectId || !src) return;
        api('/api/portfolio_project.php', {
          method: 'POST',
          body: { mode: 'detach', project_id: projectId, source_portfolio_id: src },
        })
          .then(load)
          .catch(function (err) {
            notify('Не удалось убрать проект из портфеля: ' + (err.message || ''), 'error');
          });
      });
    }
  }

  function openPortfolioCreate(parentId) {
    var portfolioModal = $('portfolioModal');
    $('portfolioModalTitle').textContent = 'Портфель';
    $('portfolioId').value = '';
    $('portfolioName').value = '';
    $('portfolioDesc').value = '';
    $('portfolioColor').value = '#0ea5e9';
    fillPortfolioParentSelect(0);
    if ($('portfolioParent')) $('portfolioParent').value = parentId ? String(parentId) : '';
    if (typeof window.bxCustomSelectSync === 'function' && $('portfolioParent')) {
      window.bxCustomSelectSync($('portfolioParent'));
    }
    fillPortfolioProjectSelect();
    Array.from(($('portfolioProjects') || { options: [] }).options || []).forEach(function (o) {
      o.selected = false;
    });
    if (typeof portfolioModal.showModal === 'function') portfolioModal.showModal();
    else portfolioModal.setAttribute('open', 'open');
  }

  function openPortfolioEdit(id) {
    if (!id) return;
    var pf = portfolios.find(function (x) {
      return parseInt(x.id, 10) === id;
    });
    if (!pf) return;
    var portfolioModal = $('portfolioModal');
    $('portfolioModalTitle').textContent = 'Редактировать портфель';
    $('portfolioId').value = String(id);
    $('portfolioName').value = pf.name || '';
    $('portfolioDesc').value = pf.description || '';
    $('portfolioColor').value = pf.color || '#0ea5e9';
    fillPortfolioParentSelect(id);
    if ($('portfolioParent')) $('portfolioParent').value = pf.parent_portfolio_id ? String(pf.parent_portfolio_id) : '';
    if (typeof window.bxCustomSelectSync === 'function' && $('portfolioParent')) {
      window.bxCustomSelectSync($('portfolioParent'));
    }
    fillPortfolioProjectSelect();
    var set = {};
    (pf.project_ids || []).forEach(function (x) {
      set[String(x)] = true;
    });
    Array.from(($('portfolioProjects') || { options: [] }).options || []).forEach(function (o) {
      o.selected = !!set[o.value];
    });
    if (typeof portfolioModal.showModal === 'function') portfolioModal.showModal();
    else portfolioModal.setAttribute('open', 'open');
  }

  function fillProjectPortfolioSelect(selectedIds) {
    var pfSel = $('projPortfolios');
    if (!pfSel) return;
    var set = {};
    (selectedIds || []).forEach(function (x) {
      set[String(x)] = true;
    });
    pfSel.innerHTML = portfolios
      .map(function (p) {
        return '<option value="' + p.id + '"' + (set[String(p.id)] ? ' selected' : '') + '>' + escapeHtml(p.name) + '</option>';
      })
      .join('');
  }

  function updateProjectDeleteUi(p) {
    var btnDel = $('projDelete');
    var hint = $('projDeleteHint');
    if (!btnDel) return;
    var editId = parseInt($('projId').value || '0', 10);
    if (!editId || !p) {
      btnDel.hidden = true;
      if (hint) hint.hidden = true;
      return;
    }
    var tc = parseInt(p.task_count, 10) || 0;
    var canDelete = !!p.can_delete;
    btnDel.hidden = !canDelete;
    if (hint) {
      if (canDelete) {
        hint.hidden = true;
      } else if (p.delete_block === 'has_tasks' && tc > 0) {
        hint.hidden = false;
        hint.textContent =
          'Удаление недоступно: в проекте ' + tc + ' ' + (tc === 1 ? 'задача' : tc < 5 ? 'задачи' : 'задач') + '.';
      } else {
        hint.hidden = false;
        hint.textContent = 'Удалять проекты может только администратор организации.';
      }
    }
  }

  function openProjectCreate() {
    var modal = $('projectModal');
    $('projectModalTitle').textContent = 'Проект';
    $('projId').value = '';
    updateProjectDeleteUi(null);
    $('projName').value = '';
    $('projDesc').value = '';
    $('projColor').value = '#2563eb';
    fillDeptSelect();
    fillProjectPortfolioSelect([]);
    if ($('projDept') && $('projDept').options.length) $('projDept').selectedIndex = 0;
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', 'open');
  }

  function openProjectEdit(id) {
    if (!id) return;
    var p = projects.find(function (x) {
      return parseInt(x.id, 10) === id;
    });
    if (!p) return;
    var modal = $('projectModal');
    $('projectModalTitle').textContent = 'Редактировать проект';
    $('projId').value = String(id);
    $('projName').value = p.name || '';
    $('projDesc').value = p.description || '';
    $('projColor').value = p.color || '#2563eb';
    fillDeptSelect();
    if ($('projDept')) $('projDept').value = p.department_id ? String(p.department_id) : '';
    if (typeof window.bxCustomSelectSync === 'function' && $('projDept')) {
      window.bxCustomSelectSync($('projDept'));
    }
    fillProjectPortfolioSelect(p.portfolio_ids || []);
    updateProjectDeleteUi(p);
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', 'open');
  }

  function deleteProject(id) {
    if (!id) return;
    var p = projects.find(function (x) {
      return parseInt(x.id, 10) === id;
    });
    var name = p ? p.name || '' : '';
    var tc = p ? parseInt(p.task_count, 10) || 0 : 0;
    if (tc > 0) {
      notify('Нельзя удалить проект: в нём есть задачи (' + tc + ').', 'error');
      return;
    }
    if (!confirm('Удалить проект «' + name + '»?')) return;
    api('/api/projects.php?id=' + id, {
      method: 'DELETE',
      has_tasks: 'Нельзя удалить: в проекте есть задачи. Сначала удалите или перенесите задачи.',
      forbidden: 'Недостаточно прав для удаления проекта',
    })
      .then(function () {
        var modal = $('projectModal');
        if (modal && typeof modal.close === 'function') modal.close();
        else if (modal) modal.removeAttribute('open');
        notify('Проект удалён.', 'success');
        load();
      })
      .catch(function (err) {
        notify(err.message || 'Не удалось удалить проект', 'error');
      });
  }

  function load() {
    Promise.all([api('/api/departments.php'), api('/api/projects.php'), api('/api/portfolios.php').catch(function(){ return {items:[]}; })])
      .then(function (res) {
        departments = res[0].items || [];
        projects = res[1].items || [];
        portfolios = res[2].items || [];
        canDeletePortfolios = !!(res[2] && res[2].can_delete);
        canCreateProjects = !!(
          (res[1] && res[1].can_create) ||
          (res[2] && res[2].can_create)
        );
        fillDeptSelect();
        fillPortfolioParentSelect();
        fillPortfolioProjectSelect();
        render(projects);
      })
      .catch(function () {
        $('projectsGrid').innerHTML = '<p class="muted">Не удалось загрузить данные.</p>';
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var modal = $('projectModal');
    var portfolioModal = $('portfolioModal');
    if ($('btnNewProject')) $('btnNewProject').addEventListener('click', openProjectCreate);
    $('projCancel').addEventListener('click', function () {
      if (typeof modal.close === 'function') modal.close();
      else modal.removeAttribute('open');
    });
    if ($('projDelete')) {
      $('projDelete').addEventListener('click', function () {
        deleteProject(parseInt($('projId').value || '0', 10));
      });
    }
    $('projectForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var editId = parseInt($('projId').value || '0', 10);
      var did = parseInt($('projDept').value, 10);
      if (!did) {
        notify('Выберите подразделение', 'error');
        return;
      }
      api(editId ? '/api/projects.php?id=' + editId : '/api/projects.php', {
        method: editId ? 'PATCH' : 'POST',
        body: {
          department_id: did,
          name: $('projName').value.trim(),
          description: $('projDesc').value.trim(),
          color: $('projColor').value,
          portfolio_ids: Array.from(($('projPortfolios') || { selectedOptions: [] }).selectedOptions || []).map(function (o) {
            return parseInt(o.value, 10);
          }),
        },
      })
        .then(function () {
          if (typeof modal.close === 'function') modal.close();
          else modal.removeAttribute('open');
          load();
        })
        .catch(function (err) {
          notify('Не удалось сохранить: ' + (err.message || ''), 'error');
        });
    });
    if ($('btnNewPortfolio')) $('btnNewPortfolio').addEventListener('click', openPortfolioCreate);
    if ($('portfolioCancel')) {
      $('portfolioCancel').addEventListener('click', function () {
        if (typeof portfolioModal.close === 'function') portfolioModal.close();
        else portfolioModal.removeAttribute('open');
      });
    }
    if ($('portfolioForm')) {
      $('portfolioForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var editId = parseInt($('portfolioId').value || '0', 10);
        var path = editId ? '/api/portfolios.php?id=' + editId : '/api/portfolios.php';
        var method = editId ? 'PATCH' : 'POST';
        api(path, {
          method: method,
          body: {
            name: $('portfolioName').value.trim(),
            description: $('portfolioDesc').value.trim(),
            color: $('portfolioColor').value,
            parent_portfolio_id: $('portfolioParent').value ? parseInt($('portfolioParent').value, 10) : null,
            project_ids: Array.from(($('portfolioProjects') || { selectedOptions: [] }).selectedOptions || []).map(function (o) {
              return parseInt(o.value, 10);
            }),
          },
        })
          .then(function () {
            if (typeof portfolioModal.close === 'function') portfolioModal.close();
            else portfolioModal.removeAttribute('open');
            load();
          })
          .catch(function (err) {
            notify('Не удалось сохранить портфель: ' + (err.message || 'ошибка'), 'error');
          });
      });
    }
    document.addEventListener('mousedown', function (ev) {
      var t = ev.target;
      if (t.closest && t.closest('.portfolio-actions')) return;
      closePortfolioMenus();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') closePortfolioMenus();
    });
    load();
  });
})();
