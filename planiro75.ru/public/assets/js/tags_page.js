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

  function setTagCreateMessage(text, kind) {
    var el = $('tagCreateMsg');
    if (!el) return;
    el.textContent = text || '';
    el.classList.remove('is-error', 'is-ok');
    if (kind === 'error') el.classList.add('is-error');
    if (kind === 'ok') el.classList.add('is-ok');
  }

  function submitCreateTag(e) {
    if (e && e.preventDefault) e.preventDefault();
    var nameEl = $('newTagName');
    var colorEl = $('newTagColor');
    if (!nameEl) return;
    var name = (nameEl.value || '').trim();
    var color = (colorEl && colorEl.value) || '#64748b';
    if (!name) {
      setTagCreateMessage('Введите название метки.', 'error');
      nameEl.focus();
      return;
    }
    setTagCreateMessage('Сохранение…', '');
    api('/api/tags.php', { method: 'POST', body: { name: name, color: color } })
      .then(function () {
        nameEl.value = '';
        setTagCreateMessage('Метка создана.', 'ok');
      })
      .catch(function (err) {
        var code = err.message || '';
        var human =
          code === 'forbidden'
            ? 'Создавать метки может только администратор.'
            : code === 'duplicate'
              ? 'Метка с таким названием уже есть.'
              : code === 'not_configured'
                ? 'Таблица меток не создана. Выполните sql/migration_extended_features.sql.'
                : code === 'bad_name'
                  ? 'Некорректное название (до 80 символов).'
                  : 'Не удалось создать: ' + code;
        setTagCreateMessage(human, 'error');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var formTag = $('formCreateTag');
    if (formTag) formTag.addEventListener('submit', submitCreateTag);
  });
})();
