<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
layout_start($user, 'Профиль', 'dashboard');
?>

<section class="bx-profile-page">
  <div class="bx-profile-head">
    <div>
      <h1 class="bx-page-title">Профиль</h1>
      <p class="muted bx-profile-lead">Данные аккаунта и настройки — сгруппированы по разделам.</p>
    </div>
    <div class="bx-profile-head-actions">
      <a class="btn btn-secondary" href="<?= htmlspecialchars(planner_url('/tasks.php'), ENT_QUOTES, 'UTF-8') ?>">К задачам</a>
      <a class="btn btn-secondary" href="<?= htmlspecialchars(planner_url('/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Выйти</a>
    </div>
  </div>

  <div class="bx-profile-grid">
    <div class="bx-profile-card">
      <div class="bx-profile-card-title">Аккаунт</div>
      <label class="field-label" for="pfNameInput">Имя</label>
      <input type="text" id="pfNameInput" class="input-block" maxlength="120" placeholder="Ваше имя">
      <label class="field-label" for="pfEmailInput">Email</label>
      <input type="email" id="pfEmailInput" class="input-block" readonly>
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-primary" id="btnSaveAccount">Сохранить аккаунт</button>
        <span class="muted bx-profile-savehint" id="pfAccountHint"></span>
      </div>
    </div>

    <div class="bx-profile-card">
      <div class="bx-profile-card-title">Организация</div>
      <div class="bx-profile-kv">
        <div class="bx-profile-k">Роль на портале</div>
        <div class="bx-profile-v" id="pfRole">—</div>
      </div>
      <div class="bx-profile-subtitle">Подразделения и роли</div>
      <div id="pfDeptRoles"></div>
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-secondary" id="btnAddDeptRole">Добавить подразделение</button>
        <button type="button" class="btn btn-primary" id="btnSaveDeptRoles">Сохранить подразделения</button>
        <span class="muted bx-profile-savehint" id="pfDeptHint"></span>
      </div>
    </div>

    <div class="bx-profile-card bx-profile-card--wide">
      <div class="bx-profile-card-title">Уведомления в MAX</div>
      <p class="muted bx-profile-lead2">
        1) Откройте чат-бота <a href="https://max.ru/irozabkrai75_bot" target="_blank" rel="noopener noreferrer">@irozabkrai75_bot</a>
        и отправьте /start. 2) В ответе бота или в первом сообщении при привязке указан <b>chat_id диалога</b> — его вставьте ниже.
        Не подставляйте user_id аккаунта MAX (другой номер): иначе будет ошибка chat.not.found.
        Если поле пустое, уведомления этому сотруднику не отправляются.
      </p>
      <label class="field-label" for="pfMaxChatInput">ID чата MAX (chat_id диалога с ботом)</label>
      <input type="text" id="pfMaxChatInput" class="input-block" inputmode="numeric" maxlength="32" placeholder="Например: 284351356">
      <div class="pf-max-row pf-max-row--top">
        <label class="bx-check-row"><input type="checkbox" id="pfMaxEnabled"> Включить уведомления MAX</label>
      </div>
      <div class="pf-max-row">
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefAssigned"> Назначение исполнителем</label>
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefStatus"> Смена статуса</label>
      </div>
      <div class="pf-max-row">
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefDue"> Смена дедлайна</label>
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefMention"> Упоминания в комментариях</label>
      </div>
      <div class="pf-max-row">
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefCreated"> Новые задачи</label>
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefParticipant"> Добавление в соисполнители</label>
      </div>
      <div class="pf-max-row">
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefSlaSoon"> SLA: срок через 2 часа</label>
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefSlaOverdue"> SLA: просрочено > 24ч</label>
      </div>
      <div class="pf-max-row">
        <label class="bx-check-row"><input type="checkbox" id="pfMaxPrefSlaStale"> SLA: нет движения X дней</label>
      </div>
      <div class="pf-max-row pf-max-row--with-input">
        <label class="bx-check-row"><input type="checkbox" id="pfMaxDigestEnabled"> Дайджест вместо потока</label>
        <input type="number" id="pfMaxDigestMinutes" class="input-inline pf-max-inline-sm" min="15" max="1440" step="5" value="60" placeholder="минут">
      </div>
      <div class="pf-max-row pf-max-row--with-input">
        <label class="bx-check-row"><input type="checkbox" id="pfMaxQuietMode"> Тихий режим (для некритичных)</label>
        <input type="text" id="pfMaxQuietStart" class="input-inline pf-max-inline-xs" placeholder="20:00">
        <input type="text" id="pfMaxQuietEnd" class="input-inline pf-max-inline-xs" placeholder="08:00">
      </div>
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-primary" id="btnSaveMax">Сохранить MAX</button>
        <button type="button" class="btn btn-secondary" id="btnSaveMaxPrefs">Сохранить подписки</button>
        <button type="button" class="btn btn-secondary" id="btnTestMax">Тест сообщения</button>
        <span class="muted bx-profile-savehint" id="pfMaxHint"></span>
      </div>
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-secondary" id="btnRefreshMaxMetrics">Обновить мониторинг MAX</button>
      </div>
      <div id="pfMaxMetrics" class="alert" hidden></div>
    </div>

    <div class="bx-profile-card">
      <div class="bx-profile-card-title">Безопасность</div>
      <label class="field-label" for="pfNewPassword">Новый пароль</label>
      <input type="password" id="pfNewPassword" class="input-block" minlength="6" autocomplete="new-password" placeholder="Оставьте пустым, если не меняете">
      <label class="field-label" for="pfNewPassword2">Повторите новый пароль</label>
      <input type="password" id="pfNewPassword2" class="input-block" minlength="6" autocomplete="new-password">
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-primary" id="btnSavePassword">Сменить пароль</button>
        <span class="muted bx-profile-savehint" id="pfPassHint"></span>
      </div>
    </div>

    <div class="bx-profile-card" id="pfOrgYearCard" hidden>
      <div class="bx-profile-card-title">Настройки организации</div>
      <p class="muted bx-profile-lead2">
        Год плана и время срока по умолчанию (для импорта Excel и даты без времени в задаче). Смена года не удаляет старые данные.
      </p>
      <div class="bx-profile-kv">
        <div class="bx-profile-k">Год плана</div>
        <div class="bx-profile-v" id="pfOrgYearNow">—</div>
      </div>
      <div class="bx-profile-kv">
        <div class="bx-profile-k">Срок по умолчанию</div>
        <div class="bx-profile-v" id="pfOrgDueTimeNow">18:00</div>
      </div>
      <div id="pfOrgYearEdit" hidden>
        <label class="field-label" for="pfOrgYearInput">Сменить год планирования</label>
        <div class="bx-profile-year-row">
          <input type="number" id="pfOrgYearInput" class="input-block" min="1990" max="2100" step="1" inputmode="numeric">
          <button type="button" class="btn btn-primary" id="btnSaveOrgYear">Сохранить год</button>
        </div>
        <label class="field-label" for="pfOrgDueTimeInput">Время срока по умолчанию (ЧЧ:ММ)</label>
        <div class="bx-profile-year-row">
          <input type="text" id="pfOrgDueTimeInput" class="input-block" placeholder="18:00" pattern="^([01]?\d|2[0-3]):[0-5]\d$" maxlength="5">
          <button type="button" class="btn btn-primary" id="btnSaveOrgDueTime">Сохранить время</button>
        </div>
        <span class="muted bx-profile-savehint" id="pfOrgYearHint"></span>
      </div>
    </div>
  </div>
