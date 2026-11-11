<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (current_user()) {
    header('Location: ' . planner_url('/index.php'), true, 302);
    exit;
}

$mode = isset($_GET['mode']) && $_GET['mode'] === 'register' ? 'register' : 'login';
$registerSuccess = isset($_GET['sent']) && (string) $_GET['sent'] === '1';
if ($registerSuccess) {
    $mode = 'register';
}

$st = db()->query('SELECT id FROM organizations ORDER BY id LIMIT 1');
$orgRow = $st->fetch(PDO::FETCH_ASSOC);
$orgId = $orgRow ? (int) $orgRow['id'] : 0;
$orgName = '';
if ($orgId > 0) {
    $st = db()->prepare('SELECT name FROM organizations WHERE id = ? LIMIT 1');
    $st->execute([$orgId]);
    $orgName = (string) ($st->fetchColumn() ?: '');
}

$st = db()->query('SELECT COUNT(*) FROM users');
$userCount = (int) $st->fetchColumn();
$isFirstUser = $userCount === 0;
$regPublicOpen = planner_registration_public_open();
$regFlow = planner_registration_schema_ready() && !$isFirstUser && $regPublicOpen;

if ($mode === 'register' && !$regPublicOpen) {
    header('Location: ' . planner_url('/auth.php?mode=login'), true, 302);
    exit;
}

$oauthCfg = app_config()['oauth']['yandex'] ?? [];
$yandexEnabled = !empty($oauthCfg['enabled'])
    && trim((string) ($oauthCfg['client_id'] ?? '')) !== ''
    && trim((string) ($oauthCfg['client_secret'] ?? '')) !== ''
    && trim((string) ($oauthCfg['redirect_uri'] ?? '')) !== '';

$loginError = '';
$registerError = '';
$registerSuccessMessage = '';
$loginNotice = '';

if (!empty($_SESSION['login_notice'])) {
    $loginNotice = (string) $_SESSION['login_notice'];
    unset($_SESSION['login_notice']);
}

if (isset($_GET['error'])) {
    $oauthError = (string) $_GET['error'];
    $map = [
        'yandex_not_configured' => 'Вход через Яндекс пока не настроен',
        'yandex_bad_state' => 'Сессия авторизации истекла, попробуйте снова',
        'yandex_access_denied' => 'Авторизация через Яндекс отменена',
        'yandex_no_code' => 'Яндекс не вернул код авторизации',
        'yandex_token_http' => 'Не удалось получить токен Яндекс',
        'yandex_token_json' => 'Некорректный ответ токена Яндекс',
        'yandex_token_missing' => 'Яндекс не вернул access_token',
        'yandex_profile_http' => 'Не удалось получить профиль Яндекс',
        'yandex_profile_json' => 'Некорректный ответ профиля Яндекс',
        'yandex_subject_missing' => 'В профиле Яндекс отсутствует идентификатор пользователя',
        'yandex_schema_missing' => 'База данных не готова для OAuth. Выполните SQL-миграцию.',
        'yandex_oauth_conflict' => 'Этот email уже связан с другим способом входа',
        'organization_missing' => 'В системе не найдена организация для автосоздания аккаунта',
        'yandex_login_failed' => 'Не удалось выполнить вход через Яндекс',
        'yandex_email_domain_not_allowed' => 'Неправильная почта',
        'yandex_registration_closed' => 'Регистрация закрыта. Обратитесь к администратору.',
        'yandex_pending_approval' => 'Аккаунт создан. Дождитесь назначения роли и подразделения администратором.',
    ];
    if (isset($map[$oauthError])) {
        $loginError = $map[$oauthError];
        $mode = 'login';
    }
}

