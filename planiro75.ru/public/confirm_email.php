<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$title = 'Подтверждение email';
$message = '';
$isError = false;

if ($token === '') {
    $message = 'Ссылка подтверждения не указана.';
    $isError = true;
} elseif (!planner_registration_schema_ready()) {
    $message = 'Функция подтверждения email не настроена. Выполните SQL-миграцию migration_user_registration_v1.sql.';
    $isError = true;
} else {
    try {
        $result = planner_registration_verify_token($token);
    } catch (Throwable $e) {
        $result = ['ok' => false, 'error' => 'server'];
    }
    if (!empty($result['ok'])) {
        $title = 'Email подтверждён';
        $message = 'Спасибо! Адрес email подтверждён. Администратор назначит вам роль и подразделение — после этого вы сможете войти в систему.';
        if (!empty($result['already'])) {
            $message = 'Этот адрес email уже был подтверждён ранее. Дождитесь активации администратором или войдите в систему.';
        }
    } else {
        $isError = true;
        $err = (string) ($result['error'] ?? '');
        $map = [
            'bad_token' => 'Некорректная ссылка подтверждения.',
            'not_found' => 'Ссылка не найдена или уже использована.',
            'expired' => 'Срок действия ссылки истёк. Запросите новое письмо на странице входа.',
            'invalid_state' => 'Аккаунт в неподходящем состоянии для подтверждения.',
            'schema' => 'Сервис подтверждения не настроен.',
            'server' => 'Не удалось подтвердить email. Попробуйте позже.',
        ];
        $message = $map[$err] ?? 'Не удалось подтвердить email.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(app_config()['app']['name'], ENT_QUOTES, 'UTF-8') ?></title>
  <?= planner_favicon_head_markup() ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(planner_url('/assets/css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="auth-page">
  <div class="auth-shell" style="max-width: 520px; margin: 4rem auto;">
    <section class="auth-panel auth-panel--form" style="width: 100%;">
      <h1 class="auth-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="alert <?= $isError ? 'alert-error' : 'alert-success' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
      <p class="auth-footer" style="margin-top: 1.5rem;">
        <a href="<?= htmlspecialchars(planner_url('/login.php'), ENT_QUOTES, 'UTF-8') ?>">Перейти ко входу</a>
      </p>
    </section>
  </div>
</body>
</html>
