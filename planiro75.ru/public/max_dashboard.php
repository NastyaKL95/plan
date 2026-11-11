<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
if (!user_may_manage_users($user)) {
    http_response_code(403);
    echo 'Доступ запрещен';
    exit;
}

layout_start($user, 'Мониторинг MAX', 'max_dashboard');
?>

<section class="bx-profile-page max-dashboard-page">
  <div class="bx-profile-head">
    <div>
      <h1 class="bx-page-title">MAX мониторинг</h1>
      <p class="muted bx-profile-lead">Метрики очереди и статус воркера. Обновление выполняется только по кнопке.</p>
    </div>
    <div class="bx-profile-head-actions">
      <button type="button" class="btn btn-primary" id="btnMaxDashRefresh">Обновить</button>
      <button type="button" class="btn btn-secondary" id="btnMaxDashRunWorker">Прогнать воркер сейчас</button>
      <button type="button" class="btn btn-secondary" id="btnMaxDashRetryFailed">Повторить failed</button>
    </div>
  </div>

  <div class="bx-profile-grid">
    <div class="bx-profile-card">
      <div class="bx-profile-card-title">Счетчики очереди</div>
      <div class="bx-profile-kv max-kv">
        <div class="bx-profile-k">В очереди (pending)</div>
        <div class="bx-profile-v max-metric max-metric--pending" id="mxPending">0</div>
        <div class="bx-profile-k">Отправлено (sent)</div>
        <div class="bx-profile-v max-metric max-metric--sent" id="mxSent">0</div>
        <div class="bx-profile-k">Ошибки (failed)</div>
        <div class="bx-profile-v max-metric max-metric--failed" id="mxFailed">0</div>
        <div class="bx-profile-k">Отменено (cancelled)</div>
        <div class="bx-profile-v max-metric max-metric--cancelled" id="mxCancelled">0</div>
        <div class="bx-profile-k">Всего (total)</div>
        <div class="bx-profile-v max-metric max-metric--total" id="mxTotal">0</div>
      </div>
    </div>

    <div class="bx-profile-card">
      <div class="bx-profile-card-title">Состояние воркера</div>
      <div class="bx-profile-kv max-kv">
        <div class="bx-profile-k">Heartbeat</div>
        <div class="bx-profile-v max-health" id="mxHeartbeat">—</div>
        <div class="bx-profile-k">Старейший pending</div>
        <div class="bx-profile-v max-health" id="mxOldestPending">—</div>
      </div>
    </div>

    <div class="bx-profile-card bx-profile-card--wide">
      <div class="bx-profile-card-title">Алерты</div>
      <div id="mxAlerts" class="alert" hidden></div>
      <p class="muted bx-profile-lead2" id="mxRunResult"></p>
      <p class="muted bx-profile-lead2" id="mxLastUpdate">Обновление еще не выполнялось.</p>
    </div>

    <div class="bx-profile-card bx-profile-card--wide">
      <div class="bx-profile-card-title">Настройки MAX (админ)</div>
      <label class="field-label" for="mxTokenInput">Токен (оставьте пустым, чтобы не менять)</label>
      <input type="text" id="mxTokenInput" class="input-block" placeholder="Новый токен MAX">
      <label class="bx-check-row"><input type="checkbox" id="mxRemoveToken"> Удалить текущий токен</label>
      <label class="field-label" for="mxWebhookSecret">Webhook secret (для /api/max_webhook.php?secret=...)</label>
      <input type="text" id="mxWebhookSecret" class="input-block" placeholder="Секрет для входящего webhook">
      <label class="bx-check-row"><input type="checkbox" id="mxRemoveWebhookSecret"> Удалить webhook secret</label>
      <label class="field-label" for="mxChatInput">Fallback chat_id</label>
      <input type="text" id="mxChatInput" class="input-block" placeholder="Например: 284351356">
      <div class="form-row max-form-row">
        <div>
          <label class="field-label" for="mxQuietStart">Quiet start</label>
          <input type="text" id="mxQuietStart" class="input-block" placeholder="20:00">
        </div>
        <div>
          <label class="field-label" for="mxQuietEnd">Quiet end</label>
          <input type="text" id="mxQuietEnd" class="input-block" placeholder="08:00">
        </div>
      </div>
      <label class="bx-check-row"><input type="checkbox" id="mxQuietEnabled"> Включить quiet hours (некритичные события)</label>
      <div class="form-row max-form-row">
        <div>
          <label class="field-label" for="mxTimeout">HTTP timeout, сек</label>
          <input type="number" id="mxTimeout" class="input-block" min="3" max="60" step="1">
        </div>
        <div>
          <label class="field-label" for="mxStaleDays">SLA "нет движения X дней"</label>
          <input type="number" id="mxStaleDays" class="input-block" min="1" max="60" step="1">
        </div>
      </div>
      <label class="field-label" for="mxOrgDueTime">Срок по умолчанию (организация, ЧЧ:ММ)</label>
      <p class="muted bx-profile-lead2">Импорт Excel и дата без времени в задаче.</p>
      <input type="text" id="mxOrgDueTime" class="input-block" placeholder="18:00" maxlength="5">
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-secondary" id="btnOrgDueTimeSave">Сохранить время срока</button>
        <button type="button" class="btn btn-primary" id="btnMaxSettingsSave">Сохранить настройки MAX</button>
        <span class="muted bx-profile-savehint" id="mxSettingsHint"></span>
      </div>
    </div>

    <div class="bx-profile-card bx-profile-card--wide">
      <div class="bx-profile-card-title">Очередь уведомлений</div>
      <div class="form-row max-form-row">
        <div>
          <label class="field-label" for="mxFilterStatus">Статус</label>
          <select id="mxFilterStatus" class="input-block">
            <option value="">Все</option>
            <option value="pending">pending</option>
            <option value="sent">sent</option>
            <option value="failed">failed</option>
            <option value="cancelled">cancelled</option>
          </select>
        </div>
        <div>
          <label class="field-label" for="mxFilterEvent">Тип события (event_type)</label>
          <input type="text" id="mxFilterEvent" class="input-block" placeholder="task_assigned / sla_due_2h ...">
        </div>
      </div>
      <label class="field-label" for="mxFilterUser">Пользователь (имя/email)</label>
      <input type="text" id="mxFilterUser" class="input-block" placeholder="например, ivanov@...">
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-secondary" id="btnMaxQueueLoad">Применить фильтр</button>
        <button type="button" class="btn btn-secondary" id="btnMaxQueueExport">Экспорт CSV</button>
        <span class="muted bx-profile-savehint" id="mxQueueHint"></span>
      </div>
      <div class="task-table-scroll max-queue-scroll">
        <table class="task-table max-queue-table">
          <thead>
            <tr>
              <th>ID</th><th>Статус</th><th>Событие</th><th>Пользователь</th><th>Попытки</th><th>Следующая попытка</th><th>Ошибка</th>
            </tr>
          </thead>
          <tbody id="mxQueueBody"></tbody>
        </table>
      </div>
    </div>

    <div class="bx-profile-card bx-profile-card--wide">
      <div class="bx-profile-card-title">Webhook MAX (команды бота)</div>
      <p class="muted bx-profile-lead2">После <strong>смены токена</strong> в MAX нужно снова зарегистрировать webhook — иначе уведомления из очереди работают, а команды в чате нет.</p>
      <p class="muted bx-profile-lead2" id="mxWebhookUrlHint">URL webhook: …</p>
      <p class="muted bx-profile-lead2" id="mxWebhookSubsHint"></p>
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-primary" id="btnMaxWebhookRegister">Зарегистрировать webhook в MAX</button>
        <button type="button" class="btn btn-secondary" id="btnMaxWebhookSubsRefresh">Обновить подписки</button>
        <span class="muted bx-profile-savehint" id="mxWebhookRegisterHint"></span>
      </div>
    </div>

    <div class="bx-profile-card bx-profile-card--wide">
      <div class="bx-profile-card-title">Тест webhook</div>
      <p class="muted bx-profile-lead2">Проверка секрета и привязки chat_id без внешнего вызова MAX. Команды указывать в формате /start, /help, /settings.</p>
      <div class="form-row">
        <div>
          <label class="field-label" for="mxWebhookTestChatId">chat_id для теста</label>
          <input type="text" id="mxWebhookTestChatId" class="input-block" placeholder="Например: 284351356">
        </div>
        <div>
          <label class="field-label" for="mxWebhookTestSecret">secret</label>
          <input type="text" id="mxWebhookTestSecret" class="input-block" placeholder="webhook secret">
        </div>
      </div>
      <label class="field-label" for="mxWebhookTestCommand">Команда</label>
      <input type="text" id="mxWebhookTestCommand" class="input-block" value="/help">
      <div class="bx-profile-card-foot">
        <button type="button" class="btn btn-secondary" id="btnMaxWebhookTest">Проверить webhook</button>
        <span class="muted bx-profile-savehint" id="mxWebhookTestHint"></span>
      </div>
    </div>
  </div>
