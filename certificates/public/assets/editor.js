(function () {
  'use strict';

  var data = window.CERT_EDITOR;
  if (!data) return;

  var BASELINE = { CertTinos: 0.9375, CertArimo: 0.9467 };
  var MAX_BG_SIDE = 3508;

  var state = {
    inputs: data.inputs.slice(),
    fields: data.fields.slice(),
    page: { w: data.page.w, h: data.page.h },
    bg: data.bg,
    selected: data.fields.length ? 0 : -1,
  };

  var $ = function (id) { return document.getElementById(id); };
  var stage = $('stage');
  var fontByKey = {};
  data.fonts.forEach(function (f) { fontByKey[f.key] = f; });

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function sampleMap() {
    var map = {};
    Object.keys(data.sampleSystem).forEach(function (k) { map[k] = data.sampleSystem[k]; });
    map.number = numberSample();
    state.inputs.forEach(function (inp) {
      var v = data.samples[inp.key] || inp.label || inp.key;
      if (inp.transform === 'upper') v = v.toUpperCase();
      map[inp.key] = v;
    });
    return map;
  }

  function fill(text, map) {
    return String(text).replace(/\{([a-z0-9_]+)\}/gi, function (m, k) {
      k = k.toLowerCase();
      return Object.prototype.hasOwnProperty.call(map, k) ? map[k] : '';
    }).trim();
  }

  function numberSample() {
    var prefix = $('numPrefix') ? $('numPrefix').value : '';
    var digits = Math.max(1, Math.min(10, parseInt(($('numDigits') || {}).value, 10) || 4));
    var n = String(window.CERT_NEXT_NUMBER || 1);
    while (n.length < digits) n = '0' + n;
    return prefix + n;
  }

  function stageSize() {
    var w = stage.clientWidth;
    return { w: w, h: w * state.page.h / state.page.w };
  }

  function layoutStage() {
    var size = stageSize();
    stage.style.height = size.h + 'px';
    stage.style.backgroundImage = state.bg ? 'url("' + state.bg + '")' : '';
    stage.classList.toggle('is-empty', !state.bg);
    renderElements();
  }

  function placeEl(el, f) {
    var size = stageSize();
    var scale = size.w / state.page.w;
    var font = fontByKey[f.font] || data.fonts[0];
    var px = f.size * scale;
    var boxW = f.maxw * size.w;
    var left = f.x * size.w;
    if (f.align === 'center') left -= boxW / 2;
    else if (f.align === 'right') left -= boxW;
    el.style.width = boxW + 'px';
    el.style.left = left + 'px';
    el.style.top = (f.y * size.h - (BASELINE[font.family] || 0.94) * px) + 'px';
    el.style.fontSize = px + 'px';
    el.style.fontFamily = font.family + ', serif';
    el.style.fontWeight = font.weight;
    el.style.fontStyle = font.style;
    el.style.color = f.color;
    el.style.textAlign = f.align;
    var anchor = el.querySelector('.el-anchor');
    if (anchor) {
      anchor.style.left = f.align === 'center' ? '50%' : f.align === 'right' ? '100%' : '0';
      anchor.style.top = (BASELINE[font.family] || 0.94) * px + 'px';
    }
  }

  function renderElements() {
    stage.querySelectorAll('.editor-el').forEach(function (n) { n.remove(); });
    if (!state.bg && !state.fields.length) {
      stage.textContent = 'Загрузите фон справа';
      return;
    }
    if (!state.bg) stage.textContent = '';
    var map = sampleMap();
    state.fields.forEach(function (f, i) {
      var el = document.createElement('div');
      el.className = 'editor-el' + (i === state.selected ? ' is-selected' : '');
      el.setAttribute('data-i', String(i));
      el.innerHTML = esc(fill(f.text, map) || f.text) + '<span class="el-anchor"></span>';
      placeEl(el, f);
      stage.appendChild(el);
    });
  }

  function renderList() {
    var ul = $('elList');
    ul.innerHTML = state.fields.map(function (f, i) {
      return '<li data-i="' + i + '" class="' + (i === state.selected ? 'is-selected' : '') + '"><span>' + esc(f.text) + '</span></li>';
    }).join('') || '<li class="muted">Надписей нет</li>';
    ul.querySelectorAll('li[data-i]').forEach(function (li) {
      li.addEventListener('click', function () { select(parseInt(li.getAttribute('data-i'), 10)); });
    });
  }

  function renderProps() {
    var f = state.fields[state.selected];
    $('elProps').hidden = !f;
    if (!f) return;
    $('pText').value = f.text;
    $('pFont').value = f.font;
    $('pSize').value = f.size;
    $('pColor').value = f.color;
    $('pAlign').value = f.align;
    $('pMaxw').value = Math.round(f.maxw * 100);
    $('pLines').value = f.lines;
    $('pX').value = Math.round(f.x * 1000) / 10;
    $('pY').value = Math.round(f.y * 1000) / 10;
  }

  function renderChips() {
    var chips = state.inputs.map(function (inp) {
      return { key: inp.key, title: inp.label };
    });
    Object.keys(data.system).forEach(function (k) { chips.push({ key: k, title: data.system[k] }); });
    $('phChips').innerHTML = chips.map(function (c) {
      return '<button type="button" class="ph-chip" data-key="' + esc(c.key) + '" title="' + esc(c.title) + '">{' + esc(c.key) + '}</button>';
    }).join('');
    $('phChips').querySelectorAll('.ph-chip').forEach(function (b) {
      b.addEventListener('click', function () {
        var ta = $('pText');
        var token = '{' + b.getAttribute('data-key') + '}';
        var s = ta.selectionStart || ta.value.length;
        var e = ta.selectionEnd || ta.value.length;
        ta.value = ta.value.slice(0, s) + token + ta.value.slice(e);
        ta.focus();
        ta.selectionStart = ta.selectionEnd = s + token.length;
        ta.dispatchEvent(new Event('input'));
      });
    });
  }

  function renderAll() {
    renderElements();
    renderList();
    renderProps();
  }

  function select(i) {
    state.selected = i;
    renderAll();
  }

  function updateSelected(patch, skipProps) {
    var f = state.fields[state.selected];
    if (!f) return;
    Object.keys(patch).forEach(function (k) { f[k] = patch[k]; });
    renderElements();
    renderList();
    if (!skipProps) renderProps();
  }

  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

  // Перетаскивание
  var drag = null;
  stage.addEventListener('pointerdown', function (ev) {
    var el = ev.target.closest('.editor-el');
    if (!el) return;
    var i = parseInt(el.getAttribute('data-i'), 10);
    if (i !== state.selected) select(i);
    var f = state.fields[i];
    var size = stageSize();
    drag = { i: i, sx: ev.clientX, sy: ev.clientY, fx: f.x, fy: f.y, w: size.w, h: size.h };
    stage.setPointerCapture(ev.pointerId);
    ev.preventDefault();
  });
  stage.addEventListener('pointermove', function (ev) {
    if (!drag) return;
    var f = state.fields[drag.i];
    var x = clamp(drag.fx + (ev.clientX - drag.sx) / drag.w, 0, 1);
    var y = clamp(drag.fy + (ev.clientY - drag.sy) / drag.h, 0, 1);
    if (Math.abs(x - 0.5) < 0.008) x = 0.5;
    f.x = Math.round(x * 10000) / 10000;
    f.y = Math.round(y * 10000) / 10000;
    var el = stage.querySelector('.editor-el[data-i="' + drag.i + '"]');
    if (el) placeEl(el, f);
  });
  function endDrag() {
    if (!drag) return;
    drag = null;
    renderProps();
  }
  stage.addEventListener('pointerup', endDrag);
  stage.addEventListener('pointercancel', endDrag);
  stage.addEventListener('click', function (ev) {
    if (!ev.target.closest('.editor-el') && state.selected >= 0 && !drag) {
      select(-1);
    }
  });

  document.addEventListener('keydown', function (ev) {
    var f = state.fields[state.selected];
    if (!f) return;
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
    var step = ev.shiftKey ? 0.01 : 0.002;
    var moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    if (!moves[ev.key]) return;
    ev.preventDefault();
    updateSelected({
      x: Math.round(clamp(f.x + moves[ev.key][0], 0, 1) * 10000) / 10000,
      y: Math.round(clamp(f.y + moves[ev.key][1], 0, 1) * 10000) / 10000,
    });
  });

  // Свойства выбранной надписи
  $('pFont').innerHTML = data.fonts.map(function (f) {
    return '<option value="' + esc(f.key) + '">' + esc(f.label) + '</option>';
  }).join('');
  $('pText').addEventListener('input', function () { updateSelected({ text: this.value }, true); });
  $('pFont').addEventListener('change', function () { updateSelected({ font: this.value }, true); });
  $('pSize').addEventListener('input', function () {
    var v = parseFloat(this.value);
    if (v >= 4 && v <= 160) updateSelected({ size: v }, true);
  });
  $('pColor').addEventListener('input', function () { updateSelected({ color: this.value }, true); });
  $('pAlign').addEventListener('change', function () { updateSelected({ align: this.value }, true); });
  $('pMaxw').addEventListener('input', function () {
    var v = parseFloat(this.value);
    if (v >= 5 && v <= 100) updateSelected({ maxw: v / 100 }, true);
  });
  $('pLines').addEventListener('input', function () {
    var v = parseInt(this.value, 10);
    if (v >= 1 && v <= 6) updateSelected({ lines: v }, true);
  });
  $('pX').addEventListener('input', function () {
    var v = parseFloat(this.value);
    if (v >= 0 && v <= 100) updateSelected({ x: v / 100 }, true);
  });
  $('pY').addEventListener('input', function () {
    var v = parseFloat(this.value);
    if (v >= 0 && v <= 100) updateSelected({ y: v / 100 }, true);
  });
  $('btnCenterEl').addEventListener('click', function () { updateSelected({ x: 0.5, align: 'center' }); });
  $('btnDelEl').addEventListener('click', function () {
    if (state.selected < 0) return;
    state.fields.splice(state.selected, 1);
    state.selected = state.fields.length ? Math.min(state.selected, state.fields.length - 1) : -1;
    renderAll();
  });
  $('btnAddEl').addEventListener('click', function () {
    state.fields.push({ text: 'Новая надпись', x: 0.5, y: 0.5, size: 18, font: 'tinos', color: '#222222', align: 'center', maxw: 0.6, lines: 1 });
    select(state.fields.length - 1);
    $('pText').focus();
    $('pText').select();
  });

  // Поля, которые заполняет слушатель
  function slugify(label) {
    var map = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'c', ч: 'ch', ш: 'sh', щ: 'sch', ы: 'y', э: 'e', ю: 'yu', я: 'ya' };
    var out = String(label).toLowerCase().split('').map(function (c) {
      return map[c] !== undefined ? map[c] : c;
    }).join('').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 20);
    return out || 'field';
  }

  function uniqueKey(base) {
    var key = base;
    var n = 2;
    var taken = function (k) {
      return state.inputs.some(function (i) { return i.key === k; }) || Object.prototype.hasOwnProperty.call(data.system, k);
    };
    while (taken(key)) key = base + '_' + n++;
    return key;
  }

  function renderInputs() {
    var box = $('inputRows');
    box.innerHTML = state.inputs.map(function (inp, i) {
      return (
        '<div class="input-row" data-i="' + i + '">' +
        '<div class="grid-2">' +
        '<label class="field"><span class="field-label">Подпись</span><input type="text" data-k="label" value="' + esc(inp.label) + '" maxlength="120"></label>' +
        '<label class="field"><span class="field-label">Подстановка</span><input type="text" value="{' + esc(inp.key) + '}" readonly class="mono"></label>' +
        '</div>' +
        '<label class="field"><span class="field-label">Подсказка слушателю</span><input type="text" data-k="hint" value="' + esc(inp.hint || '') + '" maxlength="200"></label>' +
        '<div class="grid-2">' +
        '<label class="field"><span class="field-label">Символов не больше</span><input type="number" data-k="maxlen" value="' + inp.maxlen + '" min="1" max="500"></label>' +
        '<label class="field"><span class="field-label">Регистр</span><select data-k="transform">' +
        '<option value="none"' + (inp.transform === 'none' ? ' selected' : '') + '>как ввели</option>' +
        '<option value="title"' + (inp.transform === 'title' ? ' selected' : '') + '>Каждое Слово С Заглавной</option>' +
        '<option value="upper"' + (inp.transform === 'upper' ? ' selected' : '') + '>ВСЕ ЗАГЛАВНЫЕ</option>' +
        '</select></label>' +
        '</div>' +
        '<div class="actions"><label class="check"><input type="checkbox" data-k="required"' + (inp.required ? ' checked' : '') + '> обязательно</label>' +
        '<button type="button" class="btn btn-sm btn-danger" data-del style="margin-left:auto">Убрать</button></div>' +
        '</div>'
      );
    }).join('');
    box.querySelectorAll('.input-row').forEach(function (row) {
      var i = parseInt(row.getAttribute('data-i'), 10);
      row.querySelectorAll('[data-k]').forEach(function (el) {
        el.addEventListener(el.type === 'checkbox' || el.tagName === 'SELECT' ? 'change' : 'input', function () {
          var k = el.getAttribute('data-k');
          var v = el.type === 'checkbox' ? el.checked : el.value;
          if (k === 'maxlen') v = parseInt(v, 10) || 1;
          state.inputs[i][k] = v;
          renderElements();
        });
      });
      row.querySelector('[data-del]').addEventListener('click', function () {
        if (state.inputs.length <= 1) {
          alert('Нужно хотя бы одно поле для слушателя.');
          return;
        }
        state.inputs.splice(i, 1);
        renderInputs();
        renderChips();
        renderElements();
      });
    });
  }

  $('btnAddInput').addEventListener('click', function () {
    var label = prompt('Как назвать поле? Например: Должность');
    if (!label) return;
    var key = uniqueKey(slugify(label));
    state.inputs.push({ key: key, label: label.trim(), hint: '', required: false, maxlen: 200, transform: 'none' });
    renderInputs();
    renderChips();
  });

  ['numPrefix', 'numDigits'].forEach(function (id) {
    $(id).addEventListener('input', function () {
      $('numSample').textContent = numberSample();
      renderElements();
    });
  });

  // Фон: перевод в JPG в браузере
  $('bgFile').addEventListener('change', function () {
    var input = this;
    var file = input.files && input.files[0];
    if (!file) return;
    var img = new Image();
    var src = URL.createObjectURL(file);
    img.onload = function () {
      var w = img.naturalWidth;
      var h = img.naturalHeight;
      var k = Math.min(1, MAX_BG_SIDE / Math.max(w, h));
      w = Math.round(w * k);
      h = Math.round(h * k);
      var canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, w, h);
      ctx.drawImage(img, 0, 0, w, h);
      canvas.toBlob(function (blob) {
        URL.revokeObjectURL(src);
        if (!blob) return;
        var jpg = new File([blob], 'background.jpg', { type: 'image/jpeg' });
        try {
          var dt = new DataTransfer();
          dt.items.add(jpg);
          input.files = dt.files;
        } catch (e) {}
        state.bg = URL.createObjectURL(blob);
        var base = w >= h ? 841.89 : 595.28;
        state.page = { w: base, h: Math.round(base * h / w * 100) / 100 };
        stage.textContent = '';
        layoutStage();
      }, 'image/jpeg', 0.9);
    };
    img.onerror = function () {
      alert('Не удалось открыть картинку. Выберите JPG или PNG.');
      input.value = '';
    };
    img.src = src;
  });

  $('tplForm').addEventListener('submit', function () {
    $('inputsJson').value = JSON.stringify(state.inputs);
    $('fieldsJson').value = JSON.stringify(state.fields);
  });

  window.addEventListener('resize', layoutStage);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(layoutStage);

  renderChips();
  renderInputs();
  layoutStage();
  renderList();
  renderProps();
})();
