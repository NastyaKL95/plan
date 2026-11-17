(function () {
  'use strict';

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-confirm]');
    if (btn && !confirm(btn.getAttribute('data-confirm'))) ev.preventDefault();
  });

  document.querySelectorAll('textarea[data-counter]').forEach(function (ta) {
    var max = +ta.getAttribute('data-counter');
    var out = document.createElement('span');
    out.className = 'a-counter';
    ta.insertAdjacentElement('afterend', out);
    function upd() { out.textContent = ta.value.length + ' / ' + max; }
    ta.addEventListener('input', upd);
    upd();
  });

  var start = document.getElementById('startDate');
  var end = document.getElementById('endDate');
  if (start && end) {
    var prev = start.value;
    start.addEventListener('change', function () {
      if (!end.value || end.value === prev || end.value < start.value) end.value = start.value;
      end.min = start.value;
      prev = start.value;
    });
    if (start.value) end.min = start.value;
  }

  var file = document.querySelector('input[type=file][name=image]');
  var preview = document.getElementById('imagePreview');
  if (file && preview) {
    file.addEventListener('change', function () {
      var f = file.files && file.files[0];
      if (!f) return;
      var max = +file.getAttribute('data-max-mb');
      if (max && f.size > max * 1024 * 1024) {
        alert('Файл больше ' + max + ' МБ. Выберите картинку поменьше.');
        file.value = '';
        return;
      }
      var img = document.createElement('img');
      img.alt = '';
      img.src = URL.createObjectURL(f);
      preview.innerHTML = '';
      preview.appendChild(img);
      var rm = document.querySelector('input[name=remove_image]');
      if (rm) rm.checked = false;
    });
  }

  document.querySelectorAll('.a-cat-form').forEach(function (form) {
    var color = form.querySelector('input[type=color]');
    var icons = form.querySelector('.a-icons');
    if (color && icons) color.addEventListener('input', function () { icons.style.setProperty('--c', color.value); });
  });

  var dirty = false;

  /* Программа события */
  var list = document.getElementById('sessions');
  var tpl = document.getElementById('sessTpl');
  if (list && tpl) {
    var seq = 0;
    var syncDates = function () {
      list.querySelectorAll('.a-sess-date').forEach(function (d) {
        if (start && start.value) d.min = start.value;
        var last = end && end.value ? end.value : (start ? start.value : '');
        if (last) d.max = last;
      });
    };
    var addRow = function (kind, after) {
      var html = tpl.innerHTML.replace(/__KEY__/g, 'n' + (++seq));
      var box = document.createElement('div');
      box.innerHTML = html.trim();
      var row = box.firstChild;
      row.setAttribute('data-kind', kind);
      row.querySelector('.a-sess-kind').value = kind;
      var prevRow = after || list.lastElementChild;
      var date = row.querySelector('.a-sess-date');
      var st = row.querySelector('.a-sess-start');
      if (prevRow) {
        date.value = prevRow.querySelector('.a-sess-date').value;
        st.value = prevRow.querySelector('.a-sess-end').value || '';
      } else if (start) {
        date.value = start.value;
      }
      if (after) after.insertAdjacentElement('afterend', row); else list.appendChild(row);
      row.classList.add('is-new');
      syncDates();
      return row;
    };
    document.querySelectorAll('[data-sess-add]').forEach(function (b) {
      b.addEventListener('click', function () {
        var row = addRow(b.getAttribute('data-sess-add'));
        row.querySelector('input[name$="[title]"]').focus();
        dirty = true;
      });
    });
    list.addEventListener('change', function (ev) {
      if (ev.target.classList.contains('a-sess-kind')) ev.target.closest('.a-sess').setAttribute('data-kind', ev.target.value);
    });
    list.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-sess]');
      if (!btn) return;
      var row = btn.closest('.a-sess');
      var act = btn.getAttribute('data-sess');
      if (act === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      if (act === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
      if (act === 'remove') {
        var title = row.querySelector('input[name$="[title]"]').value;
        if (title && !confirm('Удалить пункт «' + title + '»?')) return;
        row.remove();
      }
      if (act === 'copy') {
        var copy = addRow(row.getAttribute('data-kind'), row);
        row.querySelectorAll('input, select, textarea').forEach(function (src) {
          var field = src.name.replace(/^sessions\[[^\]]+\]/, '');
          var dst = copy.querySelector('[name$="' + field + '"]');
          if (dst) dst.value = src.value;
        });
        copy.setAttribute('data-kind', row.getAttribute('data-kind'));
      }
      dirty = true;
      if (act === 'up' || act === 'down') btn.focus();
    });
    if (start) start.addEventListener('change', syncDates);
    if (end) end.addEventListener('change', syncDates);
    syncDates();
  }


  var main = document.querySelector('.a-event-form');
  if (main) {
    main.addEventListener('input', function () { dirty = true; });
    main.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });
  }
})();
