(function () {
  var Core = window.PlannerCore;
  if (!Core) return;

  function $(id) {
    return document.getElementById(id);
  }

  function notify(msg, type) {
    Core.notify(msg, { type: type || 'info' });
  }

  function esc(s) {
    var t = document.createTextNode(String(s));
    var d = document.createElement('div');
    d.appendChild(t);
    return d.innerHTML;
  }

  function updatePlanExportLink(year) {
    var link = $('planExportLink');
    if (!link || !Core.apiUrl) return;
    var y = parseInt(String(year), 10);
    if (y < 1990 || y > 2100) {
      return;
    }
    link.href = Core.apiUrl('/api/plan_import.php?export=1&work_year=' + encodeURIComponent(String(y)));
  }

  document.addEventListener('DOMContentLoaded', function () {
    var save = $('planYearSave');
    var inp = $('planYearInput');
    var exportYearEl = $('planImportYear');
    if (exportYearEl) {
      updatePlanExportLink(exportYearEl.value);
      exportYearEl.addEventListener('change', function () {
        updatePlanExportLink(exportYearEl.value);
      });
      exportYearEl.addEventListener('input', function () {
        updatePlanExportLink(exportYearEl.value);
      });
    }
    if (save && inp) {
      save.addEventListener('click', function () {
        var y = parseInt(inp.value, 10);
        if (y < 1990 || y > 2100) {
          notify('Укажите год от 1990 до 2100', 'error');
          return;
        }
        Core.api(
          '/api/organization.php',
          { method: 'PATCH', body: { work_year: y } },
          {
            forbidden: 'Недостаточно прав',
            bad_year: 'Некорректный год',
            schema: 'Сначала выполните миграцию БД (work_year)',
            error: 'Не удалось сохранить',
          }
        )
          .then(function (r) {
            var v = r && r.work_year != null ? parseInt(String(r.work_year), 10) : y;
            var badge = document.getElementById('bxOrgYearBadge');
            if (badge) badge.textContent = String(v);
            var cur = $('planYearCurrent');
            if (cur) cur.textContent = String(v);
            var iy = $('planImportYear');
            if (iy) iy.value = String(v);
            updatePlanExportLink(v);
            notify('Год работы организации обновлён', 'success');
          })
          .catch(function (e) {
            notify(e.message || 'Ошибка', 'error');
          });
      });
    }

    var btn = $('planImportBtn');
    var file = $('planImportFile');
    var yearEl = $('planImportYear');
    var out = $('planImportResult');
    if (!btn || !file || !yearEl) return;

    btn.addEventListener('click', function () {
      if (!file.files || !file.files[0]) {
        notify('Выберите файл .xlsx', 'error');
        return;
      }
      var y = parseInt(yearEl.value, 10);
      if (y < 1990 || y > 2100) {
        notify('Укажите год импорта от 1990 до 2100', 'error');
        return;
      }
      var fd = new FormData();
      fd.append('file', file.files[0]);
      fd.append('work_year', String(y));
      btn.disabled = true;
      Core.api(
        '/api/plan_import.php',
        { method: 'POST', body: fd },
        {
          forbidden: 'Недостаточно прав',
          no_file: 'Файл не передан',
          upload_error: 'Ошибка загрузки файла',
          file_too_large: 'Файл слишком большой (макс. 8 МБ)',
          bad_extension: 'Нужен файл с расширением .xlsx',
          bad_year: 'Некорректный год',
          parse_failed: 'Не удалось прочитать Excel',
          error: 'Ошибка импорта',
        }
      )
        .then(function (r) {
          if (!out) return;
          var n = r && r.created != null ? parseInt(String(r.created), 10) : 0;
          var errs = r && Array.isArray(r.errors) ? r.errors : [];
          var html =
            '<p><strong>Создано задач:</strong> ' +
            esc(n) +
            '</p>';
          if (errs.length) {
            html += '<p class="muted"><strong>Замечания и пропуски:</strong></p><ul class="plan-year-error-list">';
            for (var i = 0; i < errs.length; i++) {
              html += '<li>' + esc(errs[i]) + '</li>';
            }
            html += '</ul>';
          }
          out.innerHTML = html;
          out.hidden = false;
          notify('Импорт завершён: создано ' + n, n > 0 ? 'success' : 'info');
        })
        .catch(function (e) {
          notify(e.message || 'Ошибка', 'error');
        })
        .finally(function () {
          btn.disabled = false;
        });
    });
  });
})();
