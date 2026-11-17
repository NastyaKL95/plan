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
  var main = document.querySelector('.a-event-form');
  if (main) {
    main.addEventListener('input', function () { dirty = true; });
    main.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });
  }
})();
