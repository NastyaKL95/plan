<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
if (!user_may_manage_users($user)) {
    http_response_code(403);
    layout_start($user, 'Уведомления MAX', 'max_notifications');
    echo '<div class="page-head"><h1 class="page-title">Доступ запрещён</h1><p class="muted">Журнал уведомлений MAX доступен только администратору организации.</p></div>';
    layout_end();
    exit;
}

layout_start($user, 'Уведомления MAX', 'max_notifications');
?>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Уведомления MAX</span>
</nav>

<div class="page-head">
  <div>
    <h1 class="page-title">Уведомления MAX</h1>
    <p class="muted">Все сообщения, которые уходят в MAX. Ожидающее можно отменить, ошибочное — отправить снова.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= htmlspecialchars(planner_url('/max_dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">Мониторинг</a>
    <button type="button" class="btn btn-primary" id="btnMaxNotesReload">Обновить</button>
  </div>
</div>

<div class="form-row max-form-row">
  <div>
    <label class="field-label" for="mxNotesStatus">Статус</label>
    <select id="mxNotesStatus" class="input-block">
      <option value="">Все</option>
      <option value="pending">Ожидает</option>
      <option value="sent">Отправлено</option>
      <option value="failed">Ошибка</option>
      <option value="cancelled">Отменено</option>
    </select>
  </div>
  <div>
    <label class="field-label" for="mxNotesEvent">Событие</label>
    <select id="mxNotesEvent" class="input-block">
      <option value="">Все</option>
      <option value="task_created">Создание задачи</option>
      <option value="task_assigned">Назначение исполнителя</option>
      <option value="task_status_changed">Смена статуса</option>
      <option value="task_due_changed">Смена срока</option>
      <option value="task_comment_mention">Упоминание в комментарии</option>
      <option value="task_participant_added">Соисполнитель</option>
      <option value="direct_message">Прямое сообщение</option>
      <option value="sla_due_2h">SLA: срок через 2 часа</option>
      <option value="sla_overdue_24h">SLA: просрочка</option>
      <option value="sla_stale_days">SLA: нет движения</option>
      <option value="digest_summary">Дайджест</option>
    </select>
  </div>
  <div>
    <label class="field-label" for="mxNotesUser">Кому</label>
    <input type="search" id="mxNotesUser" class="input-block" placeholder="Имя или email">
  </div>
</div>
<p class="muted" id="mxNotesHint">Загрузка…</p>

<div class="task-table-scroll max-queue-scroll">
  <table class="task-table max-queue-table">
    <thead>
      <tr>
        <th>Когда</th>
        <th>Статус</th>
        <th>Событие</th>
        <th>Кому</th>
        <th>Задача</th>
        <th>Текст</th>
        <th>Действия</th>
      </tr>
    </thead>
    <tbody id="mxNotesBody"></tbody>
  </table>
</div>

<h2 class="page-title" style="margin-top:28px;font-size:1.25rem">Ждут дайджеста</h2>
<p class="muted">Эти сообщения ещё не отправлены в MAX: они соберутся в один дайджест. Их можно убрать из очереди.</p>
<div class="task-table-scroll max-queue-scroll">
  <table class="task-table max-queue-table">
    <thead>
      <tr>
        <th>Когда</th>
        <th>Событие</th>
        <th>Кому</th>
        <th>Задача</th>
        <th>Текст</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="mxDigestBody"></tbody>
  </table>
</div>

<script src="<?= htmlspecialchars(planner_url('/assets/js/max_notifications.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php layout_end(); ?>
