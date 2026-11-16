(function () {
  'use strict';

  var C = window.PlannerCore;
  var cfg = window.__DOCS__ || { types: {}, mayManage: false };
  var $ = C.byId;
  var esc = C.escapeHtml;

  var ERRORS = {
    file_required: 'Выберите файл документа.',
    bad_extension: 'Такой формат файла не поддерживается.',
    file_too_large: 'Файл слишком большой.',
    title_required: 'Укажите название документа.',
    bad_type: 'Выберите вид документа.',
    bad_date: 'Дата указана неверно.',
    forbidden: 'Изменять документы может только администратор.',
    not_found: 'Документ не найден — возможно, его уже удалили.',
    storage_not_writable: 'Сервер не может сохранить файл: нет прав на папку storage.',
  };

  var state = { type: '', q: '', dept: '', year: '', status: 'active', sort: 'date_desc' };
  var items = [];
  var words = [];
  var reqSeq = 0;

  function readUrl() {
    var p = new URLSearchParams(location.search);
    Object.keys(state).forEach(function (k) {
      if (p.has(k)) state[k] = p.get(k) || '';
    });
    if (!cfg.types[state.type]) state.type = '';
    state.q = state.q.trim();
    if (['active', 'inactive', 'all'].indexOf(state.status) < 0) state.status = 'active';
  }

  function writeUrl() {
    var p = new URLSearchParams();
    var defaults = { status: 'active', sort: 'date_desc' };
    Object.keys(state).forEach(function (k) {
      if (state[k] && state[k] !== defaults[k]) p.set(k, state[k]);
    });
    var qs = p.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
  }

  function syncControls() {
    $('docSearch').value = state.q;
    $('docDept').value = state.dept;
    $('docSort').value = state.sort;
    if ($('docYear').querySelector('option[value="' + state.year + '"]')) $('docYear').value = state.year;
    document.querySelectorAll('#docTypes .docs-type').forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute('data-type') === state.type);
    });
    document.querySelectorAll('#docStatus button').forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute('data-status') === state.status);
    });
    $('docReset').hidden = !(state.q || state.dept || state.year || state.type || state.status !== 'active' || state.sort !== 'date_desc');
    if (window.bxCustomSelectSyncAll) window.bxCustomSelectSyncAll();
  }

  function load() {
    var seq = ++reqSeq;
    var p = new URLSearchParams();
    Object.keys(state).forEach(function (k) { if (state[k]) p.set(k, state[k]); });
    $('docList').setAttribute('aria-busy', 'true');
    $('docList').classList.add('is-loading');
    C.api('/api/documents.php?' + p.toString(), {}, ERRORS)
      .then(function (r) {
        if (seq !== reqSeq) return;
        items = r.items || [];
        words = r.words || [];
        renderCounts(r.counts || {}, r.total || 0);
        renderYears(r.years || []);
        render();
      })
      .catch(function (err) {
        if (seq !== reqSeq) return;
        $('docList').innerHTML = '<div class="docs-empty"><b>Не удалось загрузить документы</b><span>' + esc(err.message) + '</span></div>';
      })
      .then(function () {
        if (seq !== reqSeq) return;
        $('docList').removeAttribute('aria-busy');
        $('docList').classList.remove('is-loading');
      });
  }

  function renderCounts(counts, total) {
    document.querySelectorAll('#docTypes [data-count]').forEach(function (b) {
      var k = b.getAttribute('data-count');
      b.textContent = String(k === '' ? total : counts[k] || 0);
    });
  }

  function renderYears(years) {
    var sel = $('docYear');
    var cur = state.year;
    var html = '<option value="">Все годы</option>' + years.map(function (y) {
      return '<option value="' + y + '">' + y + '</option>';
    }).join('');
    if (sel.getAttribute('data-html') !== html) {
      sel.innerHTML = html;
      sel.setAttribute('data-html', html);
    }
    sel.value = years.indexOf(parseInt(cur, 10)) >= 0 ? cur : '';
    if (cur && !sel.value) setState({ year: '' });
    if (window.bxCustomSelectSync) window.bxCustomSelectSync(sel);
  }

  function highlight(text) {
    text = String(text == null ? '' : text);
    var parts = words
      .filter(Boolean)
      .sort(function (a, b) { return b.length - a.length; })
      .map(function (w) { return w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
    if (!parts.length) return esc(text);
    // Подсвечиваем по исходному тексту и экранируем каждый кусок: иначе слова вроде «amp» ломают сущности HTML.
    return text.split(new RegExp('(' + parts.join('|') + ')', 'gi')).map(function (seg, i) {
      return i % 2 ? '<mark>' + esc(seg) + '</mark>' : esc(seg);
    }).join('');
  }

  function fmtDate(iso) {
    if (!iso) return '';
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);
    return m ? m[3] + '.' + m[2] + '.' + m[1] : '';
  }

  function fmtSize(b) {
    if (b >= 1048576) return (b / 1048576).toFixed(1).replace('.', ',') + ' МБ';
    if (b >= 1024) return Math.round(b / 1024) + ' КБ';
    return b + ' Б';
  }

  function plural(n, one, few, many) {
    var m10 = n % 10;
    var m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return one;
    if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return few;
    return many;
  }

  function fileKind(ext) {
    if (ext === 'pdf') return 'pdf';
    if (['doc', 'docx', 'odt', 'rtf', 'txt'].indexOf(ext) >= 0) return 'doc';
    if (['xls', 'xlsx', 'ods'].indexOf(ext) >= 0) return 'xls';
    if (['ppt', 'pptx', 'odp'].indexOf(ext) >= 0) return 'ppt';
    if (['png', 'jpg', 'jpeg'].indexOf(ext) >= 0) return 'img';
    return 'other';
  }

  function fileUrl(d, inline) {
    return C.apiUrl('/document_file.php?id=' + d.id + (inline ? '&inline=1' : ''));
  }

  var ICON_PIN = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 3l5 5-3 1-4 4 1 5-2 2-4-4-5 5-1-1 5-5-4-4 2-2 5 1 4-4z"/></svg>';
  var ICON_DL = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>';
  var ICON_EYE = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  var ICON_EDIT = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>';
  var ICON_LINK = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 007.07 0l3-3a5 5 0 00-7.07-7.07l-1 1"/><path d="M14 11a5 5 0 00-7.07 0l-3 3a5 5 0 007.07 7.07l1-1"/></svg>';

  function itemHtml(d) {
    var t = cfg.types[d.doc_type] || { label: d.type_label, color: '#64748b' };
    var numDate = [];
    if (d.doc_number) numDate.push('№ ' + highlight(d.doc_number.replace(/^№\s*/, '')));
    if (d.doc_date) numDate.push('от ' + fmtDate(d.doc_date));
    var foot = [];
    if (d.department_name) foot.push(esc(d.department_name));
    foot.push('<span class="doc-file-name">' + highlight(d.original_name) + '</span>');
    foot.push(fmtSize(d.size_bytes));
    if (d.uploaded_by_name) foot.push('добавил(а) ' + esc(d.uploaded_by_name) + ' ' + fmtDate(d.created_at));
    var openHref = fileUrl(d, d.can_view_inline);
    var actions =
      (d.can_view_inline ? '<a class="doc-btn" href="' + fileUrl(d, true) + '" target="_blank" rel="noopener" title="Открыть в браузере">' + ICON_EYE + '<span>Открыть</span></a>' : '') +
      '<a class="doc-btn" href="' + fileUrl(d, false) + '" title="Скачать файл">' + ICON_DL + '<span>Скачать</span></a>' +
      '<button type="button" class="doc-btn doc-btn--icon" data-act="link" title="Скопировать ссылку">' + ICON_LINK + '</button>' +
      (cfg.mayManage
        ? '<button type="button" class="doc-btn doc-btn--icon' + (d.is_pinned ? ' is-on' : '') + '" data-act="pin" title="' + (d.is_pinned ? 'Открепить' : 'Закрепить вверху') + '">' + ICON_PIN + '</button>' +
          '<button type="button" class="doc-btn doc-btn--icon" data-act="edit" title="Изменить">' + ICON_EDIT + '</button>'
        : '');
    return (
      '<article class="doc-item' + (d.is_active ? '' : ' is-inactive') + (d.is_pinned ? ' is-pinned' : '') + '" data-id="' + d.id + '" style="--doc-c:' + esc(t.color) + '">' +
      '<a class="doc-file doc-file--' + fileKind(d.ext) + '" href="' + openHref + '"' + (d.can_view_inline ? ' target="_blank" rel="noopener"' : '') + ' aria-hidden="true" tabindex="-1">' + esc((d.ext || 'файл').toUpperCase()) + '</a>' +
      '<div class="doc-main">' +
      '<div class="doc-top"><span class="doc-type">' + esc(t.label) + '</span>' +
      (numDate.length ? '<span class="doc-num">' + numDate.join(' ') + '</span>' : '') +
      (d.is_pinned ? '<span class="doc-pin">' + ICON_PIN + ' Закреплён</span>' : '') +
      (d.is_active ? '' : '<span class="doc-off">Утратил силу</span>') +
      '</div>' +
      '<h3 class="doc-title"><a href="' + openHref + '"' + (d.can_view_inline ? ' target="_blank" rel="noopener"' : '') + '>' + highlight(d.title) + '</a></h3>' +
      (d.description ? '<p class="doc-desc">' + highlight(d.description) + '</p>' : '') +
      (d.snippet ? '<p class="doc-snippet"><span>Найдено в тексте:</span> ' + highlight(d.snippet) + '</p>' : '') +
      '<div class="doc-foot">' + foot.join('<i>·</i>') + '</div>' +
      '</div>' +
      '<div class="doc-actions">' + actions + '</div>' +
      '</article>'
    );
  }

  function render() {
    var list = $('docList');
    var n = items.length;
    var typeLabel = state.type && cfg.types[state.type] ? cfg.types[state.type].plural.toLowerCase() : '';
    $('docSummary').textContent = n
      ? (state.q ? 'Найдено ' : 'Показано ') + n + ' ' + plural(n, 'документ', 'документа', 'документов') + (typeLabel ? ' · ' + typeLabel : '')
      : '';
    if (!n) {
      var filtered = state.q || state.dept || state.year || state.type || state.status !== 'active';
      list.innerHTML =
        '<div class="docs-empty">' +
        '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>' +
        (filtered
          ? '<b>Ничего не найдено</b><span>Попробуйте изменить запрос или сбросить фильтры.</span><button type="button" class="btn btn-secondary btn-sm" data-act="reset">Сбросить фильтры</button>'
          : '<b>Документов пока нет</b><span>' + (cfg.mayManage ? 'Добавьте первый документ — он сразу станет доступен всем сотрудникам.' : 'Здесь появятся инструкции, положения, регламенты и приказы.') + '</span>' +
            (cfg.mayManage ? '<button type="button" class="btn btn-primary btn-sm" data-act="add">Добавить документ</button>' : '')) +
        '</div>';
      return;
    }
    list.innerHTML = items.map(itemHtml).join('');
  }

  function setState(patch) {
    Object.keys(patch).forEach(function (k) { state[k] = patch[k]; });
    writeUrl();
    syncControls();
    load();
  }

  // Фильтры
  var searchTimer = null;
  $('docSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      var v = $('docSearch').value.trim();
      if (v !== state.q) setState({ q: v });
    }, 250);
  });
  $('docSearch').addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') {
      clearTimeout(searchTimer);
      var v = this.value.trim();
      if (v !== state.q) setState({ q: v });
    } else if (ev.key === 'Escape' && this.value) {
      clearTimeout(searchTimer);
      this.value = '';
      if (state.q) setState({ q: '' });
    }
  });
  document.addEventListener('keydown', function (ev) {
    var tag = (ev.target && ev.target.tagName) || '';
    if (ev.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT' && !ev.target.isContentEditable) {
      ev.preventDefault();
      $('docSearch').focus();
    }
  });
  $('docTypes').addEventListener('click', function (ev) {
    var b = ev.target.closest('.docs-type');
    if (b) setState({ type: b.getAttribute('data-type') });
  });
  $('docStatus').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-status]');
    if (b) setState({ status: b.getAttribute('data-status') });
  });
  $('docDept').addEventListener('change', function () { setState({ dept: this.value }); });
  $('docYear').addEventListener('change', function () { setState({ year: this.value }); });
  $('docSort').addEventListener('change', function () { setState({ sort: this.value }); });
  function resetFilters() {
    clearTimeout(searchTimer);
    setState({ type: '', q: '', dept: '', year: '', status: 'active', sort: 'date_desc' });
  }
  $('docReset').addEventListener('click', resetFilters);

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    var ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    return Promise.resolve();
  }

  $('docList').addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-act]');
    if (!btn) return;
    var act = btn.getAttribute('data-act');
    if (act === 'reset') return resetFilters();
    if (act === 'add') return openModal(null);
    var el = btn.closest('.doc-item');
    var d = el && items.filter(function (x) { return String(x.id) === el.getAttribute('data-id'); })[0];
    if (!d) return;
    if (act === 'link') {
      copyText(new URL(fileUrl(d, d.can_view_inline), location.href).href).then(function () {
        C.notify('Ссылка на документ скопирована', { type: 'success' });
      });
    } else if (act === 'pin') {
      C.api('/api/document.php?id=' + d.id, { method: 'POST', body: { pin_only: 1, is_pinned: d.is_pinned ? 0 : 1 } }, ERRORS)
        .then(function () {
          C.notify(d.is_pinned ? 'Документ откреплён' : 'Документ закреплён вверху списка', { type: 'success' });
          load();
        })
        .catch(function (err) { C.notify(err.message, { type: 'error' }); });
    } else if (act === 'edit') {
      openModal(d);
    }
  });

  // Добавление и изменение (только администратор)
  var modal = $('docModal');
  var editing = null;

  function formError(msg) {
    var p = $('docFormError');
    p.textContent = msg || '';
    p.hidden = !msg;
  }

  function setDropFile(file) {
    var drop = $('docDrop');
    drop.classList.toggle('has-file', !!file);
    if (file) {
      $('docDropText').innerHTML = '<b>' + esc(file.name) + '</b>';
      $('docDropHint').textContent = fmtSize(file.size) + ' · нажмите, чтобы выбрать другой файл';
    } else if (editing) {
      $('docDropText').innerHTML = 'Текущий файл: <b>' + esc(editing.original_name) + '</b>';
      $('docDropHint').textContent = 'Чтобы заменить файл новой редакцией, перетащите его сюда или нажмите.';
    } else {
      $('docDropText').innerHTML = 'Перетащите файл сюда или <u>выберите на компьютере</u>';
      $('docDropHint').textContent = 'PDF, Word, Excel, PowerPoint, картинки — до ' + Math.round(cfg.maxBytes / 1048576) + ' МБ. По тексту Word-файлов работает поиск.';
    }
  }

  function guessType(name) {
    var n = name.toLowerCase();
    if (/приказ/.test(n)) return 'order';
    if (/положени/.test(n)) return 'regulation';
    if (/регламент/.test(n)) return 'reglament';
    if (/инструкц/.test(n)) return 'instruction';
    return '';
  }

  function onFileChosen(file) {
    if (!file) return;
    var ext = (file.name.split('.').pop() || '').toLowerCase();
    if (cfg.extensions.indexOf(ext) < 0) {
      formError('Формат .' + ext + ' не поддерживается.');
      $('docFile').value = '';
      setDropFile(null);
      return;
    }
    if (file.size > cfg.maxBytes) {
      formError('Файл больше ' + Math.round(cfg.maxBytes / 1048576) + ' МБ.');
      $('docFile').value = '';
      setDropFile(null);
      return;
    }
    formError('');
    setDropFile(file);
    var base = file.name.replace(/\.[^.]+$/, '').replace(/[_]+/g, ' ').trim();
    if (!$('docTitle').value.trim()) $('docTitle').value = base;
    if (!modal.querySelector('input[name=doc_type]:checked')) {
      var g = guessType(base);
      if (g) modal.querySelector('input[name=doc_type][value="' + g + '"]').checked = true;
    }
    var num = /№\s*([0-9A-Za-zА-Яа-яЁё][0-9A-Za-zА-Яа-яЁё\-\/.]*)/.exec(base);
    if (num && !$('docNumber').value) $('docNumber').value = num[1];
  }

  function openModal(d) {
    editing = d;
    $('docForm').reset();
    formError('');
    $('docModalTitle').textContent = d ? 'Изменить документ' : 'Новый документ';
    $('docId').value = d ? d.id : '';
    $('docDelete').hidden = !d;
    var type = d ? d.doc_type : state.type;
    modal.querySelectorAll('input[name=doc_type]').forEach(function (r) { r.checked = r.value === type; });
    $('docTitle').value = d ? d.title : '';
    $('docNumber').value = d ? d.doc_number : '';
    $('docDate').value = d && d.doc_date ? d.doc_date : '';
    $('docDeptInput').value = d && d.department_id ? String(d.department_id) : (state.dept || '');
    $('docDesc').value = d ? d.description : '';
    $('docActive').checked = d ? d.is_active : true;
    $('docPinned').checked = d ? d.is_pinned : false;
    setDropFile(null);
    if (window.bxCustomSelectSyncAll) window.bxCustomSelectSyncAll();
    modal.showModal();
    if (!d) $('docFile').focus();
  }

  if (modal) {
    $('btnDocAdd').addEventListener('click', function () { openModal(null); });
    $('docCancel').addEventListener('click', function () { modal.close(); });
    $('docFile').addEventListener('change', function () { onFileChosen(this.files && this.files[0]); });
    var drop = $('docDrop');
    ['dragenter', 'dragover'].forEach(function (evName) {
      drop.addEventListener(evName, function (ev) { ev.preventDefault(); drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (evName) {
      drop.addEventListener(evName, function () { drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (ev) {
      ev.preventDefault();
      var f = ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0];
      if (!f) return;
      try {
        var dt = new DataTransfer();
        dt.items.add(f);
        $('docFile').files = dt.files;
      } catch (e) {}
      onFileChosen(f);
    });

    // Файл можно бросить прямо на страницу — откроется форма нового документа
    document.addEventListener('dragover', function (ev) {
      if (!modal.open && ev.dataTransfer && Array.prototype.indexOf.call(ev.dataTransfer.types || [], 'Files') >= 0) ev.preventDefault();
    });
    document.addEventListener('drop', function (ev) {
      if (modal.open || !ev.dataTransfer || !ev.dataTransfer.files || !ev.dataTransfer.files[0]) return;
      ev.preventDefault();
      var f = ev.dataTransfer.files[0];
      openModal(null);
      try {
        var dt = new DataTransfer();
        dt.items.add(f);
        $('docFile').files = dt.files;
      } catch (e) {}
      onFileChosen(f);
    });

    $('docForm').addEventListener('submit', function (ev) {
      ev.preventDefault();
      var type = modal.querySelector('input[name=doc_type]:checked');
      if (!editing && !($('docFile').files && $('docFile').files[0])) return formError(ERRORS.file_required);
      if (!type) return formError(ERRORS.bad_type);
      if (!$('docTitle').value.trim()) {
        $('docTitle').focus();
        return formError(ERRORS.title_required);
      }
      var fd = new FormData();
      fd.append('doc_type', type.value);
      fd.append('title', $('docTitle').value.trim());
      fd.append('doc_number', $('docNumber').value.trim());
      fd.append('doc_date', $('docDate').value);
      fd.append('department_id', $('docDeptInput').value);
      fd.append('description', $('docDesc').value.trim());
      fd.append('is_active', $('docActive').checked ? '1' : '0');
      fd.append('is_pinned', $('docPinned').checked ? '1' : '0');
      if ($('docFile').files && $('docFile').files[0]) fd.append('file', $('docFile').files[0]);
      var save = $('docSave');
      save.disabled = true;
      save.textContent = fd.has('file') ? 'Загружаем…' : 'Сохраняем…';
      C.api(editing ? '/api/document.php?id=' + editing.id : '/api/documents.php', { method: 'POST', body: fd }, ERRORS)
        .then(function () {
          modal.close();
          C.notify(editing ? 'Документ сохранён' : 'Документ добавлен', { type: 'success' });
          load();
        })
        .catch(function (err) { formError(err.message); })
        .then(function () {
          save.disabled = false;
          save.textContent = 'Сохранить';
        });
    });

    $('docDelete').addEventListener('click', function () {
      if (!editing || !confirm('Удалить документ «' + editing.title + '»? Файл будет удалён без возможности восстановления.\n\nЕсли документ просто утратил силу, лучше снимите галочку «Действующий».')) return;
      C.api('/api/document.php?id=' + editing.id, { method: 'DELETE' }, ERRORS)
        .then(function () {
          modal.close();
          C.notify('Документ удалён', { type: 'success' });
          load();
        })
        .catch(function (err) { formError(err.message); });
    });
  }

  readUrl();
  syncControls();
  load();
})();
