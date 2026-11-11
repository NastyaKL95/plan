<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
$planner_hide_done_days = max(
    0,
    min(3650, (int) ((app_config()['tasks'] ?? [])['hide_done_cancelled_after_days'] ?? 90))
);
layout_start($user, 'Задачи', 'tasks');
$user_display = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
?>
<script>
window.__PLANNER_USER_ROLE__=<?= json_encode($user['role'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
window.__PLANNER_USER_HAS_MANAGER__=<?= user_has_manager_visibility_privileges($user) ? 'true' : 'false' ?>;
window.__PLANNER_USER_NAME__=<?= json_encode($user['name'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
window.__PLANNER_USER_DEPT_ID__=<?= json_encode(isset($user['department_id']) && $user['department_id'] !== null ? (int) $user['department_id'] : null, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
</script>
<section class="bx-task-page">
  <div class="bx-main-card">
    <header class="bx-task-head">
      <div class="bx-task-title-row">
        <div class="bx-task-title-block">
          <h1 class="bx-page-title">Мои задачи</h1>
        </div>
        <div class="bx-task-head-actions">
          <select id="filterRole" class="bx-select bx-select--roles" aria-label="Мои роли в задачах">
            <option value="">Все роли</option>
            <option value="responsible">Я исполнитель</option>
            <option value="author">Я постановщик</option>
            <option value="assistant">Я соисполнитель</option>
            <option value="observer">Я наблюдатель</option>
          </select>
          <select id="filterVisibility" class="bx-select" aria-label="Видимость задач">
            <option value="">Вся видимость</option>
            <option value="institute">Только Институт</option>
            <option value="department">Только Структура</option>
            <option value="personal">Только Личные</option>
          </select>
          <select id="filterProject" class="bx-select" aria-label="Проект">
            <option value="">Все проекты</option>
          </select>
          <select id="taskTemplatePick" class="bx-select bx-select--template" aria-label="Шаблон задачи">
            <option value="">Шаблон…</option>
          </select>
          <button type="button" class="btn btn-secondary" id="btnCreateFromTemplate" title="Создать задачу из выбранного шаблона">Из шаблона</button>
          <div class="bx-create-wrap bx-create-split">
            <button type="button" class="btn btn-primary btn-bx-create-split-main" id="btnNewTask">
              + Создать
            </button>
            <button type="button" class="btn btn-primary btn-bx-create-split-more" id="btnCreateMenu" aria-label="Другие способы создать задачу" aria-expanded="false" aria-haspopup="true" title="Другие способы создать">
              <svg class="bx-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="bx-create-menu" id="createTaskMenu" hidden role="menu" aria-label="Создание задачи">
              <button type="button" class="bx-create-menu-item" id="menuItemQuickTask" role="menuitem">Быстрая задача</button>
            </div>
          </div>
        </div>
      </div>
      
      <div class="bx-task-nav-row">
        <div class="bx-tabs-bitrix" role="tablist" aria-label="Вид задач">
          <button type="button" class="bx-tab-bitrix is-active" id="tabViewBoard" aria-selected="true">Доска</button>
          <button type="button" class="bx-tab-bitrix" id="tabViewList" aria-selected="false">Список</button>
          <button type="button" class="bx-tab-bitrix" id="tabViewDeadlines" aria-selected="false">Сроки</button>
          <button type="button" class="bx-tab-bitrix" id="tabViewPlan" aria-selected="false">Мой план</button>
          <button type="button" class="bx-tab-bitrix" id="tabViewCalendar" aria-selected="false">Календарь</button>
          <button type="button" class="bx-tab-bitrix" id="tabViewGantt" aria-selected="false">Гант</button>
        </div>
        <div class="bx-stat-pills">
          <span class="bx-stat-pill"><strong id="statOverdue">0</strong> Просрочены</span>
          <span class="bx-stat-pill"><strong id="statComments">0</strong> С комментариями</span>
          <span class="bx-stat-pill"><strong id="statChatsWeek">0</strong> Комментарии за 7 дней</span>
        </div>
        <div class="bx-task-extras">
          <a href="<?= htmlspecialchars(planner_url('/tags.php'), ENT_QUOTES, 'UTF-8') ?>" class="bx-muted-link">Метки</a>
          <a href="<?= htmlspecialchars(planner_url('/automation.php'), ENT_QUOTES, 'UTF-8') ?>" class="bx-muted-link">Роботы</a>
        </div>
      </div>
    </header>

    <details class="bx-adv-search-details" id="advSearchDetails">
      <summary class="bx-adv-search-summary">Расширенный поиск и сохранённые представления</summary>
      <div class="bx-adv-search-inner">
        <div class="bx-adv-active-filters" id="tasksActiveFiltersRow" hidden>
          <div class="bx-filter-tags" id="tasksActiveFilters"></div>
          <div class="bx-adv-active-filters-actions">
            <button type="button" class="btn btn-secondary" id="btnClearOneAdvFilter" hidden>Сбросить расширенное</button>
            <button type="button" class="btn btn-secondary" id="btnClearAllFilters">Сбросить всё</button>
          </div>
        </div>
        <div class="bx-adv-quickrow">
          <button type="button" class="btn btn-secondary bx-adv-more-btn" id="btnAdvMore" aria-expanded="false">
            Ещё фильтры
          </button>
          <span class="muted bx-adv-quickhint">По умолчанию показаны частые поля. Остальные — в «Ещё фильтры».</span>
        </div>
        <div class="bx-adv-search-grid">
          <label class="bx-adv-field bx-adv-field--grow">
            <span class="bx-adv-label">Название, описание, проект..</span>
            <input type="search" id="tasksAdvSearch" class="input-block" placeholder="Поиск по задачам…" autocomplete="off" maxlength="500" aria-label="Поиск по задачам на доске">
          </label>
          <label class="bx-adv-field">
            <span class="bx-adv-label">Исполнитель</span>
            <select id="filterAdvAssignee" class="bx-select" aria-label="Фильтр по исполнителю">
              <option value="">Любой</option>
            </select>
          </label>
          <label class="bx-adv-field bx-adv-extra">
            <span class="bx-adv-label">Участник (любая роль)</span>
            <input
              type="search"
              id="filterAdvParticipantSearch"
              class="input-block"
              placeholder="Поиск участника…"
              autocomplete="off"
              list="filterAdvParticipantList"
              aria-label="Поиск участника">
            <datalist id="filterAdvParticipantList"></datalist>
            <input type="hidden" id="filterAdvParticipant" value="">
          </label>
          <label class="bx-adv-field bx-adv-extra">
            <span class="bx-adv-label">Подразделение (проект)</span>
            <select id="filterDepartment" class="bx-select" aria-label="Фильтр по отделу проекта">
              <option value="">— любой —</option>
            </select>
          </label>
          <label class="bx-adv-field bx-adv-field--tags bx-adv-extra">
            <span class="bx-adv-label">Метки</span>
            <select id="filterAdvTags" class="bx-select" aria-label="Фильтр по меткам">
              <option value="">Любая</option>
            </select>
          </label>
          <label class="bx-adv-field">
            <span class="bx-adv-label">Срок с</span>
            <input type="datetime-local" id="filterDueFrom" class="input-block" autocomplete="off">
          </label>
          <label class="bx-adv-field">
            <span class="bx-adv-label">Срок по</span>
            <input type="datetime-local" id="filterDueTo" class="input-block" autocomplete="off">
          </label>
          <label class="bx-adv-field bx-adv-extra">
            <span class="bx-adv-label">Комментарии</span>
            <select id="filterCommentsMode" class="bx-select" aria-label="Фильтр по комментариям">
              <option value="">Любые</option>
              <option value="1">С комментариями</option>
              <option value="0">Без комментариев</option>
            </select>
          </label>
          <label class="bx-adv-field bx-adv-extra">
            <span class="bx-adv-label">Вложения</span>
            <select id="filterAttachmentsMode" class="bx-select" aria-label="Фильтр по вложениям">
              <option value="">Любые</option>
              <option value="1">С вложениями</option>
              <option value="0">Без вложений</option>
            </select>
          </label>
          <label class="bx-adv-field bx-adv-extra">
            <span class="bx-adv-label">Тип</span>
            <select id="filterTaskKind" class="bx-select" aria-label="Обычные или мелкие быстрые задачи">
              <option value="">Все задачи</option>
              <option value="normal">Только обычные</option>
              <option value="quick">Только мелкие (быстрые)</option>
            </select>
          </label>
          <?php if ($planner_hide_done_days > 0): ?>
          <label class="bx-adv-field bx-adv-field--grow bx-adv-extra">
            <span class="bx-adv-label">Закрытые задачи</span>
            <label class="bx-check-row">
              <input type="checkbox" id="filterIncludeArchivedDone" value="1">
              <span class="bx-check-row-text-small">Показывать все выполненные и отменённые (включая старше <?= (int) $planner_hide_done_days ?> дн.)</span>
            </label>
          </label>
          <?php endif; ?>
        </div>
        <div class="bx-adv-search-actions">
          <button type="button" class="btn btn-primary" id="btnApplyAdvSearch">Применить фильтр</button>
          <button type="button" class="btn btn-secondary" id="btnResetAdvSearch">Сбросить расширенное</button>
        </div>
        <div class="bx-saved-views-row">
          <label class="bx-adv-field bx-adv-field--grow">
            <span class="bx-adv-label">Представление</span>
            <input type="search" id="savedViewSearch" class="input-block bx-adv-inline-search" placeholder="Быстрый поиск по представлениям…" autocomplete="off" maxlength="120" aria-label="Поиск по сохранённым представлениям">
            <select id="savedViewPick" class="bx-select" aria-label="Сохранённое представление">
              <option value="">— не выбрано —</option>
            </select>
          </label>
          <label class="bx-adv-field" id="savedViewScopeWrap">
            <span class="bx-adv-label">Область при сохранении</span>
            <select id="savedViewScope" class="bx-select" aria-label="Личное или командное представление">
              <option value="personal">Только я</option>
              <option value="team">Подразделение (командное)</option>
            </select>
          </label>
          <label class="bx-adv-field" id="savedViewDeptWrap" hidden>
            <span class="bx-adv-label">Подразделение</span>
            <select id="savedViewDept" class="bx-select" aria-label="Отдел для представления">
              <option value="">Вся организация</option>
            </select>
          </label>
          <button type="button" class="btn btn-secondary" id="btnSaveView">Сохранить текущее…</button>
          <button type="button" class="btn btn-secondary" id="btnUpdateSavedView" title="Обновить фильтры выбранного представления">Обновить текущее</button>
          <button type="button" class="btn btn-secondary" id="btnDuplicateSavedView" title="Копия выбранного представления">Дублировать</button>
          <button type="button" class="btn btn-secondary" id="btnRenameSavedView" title="Переименовать выбранное представление">Переименовать</button>
          <button type="button" class="btn btn-secondary" id="btnDefaultSavedView" title="Открывать при входе на страницу">По умолчанию</button>
          <button type="button" class="btn btn-secondary" id="btnDeleteView" title="Удалить выбранное">Удалить</button>
        </div>
      </div>
    </details>

<div class="quick-task-overlay" id="quickTaskOverlay" hidden></div>
<div class="quick-task-dialog" id="quickTaskDialog" role="dialog" aria-modal="true" aria-labelledby="quickTaskDialogTitle" hidden>
  <h3 id="quickTaskDialogTitle" class="quick-task-dialog__title">Быстрая задача</h3>
  <p class="muted quick-task-dialog__lead">Минимум полей: попадает в бэклог и в отчёты руководителя (если включена видимость).</p>
  <label class="quick-task-dialog__field">
    <span class="quick-task-dialog__label">Заголовок</span>
    <input type="text" id="quickTaskTitle" class="input-block" maxlength="500" placeholder="Что сделать" autocomplete="off">
  </label>
  <label class="quick-task-dialog__field">
    <span class="quick-task-dialog__label">Проект</span>
    <select id="quickTaskProject" class="bx-select"></select>
  </label>
  <label class="quick-task-dialog__field">
    <span class="quick-task-dialog__label">Исполнитель</span>
    <select id="quickTaskAssignee" class="bx-select"></select>
  </label>
  <label class="quick-task-dialog__check">
    <input type="checkbox" id="quickTaskVisibleManagers" checked>
    <span>Видно руководителю подразделения в отчётах</span>
  </label>
  <p class="muted quick-task-dialog__hint" id="quickTaskDeptPolicyHint" hidden></p>
  <div class="quick-task-dialog__actions">
    <button type="button" class="btn btn-secondary" id="quickTaskCancel">Отмена</button>
    <button type="button" class="btn btn-primary" id="quickTaskSubmit">Создать</button>
  </div>
</div>

<div class="kanban" id="kanban"></div>

<div class="deadlines-kanban-host" id="taskDeadlinesWrap" hidden></div>

<div class="plan-toolbar" id="myPlanToolbar" hidden>
  <button type="button" class="btn btn-secondary" id="btnMyPlanAllStages">Все стадии</button>
</div>
<div class="plan-kanban-host" id="taskPlanWrap" hidden></div>

<div class="task-calendar-host" id="taskCalendarWrap" hidden>
  <div class="task-cal-nav">
    <button type="button" class="btn btn-secondary" id="taskCalPrev" aria-label="Предыдущий месяц">←</button>
    <h2 class="task-cal-title" id="taskCalMonthTitle"></h2>
    <button type="button" class="btn btn-secondary" id="taskCalNext" aria-label="Следующий месяц">→</button>
  </div>
  <div class="task-cal-weekdays">
    <span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span>
  </div>
  <div class="task-cal-grid" id="taskCalGrid"></div>
</div>

<div class="task-gantt-host" id="taskGanttWrap" hidden>
  <p class="muted task-gantt-lead" id="taskGanttLead"></p>
  <div class="task-gantt-scroll-x">
    <div class="task-gantt-inner" id="taskGanttInner"></div>
  </div>
</div>

<div class="task-list-wrap" id="taskListWrap" hidden>
  <div class="bx-bulk-bar" id="bulkBar" hidden>
    <label class="visually-hidden" for="bulkAction">Массовое действие</label>
    <select id="bulkAction" class="bx-select" aria-label="Действие">
      <option value="">Выберите действие</option>
      <option value="set_status">Сменить статус</option>
      <option value="set_assignee">Назначить исполнителя</option>
    </select>
    <select id="bulkStatus" class="bx-select" aria-label="Новый статус" hidden>
      <option value="new">Новая</option>
      <option value="in_progress">В работе</option>
      <option value="review">На проверке</option>
      <option value="done">Выполнена</option>
      <option value="cancelled">Отменена</option>
    </select>
    <select id="bulkAssignee" class="bx-select" aria-label="Исполнитель" hidden></select>
    <button type="button" class="btn btn-primary" id="btnBulkApply">Применить к выбранным</button>
    <span class="muted bx-bulk-count" id="bulkSelectedCount">Выбрано: 0</span>
    <span class="muted bx-bulk-preview" id="bulkPreview">Выберите действие и задачи для предпросмотра.</span>
    <span class="muted bx-bulk-progress" id="bulkProgress" hidden>Применение...</span>
  </div>
  <div class="task-table-scroll">
    <table class="task-table" id="taskTable">
      <thead>
        <tr>
          <th class="task-table-check"><input type="checkbox" id="taskSelectAll" aria-label="Выбрать все на странице"></th>
          <th>Название</th>
          <th>Активность</th>
          <th>Срок</th>
          <th>Постановщик</th>
          <th>Исполнитель</th>
          <th>Соисполнители</th>
          <th>Проект</th>
          <th>Метки</th>
          <th>Статус</th>
        </tr>
      </thead>
      <tbody id="taskTableBody"></tbody>
    </table>
  </div>
</div>

<div class="bx-tasks-load-more" id="tasksLoadMoreBar">
  <p class="bx-tasks-load-more-hint muted" id="tasksPaginationHint"></p>
  <div id="tasksLoadMoreSentinel" class="bx-tasks-load-sentinel" aria-hidden="true"></div>
</div>

<footer class="bx-kanban-footer" aria-label="Сводка по списку">
  <div class="bx-kf-left">
    <span>ОТМЕЧЕНО: <strong>0</strong> / <strong id="taskListTotal">0</strong></span>
    <span class="bx-kf-sep">·</span>
    <span class="bx-kf-link">Всего: <span id="taskListShowCount">показать количество</span></span>
    <span class="bx-kf-sep">·</span>
    <span id="tasksPaginationFooter">0 из 0</span>
  </div>
  <div class="bx-kf-right">
    <span class="bx-kf-per">НА СТРАНИЦЕ: <span class="muted">подгрузка по 80</span></span>
  </div>
</footer>

  </div>
</section>

<div class="my-plan-stages-dialog" id="myPlanStagesDialog" hidden aria-modal="true" role="dialog" aria-labelledby="myPlanStagesDialogTitle">
  <div class="my-plan-stages-dialog__backdrop" aria-hidden="true"></div>
  <div class="my-plan-stages-dialog__card">
    <h2 class="my-plan-stages-dialog__title" id="myPlanStagesDialogTitle">Все стадии</h2>
    <p class="muted-inline my-plan-stages-dialog__hint">Название и цвет колонок «Мой план». Порядок — стрелки вверх/вниз.</p>
    <ul id="myPlanStagesList" class="my-plan-stages-list"></ul>
    <div class="my-plan-stages-dialog__actions">
      <button type="button" class="btn btn-primary" id="myPlanStagesAdd">+ Стадия</button>
      <button type="button" class="btn btn-secondary" id="myPlanStagesClose">Закрыть</button>
    </div>
  </div>
</div>

<div class="task-modal-overlay" id="taskSplitOverlay" hidden></div>
<div class="task-modal" id="taskSplitModal" role="dialog" aria-modal="true" aria-labelledby="taskChatHeading" data-task-mode="create" hidden tabindex="-1">
  <div class="task-modal-card">
    <div class="task-modal-split">
      <div class="task-modal-left">
        <input type="hidden" id="fTaskId" value="">
        <div class="task-modal-title-row">
          <input type="text" id="fTitle" class="task-modal-title-input" maxlength="500" placeholder="Название задачи" autocomplete="off" aria-label="Название задачи">
          <div class="task-modal-title-actions">
            <input type="hidden" id="fPriority" value="normal">
            <button type="button" id="btnPriorityToggle" class="task-modal-priority-toggle is-normal" aria-label="Приоритет: обычный" title="Приоритет: обычный">
              <img id="priorityToggleIcon" src="<?= htmlspecialchars(planner_url('/assets/icons/flame-gray.svg'), ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true">
            </button>
            <div class="task-density-switch" role="group" aria-label="Плотность модального окна">
              <button type="button" class="task-density-btn" id="btnDensityComfort" data-density="comfortable">Комфортно</button>
              <button type="button" class="task-density-btn" id="btnDensityCompact" data-density="compact">Плотно</button>
            </div>
            
          </div>
        </div>
        <label class="task-modal-field-label" for="fDesc">Описание</label>
        <textarea id="fDesc" class="task-modal-desc" rows="4" maxlength="16000" placeholder="Добавьте описание задачи"></textarea>

        <ul class="task-modal-meta-list">
          <li class="task-modal-meta-row">
            <span class="task-modal-meta-ico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <span class="task-modal-meta-key">Постановщик</span>
            <span class="task-modal-meta-val" id="taskModalAuthorDisplay"><?= $user_display ?></span>
          </li>
          <li class="task-modal-meta-row">
            <span class="task-modal-meta-ico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg></span>
            <span class="task-modal-meta-key">Исполнитель</span>
            <div class="participant-picker-field">
              <button type="button" id="btnAssigneePicker" class="participant-picker-trigger task-modal-meta-select input-block is-empty" aria-haspopup="dialog" aria-expanded="false">
                Не назначен
              </button>
              <select id="fAssignee" class="task-modal-meta-select participant-picker-native" aria-label="Исполнитель" data-bx-dropdown-skip="1">
                <option value="">Не назначен</option>
              </select>
            </div>
          </li>
          <li class="task-modal-meta-row">
            <span class="task-modal-meta-ico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
            <span class="task-modal-meta-key">Крайний срок</span>
            <input type="datetime-local" id="fDue" class="task-modal-meta-input" title="Дата и время">
          </li>
          <li class="task-modal-meta-row">
            <span class="task-modal-meta-ico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg></span>
            <span class="task-modal-meta-key">Статус</span>
            <select id="fStatus" class="task-modal-meta-select" aria-label="Статус">
              <option value="new">Новая</option>
              <option value="in_progress">В работе</option>
              <option value="review">На проверке</option>
              <option value="done">Выполнена</option>
              <option value="cancelled">Отменена</option>
            </select>
          </li>
          <li class="task-modal-meta-row">
            <span class="task-modal-meta-ico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg></span>
            <span class="task-modal-meta-key">Проект</span>
            <div class="participant-picker-field">
              <button type="button" id="btnProjectPicker" class="participant-picker-trigger task-modal-meta-select input-block is-empty" aria-haspopup="dialog" aria-expanded="false">
                Выбрать проект
              </button>
              <select id="fProject" class="task-modal-meta-select participant-picker-native" aria-label="Проект" required data-bx-dropdown-skip="1"></select>
            </div>
          </li>
          <li class="task-modal-meta-row">
            <span class="task-modal-meta-ico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 4v6c0 5-3.5 9-7 10-3.5-1-7-5-7-10V6l7-4z"/></svg></span>
            <span class="task-modal-meta-key">Видимость</span>
            <div class="task-modal-meta-val">
              <label><input type="checkbox" id="fVisibleDepartment"> Видит вся структура</label>
              <label><input type="checkbox" id="fVisibleInstitute"> Видит институт</label>
            </div>
          </li>
          <li class="task-modal-meta-row task-work-row">
            <span class="task-modal-meta-ico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></span>
            <span class="task-modal-meta-key">Вид работы</span>
            <div class="task-modal-meta-val task-work-kinds">
              <label><input type="checkbox" id="fWorkEdu"> Учебная работа</label>
              <label><input type="checkbox" id="fWorkEduMethod"> Учебно-методическая работа</label>
              <label><input type="checkbox" id="fWorkResearch"> Научно-исследовательская работа</label>
              <label><input type="checkbox" id="fWorkOrgMethod"> Организационно-методическая работа</label>
              <p class="task-work-hint">Отмеченные виды попадут в индивидуальный план/отчёт. Можно отметить несколько.</p>
              <label class="task-work-field" for="fWorkPeriod">Сроки выполнения
                <input type="text" class="task-modal-meta-input" id="fWorkPeriod" maxlength="160" placeholder="Март 2026 или Январь–декабрь" autocomplete="off">
              </label>
              <label class="task-work-field" for="fWorkIndicator">Показатель выполнения
                <input type="text" class="task-modal-meta-input" id="fWorkIndicator" maxlength="255" placeholder="кол-во чел., п. л., стр." autocomplete="off">
              </label>
              <div class="task-work-hours">
                <label class="task-work-field" for="fWorkHoursH1">Часы, 1 полугодие
                  <input type="text" class="task-modal-meta-input" inputmode="decimal" id="fWorkHoursH1" maxlength="12" placeholder="0" autocomplete="off">
                </label>
                <label class="task-work-field" for="fWorkHoursH2">Часы, 2 полугодие
                  <input type="text" class="task-modal-meta-input" inputmode="decimal" id="fWorkHoursH2" maxlength="12" placeholder="0" autocomplete="off">
                </label>
              </div>
            </div>
          </li>
        </ul>

        <div class="task-modal-pills" role="toolbar" aria-label="Блоки задачи">
          <button type="button" class="task-modal-pill" data-target="modalSectionTags">Теги</button>
          <button type="button" class="task-modal-pill" data-target="modalSectionPeople">Участники</button>
          <button type="button" class="task-modal-pill task-modal-edit-only" data-target="modalSectionMore">Ещё</button>
          <button type="button" class="task-modal-pill task-modal-edit-only" data-target="modalSectionLinks">Связи</button>
          <button type="button" class="task-modal-pill task-modal-edit-only" data-target="modalSectionAttach">Файлы</button>
        </div>

        <div id="modalSectionTags" class="task-modal-extra" hidden>
          <span class="task-modal-field-label" id="fTagsLabel">Метки</span>
          <div class="tag-pick" id="fTags" role="group" aria-labelledby="fTagsLabel"></div>
        </div>
        <div id="modalSectionPeople" class="task-modal-extra" hidden>
          <div class="task-modal-dual-row">
            <div>
              <label class="task-modal-field-label" for="btnAssistantsPicker">Соисполнители</label>
              <div class="participant-picker-field">
                <button type="button" id="btnAssistantsPicker" class="participant-picker-trigger input-block" aria-haspopup="dialog" aria-expanded="false">
                  Выбрать соисполнителей
                </button>
                <select id="fAssistants" class="input-block participant-picker-native" multiple size="4" aria-label="Соисполнители"></select>
              </div>
            </div>
            <div>
              <label class="task-modal-field-label" for="btnObserversPicker">Наблюдатели</label>
              <div class="participant-picker-field">
                <button type="button" id="btnObserversPicker" class="participant-picker-trigger input-block" aria-haspopup="dialog" aria-expanded="false">
                  Выбрать наблюдателей
                </button>
                <select id="fObservers" class="input-block participant-picker-native" multiple size="4" aria-label="Наблюдатели"></select>
              </div>
            </div>
          </div>
        </div>
        <div class="participant-picker-popover" id="participantPicker" hidden role="dialog" aria-modal="false" aria-label="Выбор участников">
          <div class="participant-picker-popover-head">
            <span id="participantPickerTitle">Выбор участников</span>
            <button type="button" class="participant-picker-close" id="participantPickerClose" aria-label="Закрыть">×</button>
          </div>
          <div class="participant-picker-search-wrap">
            <div class="participant-picker-searchbox">
              <div class="participant-picker-chips" id="participantPickerChips"></div>
              <input type="text" id="participantPickerSearch" class="participant-picker-search" placeholder="Поиск" autocomplete="off">
            </div>
          </div>
          <div class="participant-picker-list" id="participantPickerList"></div>
          
        </div>

        <div id="modalSectionMore" class="task-modal-extra task-modal-edit-only" hidden>
          <div class="form-row" id="rowRemindRecurrence">
            <div>
              <label class="field-label" for="fRemind">Напоминание</label>
              <input type="date" id="fRemind" class="input-block" title="Дата напоминания">
            </div>
            <div>
              <label class="field-label" for="fRecurrence">Повтор после «Выполнена»</label>
              <select id="fRecurrence" class="input-block">
                <option value="none">Нет</option>
                <option value="weekly">Еженедельно</option>
                <option value="monthly">Ежемесячно</option>
              </select>
            </div>
          </div>
          <label class="field-label" for="fTransitionNote">Примечание к году работы организации (при переводе в «На проверке»)</label>
          <input type="text" id="fTransitionNote" class="input-block" maxlength="500" placeholder="Кратко: что сделано, что на проверку">
        </div>

        <div id="modalSectionLinks" class="task-modal-extra task-modal-edit-only" hidden>
          <h3 class="subtasks-head">Связи</h3>
          <div class="links-row">
            <input type="number" id="linkTaskId" class="input-inline" min="1" placeholder="ID задачи">
            <select id="linkType" class="input-inline">
              <option value="blocks">Блокирует</option>
              <option value="duplicates">Дублирует</option>
              <option value="relates">Связана с</option>
            </select>
            <button type="button" class="btn btn-secondary" id="btnAddLink">Добавить</button>
          </div>
          <div id="linkList" class="link-list"></div>
        </div>

        <div id="modalSectionAttach" class="task-modal-extra task-modal-edit-only" hidden>
          <h3 class="subtasks-head">Вложения</h3>
          <div class="attach-row">
            <div class="bx-file-field bx-file-field--grow">
              <input type="file" id="attachFile" class="bx-file-field__native" aria-label="Файл для вложения">
              <div class="bx-file-field__face" aria-hidden="true">
                <span class="bx-file-field__btn">Выбрать файл</span>
                <span class="bx-file-field__name" data-bx-file-name data-bx-file-placeholder="Файл не выбран">Файл не выбран</span>
              </div>
            </div>
            <button type="button" class="btn btn-secondary" id="btnUploadAttach">Загрузить</button>
          </div>
          <ul class="attach-list" id="attachList"></ul>
        </div>

        <div id="modalSectionSubtasks" class="task-modal-extra task-modal-edit-only" hidden>
          <h3 class="subtasks-head">Чек-листы</h3>
          <div class="subtask-template-row">
            <select id="checklistTemplate" class="input-inline"><option value="">Шаблон чек-листа…</option></select>
            <select id="checklistTemplateMode" class="input-inline" aria-label="Режим вставки шаблона">
              <option value="append" selected>Добавить к текущему</option>
              <option value="replace">Заменить текущий</option>
            </select>
            <button type="button" class="btn btn-secondary" id="btnApplyTemplate">Вставить из шаблона</button>
            <div class="checklist-template-menu-wrap">
              <button type="button" class="checklist-template-menu-btn" id="btnChecklistTemplateMenu" aria-haspopup="true" aria-expanded="false" aria-label="Управление шаблонами">
                <span aria-hidden="true">•••</span>
              </button>
              <div class="checklist-template-menu" id="checklistTemplateMenu" hidden role="menu" aria-label="Действия с шаблонами">
                <button type="button" class="checklist-template-menu-item" id="btnSaveChecklistTemplate" role="menuitem">Сохранить как шаблон</button>
                <button type="button" class="checklist-template-menu-item" id="btnRenameChecklistTemplate" role="menuitem">Переименовать</button>
                <button type="button" class="checklist-template-menu-item is-danger" id="btnDeleteChecklistTemplate" role="menuitem">Удалить шаблон</button>
              </div>
            </div>
          </div>
          <p class="muted checklist-template-preview" id="checklistTemplatePreview">Выберите шаблон для предпросмотра.</p>
          <div class="checklist-template-dialog" id="checklistTemplateDialog" hidden role="dialog" aria-modal="false" aria-labelledby="checklistTemplateDialogTitle" tabindex="-1">
            <div class="checklist-template-dialog__title" id="checklistTemplateDialogTitle">Шаблон чек-листа</div>
            <p class="checklist-template-dialog__text" id="checklistTemplateDialogText"></p>
            <input type="text" class="input-block checklist-template-dialog__input" id="checklistTemplateDialogInput" maxlength="120" placeholder="Название шаблона">
            <div class="checklist-template-dialog__actions">
              <button type="button" class="btn btn-secondary" id="checklistTemplateDialogCancel">Отмена</button>
              <button type="button" class="btn btn-primary" id="checklistTemplateDialogSubmit">Сохранить</button>
            </div>
          </div>
          <div class="subtask-add">
            <input type="text" id="newSubtaskTitle" placeholder="Новый пункт чек-листа…" maxlength="500">
            <button type="button" class="btn btn-secondary" id="btnAddSubtask">Добавить</button>
          </div>
          <ul class="subtask-list" id="subtaskList"></ul>
        </div>

        <div class="task-modal-footer task-modal-footer-create">
          <button type="button" class="btn btn-primary" id="btnModalCreateTask">Создать задачу</button>
          <button type="button" class="btn btn-secondary" id="btnModalCreateCancel">Отмена</button>
        </div>
        <div class="task-modal-footer task-modal-footer-edit drawer-actions task-modal-edit-only">
          <button type="button" class="btn btn-secondary" id="btnSaveTemplate">Сохранить как шаблон</button>
          <button type="button" class="btn btn-danger-outline" id="btnDeleteTask">Удалить</button>
          <button type="button" class="btn btn-primary" id="btnSaveTask">Сохранить</button>
        </div>
      </div>

      <aside class="task-modal-right">
        <div class="task-modal-chat-head">
          <div class="task-modal-chat-head-text">
            <h3 id="taskChatHeading" class="task-modal-chat-title">Чат задачи</h3>
            <p class="task-modal-chat-sub muted" id="taskModalSubtitle">Чат и журнал событий</p>
          </div>
          <button type="button" class="task-modal-close-x" id="taskSplitModalCloseX" aria-label="Закрыть">×</button>
        </div>

        <div id="taskModalEditChat" class="task-modal-edit-chat">
          <div class="task-modal-chat-scroll" id="taskModalChatScroll">
            <button type="button" class="btn btn-secondary btn-block task-chat-load-older" id="btnLoadOlderComments" hidden>
              Ранние сообщения…
            </button>
            <div id="commentList" class="comment-list task-chat-feed" role="log" aria-live="polite"></div>
          </div>
          <div class="task-modal-chat-compose task-modal-chat-compose-edit">
            <div class="task-modal-chat-input-shell">
              <div class="task-modal-chat-mention-hit" title="Упоминание участника">
                <span class="task-modal-chat-mention-icon" aria-hidden="true">@</span>
                <select id="mentionQuickPick" class="bx-select task-modal-mention-pick" aria-label="Вставить упоминание участника">
                  <option value="">Упомянуть участника…</option>
                </select>
              </div>
              <textarea id="newCommentBody" class="input-block task-modal-comment-input" rows="1" maxlength="16000" placeholder="Нажмите @ или + чтобы упомянуть человека, чат или AI"></textarea>
              <button type="button" class="btn btn-primary" id="btnPostComment" aria-label="Отправить комментарий"></button>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</div>

<div id="appInlineDialog" class="app-inline-dialog-shell" hidden aria-hidden="true">
  <div class="app-inline-dialog-backdrop" aria-hidden="true"></div>
  <div
    class="checklist-template-dialog app-inline-dialog-card"
    role="dialog"
    aria-modal="true"
    aria-labelledby="appInlineDialogTitle"
    tabindex="-1"
    id="appInlineDialogCard"
  >
    <div class="checklist-template-dialog__title" id="appInlineDialogTitle"></div>
    <p class="checklist-template-dialog__text" id="appInlineDialogText"></p>
    <input type="text" class="input-block checklist-template-dialog__input" id="appInlineDialogInput" maxlength="16000" autocomplete="off" />
    <div class="checklist-template-dialog__actions">
      <button type="button" class="btn btn-secondary" id="appInlineDialogCancel">Отмена</button>
      <button type="button" class="btn btn-primary" id="appInlineDialogConfirm">OK</button>
    </div>
  </div>
</div>

<script src="<?= htmlspecialchars(planner_url('/assets/js/tasks/tasks-constants.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
<?php
$__tasksJsDir = __DIR__ . '/assets/js/tasks/';
foreach (
    [
        'tasks-shell-start.js',
        'tasks-filters.js',
        'tasks-list.js',
        'tasks-my-plan-stages-ui.js',
        'tasks-modal.js',
        'tasks-chat.js',
        'tasks-shell-end.js',
    ] as $__tasksPart
) {
    readfile($__tasksJsDir . $__tasksPart);
}
?>
</script>
<?php layout_end(); ?>