</section>

<script>
  (function () {
    function byId(id) { return document.getElementById(id); }
    function setHint(id, msg, ok) {
      var el = byId(id);
      if (!el) return;
      el.textContent = msg || '';
      el.style.color = ok ? '#15803d' : '';
    }

    function roleRu(role) {
      var map = {
        admin: 'Администратор',
        member: 'Пользователь',
        head: 'Пользователь',
        rectorate: 'Пользователь',
        owner: 'Администратор',
      };
      return map[String(role || '')] || 'Пользователь';
    }

    function normalizeDeptRole(role) {
      var r = String(role || 'user');
      if (r === 'head') return 'administrator';
      if (r === 'member') return 'user';
      return r === 'manager' || r === 'administrator' ? r : 'user';
    }

    function deptRoleRu(role) {
      var map = {
        user: 'Сотрудник',
        manager: 'Руководитель организации',
        administrator: 'Руководитель подразделения',
      };
      return map[normalizeDeptRole(role)] || 'Сотрудник';
    }

    var deptRows = [];
    var deptOptions = [];
    var mayAssignDeptRoles = false;
    var canViewMaxMetrics = false;

    function renderDeptRows() {
      var host = byId('pfDeptRoles');
      if (!host) return;
      if (!deptRows.length) {
        host.innerHTML = '<p class="muted">Подразделения не выбраны.</p>';
        if (typeof window.bxCustomSelectInit === 'function') window.bxCustomSelectInit(host);
        return;
      }
      host.innerHTML = deptRows.map(function (r, idx) {
        var depOpts =
          '<option value="">— выберите подразделение —</option>' +
          deptOptions.map(function (d) {
            var sel = String(d.id) === String(r.department_id || '') ? ' selected' : '';
            return '<option value="' + d.id + '"' + sel + '>' + String(d.name || '') + '</option>';
          }).join('');
        var nr = normalizeDeptRole(r.role);
        var roleOpts = '<option value="user"' + (nr === 'user' ? ' selected' : '') + '>Сотрудник</option>';
        if (mayAssignDeptRoles) {
          roleOpts +=
            '<option value="administrator"' + (nr === 'administrator' ? ' selected' : '') + '>Руководитель подразделения</option>' +
            '<option value="manager"' + (nr === 'manager' ? ' selected' : '') + '>Руководитель организации</option>';
        } else if (nr === 'manager' || nr === 'administrator') {
          roleOpts += '<option value="' + nr + '" selected>' + deptRoleRu(nr) + '</option>';
        }
        return (
          '<div class="user-dept-role-row" data-idx="' + idx + '">' +
          '<select class="input-block pf-dept-role-dept">' + depOpts + '</select>' +
          '<select class="input-block pf-dept-role-role">' + roleOpts + '</select>' +
          '<button type="button" class="btn btn-secondary btn-sm pf-dept-role-del">Удалить</button>' +
          '</div>'
        );
      }).join('');
      if (typeof window.bxCustomSelectInit === 'function') window.bxCustomSelectInit(host);
      host.querySelectorAll('.user-dept-role-row').forEach(function (rowEl) {
        var idx = parseInt(rowEl.getAttribute('data-idx'), 10) || 0;
        var dep = rowEl.querySelector('.pf-dept-role-dept');
        var role = rowEl.querySelector('.pf-dept-role-role');
        var del = rowEl.querySelector('.pf-dept-role-del');
        if (dep) dep.addEventListener('change', function () {
          if (!deptRows[idx]) return;
          deptRows[idx].department_id = dep.value ? parseInt(dep.value, 10) : 0;
        });
        if (role) role.addEventListener('change', function () {
          if (!deptRows[idx]) return;
          deptRows[idx].role = normalizeDeptRole(role.value);
        });
        if (del) del.addEventListener('click', function () {
          deptRows.splice(idx, 1);
          renderDeptRows();
        });
      });
    }

    if (window.PlannerCore && window.PlannerCore.api) {
      window.PlannerCore.api('/api/me.php').then(function (res) {
        var it = (res && res.item) || {};
        var role = String(it.role || '');
        canViewMaxMetrics = role === 'admin' || role === 'owner' || !!(res && res.is_institute_manager);
        deptOptions = (res && res.department_options) || [];
        mayAssignDeptRoles = !!(res && (res.may_assign_department_roles || res.may_assign_head_role));
        if (byId('pfNameInput')) byId('pfNameInput').value = it.name || '';
        if (byId('pfEmailInput')) byId('pfEmailInput').value = it.email || '';
        if (byId('pfRole')) byId('pfRole').textContent = roleRu(it.role);
        if (byId('pfMaxChatInput')) byId('pfMaxChatInput').value = it.max_chat_id || '';
        var mp = (res && res.max_prefs) || {};
        if (byId('pfMaxEnabled')) byId('pfMaxEnabled').checked = !!mp.enabled;
        if (byId('pfMaxPrefAssigned')) byId('pfMaxPrefAssigned').checked = !!mp.notify_task_assigned;
        if (byId('pfMaxPrefStatus')) byId('pfMaxPrefStatus').checked = !!mp.notify_task_status_changed;
        if (byId('pfMaxPrefDue')) byId('pfMaxPrefDue').checked = !!mp.notify_task_due_changed;
        if (byId('pfMaxPrefMention')) byId('pfMaxPrefMention').checked = !!mp.notify_task_comment_mention;
        if (byId('pfMaxPrefCreated')) byId('pfMaxPrefCreated').checked = !!mp.notify_task_created;
        if (byId('pfMaxPrefParticipant')) byId('pfMaxPrefParticipant').checked = !!mp.notify_task_participant_added;
        if (byId('pfMaxPrefSlaSoon')) byId('pfMaxPrefSlaSoon').checked = !!mp.notify_sla_due_2h;
        if (byId('pfMaxPrefSlaOverdue')) byId('pfMaxPrefSlaOverdue').checked = !!mp.notify_sla_overdue_24h;
        if (byId('pfMaxPrefSlaStale')) byId('pfMaxPrefSlaStale').checked = !!mp.notify_sla_stale_days;
        if (byId('pfMaxDigestEnabled')) byId('pfMaxDigestEnabled').checked = !!mp.digest_enabled;
        if (byId('pfMaxDigestMinutes')) byId('pfMaxDigestMinutes').value = String(mp.digest_interval_minutes || 60);
        if (byId('pfMaxQuietMode')) byId('pfMaxQuietMode').checked = !!mp.quiet_mode_enabled;
        if (byId('pfMaxQuietStart')) byId('pfMaxQuietStart').value = String(mp.quiet_start || '20:00');
        if (byId('pfMaxQuietEnd')) byId('pfMaxQuietEnd').value = String(mp.quiet_end || '08:00');
        deptRows = ((it.department_roles || []).map(function (x) {
          return { department_id: parseInt(x.department_id, 10) || 0, role: normalizeDeptRole(x.role) };
        })).filter(function (x) { return x.department_id > 0; });
        renderDeptRows();
        if (canViewMaxMetrics) {
          loadMaxMetrics();
        }

        var oyCard = byId('pfOrgYearCard');
        if (oyCard && res.organization_work_year != null && res.organization_work_year !== '') {
          oyCard.hidden = false;
          if (byId('pfOrgYearNow')) byId('pfOrgYearNow').textContent = String(res.organization_work_year);
          if (byId('pfOrgYearInput')) byId('pfOrgYearInput').value = String(res.organization_work_year);
          var dueHm = res.organization_default_due_time != null ? String(res.organization_default_due_time) : '18:00';
          if (byId('pfOrgDueTimeNow')) byId('pfOrgDueTimeNow').textContent = dueHm;
          if (byId('pfOrgDueTimeInput')) byId('pfOrgDueTimeInput').value = dueHm;
          var canEdit = !!res.may_edit_organization_work_year || !!res.may_edit_organization_default_due_time;
          if (byId('pfOrgYearEdit')) byId('pfOrgYearEdit').hidden = !canEdit;
        }
      }).catch(function () {});
    }

    function apiPatch(body, hintId, okMsg) {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      setHint(hintId, 'Сохранение…', true);
      window.PlannerCore.api('/api/me.php', { method: 'PATCH', body: body }, {
        bad_name: 'Некорректное имя',
        bad_password: 'Некорректный пароль',
        bad_department: 'Некорректное подразделение',
        bad_role: 'Некорректная роль',
        bad_max_chat_id: 'Некорректный ID чата MAX',
        schema_max_chat_id_missing: 'Нужно выполнить миграцию БД для поля MAX chat_id',
        schema_max_prefs_missing: 'Нужно выполнить миграцию БД для подписок MAX',
        forbidden: 'Роли руководителя может назначить только администратор организации',
        head_role_forbidden: 'Роли руководителя может назначить только администратор организации',
        no_fields: 'Нет изменений',
        error: 'Ошибка сохранения',
      }).then(function () {
        setHint(hintId, okMsg || 'Сохранено.', true);
        try {
          var nm = body && body.name ? String(body.name) : '';
          if (nm && byId('topUserAvatar')) {
            byId('topUserAvatar').textContent = nm.trim().charAt(0).toUpperCase();
          }
        } catch (eA) {}
      }).catch(function (e) {
        setHint(hintId, e && e.message ? e.message : 'Ошибка', false);
      });
    }

    var btnAcc = byId('btnSaveAccount');
    if (btnAcc) btnAcc.addEventListener('click', function () {
      var nm = byId('pfNameInput') ? byId('pfNameInput').value.trim() : '';
      if (!nm) return setHint('pfAccountHint', 'Введите имя.', false);
      apiPatch({ name: nm }, 'pfAccountHint', 'Аккаунт сохранён.');
    });

    var btnMax = byId('btnSaveMax');
    if (btnMax) btnMax.addEventListener('click', function () {
      var v = byId('pfMaxChatInput') ? byId('pfMaxChatInput').value.trim() : '';
      apiPatch({ max_chat_id: v }, 'pfMaxHint', 'MAX сохранён.');
    });

    var btnMaxPrefs = byId('btnSaveMaxPrefs');
    if (btnMaxPrefs) btnMaxPrefs.addEventListener('click', function () {
      var prefs = {
        enabled: byId('pfMaxEnabled') ? !!byId('pfMaxEnabled').checked : true,
        notify_task_created: byId('pfMaxPrefCreated') ? !!byId('pfMaxPrefCreated').checked : true,
        notify_task_assigned: byId('pfMaxPrefAssigned') ? !!byId('pfMaxPrefAssigned').checked : true,
        notify_task_status_changed: byId('pfMaxPrefStatus') ? !!byId('pfMaxPrefStatus').checked : true,
        notify_task_due_changed: byId('pfMaxPrefDue') ? !!byId('pfMaxPrefDue').checked : true,
        notify_task_comment_mention: byId('pfMaxPrefMention') ? !!byId('pfMaxPrefMention').checked : true,
        notify_task_participant_added: byId('pfMaxPrefParticipant') ? !!byId('pfMaxPrefParticipant').checked : true,
        notify_direct_message: true,
        notify_sla_due_2h: byId('pfMaxPrefSlaSoon') ? !!byId('pfMaxPrefSlaSoon').checked : true,
        notify_sla_overdue_24h: byId('pfMaxPrefSlaOverdue') ? !!byId('pfMaxPrefSlaOverdue').checked : true,
        notify_sla_stale_days: byId('pfMaxPrefSlaStale') ? !!byId('pfMaxPrefSlaStale').checked : true,
        digest_enabled: byId('pfMaxDigestEnabled') ? !!byId('pfMaxDigestEnabled').checked : false,
        digest_interval_minutes: byId('pfMaxDigestMinutes') ? (parseInt(byId('pfMaxDigestMinutes').value, 10) || 60) : 60,
        quiet_mode_enabled: byId('pfMaxQuietMode') ? !!byId('pfMaxQuietMode').checked : false,
        quiet_start: byId('pfMaxQuietStart') ? byId('pfMaxQuietStart').value.trim() : '20:00',
        quiet_end: byId('pfMaxQuietEnd') ? byId('pfMaxQuietEnd').value.trim() : '08:00'
      };
      apiPatch({ max_prefs: prefs }, 'pfMaxHint', 'Подписки MAX сохранены.');
    });

    function renderMaxMetrics(metrics) {
      var host = byId('pfMaxMetrics');
      if (!host || !metrics) return;
      var counts = metrics.counts || {};
      var alerts = Array.isArray(metrics.alerts) ? metrics.alerts : [];
      var hb = metrics.heartbeat || {};
      var parts = [];
      parts.push('<strong>Очередь MAX</strong>');
      parts.push(
        ' pending: ' + String(counts.pending || 0) +
        ', sent: ' + String(counts.sent || 0) +
        ', failed: ' + String(counts.failed || 0) +
        ', cancelled: ' + String(counts.cancelled || 0)
      );
      if (metrics.pending_oldest_age_minutes != null) {
        parts.push('<br>Старейший pending: ' + String(metrics.pending_oldest_age_minutes) + ' мин.');
      }
      if (hb.last_ts) {
        parts.push('<br>Heartbeat воркера: ' + String(hb.last_ts) + ' (' + String(hb.age_minutes || 0) + ' мин. назад)');
      } else {
        parts.push('<br>Heartbeat воркера: нет данных');
      }
      if (alerts.length) {
        parts.push('<br><strong>Алерты:</strong> ' + alerts.join(' | '));
      } else {
        parts.push('<br>Статус: без критичных алертов');
      }
      host.innerHTML = parts.join('');
      host.className = alerts.length ? 'alert alert-error' : 'alert';
      host.hidden = false;
    }

    function loadMaxMetrics() {
      if (!canViewMaxMetrics) return;
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      window.PlannerCore.api('/api/max_metrics.php')
        .then(function (res) {
          if (!res || !res.metrics) return;
          renderMaxMetrics(res.metrics);
        })
        .catch(function (e) {
          var host = byId('pfMaxMetrics');
          if (!host) return;
          host.hidden = false;
          host.className = 'alert alert-error';
          host.textContent = 'Мониторинг MAX недоступен: ' + (e && e.message ? e.message : 'ошибка');
        });
    }

    var btnTestMax = byId('btnTestMax');
    if (btnTestMax) btnTestMax.addEventListener('click', function () {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      setHint('pfMaxHint', 'Отправка теста…', true);
      window.PlannerCore.api('/api/max_test.php', { method: 'POST', body: {} }, {
        not_configured: 'MAX не настроен (token/chat_id).',
        error: 'Не удалось отправить тест в MAX',
      }).then(function () {
        setHint('pfMaxHint', 'Тест поставлен в очередь MAX.', true);
        loadMaxMetrics();
      }).catch(function (e) {
        setHint('pfMaxHint', e && e.message ? e.message : 'Ошибка', false);
      });
    });

    var btnRefreshMaxMetrics = byId('btnRefreshMaxMetrics');
    if (btnRefreshMaxMetrics) btnRefreshMaxMetrics.addEventListener('click', function () {
      if (!canViewMaxMetrics) {
        setHint('pfMaxHint', 'Мониторинг доступен только администраторам.', false);
        return;
      }
      loadMaxMetrics();
    });

    var btnPass = byId('btnSavePassword');
    if (btnPass) btnPass.addEventListener('click', function () {
      var p1 = byId('pfNewPassword') ? byId('pfNewPassword').value : '';
      var p2 = byId('pfNewPassword2') ? byId('pfNewPassword2').value : '';
      if (!p1 && !p2) return setHint('pfPassHint', 'Введите новый пароль.', false);
      if (p1.length < 6) return setHint('pfPassHint', 'Пароль должен быть не короче 6 символов.', false);
      if (p1 !== p2) return setHint('pfPassHint', 'Пароли не совпадают.', false);
      apiPatch({ new_password: p1 }, 'pfPassHint', 'Пароль обновлён.');
      if (byId('pfNewPassword')) byId('pfNewPassword').value = '';
      if (byId('pfNewPassword2')) byId('pfNewPassword2').value = '';
    });

    var btnAdd = byId('btnAddDeptRole');
    if (btnAdd) btnAdd.addEventListener('click', function () {
      deptRows.push({ department_id: 0, role: 'user' });
      renderDeptRows();
    });

    var btnSaveDept = byId('btnSaveDeptRoles');
    if (btnSaveDept) btnSaveDept.addEventListener('click', function () {
      var dedup = {};
      var rows = [];
      deptRows.forEach(function (r) {
        var did = parseInt(r.department_id, 10) || 0;
        if (did <= 0 || dedup[did]) return;
        dedup[did] = true;
        rows.push({ department_id: did, role: normalizeDeptRole(r.role) });
      });
      apiPatch({ department_roles: rows }, 'pfDeptHint', 'Подразделения сохранены.');
    });

    var btnOrgDueTime = byId('btnSaveOrgDueTime');
    if (btnOrgDueTime) btnOrgDueTime.addEventListener('click', function () {
      var inp = byId('pfOrgDueTimeInput');
      var v = inp ? inp.value.trim() : '';
      if (!/^([01]?\d|2[0-3]):[0-5]\d$/.test(v)) return setHint('pfOrgYearHint', 'Укажите время в формате ЧЧ:ММ (например 18:00).', false);
      setHint('pfOrgYearHint', 'Сохранение…', true);
      window.PlannerCore.api('/api/organization.php', { method: 'PATCH', body: { default_due_time: v } }, {
        forbidden: 'Только администратор организации',
        bad_due_time: 'Некорректное время (ЧЧ:ММ)',
        schema: 'Выполните миграцию default_due_time',
      }).then(function () {
        if (byId('pfOrgDueTimeNow')) byId('pfOrgDueTimeNow').textContent = v;
        setHint('pfOrgYearHint', 'Время срока обновлено.', true);
      }).catch(function (e) {
        setHint('pfOrgYearHint', e && e.message ? e.message : 'Ошибка', false);
      });
    });

    var btnOrgYear = byId('btnSaveOrgYear');
    if (btnOrgYear) btnOrgYear.addEventListener('click', function () {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      var inp = byId('pfOrgYearInput');
      if (!inp) return;
      var y = parseInt(inp.value, 10);
      if (y < 1990 || y > 2100) return setHint('pfOrgYearHint', 'Укажите год от 1990 до 2100.', false);
      setHint('pfOrgYearHint', 'Сохранение…', true);
      window.PlannerCore.api('/api/organization.php', { method: 'PATCH', body: { work_year: y } }, {
        forbidden: 'Недостаточно прав',
        bad_year: 'Некорректный год',
        schema: 'Сначала выполните миграцию БД (work_year)',
        error: 'Не удалось сохранить',
      }).then(function (r) {
        var v = r && r.work_year != null ? String(r.work_year) : String(y);
        if (byId('bxOrgYearBadge')) byId('bxOrgYearBadge').textContent = v;
        if (byId('pfOrgYearNow')) byId('pfOrgYearNow').textContent = v;
        setHint('pfOrgYearHint', 'Год обновлён.', true);
      }).catch(function (e) {
        setHint('pfOrgYearHint', e && e.message ? e.message : 'Ошибка', false);
      });
    });

  })();
</script>

<?php layout_end(); ?>

