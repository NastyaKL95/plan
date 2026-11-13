/**
 * Единый вид полей выбора файла: подпись имени и сброс при очистке value.
 * Разметка: .bx-file-field > input.bx-file-field__native + .bx-file-field__face > [data-bx-file-name]
 */
(function () {
  function defaultPlaceholder(el) {
    var a = el.getAttribute('data-bx-file-placeholder');
    return a != null && String(a) !== '' ? String(a) : 'Файл не выбран';
  }

  function syncOne(inp) {
    var wrap = inp.closest('.bx-file-field');
    if (!wrap) return;
    var nameEl = wrap.querySelector('[data-bx-file-name]');
    if (!nameEl) return;
    var ph = defaultPlaceholder(nameEl);
    if (!inp.files || inp.files.length === 0) {
      nameEl.textContent = ph;
      return;
    }
    if (inp.files.length === 1) {
      nameEl.textContent = inp.files[0].name;
      return;
    }
    var names = [];
    for (var i = 0; i < inp.files.length; i++) {
      names.push(inp.files[i].name);
    }
    nameEl.textContent = names.join(', ');
  }

  document.addEventListener('change', function (ev) {
    var t = ev.target;
    if (!t || t.tagName !== 'INPUT' || t.type !== 'file') return;
    syncOne(t);
  });

  document.addEventListener('focusin', function (ev) {
    var t = ev.target;
    if (!t || t.tagName !== 'INPUT' || t.type !== 'file') return;
    if (t.classList.contains('bx-file-field__native')) {
      syncOne(t);
    }
  });
})();
