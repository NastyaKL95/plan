<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$user = require_login();
if (!user_may_manage_users($user)) {
    http_response_code(403);
    echo 'Доступ только для администратора организации.';
    exit;
}

layout_start($user, 'Команда', 'users');
?>
<nav class="breadcrumbs" aria-label="Навигация">
  <a href="<?= htmlspecialchars(planner_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a><span class="bc-sep">/</span><span>Команда</span>
</nav>

<div class="page-head">
  <h1 class="page-title">Пользователи организации</h1>
  </div>

<div class="panel users-filter-panel notranslate" translate="no">
  <div class="users-filters">
    <input type="search" id="usersSearch" class="input-inline" placeholder="Поиск по имени, email, подразделению" aria-label="Поиск пользователей">
    <select id="usersRoleFilter" class="bx-select bx-select--inline" aria-label="Фильтр по роли на портале">
      <option value="">Все роли</option>
      <option value="member">Пользователь</option>
      <option value="admin">Администратор</option>
    </select>
    <select id="usersStatusFilter" class="bx-select bx-select--inline" aria-label="Фильтр по статусу">
      <option value="">Все статусы</option>
      <option value="active">Активен</option>
      <option value="blocked">Заблокирован</option>
      <option value="pending_email">Ожидает email</option>
      <option value="pending_approval">Ожидает активации</option>
    </select>
    <select id="usersDeptFilter" class="bx-select bx-select--inline" aria-label="Фильтр по подразделению">
      <option value="">Все подразделения</option>
    </select>
    <button type="button" class="btn btn-secondary btn-sm" id="usersFilterReset">Сбросить</button>
    <span class="muted users-filter-stats" id="usersFilterStats"></span>
  </div>
</div>

<div class="bx-bulk-bar notranslate" id="usersBulkBar" translate="no">
  <label class="visually-hidden" for="usersBulkAction">Массовое действие</label>
  <select id="usersBulkAction" class="bx-select" aria-label="Действие">
    <option value="">Выберите действие</option>
    <option value="block">Заблокировать</option>
    <option value="unblock">Разблокировать</option>
    <option value="delete">Удалить</option>
    <option value="approve" id="usersBulkActionApprove" hidden>Активировать доступ</option>
  </select>
  <button type="button" class="btn btn-primary" id="btnUsersBulkApply">Применить к выбранным</button>
  <span class="muted bx-bulk-count" id="usersBulkSelectedCount">Выбрано: 0</span>
  <span class="muted bx-bulk-preview" id="usersBulkPreview">Отметьте пользователей и выберите действие.</span>
  <span class="muted bx-bulk-progress" id="usersBulkProgress" hidden>Применение...</span>
</div>

<div class="task-table-scroll notranslate" translate="no">
  <table class="task-table" id="usersTable">
    <thead id="usersTableHead"></thead>
    <tbody id="usersTableBody"></tbody>
  </table>
</div>
<div class="users-pager" id="usersPager">
  <div class="users-pager-left">
    <button type="button" class="btn btn-secondary btn-sm" id="usersPagePrev">Назад</button>
    <button type="button" class="btn btn-secondary btn-sm" id="usersPageNext">Вперёд</button>
    <span class="muted" id="usersPageInfo">Страница 1 из 1</span>
  </div>
  <div class="users-pager-right">
    <label class="muted" for="usersPerPage">На странице</label>
    <select id="usersPerPage" class="bx-select bx-select--inline">
      <option value="10">10</option>
      <option value="20" selected>20</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
  </div>
</div>

<dialog class="modal user-edit-modal" id="userEditModal">
  <form method="dialog" class="modal-form" id="userEditForm">
    <h2 class="modal-title">Пользователь</h2>
    <input type="hidden" id="editUserId">
    <label class="field-label" for="editUserName">ФИО</label>
    <input type="text" id="editUserName" class="input-block" required maxlength="120">
    <label class="field-label" for="editUserRole">Роль на портале</label>
    <select id="editUserRole" class="input-block">
      <option value="member">Пользователь</option>
      <option value="admin">Администратор</option>
    </select>
    <p class="auth-hint">Руководитель организации — обзор по всему институту. Руководитель подразделения — задачи своих отделов.</p>
    <p class="user-modal-banner user-modal-banner--info" id="editUserPendingBanner" hidden>Аккаунт ожидает активации: назначьте роль и подразделение, затем нажмите «Активировать доступ».</p>
    <p class="user-modal-banner user-modal-banner--blocked" id="editUserBlockedBanner" hidden>Пользователь заблокирован и не может войти в систему. Его задачи и роли в них сохранены.</p>
    <span class="field-label" id="editUserDeptRolesLabel">Подразделения и роль в них</span>
    <div id="editUserDeptRoles" role="group" aria-labelledby="editUserDeptRolesLabel"></div>
    <button type="button" class="btn btn-secondary btn-sm" id="btnAddUserDeptRole">Добавить подразделение</button>
    <div class="user-modal-actions">
      <div class="user-modal-actions-danger" id="userEditDangerActions" hidden>
        <span class="user-modal-actions-label">Доступ</span>
        <button type="button" class="btn btn-danger-outline btn-sm" id="userEditBlock" hidden>Заблокировать</button>
        <button type="button" class="btn btn-secondary btn-sm" id="userEditUnblock" hidden>Разблокировать</button>
        <button type="button" class="btn btn-danger-outline btn-sm" id="userEditDelete" hidden>Удалить</button>
      </div>
      <div class="user-modal-actions-primary">
        <button type="button" class="btn btn-secondary" id="userEditCancel">Отмена</button>
        <button type="button" class="btn btn-primary" id="userEditApprove" hidden>Активировать доступ</button>
        <button type="submit" class="btn btn-primary" id="userEditSave">Сохранить</button>
      </div>
    </div>
  </form>
</dialog>

<script src="<?= htmlspecialchars(planner_url('/assets/js/users_admin.js?v=20260724c'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php layout_end(); ?>