$loginEmail = trim((string) ($_POST['login_email'] ?? ''));
$registerName = trim((string) ($_POST['register_name'] ?? ''));
$registerEmail = trim((string) ($_POST['register_email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['auth_action'] ?? '');
    if ($action === 'resend_verify') {
        $mode = 'register';
        $email = trim((string) ($_POST['resend_email'] ?? ''));
        $registerEmail = $email;
        if ($email === '') {
            $registerError = 'Укажите email';
        } elseif (!planner_registration_schema_ready()) {
            $registerError = 'Повторная отправка письма недоступна';
        } else {
            $st = db()->prepare(
                "SELECT id FROM users WHERE email = ? AND account_status = 'pending_email' LIMIT 1"
            );
            $st->execute([$email]);
            $uid = (int) ($st->fetchColumn() ?: 0);
            if ($uid > 0) {
                planner_registration_resend_verification($uid);
            }
            $registerSuccessMessage = 'Если аккаунт ожидает подтверждения email, письмо отправлено повторно.';
        }
    } elseif ($action === 'login') {
        $mode = 'login';
        $email = trim((string) ($_POST['login_email'] ?? ''));
        $password = (string) ($_POST['login_password'] ?? '');
        if ($email === '' || $password === '') {
            $loginError = 'Заполните email и пароль';
        } else {
            $cols = 'id, password_hash';
            if (planner_table_has_column('users', 'account_status')) {
                $cols .= ', account_status';
            }
            $st = db()->prepare("SELECT {$cols} FROM users WHERE email = ? LIMIT 1");
            $st->execute([$email]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($password, (string) $row['password_hash'])) {
                $loginError = 'Неверный email или пароль';
            } else {
                $full = db()->prepare(
                    'SELECT u.id, u.organization_id, u.email, u.name, u.role, u.department_id, o.name AS organization_name'
                    . (planner_table_has_column('users', 'account_status') ? ', u.account_status' : '')
                    . (planner_table_has_column('users', 'email_verified_at') ? ', u.email_verified_at' : '')
                    . ' FROM users u JOIN organizations o ON o.id = u.organization_id WHERE u.id = ? LIMIT 1'
                );
                $full->execute([(int) $row['id']]);
                $userRow = $full->fetch(PDO::FETCH_ASSOC) ?: $row;
                $block = planner_login_block_message($userRow);
                if ($block !== null) {
                    $loginError = $block;
                } else {
                    if (function_exists('session_regenerate_id')) {
                        @session_regenerate_id(true);
                    }
                    $_SESSION['user_id'] = (int) $row['id'];
                    planner_csrf_rotate();
                    header('Location: ' . planner_url('/index.php'), true, 302);
                    exit;
                }
            }
        }
    } elseif ($action === 'register') {
        $mode = 'register';
        if (!$regPublicOpen) {
            $registerError = 'Регистрация закрыта. Обратитесь к администратору организации.';
        } elseif ($registerError === '') {
            $name = trim((string) ($_POST['register_name'] ?? ''));
            $email = trim((string) ($_POST['register_email'] ?? ''));
            $password = (string) ($_POST['register_password'] ?? '');

            if ($name === '' || $email === '' || strlen($password) < 6) {
                $registerError = 'Заполните имя, email и пароль (минимум 6 символов)';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !planner_registration_email_allowed($email)) {
                $registerError = 'Неправильная почта';
            } else {
                $st = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $st->execute([$email]);
                if ($st->fetch()) {
                    $registerError = 'Пользователь с таким email уже существует';
                } else {
                    try {
                        db()->beginTransaction();
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $userHasDeptCol = planner_table_has_column('users', 'department_id');
                        $hasRegCols = planner_registration_schema_ready();

                        if ($isFirstUser) {
                            if ($userHasDeptCol) {
                                if ($hasRegCols) {
                                    $st = db()->prepare(
                                        'INSERT INTO users (organization_id, email, password_hash, email_verified_at, account_status, name, role, department_id)
                                         VALUES (?,?,?,NOW(),\'active\',?,?,NULL)'
                                    );
                                    $st->execute([$orgId, $email, $hash, $name, 'admin']);
                                } else {
                                    $st = db()->prepare(
                                        'INSERT INTO users (organization_id, email, password_hash, name, role, department_id)
                                         VALUES (?,?,?,?,?,NULL)'
                                    );
                                    $st->execute([$orgId, $email, $hash, $name, 'admin']);
                                }
                            } else {
                                $st = db()->prepare(
                                    'INSERT INTO users (organization_id, email, password_hash, name, role) VALUES (?,?,?,?,?)'
                                );
                                $st->execute([$orgId, $email, $hash, $name, 'admin']);
                            }
                            $userId = (int) db()->lastInsertId();
                            log_activity(
                                $orgId,
                                $userId,
                                'register',
                                'user',
                                $userId,
                                'Зарегистрирован первый пользователь (администратор)'
                            );
                            db()->commit();
                            if (function_exists('session_regenerate_id')) {
                                @session_regenerate_id(true);
                            }
                            $_SESSION['user_id'] = $userId;
                            planner_csrf_rotate();
                            header('Location: ' . planner_url('/index.php'), true, 302);
                            exit;
                        }

                        if ($hasRegCols) {
                            if (!planner_mail_enabled()) {
                                throw new RuntimeException('mail_disabled');
                            }
                            if ($userHasDeptCol) {
                                $st = db()->prepare(
                                    'INSERT INTO users (organization_id, email, password_hash, account_status, name, role, department_id)
                                     VALUES (?,?,?,?,?,?,NULL)'
                                );
                                $st->execute([$orgId, $email, $hash, 'pending_email', $name, 'member']);
                            } else {
                                throw new RuntimeException('no_department_column');
                            }
                            $userId = (int) db()->lastInsertId();
                            log_activity($orgId, $userId, 'register', 'user', $userId, 'Заявка на регистрацию (ожидает email)');
                            db()->commit();
                            if (!planner_registration_send_verification_email($orgId, $userId, $email, $name)) {
                                $registerError = 'Не удалось отправить письмо с подтверждением. Проверьте настройки почты.';
                            } else {
                                header('Location: ' . planner_url('/auth.php?mode=register&sent=1'), true, 302);
                                exit;
                            }
                        } else {
                            if (!$userHasDeptCol) {
                                throw new RuntimeException('no_department_column');
                            }
                            $st = db()->prepare(
                                'INSERT INTO users (organization_id, email, password_hash, name, role, department_id)
                                 VALUES (?,?,?,?,?,NULL)'
                            );
                            $st->execute([$orgId, $email, $hash, $name, 'member']);
                            $userId = (int) db()->lastInsertId();
                            log_activity($orgId, $userId, 'register', 'user', $userId, 'Зарегистрирован сотрудник');
                            db()->commit();
                            if (function_exists('session_regenerate_id')) {
                                @session_regenerate_id(true);
                            }
                            $_SESSION['user_id'] = $userId;
                            planner_csrf_rotate();
                            header('Location: ' . planner_url('/index.php'), true, 302);
                            exit;
                        }
                    } catch (Throwable $e) {
                        if (db()->inTransaction()) {
                            db()->rollBack();
                        }
                        $msg = $e->getMessage();
                        $registerError = match ($msg) {
                            'no_department_column' => 'Добавьте в таблицу users колонку department_id (см. sql/upgrade_iro.sql)',
                            'mail_disabled' => 'Регистрация временно недоступна: не настроена отправка почты.',
                            default => 'Не удалось зарегистрироваться. Проверьте настройки БД.',
                        };
                    }
                }
            }
        }
    }
}

if ($registerSuccess) {
    $registerSuccessMessage = 'На указанный email отправлена ссылка для подтверждения. После подтверждения администратор назначит вам роль и подразделение.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $mode === 'register' ? 'Регистрация' : 'Авторизация' ?> — <?= htmlspecialchars(app_config()['app']['name'], ENT_QUOTES, 'UTF-8') ?></title>
  <?= planner_favicon_head_markup() ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(planner_url('/assets/css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
  <script src="<?= htmlspecialchars(planner_url('/assets/js/bx-custom-select.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="auth-page" data-auth-mode="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
  <div class="auth-shell">
    <section class="auth-panel auth-panel--form">
      <div class="auth-forms">
        <div class="auth-form-screen<?= $mode === 'login' ? ' is-active' : '' ?>" data-mode="login">
          <div class="auth-brand auth-brand--mini">Вектор</div>
          <h1 class="auth-title">Авторизация</h1>
          <?php if ($loginNotice !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($loginNotice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
          <?php if ($loginError !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
          <form method="post" class="auth-form">
            <input type="hidden" name="auth_action" value="login">
            <div class="auth-form-row">
              <label for="login_email">Email</label>
              <input type="email" id="login_email" name="login_email" required autocomplete="username" value="<?= htmlspecialchars($loginEmail, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="auth-form-row">
              <label for="login_password">Пароль</label>
              <input type="password" id="login_password" name="login_password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block auth-submit">Войти в систему</button>
            <?php if ($yandexEnabled): ?>
              <a class="btn btn-yandex btn-block" href="<?= htmlspecialchars(planner_url('/auth/yandex_start.php'), ENT_QUOTES, 'UTF-8') ?>">Продолжить через Яндекс</a>
              <?php if ($regPublicOpen): ?>
                <p class="auth-hint">При первом входе через Яндекс аккаунт будет создан автоматически.</p>
              <?php endif; ?>
            <?php endif; ?>
          </form>
          <?php if ($regPublicOpen): ?>
          <p class="auth-footer">Нет аккаунта? <a class="auth-switch-link" href="<?= htmlspecialchars(planner_url('/auth.php?mode=register'), ENT_QUOTES, 'UTF-8') ?>" data-auth-switch="register">Зарегистрироваться</a></p>
          <?php endif; ?>
        </div>

        <?php if ($regPublicOpen): ?>
        <div class="auth-form-screen<?= $mode === 'register' ? ' is-active' : '' ?>" data-mode="register">
          <div class="auth-brand auth-brand--mini">Вектор</div>
          <h1 class="auth-title">Создать аккаунт</h1>
          <?php if ($registerSuccessMessage !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($registerSuccessMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if ($registerError !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($registerError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <form method="post" class="auth-form">
              <input type="hidden" name="auth_action" value="register">
              <div class="auth-form-row">
                <label for="register_name">Ваше имя</label>
                <input type="text" id="register_name" name="register_name" required maxlength="120" value="<?= htmlspecialchars($registerName, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="auth-form-row">
                <label for="register_email">Email</label>
                <input type="email" id="register_email" name="register_email" required autocomplete="username" pattern="[^@\s]+@iro75\.ru" title="Неправильная почта" value="<?= htmlspecialchars($registerEmail, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="auth-form-row">
                <label for="register_password">Пароль</label>
                <input type="password" id="register_password" name="register_password" required minlength="6" autocomplete="new-password">
              </div>
              <?php if ($isFirstUser): ?>
                <p class="auth-hint">Вы создаёте первый аккаунт — он получит роль <strong>администратор</strong>.</p>
              <?php elseif ($regFlow): ?>
                <p class="auth-hint">На email придёт ссылка для подтверждения. Роль и подразделение назначит администратор после подтверждения.</p>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary btn-block auth-submit">Создать аккаунт</button>
            </form>
          <p class="auth-footer">Уже есть аккаунт? <a class="auth-switch-link" href="<?= htmlspecialchars(planner_url('/auth.php?mode=login'), ENT_QUOTES, 'UTF-8') ?>" data-auth-switch="login">Войти</a></p>
          <?php if ($regFlow): ?>
            <form method="post" class="auth-form auth-form--resend">
              <input type="hidden" name="auth_action" value="resend_verify">
              <p class="auth-hint">Не пришло письмо с подтверждением?</p>
              <div class="auth-form-row">
                <label for="resend_email">Email</label>
                <input type="email" id="resend_email" name="resend_email" value="<?= htmlspecialchars($registerEmail !== '' ? $registerEmail : $loginEmail, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <button type="submit" class="btn btn-secondary btn-block">Отправить письмо повторно</button>
            </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <aside class="auth-panel auth-panel--visual" aria-hidden="true">
      <div class="auth-visual-chip auth-visual-chip--left">Забайкальский край</div>
      <div class="auth-visual-chip auth-visual-chip--right">Институт развития образования</div>
      <div class="auth-visual-chip auth-visual-chip--bottom">Проекты</div>
    </aside>
  </div>

  <script>
    (function () {
      var body = document.body;
      var shell = document.querySelector('.auth-shell');
      var screens = Array.prototype.slice.call(document.querySelectorAll('.auth-form-screen'));
      if (!body || !shell || !screens.length) return;

      var currentMode = body.getAttribute('data-auth-mode') === 'register' ? 'register' : 'login';

      function setActiveScreen(mode) {
        screens.forEach(function (el) {
          el.classList.toggle('is-active', el.getAttribute('data-mode') === mode);
        });
        if (typeof window.bxCustomSelectSyncAll === 'function') window.bxCustomSelectSyncAll();
      }

      function setMode(mode) {
        currentMode = mode === 'register' ? 'register' : 'login';
        body.setAttribute('data-auth-mode', currentMode);
        setActiveScreen(currentMode);
      }

      function bindLinks() {
        document.querySelectorAll('.auth-switch-link[data-auth-switch]').forEach(function (a) {
          a.addEventListener('click', function (ev) {
            ev.preventDefault();
            var target = a.getAttribute('data-auth-switch') === 'register' ? 'register' : 'login';
            if (target === currentMode) return;
            setMode(target);
            var url = target === 'register' ? '<?= htmlspecialchars(planner_url('/auth.php?mode=register'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(planner_url('/auth.php?mode=login'), ENT_QUOTES, 'UTF-8') ?>';
            window.history.pushState({ authMode: target }, '', url);
          });
        });
      }

      window.addEventListener('popstate', function () {
        var sp = new URLSearchParams(window.location.search);
        setMode(sp.get('mode') === 'register' ? 'register' : 'login');
      });

      setMode(currentMode);
      bindLinks();
    })();
  </script>
</body>
</html>
