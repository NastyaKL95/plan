<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
if (!user_may_view_department_tasks_page($user)) {
    http_response_code(403);
    echo 'Раздел доступен руководителям подразделения и организации.';
    exit;
}

$seesAllDeptTasks = user_sees_all_department_tasks_in_nav($user);
$filterDeptIds = null;
if (!$seesAllDeptTasks) {
    $filterDeptIds = array_values(array_unique(array_merge(
        user_managed_department_ids($user),
        user_administered_department_ids($user)
    )));
    if ($filterDeptIds === []) {
        $filterDeptIds = user_department_membership_ids($user);
    }
}
$deptTasksLead = $seesAllDeptTasks
    ? 'Все задачи проектов организации по подразделениям. Откройте карточку по ссылке в названии.'
    : 'Все задачи проектов ваших подразделений. Откройте карточку по ссылке в названии.';

layout_start($user, 'Задачи подразделения', 'department_tasks');
?>
<script>
window.__PLANNER_DEPT_TASKS_FILTER_IDS__=<?= json_encode($filterDeptIds, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
</script>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Задачи подразделения</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Задачи подразделения</h1>
  <p class="muted page-subline"><?= htmlspecialchars($deptTasksLead, ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="panel dept-tasks-filters">
  <div class="users-filters">
    <input type="search" id="deptTasksSearch" class="input-inline" placeholder="Поиск по названию, проекту, исполнителю" aria-label="Поиск">
    <select id="deptTasksDepartment" class="bx-select bx-select--inline" aria-label="Подразделение">
      <option value="">Все подразделения</option>
    </select>
    <select id="deptTasksStatus" class="bx-select bx-select--inline" aria-label="Статус">
      <option value="">Все статусы</option>
      <option value="new">Новая</option>
      <option value="in_progress">В работе</option>
      <option value="review">На проверке</option>
      <option value="done">Выполнена</option>
      <option value="cancelled">Отменена</option>
    </select>
    <button type="button" class="btn btn-secondary btn-sm" id="deptTasksReset">Сбросить</button>
    <span class="muted" id="deptTasksStats"></span>
  </div>
</div>

<div class="task-table-scroll">
  <table class="task-table" id="deptTasksTable">
    <thead>
      <tr>
        <th>Задача</th>
        <th>Подразделение</th>
        <th>Проект</th>
        <th>Статус</th>
        <th>Исполнитель</th>
        <th>Срок</th>
      </tr>
    </thead>
    <tbody id="deptTasksBody"></tbody>
  </table>
</div>

<div class="bx-tasks-load-more" id="deptTasksLoadMore">
  <p class="muted" id="deptTasksPager"></p>
  <button type="button" class="btn btn-secondary btn-sm" id="deptTasksLoadMoreBtn" hidden>Загрузить ещё</button>
</div>

<script src="<?= htmlspecialchars(planner_url('/assets/js/department_tasks.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php layout_end(); ?>