</section>

<script>
  (function () {
    var EVENT_LABELS_RU = {
      task_created: 'Создание задачи',
      task_assigned: 'Назначение исполнителя',
      task_status_changed: 'Смена статуса',
      task_due_changed: 'Смена срока',
      task_comment_mention: 'Упоминание в комментарии',
      task_participant_added: 'Добавление соисполнителя',
      direct_message: 'Прямое сообщение',
      sla_due_2h: 'SLA: срок через 2 часа',
      sla_overdue_24h: 'SLA: просрочка > 24ч',
      sla_stale_days: 'SLA: нет движения X дней',
      digest_summary: 'Дайджест',
    };

    function byId(id) { return document.getElementById(id); }
    function setText(id, value) {
      var el = byId(id);
      if (el) el.textContent = String(value == null ? '—' : value);
    }
    function setMetricState(id, type, value) {
      var el = byId(id);
      if (!el) return;
      el.textContent = String(value == null ? '0' : value);
      el.className = 'bx-profile-v max-metric max-metric--' + type;
      if (type === 'failed' && Number(value || 0) > 0) {
        el.classList.add('is-hot');
      }
      if (type === 'pending' && Number(value || 0) > 25) {
        el.classList.add('is-warn');
      }
    }
    function renderAlerts(alerts) {
      var host = byId('mxAlerts');
      if (!host) return;
      if (!alerts || !alerts.length) {
        host.hidden = false;
        host.className = 'alert';
        host.textContent = 'Критичных алертов нет.';
        return;
      }
      host.hidden = false;
      host.className = 'alert alert-error';
      host.textContent = alerts.join(' | ');
    }
    function updateMetrics() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      window.PlannerCore.api('/api/max_metrics.php').then(function (res) {
        var m = (res && res.metrics) || {};
        var c = m.counts || {};
        setMetricState('mxPending', 'pending', c.pending || 0);
        setMetricState('mxSent', 'sent', c.sent || 0);
        setMetricState('mxFailed', 'failed', c.failed || 0);
        setMetricState('mxCancelled', 'cancelled', c.cancelled || 0);
        setMetricState('mxTotal', 'total', c.total || 0);
        var hb = m.heartbeat || {};
        var hbEl = byId('mxHeartbeat');
        if (hb.last_ts) {
          setText('mxHeartbeat', hb.last_ts + ' (' + String(hb.age_minutes || 0) + ' мин. назад)');
          if (hbEl) {
            hbEl.className = 'bx-profile-v max-health ' + (hb.ok ? 'is-ok' : 'is-bad');
          }
        } else {
          setText('mxHeartbeat', 'нет данных');
          if (hbEl) hbEl.className = 'bx-profile-v max-health is-bad';
        }
        var opEl = byId('mxOldestPending');
        if (m.pending_oldest_age_minutes != null) {
          setText('mxOldestPending', String(m.pending_oldest_age_minutes) + ' мин.');
          if (opEl) {
            opEl.className = 'bx-profile-v max-health ' + (Number(m.pending_oldest_age_minutes) > 15 ? 'is-warn' : 'is-ok');
          }
        } else {
          setText('mxOldestPending', 'нет pending');
          if (opEl) opEl.className = 'bx-profile-v max-health is-ok';
        }
        renderAlerts(Array.isArray(m.alerts) ? m.alerts : []);
        setText('mxLastUpdate', 'Последнее обновление: ' + new Date().toLocaleString('ru-RU'));
      }).catch(function (e) {
        renderAlerts(['Ошибка загрузки метрик: ' + (e && e.message ? e.message : 'неизвестно')]);
      });
    }

    function runWorkerNow() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      setText('mxRunResult', 'Запуск воркера...');
      window.PlannerCore.api('/api/max_run_worker.php', { method: 'POST', body: { limit: 100 } }, {
        forbidden: 'Недостаточно прав',
        error: 'Не удалось запустить воркер',
      }).then(function (res) {
        var r = (res && res.result) || {};
        setText(
          'mxRunResult',
          'Воркер выполнен: processed=' + String(r.processed || 0) +
          ', sent=' + String(r.sent || 0) +
          ', failed=' + String(r.failed || 0) +
          ', skipped=' + String(r.skipped || 0)
        );
        updateMetrics();
      }).catch(function (e) {
        setText('mxRunResult', 'Ошибка запуска: ' + (e && e.message ? e.message : 'неизвестно'));
      });
    }

    function retryFailedNow() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      setText('mxRunResult', 'Повторная постановка failed...');
      window.PlannerCore.api('/api/max_retry_failed.php', { method: 'POST', body: { limit: 300 } }, {
        forbidden: 'Недостаточно прав',
      }).then(function (res) {
        setText('mxRunResult', 'Повторно поставлено failed: ' + String((res && res.retried) || 0));
        updateMetrics();
      }).catch(function (e) {
        setText('mxRunResult', 'Ошибка retry: ' + (e && e.message ? e.message : 'неизвестно'));
      });
    }

    function queueParams() {
      var st = byId('mxFilterStatus') ? byId('mxFilterStatus').value : '';
      var ev = byId('mxFilterEvent') ? byId('mxFilterEvent').value.trim() : '';
      var uq = byId('mxFilterUser') ? byId('mxFilterUser').value.trim() : '';
      var p = new URLSearchParams();
      if (st) p.set('status', st);
      if (ev) p.set('event_type', ev);
      if (uq) p.set('user_q', uq);
      p.set('limit', '100');
      return p;
    }

    function loadQueue() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      var p = queueParams();
      window.PlannerCore.api('/api/max_queue_list.php?' + p.toString()).then(function (res) {
        var body = byId('mxQueueBody');
        if (!body) return;
        var items = (res && res.items) || [];
        body.innerHTML = items.map(function (it) {
          var user = String(it.user_name || '') || String(it.user_email || '');
          var err = String(it.last_error || '');
          var ev = String(it.event_type || '');
          var evRu = EVENT_LABELS_RU[ev] || 'Другое событие';
          var evCell = evRu + (ev ? ' (' + ev + ')' : '');
          return '<tr>' +
            '<td>' + String(it.id || '') + '</td>' +
            '<td>' + String(it.status || '') + '</td>' +
            '<td>' + evCell + '</td>' +
            '<td>' + user + '</td>' +
            '<td>' + String(it.attempts || 0) + '</td>' +
            '<td>' + String(it.next_retry_at || '') + '</td>' +
            '<td title="' + err.replace(/"/g, '&quot;') + '">' + err.slice(0, 120) + '</td>' +
            '</tr>';
        }).join('');
        setText('mxQueueHint', 'Найдено: ' + String((res && res.total) || 0));
      }).catch(function (e) {
        setText('mxQueueHint', 'Ошибка загрузки: ' + (e && e.message ? e.message : 'неизвестно'));
      });
    }

    function exportQueue() {
      var p = queueParams();
      var root = typeof window.__PLANNER_ROOT__ === 'string' ? window.__PLANNER_ROOT__ : '';
      window.location.href = root + '/api/max_queue_export.php?' + p.toString();
    }

    function loadOrgDueTime() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      window.PlannerCore.api('/api/organization.php').then(function (res) {
        if (byId('mxOrgDueTime')) {
          byId('mxOrgDueTime').value = String((res && res.default_due_time) || '18:00');
        }
      }).catch(function () {});
    }

    function saveOrgDueTime() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      var v = byId('mxOrgDueTime') ? byId('mxOrgDueTime').value.trim() : '';
      setText('mxSettingsHint', 'Сохранение времени срока...');
      window.PlannerCore.api('/api/organization.php', { method: 'PATCH', body: { default_due_time: v } }, {
        forbidden: 'Только администратор',
        bad_due_time: 'Формат ЧЧ:ММ, например 18:00',
      }).then(function () {
        setText('mxSettingsHint', 'Время срока организации сохранено.');
        loadOrgDueTime();
      }).catch(function (e) {
        setText('mxSettingsHint', e && e.message ? e.message : 'Ошибка');
      });
    }

    function loadSettings() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      loadOrgDueTime();
      window.PlannerCore.api('/api/max_settings.php').then(function (res) {
        var s = (res && res.settings) || {};
        if (byId('mxChatInput')) byId('mxChatInput').value = String(s.chat_id || '');
        if (byId('mxQuietEnabled')) byId('mxQuietEnabled').checked = !!s.quiet_hours_enabled;
        if (byId('mxQuietStart')) byId('mxQuietStart').value = String(s.quiet_start || '20:00');
        if (byId('mxQuietEnd')) byId('mxQuietEnd').value = String(s.quiet_end || '08:00');
        if (byId('mxTimeout')) byId('mxTimeout').value = String(s.timeout || 12);
        if (byId('mxStaleDays')) byId('mxStaleDays').value = String(s.stale_days || 3);
        if (byId('mxWebhookSecret')) byId('mxWebhookSecret').value = '';
      }).catch(function (e) {
        setText('mxSettingsHint', 'Ошибка чтения настроек: ' + (e && e.message ? e.message : 'неизвестно'));
      });
    }

    function saveSettings() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      setText('mxSettingsHint', 'Сохранение...');
      var body = {
        token: byId('mxTokenInput') ? byId('mxTokenInput').value.trim() : '',
        remove_token: byId('mxRemoveToken') ? !!byId('mxRemoveToken').checked : false,
        webhook_secret: byId('mxWebhookSecret') ? byId('mxWebhookSecret').value.trim() : '',
        remove_webhook_secret: byId('mxRemoveWebhookSecret') ? !!byId('mxRemoveWebhookSecret').checked : false,
        chat_id: byId('mxChatInput') ? byId('mxChatInput').value.trim() : '',
        quiet_hours_enabled: byId('mxQuietEnabled') ? !!byId('mxQuietEnabled').checked : false,
        quiet_start: byId('mxQuietStart') ? byId('mxQuietStart').value.trim() : '20:00',
        quiet_end: byId('mxQuietEnd') ? byId('mxQuietEnd').value.trim() : '08:00',
        timeout: byId('mxTimeout') ? parseInt(byId('mxTimeout').value, 10) || 12 : 12,
        stale_days: byId('mxStaleDays') ? parseInt(byId('mxStaleDays').value, 10) || 3 : 3
      };
      window.PlannerCore.api('/api/max_settings.php', { method: 'PATCH', body: body }, {
        bad_quiet_time: 'Некорректное время quiet hours',
        schema_missing: 'Нужна миграция max_settings',
      }).then(function (res) {
        var hint = 'Настройки сохранены.';
        if (res && res.reregister_webhook) {
          hint += ' Токен изменён — нажмите «Зарегистрировать webhook в MAX» (иначе в логе будет bad_secret).';
          loadWebhookStatus();
        }
        setText('mxSettingsHint', hint);
        if (byId('mxTokenInput')) byId('mxTokenInput').value = '';
        if (byId('mxRemoveToken')) byId('mxRemoveToken').checked = false;
        if (byId('mxWebhookSecret')) byId('mxWebhookSecret').value = '';
        if (byId('mxRemoveWebhookSecret')) byId('mxRemoveWebhookSecret').checked = false;
      }).catch(function (e) {
        setText('mxSettingsHint', 'Ошибка сохранения: ' + (e && e.message ? e.message : 'неизвестно'));
      });
    }

    function formatWebhookSubs(subs) {
      if (!subs || !subs.length) return 'Подписки в MAX: не найдены (нажмите «Зарегистрировать webhook»).';
      return subs.map(function (s) {
        var url = String((s && (s.url || s.webhook_url)) || '');
        var types = s && s.update_types ? JSON.stringify(s.update_types) : '';
        return url + (types ? ' [' + types + ']' : '');
      }).join(' · ');
    }

    function loadWebhookStatus() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      setText('mxWebhookRegisterHint', 'Загрузка…');
      window.PlannerCore.api('/api/max_webhook_register.php').then(function (res) {
        var url = String((res && res.webhook_url) || '');
        setText('mxWebhookUrlHint', url ? ('URL webhook: ' + url) : 'URL webhook: не задан (нужен HTTPS public_origin в config)');
        var err = res && res.subscriptions_error ? String(res.subscriptions_error) : '';
        var subs = (res && res.subscriptions) || [];
        setText('mxWebhookSubsHint', err ? ('Ошибка списка подписок: ' + err) : formatWebhookSubs(subs));
        var hint = (res && res.has_webhook_secret)
          ? ('Секрет webhook в БД: есть (' + String(res.webhook_secret_len || '?') + ' симв.).')
          : 'Секрет webhook в БД: нет (создастся при регистрации).';
        if (res && !res.max_enabled) hint += ' Токен MAX не настроен.';
        setText('mxWebhookRegisterHint', hint);
      }).catch(function (e) {
        setText('mxWebhookRegisterHint', 'Ошибка: ' + (e && e.message ? e.message : 'неизвестно'));
      });
    }

    function registerWebhook() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      if (!window.confirm('Зарегистрировать webhook в MAX? Старые подписки будут удалены, secret синхронизируется с БД.')) return;
      setText('mxWebhookRegisterHint', 'Регистрация…');
      var secret = byId('mxWebhookSecret') ? byId('mxWebhookSecret').value.trim() : '';
      var body = { regenerate_secret: !secret };
      if (secret) body.webhook_secret = secret;
      window.PlannerCore.api('/api/max_webhook_register.php', { method: 'POST', body: body }, {
        forbidden: 'Недостаточно прав',
        bad_webhook_url: 'Задайте PLANNER_PUBLIC_ORIGIN=https://ваш-домен в config',
        not_configured: 'Сначала сохраните токен MAX',
        api_error: 'MAX API отклонил регистрацию',
      }).then(function (res) {
        var msg = String((res && res.message) || (res && res.secret_hint) || 'Webhook зарегистрирован.');
        setText('mxWebhookRegisterHint', msg);
        loadWebhookStatus();
      }).catch(function (e) {
        var msg = (e && e.message) ? e.message : 'неизвестно';
        if (e && e.payload && e.payload.message) msg = e.payload.message;
        setText('mxWebhookRegisterHint', 'Ошибка: ' + msg);
      });
    }

    function testWebhook() {
      if (!window.PlannerCore || !window.PlannerCore.api) return;
      setText('mxWebhookTestHint', 'Проверка...');
      var body = {
        chat_id: byId('mxWebhookTestChatId') ? byId('mxWebhookTestChatId').value.trim() : '',
        secret: byId('mxWebhookTestSecret') ? byId('mxWebhookTestSecret').value.trim() : '',
        command: byId('mxWebhookTestCommand') ? byId('mxWebhookTestCommand').value.trim() : '/help'
      };
      window.PlannerCore.api('/api/max_webhook_test.php', { method: 'POST', body: body }, {
        chat_id_required: 'Укажите chat_id',
        bad_secret: 'Неверный secret',
      }).then(function (res) {
        var note = String((res && res.note) || 'ok');
        if (res && res.user && res.user.name) {
          note += ' (пользователь: ' + String(res.user.name) + ')';
        }
        setText('mxWebhookTestHint', note);
      }).catch(function (e) {
        setText('mxWebhookTestHint', 'Ошибка: ' + (e && e.message ? e.message : 'неизвестно'));
      });
    }

    var btn = byId('btnMaxDashRefresh');
    if (btn) btn.addEventListener('click', updateMetrics);
    var btnRun = byId('btnMaxDashRunWorker');
    if (btnRun) btnRun.addEventListener('click', runWorkerNow);
    var btnRetry = byId('btnMaxDashRetryFailed');
    if (btnRetry) btnRetry.addEventListener('click', retryFailedNow);
    var btnQueue = byId('btnMaxQueueLoad');
    if (btnQueue) btnQueue.addEventListener('click', loadQueue);
    var btnExport = byId('btnMaxQueueExport');
    if (btnExport) btnExport.addEventListener('click', exportQueue);
    var btnSet = byId('btnMaxSettingsSave');
    if (btnSet) btnSet.addEventListener('click', saveSettings);
    var btnOrgDue = byId('btnOrgDueTimeSave');
    if (btnOrgDue) btnOrgDue.addEventListener('click', saveOrgDueTime);
    var btnWhReg = byId('btnMaxWebhookRegister');
    if (btnWhReg) btnWhReg.addEventListener('click', registerWebhook);
    var btnWhSubs = byId('btnMaxWebhookSubsRefresh');
    if (btnWhSubs) btnWhSubs.addEventListener('click', loadWebhookStatus);
    var btnWhTest = byId('btnMaxWebhookTest');
    if (btnWhTest) btnWhTest.addEventListener('click', testWebhook);
    loadSettings();
    loadWebhookStatus();
  })();
</script>

<?php layout_end(); ?>
