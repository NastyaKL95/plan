<?php
declare(strict_types=1);

/**
 * @param array $user from require_login()
 * @param string $active 'dashboard'|'tasks'|'department_tasks'|'tags'|'automation'|'plan_year'|'projects'|'departments'|'reports'|'users'|'max_dashboard'
 */
function layout_start(array $user, string $title, string $active = 'dashboard'): void
{
    $rootJson = json_encode(planner_app_root(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $userIdJson = json_encode((int) $user['id'], JSON_THROW_ON_ERROR);
    $cfgName = trim((string) app_config()['app']['name']);
    $app = htmlspecialchars($cfgName !== '' ? $cfgName : 'Planner', ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
    $role = htmlspecialchars(role_label($user['role']), ENT_QUOTES, 'UTF-8');
    $org = htmlspecialchars($user['organization_name'], ENT_QUOTES, 'UTF-8');
    $orgWorkYear = isset($user['organization_work_year']) ? (int) $user['organization_work_year'] : (int) date('Y');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $url = static function (string $path): string {
        return htmlspecialchars(planner_url($path), ENT_QUOTES, 'UTF-8');
    };
    $mayAdminNav = function_exists('user_may_manage_users') && user_may_manage_users($user);
    $mayPlanYearNav = function_exists('user_may_access_org_plan_console') && user_may_access_org_plan_console($user);
    $mayDeptTasksNav = function_exists('user_may_view_department_tasks_page') && user_may_view_department_tasks_page($user);
    $logoLetter = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($cfgName !== '' ? $cfgName : 'P', 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($cfgName !== '' ? $cfgName : 'P', 0, 1));
    $logoLetter = htmlspecialchars($logoLetter, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $t ?> — <?= $app ?></title>
  <?= planner_favicon_head_markup() ?>
  <script>window.__PLANNER_ROOT__=<?= $rootJson ?>;window.__PLANNER_USER_ID__=<?= $userIdJson ?>;window.__PLANNER_CSRF__=<?= json_encode(planner_csrf_token(), JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <link rel="stylesheet" href="<?= htmlspecialchars(planner_url('/assets/css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
  <script src="<?= htmlspecialchars(planner_url('/assets/js/core.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(planner_url('/assets/js/file_fields.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(planner_url('/assets/js/bx-custom-select.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="app-body bx-app">
<div class="app-shell bx-shell">
  <aside class="bx-iconbar" aria-label="Разделы">
    <div class="bx-ibar-logo" title="<?= $app ?>"><?= $logoLetter ?></div>
    <a class="bx-ibar-item<?= $active === 'dashboard' ? ' is-active' : '' ?>" href="<?= $url('/index.php') ?>" title="Обзор">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>
    </a>
    <a class="bx-ibar-item<?= $active === 'tasks' ? ' is-active' : '' ?>" href="<?= $url('/tasks.php') ?>" title="Задачи">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    </a>
    <?php if ($mayDeptTasksNav): ?>
    <a class="bx-ibar-item<?= $active === 'department_tasks' ? ' is-active' : '' ?>" href="<?= $url('/department_tasks.php') ?>" title="Задачи подразделения">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h14"/></svg>
    </a>
    <?php endif; ?>
    <a class="bx-ibar-item<?= $active === 'tags' ? ' is-active' : '' ?>" href="<?= $url('/tags.php') ?>" title="Метки">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><circle cx="7" cy="7" r="1.5" fill="currentColor" stroke="none"/></svg>
    </a>
    <a class="bx-ibar-item<?= $active === 'automation' ? ' is-active' : '' ?>" href="<?= $url('/automation.php') ?>" title="Автоматизация">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
    </a>
    <?php if ($mayPlanYearNav): ?>
    <a class="bx-ibar-item<?= $active === 'plan_year' ? ' is-active' : '' ?>" href="<?= $url('/plan_year.php') ?>" title="План года">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    </a>
    <?php endif; ?>
    <a class="bx-ibar-item<?= $active === 'departments' ? ' is-active' : '' ?>" href="<?= $url('/departments.php') ?>" title="Подразделения">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 22V4a2 2 0 012-2h8a2 2 0 012 2v18"/><path d="M6 12h12"/><path d="M6 16h12"/><path d="M10 6h4"/></svg>
    </a>
    <?php if ($mayAdminNav): ?>
    <a class="bx-ibar-item<?= $active === 'users' ? ' is-active' : '' ?>" href="<?= $url('/users.php') ?>" title="Команда">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
    </a>
    <a class="bx-ibar-item<?= $active === 'max_dashboard' ? ' is-active' : '' ?>" href="<?= $url('/max_dashboard.php') ?>" title="MAX мониторинг">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 2 4-5"/></svg>
    </a>
    <?php endif; ?>
    <div class="bx-ibar-spacer"></div>
    <a class="bx-ibar-item bx-ibar-item--mute" href="<?= $url('/logout.php') ?>" title="Выйти">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
    </a>
  </aside>
  <div class="bx-stage">
    <header class="bx-topnav">
      <button type="button" class="bx-burger" aria-label="Меню" disabled>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <nav class="bx-topnav-links" aria-label="Разделы продукта">
        <span class="bx-org-phase" id="bxOrgPhase" title="Год планирования организации">
          <span class="bx-org-phase-year" id="bxOrgYearBadge"><?= (int) $orgWorkYear ?></span>
        </span>
        <a class="bx-toplink<?= $active === 'dashboard' ? ' is-active' : '' ?>" href="<?= $url('/index.php') ?>">Обзор</a>
        <a class="bx-toplink<?= $active === 'tasks' ? ' is-active' : '' ?>" href="<?= $url('/tasks.php') ?>">Задачи</a>
        <a class="bx-toplink<?= $active === 'projects' ? ' is-active' : '' ?>" href="<?= $url('/projects.php') ?>">Проекты</a>

        <a class="bx-toplink<?= $active === 'reports' ? ' is-active' : '' ?>" href="<?= $url('/reports.php') ?>">Отчёты</a>
      </nav>
      <div class="bx-topnav-search">
        <svg class="bx-search-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="search" id="globalSearch" placeholder="Поиск задач, проектов, людей…" autocomplete="off" aria-label="Поиск по задачам и проектам">
      </div>
      <div class="bx-topnav-actions">
        <a class="bx-topnav-notif" href="<?= htmlspecialchars(planner_url('/notifications.php'), ENT_QUOTES, 'UTF-8') ?>" title="Уведомления" aria-label="Уведомления">
          <img class="bx-topnav-notif-icon" src="<?= htmlspecialchars(planner_url('/assets/img/notification-bell.png'), ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true">
          <span class="bx-rail-badge" id="bxNotifBadge">0</span>
        </a>
      </div>
      <time class="bx-clock" id="bxClock" datetime="">—:—</time>
      <div class="bx-user-block">
        <div class="bx-user-menu" id="bxUserMenu">
          <button type="button" class="bx-user-profile-btn" id="btnProfileMenuTop" aria-label="Меню профиля" aria-haspopup="true" aria-expanded="false" title="<?= $name ?>">
            <span class="user-avatar bx-avatar-notify" id="topUserAvatar" aria-hidden="true"><?php
              $n = (string) $user['name'];
              $initial = function_exists('mb_substr')
                  ? mb_strtoupper(mb_substr($n, 0, 1, 'UTF-8'), 'UTF-8')
                  : strtoupper(substr($n, 0, 1));
              echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8');
          ?></span>
          </button>
          <div class="bx-user-menu-panel" id="bxUserMenuPanel" hidden role="menu" aria-label="Меню пользователя">
            <div class="bx-user-menu-head">
              <div class="bx-user-menu-name"><?= $name ?></div>
              <div class="bx-user-menu-meta"><?= $role ?> · <?= $org ?></div>
            </div>
            <a class="bx-user-menu-item" href="<?= $url('/profile.php') ?>" role="menuitem">
              <svg class="bx-user-menu-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Профиль
            </a>
            <button type="button" class="bx-user-menu-item" id="btnProfileOpenTop" role="menuitem">
              <svg class="bx-user-menu-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
              Редактировать профиль
            </button>
            <div class="bx-user-menu-sep"></div>
            <a class="bx-user-menu-item bx-user-menu-item--danger" href="<?= $url('/logout.php') ?>" role="menuitem">
              <svg class="bx-user-menu-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
              Выйти
            </a>
          </div>
        </div>
      </div>
    </header>
    <main class="content-wrap bx-content">
<?php
}

function layout_end(): void
{
    ?>
    </main>
  </div>
</div>
<dialog class="modal" id="profileModal">
  <form method="dialog" class="modal-form" id="profileForm">
    <h2 class="modal-title">Мой профиль</h2>
    <label class="field-label" for="profileName">Имя</label>
    <input type="text" id="profileName" class="input-block" maxlength="120" required>
    <label class="field-label" for="profileEmail">Email</label>
    <input type="email" id="profileEmail" class="input-block" readonly>
    <p class="muted profile-meta" id="profileMeta"></p>
    <span class="field-label" id="profileDeptRolesLabel">Подразделения и роли</span>
    <div id="profileDeptRoles" role="group" aria-labelledby="profileDeptRolesLabel"></div>
    <button type="button" class="btn btn-secondary btn-sm" id="btnAddProfileDeptRole">Добавить подразделение</button>
    <label class="field-label" for="profileNewPassword">Новый пароль</label>
    <input type="password" id="profileNewPassword" class="input-block" minlength="6" autocomplete="new-password" placeholder="Оставьте пустым, если не меняете">
    <label class="field-label" for="profileNewPassword2">Повторите новый пароль</label>
    <input type="password" id="profileNewPassword2" class="input-block" minlength="6" autocomplete="new-password">
    <hr class="profile-org-divider">
    <h3 class="modal-subtitle">Уведомления в MAX</h3>
    <p class="muted profile-org-year-hint">
      1) Откройте чат-бота:
      <a href="https://max.ru/irozabkrai75_bot" target="_blank" rel="noopener noreferrer">@irozabkrai75_bot</a>,
      начните диалог и скопируйте ID чата. 2) Вставьте ID в поле ниже — в этот чат будут приходить уведомления Planner.
    </p>
    <label class="field-label" for="profileMaxChatId">ID чата MAX</label>
    <input type="text" id="profileMaxChatId" class="input-block" inputmode="numeric" maxlength="32" placeholder="Например: 284351356">
    <div id="profileOrgYearBlock" class="profile-org-year-block" hidden>
      <hr class="profile-org-divider">
      <h3 class="modal-subtitle">Год работы организации</h3>
      <p class="muted profile-org-year-hint">Год работы организации в шапке: в задачах и проектах в интерфейсе виден только этот год плана. Смена года не удаляет старые данные — они остаются в базе, но скрываются из списков (карточка по ссылке открывается).</p>
      <p class="profile-org-year-view">Сейчас: <strong id="profileOrgYearDisplay">—</strong> <span class="muted">год</span></p>
      <div id="profileOrgYearEditRow" hidden>
        <label class="field-label" for="profileOrgYear">Сменить год планирования</label>
        <div class="profile-org-year-actions">
          <input type="number" id="profileOrgYear" class="input-block" min="1990" max="2100" step="1" inputmode="numeric">
          <button type="button" class="btn btn-primary" id="profileOrgYearSave">Сохранить год</button>
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" id="profileCancel">Отмена</button>
      <button type="submit" class="btn btn-primary" id="profileSave">Сохранить</button>
    </div>
  </form>
</dialog>
<script src="<?= htmlspecialchars(planner_url('/assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(planner_url('/assets/js/notifications_badge.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(planner_url('/assets/js/profile_menu.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
<?php
}

function role_label(string $role): string
{
    return org_role_label($role);
}
