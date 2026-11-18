<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/ecosystem.php';

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ecoUser = null;
try {
    $ecoUser = current_user();
} catch (Throwable $e) {
    $ecoUser = null;
}
if ($ecoUser && planner_login_block_message($ecoUser) !== null) {
    $ecoUser = null;
}
$cfgName = trim((string) (app_config()['app']['name'] ?? ''));
$vectorName = $cfgName !== '' ? $cfgName : 'Вектор';
$vectorUrl = planner_url('/index.php');
$ecoServices = planner_ecosystem_services();
$orbit = [];
foreach ($ecoServices as $s) {
    $orbit[] = [
        'name' => $s['short'],
        'dom' => $s['key'] === 'portal' ? 'zabedu.ru' : explode('.', $s['domain'])[0],
        'url' => planner_ecosystem_service_url($s),
        'internal' => !empty($s['internal']),
    ];
}
$orbitJson = json_encode($orbit, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
$userInitial = '';
if ($ecoUser) {
    $n = trim((string) ($ecoUser['name'] ?? ''));
    $userInitial = $n !== '' ? mb_strtoupper(mb_substr($n, 0, 1, 'UTF-8'), 'UTF-8') : '•';
}
$ecoStats = null;
$ecoGreetName = '';
$ecoGreeting = 'Добрый день';
if ($ecoUser) {
    $uid = (int) $ecoUser['id'];
    $orgId = (int) ($ecoUser['organization_id'] ?? 0);
    $workYear = (int) ($ecoUser['organization_work_year'] ?? date('Y'));
    $active = "status NOT IN ('done','cancelled')";
    try {
        $yearSql = planner_table_has_column('tasks', 'work_year') ? ' AND work_year = ?' : '';
        $params = $yearSql !== '' ? [$uid, $orgId, $workYear] : [$uid, $orgId];
        $st = db()->prepare(
            "SELECT COALESCE(SUM($active), 0) AS active,
                    COALESCE(SUM($active AND due_date IS NOT NULL AND due_date < NOW()), 0) AS overdue
               FROM tasks WHERE assignee_id = ? AND organization_id = ?$yearSql"
        );
        $st->execute($params);
        $mine = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $st = db()->prepare("SELECT COUNT(*) FROM tasks WHERE author_id = ? AND organization_id = ? AND status = 'review'$yearSql");
        $st->execute($params);
        $review = (int) $st->fetchColumn();
        $unread = 0;
        if (planner_table_exists('notifications')) {
            $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND organization_id = ? AND read_at IS NULL');
            $st->execute([$uid, $orgId]);
            $unread = (int) $st->fetchColumn();
        }
        $ecoStats = [
            ['n' => (int) ($mine['active'] ?? 0), 'label' => 'в работе у меня', 'href' => planner_url('/tasks.php?assignee_id=' . $uid . '&report_active=1'), 'tone' => ''],
            ['n' => (int) ($mine['overdue'] ?? 0), 'label' => 'просрочено', 'href' => planner_url('/tasks.php?assignee_id=' . $uid . '&report_overdue=1'), 'tone' => 'warn'],
            ['n' => $review, 'label' => 'ждут моей проверки', 'href' => planner_url('/tasks.php?status=review'), 'tone' => ''],
            ['n' => $unread, 'label' => 'новых уведомлений', 'href' => planner_url('/notifications.php'), 'tone' => ''],
        ];
    } catch (Throwable $e) {
        $ecoStats = null;
    }
    $parts = preg_split('/\s+/u', trim((string) ($ecoUser['name'] ?? ''))) ?: [];
    $ecoGreetName = count($parts) === 3 ? $parts[1] . ' ' . $parts[2] : implode(' ', $parts);
    $hour = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Chita')))->format('G');
    $ecoGreeting = $hour < 5 ? 'Доброй ночи' : ($hour < 12 ? 'Доброе утро' : ($hour < 18 ? 'Добрый день' : 'Добрый вечер'));
}
$ecoActions = [
    ['title' => 'Мои задачи', 'text' => 'Список, канбан и «Мой план» на неделю', 'href' => planner_url('/tasks.php'), 'color' => '#2fc06e', 'icon' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
    ['title' => 'План и отчёт', 'text' => 'Индивидуальный план и отчёт преподавателя', 'href' => planner_url('/reports.php'), 'color' => '#8b5cf6', 'icon' => '<path d="M3 3v18h18"/><path d="M7 14l3-3 3 2 4-5"/>'],
    ['title' => 'Документы', 'text' => 'Приказы, положения, регламенты, инструкции', 'href' => planner_url('/documents.php'), 'color' => '#f59e0b', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>'],
    ['title' => 'Проекты отдела', 'text' => 'Проекты и портфели подразделения', 'href' => planner_url('/projects.php'), 'color' => '#2b8cff', 'icon' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
    ['title' => 'Свободная аудитория', 'text' => 'Занятость помещений и расписание', 'href' => 'https://af.zabedu.ru/', 'color' => '#14b8a6', 'icon' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-5h6v5"/>', 'ext' => true],
    ['title' => 'Анонс мероприятия', 'text' => 'Как событие видят педагоги на витрине', 'href' => 'https://eventt.zabedu.ru/', 'color' => '#1d7fe0', 'icon' => '<rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18"/><path d="M8 14h3M8 17.5h7"/>', 'ext' => true],
];
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Экосистема ИРО — рабочие сервисы сотрудников</title>
  <script>
    (function () {
      var t = null;
      try { t = localStorage.getItem('eco-theme'); } catch (e) {}
      if (t !== 'light' && t !== 'dark') t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
  <meta name="description" content="Цифровые сервисы Института развития образования Забайкальского края: портал, обучение, конкурсы, мероприятия, методическая поддержка и планирование работы.">
  <meta property="og:title" content="Экосистема ИРО Забайкальского края">
  <meta property="og:description" content="Все цифровые сервисы института в одном месте.">
  <meta name="theme-color" content="#2fc06e">
  <?= planner_favicon_head_markup() ?>
  <style>
    :root {
      --bg: #f3f6f8;
      --surface: #ffffff;
      --surface-2: #f7fafb;
      --text: #15202b;
      --muted: #5b6b7b;
      --line: rgba(21, 32, 43, 0.08);
      --primary: #2fc06e;
      --primary-dark: #1f9a55;
      --shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 8px 24px rgba(16, 24, 40, 0.06);
      --shadow-hover: 0 2px 4px rgba(16, 24, 40, 0.05), 0 18px 40px rgba(16, 24, 40, 0.12);
      --radius: 20px;
      --font: "Segoe UI", system-ui, -apple-system, Roboto, Ubuntu, "Helvetica Neue", sans-serif;
      color-scheme: light dark;
    }
    :root[data-theme="light"] { color-scheme: light; }
    :root[data-theme="dark"] {
      color-scheme: dark;
      --bg: #0d1419;
      --surface: #141e25;
      --surface-2: #19252d;
      --text: #e8eef2;
      --muted: #93a4b1;
      --line: rgba(255, 255, 255, 0.08);
      --shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 8px 24px rgba(0, 0, 0, 0.25);
      --shadow-hover: 0 2px 4px rgba(0, 0, 0, 0.35), 0 18px 40px rgba(0, 0, 0, 0.45);
    }
    @media (prefers-color-scheme: dark) {
      :root:not([data-theme]) {
        --bg: #0d1419;
        --surface: #141e25;
        --surface-2: #19252d;
        --text: #e8eef2;
        --muted: #93a4b1;
        --line: rgba(255, 255, 255, 0.08);
        --shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 8px 24px rgba(0, 0, 0, 0.25);
        --shadow-hover: 0 2px 4px rgba(0, 0, 0, 0.35), 0 18px 40px rgba(0, 0, 0, 0.45);
      }
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      font-family: var(--font);
      background:
        radial-gradient(720px 520px at 85% 80px, rgba(47, 192, 110, 0.18), transparent 70%),
        radial-gradient(640px 520px at 0% 520px, rgba(56, 132, 255, 0.12), transparent 70%),
        var(--bg);
      background-repeat: no-repeat;
      color: var(--text);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }
    a { color: inherit; }
    .wrap { width: min(1180px, 100% - 40px); margin-inline: auto; }

    /* Шапка */
    .topbar {
      position: sticky; top: 0; z-index: 20;
      backdrop-filter: saturate(1.4) blur(14px);
      -webkit-backdrop-filter: saturate(1.4) blur(14px);
      background: color-mix(in srgb, var(--bg) 78%, transparent);
      border-bottom: 1px solid var(--line);
    }
    .topbar .wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; height: 64px; }
    .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; font-weight: 700; letter-spacing: -0.01em; }
    .brand-mark { width: 36px; height: 36px; flex: none; }
    .brand small { display: block; font-weight: 500; font-size: 0.78rem; color: var(--muted); letter-spacing: 0; }
    .top-link {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 9px 16px; border-radius: 999px;
      background: var(--primary); color: #fff; text-decoration: none;
      font-weight: 600; font-size: 0.9rem;
      transition: background .15s ease, transform .15s ease;
    }
    .top-link:hover { background: var(--primary-dark); }
    .top-actions { display: flex; align-items: center; gap: 10px; }
    .theme-btn {
      display: grid; place-items: center; width: 38px; height: 38px; padding: 0;
      border-radius: 999px; border: 1px solid var(--line); background: var(--surface); color: var(--text);
      cursor: pointer; box-shadow: var(--shadow); transition: transform .15s ease, border-color .15s ease, color .15s ease;
    }
    .theme-btn:hover { transform: translateY(-1px); border-color: rgba(47, 192, 110, 0.5); color: var(--primary-dark); }
    .theme-btn:focus-visible { outline: 3px solid rgba(47, 192, 110, 0.6); outline-offset: 3px; }
    .theme-btn .ico-sun { display: none; }
    :root[data-theme="dark"] .theme-btn { color: #f5c451; }
    :root[data-theme="dark"] .theme-btn .ico-sun { display: block; }
    :root[data-theme="dark"] .theme-btn .ico-moon { display: none; }
    .top-link--vector { padding: 5px 16px 5px 5px; gap: 10px; box-shadow: 0 6px 18px rgba(47, 192, 110, 0.3); }
    .top-link--vector:hover { transform: translateY(-1px); }
    .top-link-mark { display: block; border-radius: 999px; box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.55); }
    .top-link-mark rect { rx: 16px; }
    .top-link-avatar {
      display: grid; place-items: center; width: 26px; height: 26px; margin: 0 -10px 0 2px;
      border-radius: 50%; background: #fff; color: var(--primary-dark);
      font-style: normal; font-size: 0.78rem; font-weight: 700;
    }

    /* Первый экран */
    .hero { position: relative; padding: 72px 0 56px; }
    .hero .wrap { position: relative; z-index: 1; display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 48px; align-items: center; }
    .eyebrow {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 6px 12px; border-radius: 999px;
      background: rgba(47, 192, 110, 0.12); color: var(--primary-dark);
      font-size: 0.82rem; font-weight: 600;
    }
    :root[data-theme="dark"] .eyebrow { color: #6fe0a0; }
    .eyebrow::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 0 4px rgba(47, 192, 110, 0.2); }
    h1 {
      margin: 18px 0 16px;
      font-size: clamp(2.1rem, 4.6vw, 3.6rem);
      line-height: 1.05; letter-spacing: -0.03em; font-weight: 800;
    }
    h1 .accent {
      background: linear-gradient(100deg, #1f9a55 0%, #2fc06e 45%, #2b8cff 100%);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .lead { font-size: 1.12rem; color: var(--muted); max-width: 560px; margin: 0 0 28px; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; }
    .btn {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 13px 22px; border-radius: 999px;
      font-weight: 600; text-decoration: none; font-size: 0.98rem;
      transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .btn-primary { background: var(--text); color: var(--bg); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: var(--shadow-hover); }
    .btn-ghost { background: var(--surface); border: 1px solid var(--line); }
    .btn-ghost:hover { transform: translateY(-1px); box-shadow: var(--shadow); }
    .stats { display: flex; gap: 28px; margin-top: 36px; flex-wrap: wrap; }
    .stat b { display: block; font-size: 1.6rem; letter-spacing: -0.02em; }
    .stat span { color: var(--muted); font-size: 0.88rem; }
    .btn-vector { background: var(--primary); color: #fff; padding: 9px 22px 9px 9px; box-shadow: 0 8px 22px rgba(47, 192, 110, 0.32); }
    .btn-vector svg { display: block; border-radius: 999px; box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.55); }
    .btn-vector svg rect { rx: 16px; }
    .btn-vector:hover { background: var(--primary-dark); transform: translateY(-1px); }

    /* Моя сводка */
    .my-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 32px; max-width: 580px; }
    .my-stat {
      display: flex; flex-direction: column; gap: 2px; padding: 14px 14px 12px;
      border-radius: 16px; background: var(--surface); border: 1px solid var(--line); box-shadow: var(--shadow);
      text-decoration: none; transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .my-stat:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: rgba(47, 192, 110, 0.45); }
    .my-stat b { font-size: 1.7rem; line-height: 1.1; letter-spacing: -0.02em; }
    .my-stat span { font-size: 0.8rem; color: var(--muted); line-height: 1.25; }
    .my-stat.is-warn { border-color: rgba(239, 79, 107, 0.45); }
    .my-stat.is-warn b { color: #e23d5b; }

    /* Быстрые действия */
    .quick { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .qa {
      --c: #2fc06e;
      position: relative; display: flex; align-items: center; gap: 14px; padding: 16px 18px;
      border-radius: 18px; background: var(--surface); border: 1px solid var(--line); box-shadow: var(--shadow);
      text-decoration: none; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .qa:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: color-mix(in srgb, var(--c) 45%, transparent); }
    .qa-ico { flex: none; width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; color: var(--c); background: color-mix(in srgb, var(--c) 14%, transparent); }
    .qa-body { min-width: 0; }
    .qa-body b { display: block; font-size: 1rem; }
    .qa-body small { display: block; color: var(--muted); font-size: 0.84rem; line-height: 1.35; }
    .qa-ext { position: absolute; top: 12px; right: 12px; color: var(--muted); opacity: 0.7; }

    /* Помощь */
    .help {
      display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;
      margin-top: 28px; padding: 22px 26px; border-radius: var(--radius);
      background: linear-gradient(120deg, rgba(47, 192, 110, 0.12), rgba(43, 140, 255, 0.10));
      border: 1px solid rgba(47, 192, 110, 0.25);
    }
    .help-text { max-width: 640px; }
    .help-text b { display: block; font-size: 1.05rem; margin-bottom: 4px; }
    .help-text span { color: var(--muted); }
    .help-links { display: flex; gap: 10px; flex-wrap: wrap; }

    /* Схема экосистемы */
    .orbit { position: relative; aspect-ratio: 1; width: 100%; max-width: 500px; justify-self: center; }
    .orbit svg { width: 100%; height: 100%; overflow: visible; }
    .orbit .ring { fill: none; stroke: var(--line); stroke-width: 1.5; }
    .orbit .ring-dash { fill: none; stroke: rgba(47, 192, 110, 0.35); stroke-width: 1.5; stroke-dasharray: 3 7; }
    .orbit .spoke { stroke: rgba(47, 192, 110, 0.28); stroke-width: 1.5; }
    .orbit .node circle { fill: var(--surface); stroke: var(--line); stroke-width: 1; filter: drop-shadow(0 6px 14px rgba(16, 24, 40, 0.10)); }
    .orbit .node text { font: 600 11px var(--font); fill: var(--text); text-anchor: middle; }
    .orbit .node .dom { font-weight: 500; font-size: 9px; fill: var(--muted); }
    .orbit .node a:hover circle, .orbit .node a:focus circle { stroke: var(--primary); stroke-width: 2; }
    .orbit .node--home circle { fill: var(--primary); stroke: var(--primary); }
    .orbit .node--home text, .orbit .node--home .dom { fill: #fff; }
    .orbit .hub circle { fill: url(#hubGrad); }
    .orbit .hub text { fill: #fff; font: 800 22px var(--font); text-anchor: middle; }
    .orbit .hub .sub { font: 600 9.5px var(--font); letter-spacing: 0.08em; }
    .spin { transform-origin: 250px 250px; animation: spin 80s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .pulse { transform-origin: 250px 250px; animation: pulse 3.2s ease-in-out infinite; }
    @keyframes pulse { 0%, 100% { opacity: .55; transform: scale(1); } 50% { opacity: 0; transform: scale(1.35); } }

    /* Витрина */
    section { padding: 28px 0; }
    .section-head { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }
    h2 { margin: 0; font-size: clamp(1.5rem, 2.6vw, 2rem); letter-spacing: -0.02em; }
    .section-head p { margin: 6px 0 0; color: var(--muted); }

    .showcase {
      position: relative; overflow: hidden;
      display: grid; grid-template-columns: 1.2fr 1fr; gap: 32px; align-items: center;
      padding: 44px; border-radius: 28px; text-decoration: none; color: #fff;
      background:
        radial-gradient(600px 300px at 90% 10%, rgba(255, 255, 255, 0.18), transparent 60%),
        linear-gradient(125deg, #0f7a45 0%, #1fae62 40%, #1d7fe0 100%);
      box-shadow: 0 24px 60px rgba(31, 154, 85, 0.28);
      transition: transform .2s ease, box-shadow .2s ease;
    }
    .showcase:hover { transform: translateY(-3px); box-shadow: 0 30px 70px rgba(31, 154, 85, 0.36); }
    .showcase .tag-white { display: inline-block; padding: 5px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.18); font-size: 0.8rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
    .showcase h3 { margin: 16px 0 10px; font-size: clamp(1.7rem, 3vw, 2.4rem); letter-spacing: -0.02em; line-height: 1.1; }
    .showcase p { margin: 0 0 24px; opacity: 0.92; font-size: 1.05rem; max-width: 520px; }
    .showcase .go { display: inline-flex; align-items: center; gap: 10px; padding: 12px 20px; border-radius: 999px; background: #fff; color: #10663b; font-weight: 700; }
    .showcase .domain { display: block; margin-top: 14px; opacity: 0.8; font-size: 0.9rem; }
    .cal {
      justify-self: end; width: 100%; max-width: 360px;
      background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.22);
      border-radius: 20px; padding: 18px; backdrop-filter: blur(6px);
    }
    .cal-head { display: flex; justify-content: space-between; font-weight: 700; margin-bottom: 12px; font-size: 0.95rem; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
    .cal-grid span { aspect-ratio: 1; display: grid; place-items: center; border-radius: 9px; font-size: 0.78rem; background: rgba(255, 255, 255, 0.08); }
    .cal-grid .dow { background: none; opacity: 0.7; font-weight: 600; aspect-ratio: auto; padding-bottom: 2px; }
    .cal-grid .ev { background: #fff; color: #10663b; font-weight: 700; }
    .cal-grid .ev2 { background: rgba(255, 255, 255, 0.4); font-weight: 700; }
    .cal-list { margin-top: 14px; display: grid; gap: 8px; }
    .cal-list div { display: flex; gap: 10px; align-items: center; font-size: 0.85rem; padding: 8px 10px; border-radius: 12px; background: rgba(255, 255, 255, 0.12); }
    .cal-list i { width: 8px; height: 8px; border-radius: 50%; background: #fff; flex: none; }

    /* Фильтр и карточки */
    .chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .chip {
      border: 1px solid var(--line); background: var(--surface); color: var(--text);
      padding: 8px 14px; border-radius: 999px; font: 600 0.88rem var(--font); cursor: pointer;
      transition: background .15s ease, color .15s ease, border-color .15s ease;
    }
    .chip:hover { border-color: rgba(47, 192, 110, 0.5); }
    .chip[aria-pressed="true"] { background: var(--primary); border-color: var(--primary); color: #fff; }

    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
    .card {
      --c: #2fc06e;
      position: relative; display: flex; flex-direction: column; gap: 14px;
      padding: 24px; border-radius: var(--radius); text-decoration: none;
      background: var(--surface); border: 1px solid var(--line); box-shadow: var(--shadow);
      transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, opacity .2s ease;
      min-height: 250px; overflow: hidden; isolation: isolate;
    }
    .card::before {
      content: ""; position: absolute; inset: 0 0 auto 0; height: 4px;
      background: var(--c); opacity: 0; transition: opacity .2s ease;
    }
    .card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); border-color: color-mix(in srgb, var(--c) 40%, transparent); }
    .card:hover::before { opacity: 1; }
    .card:focus-visible, .showcase:focus-visible, .btn:focus-visible, .chip:focus-visible, .top-link:focus-visible { outline: 3px solid rgba(47, 192, 110, 0.6); outline-offset: 3px; }
    .card-top { display: flex; align-items: center; justify-content: space-between; }
    .card-top .here {
      margin: 0 10px 0 auto; padding: 4px 10px; border-radius: 999px;
      background: color-mix(in srgb, var(--c) 14%, transparent); color: var(--primary-dark);
      font-size: 0.74rem; font-weight: 700;
    }
    :root[data-theme="dark"] .card-top .here { color: #6fe0a0; }
    .card--home { border-color: color-mix(in srgb, var(--c) 45%, transparent); }
    .icon {
      width: 52px; height: 52px; border-radius: 15px; display: grid; place-items: center;
      background: color-mix(in srgb, var(--c) 14%, transparent); color: var(--c);
    }
    .icon svg { width: 26px; height: 26px; }
    .arrow {
      width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center;
      background: var(--surface-2); color: var(--muted);
      transition: background .2s ease, color .2s ease, transform .2s ease;
    }
    .card:hover .arrow { background: var(--c); color: #fff; transform: rotate(-45deg); }
    .card h3 { margin: 0; font-size: 1.2rem; letter-spacing: -0.01em; }
    .card .domain { font-size: 0.85rem; color: var(--c); font-weight: 600; }
    .card p { margin: 0; color: var(--muted); font-size: 0.95rem; flex: 1; }
    .tags { display: flex; flex-wrap: wrap; gap: 6px; }
    .tags span { font-size: 0.76rem; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: var(--surface-2); color: var(--muted); }
    .card.is-hidden { display: none; }
    .empty { display: none; padding: 32px; text-align: center; color: var(--muted); }

    /* Кому что */
    .paths { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
    .path { padding: 26px; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--line); }
    .path h3 { margin: 0 0 6px; font-size: 1.1rem; }
    .path p { margin: 0 0 14px; color: var(--muted); font-size: 0.93rem; }
    .path ol { margin: 0; padding: 0; list-style: none; counter-reset: s; display: grid; gap: 10px; }
    .path li { counter-increment: s; display: flex; gap: 12px; align-items: baseline; font-size: 0.95rem; }
    .path li::before {
      content: counter(s); flex: none; width: 24px; height: 24px; border-radius: 50%;
      display: grid; place-items: center; font-size: 0.78rem; font-weight: 700;
      background: rgba(47, 192, 110, 0.14); color: var(--primary-dark);
    }
    .path a { font-weight: 600; text-decoration-color: rgba(47, 192, 110, 0.5); text-underline-offset: 3px; }
    .path a:hover { color: var(--primary-dark); }

    footer { margin-top: 40px; padding: 32px 0 40px; border-top: 1px solid var(--line); color: var(--muted); font-size: 0.9rem; }
    footer .wrap { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    footer a { text-decoration: none; }
    footer a:hover { color: var(--text); }

    .reveal { opacity: 0; transform: translateY(14px); transition: opacity .6s ease, transform .6s ease; }
    .reveal.is-in { opacity: 1; transform: none; }

    @media (max-width: 960px) {
      .hero .wrap { grid-template-columns: 1fr; }
      .orbit { max-width: 420px; }
      .grid, .paths { grid-template-columns: repeat(2, 1fr); }
      .showcase { grid-template-columns: 1fr; padding: 32px; }
      .quick { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .cal { justify-self: stretch; max-width: none; }
    }
    @media (max-width: 620px) {
      .hero { padding: 40px 0 32px; }
      .grid, .paths, .quick { grid-template-columns: 1fr; }
      .my-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .showcase { padding: 24px; border-radius: 22px; }
      .brand small { display: none; }
      .top-link span { display: none; }
      .top-link--vector { padding: 5px; }
      .top-link-avatar { margin-right: 0; }
      .stats { gap: 20px; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
      .reveal { opacity: 1; transform: none; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="wrap">
      <a class="brand" href="#top" aria-label="Экосистема ИРО Забайкальского края">
        <svg class="brand-mark" viewBox="0 0 36 36" aria-hidden="true">
          <defs><linearGradient id="bm" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1f9a55"/><stop offset="1" stop-color="#2b8cff"/></linearGradient></defs>
          <rect width="36" height="36" rx="10" fill="url(#bm)"/>
          <path d="M6 26l7-10 5 6 4-5 8 9z" fill="#fff" opacity=".95"/>
          <circle cx="25" cy="11" r="3" fill="#fff"/>
        </svg>
        <span>ИРО Забайкальского края<small>Цифровая экосистема</small></span>
      </a>
      <div class="top-actions">
      <button type="button" class="theme-btn" id="themeBtn" aria-label="Тёмная тема" title="Тёмная тема">
        <svg class="ico-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <svg class="ico-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
<?php if ($ecoUser): ?>
      <a class="top-link top-link--vector" href="<?= $h($vectorUrl) ?>" title="Открыть <?= $h($vectorName) ?>">
        <?= planner_vector_mark_svg(26, 'top-link-mark') ?>
        <span>Открыть <?= $h($vectorName) ?></span>
        <i class="top-link-avatar" aria-hidden="true"><?= $h($userInitial) ?></i>
      </a>
<?php else: ?>
      <a class="top-link top-link--vector" href="<?= $h(planner_url('/login.php')) ?>">
        <?= planner_vector_mark_svg(26, 'top-link-mark') ?>
        <span>Войти в <?= $h($vectorName) ?></span>
      </a>
<?php endif; ?>
      </div>
    </div>
  </header>

  <main id="top">
    <section class="hero">
      <div class="wrap">
        <div>
          <span class="eyebrow">Для сотрудников ИРО Забайкальского края</span>
<?php if ($ecoUser): ?>
          <h1><?= $h($ecoGreeting) ?>,<br><span class="accent"><?= $h($ecoGreetName) ?></span></h1>
          <p class="lead">Ваш рабочий день в одном месте. «<?= $h($vectorName) ?>» — для задач, плана и отчёта, соседние сервисы института — для аудиторий, мероприятий, курсов и заявок.</p>
<?php else: ?>
          <h1>Рабочее пространство <span class="accent">сотрудника ИРО</span></h1>
          <p class="lead">Задачи и отчёты, аудитории, мероприятия, курсы и заявки — все рабочие сервисы института в одном месте. Начните со входа в «<?= $h($vectorName) ?>»: там ваши задачи, план и отчёт.</p>
<?php endif; ?>
          <div class="hero-actions">
            <a class="btn btn-vector" href="<?= $h($ecoUser ? $vectorUrl : planner_url('/login.php')) ?>"><?= planner_vector_mark_svg(22) ?><?= $ecoUser ? 'Открыть' : 'Войти в' ?> <?= $h($vectorName) ?></a>
            <a class="btn btn-ghost" href="#services">Все сервисы
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            </a>
          </div>
<?php if ($ecoStats): ?>
          <div class="my-stats" aria-label="Моя сводка">
<?php foreach ($ecoStats as $st): ?>
            <a class="my-stat<?= $st['tone'] === 'warn' && $st['n'] > 0 ? ' is-warn' : '' ?>" href="<?= $h($st['href']) ?>"><b><?= (int) $st['n'] ?></b><span><?= $h($st['label']) ?></span></a>
<?php endforeach; ?>
          </div>
<?php elseif (!$ecoUser): ?>
          <div class="stats">
            <div class="stat"><b>7</b><span>рабочих сервисов</span></div>
            <div class="stat"><b>1</b><span>вход для задач и отчётов</span></div>
            <div class="stat"><b>MAX</b><span>уведомления о задачах</span></div>
          </div>
<?php endif; ?>
        </div>

        <div class="orbit" aria-label="Схема экосистемы: сервисы вокруг института">
          <svg viewBox="0 0 500 500" role="img">
            <title>Сервисы экосистемы ИРО</title>
            <defs>
              <linearGradient id="hubGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1f9a55"/><stop offset="1" stop-color="#2b8cff"/></linearGradient>
            </defs>
            <circle class="ring" cx="250" cy="250" r="180"/>
            <g class="spin"><circle class="ring-dash" cx="250" cy="250" r="118"/><circle class="ring-dash" cx="250" cy="250" r="225"/></g>
            <g id="spokes"></g>
            <circle class="pulse" cx="250" cy="250" r="62" fill="rgba(47,192,110,.35)"/>
            <g class="hub">
              <circle cx="250" cy="250" r="62"/>
              <text x="250" y="254">ИРО</text>
              <text class="sub" x="250" y="274">ЗАБАЙКАЛЬЕ</text>
            </g>
            <g id="nodes"></g>
          </svg>
        </div>
      </div>
    </section>

    <section aria-labelledby="quick-title">
      <div class="wrap">
        <div class="section-head">
          <div>
            <h2 id="quick-title">Быстрые действия</h2>
            <p><?= $ecoUser ? 'То, что нужно каждый день.' : 'Разделы Вектора откроются после входа под рабочей почтой @iro75.ru.' ?></p>
          </div>
        </div>
        <div class="quick">
<?php foreach ($ecoActions as $a): ?>
          <a class="qa reveal" style="--c:<?= $h($a['color']) ?>" href="<?= $h($a['href']) ?>"<?= !empty($a['ext']) ? ' target="_blank" rel="noopener"' : '' ?>>
            <span class="qa-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $a['icon'] ?></svg></span>
            <span class="qa-body"><b><?= $h($a['title']) ?></b><small><?= $h($a['text']) ?></small></span>
            <?php if (!empty($a['ext'])): ?><span class="qa-ext" title="Откроется в новой вкладке"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M8 7h9v9"/></svg></span><?php endif; ?>
          </a>
<?php endforeach; ?>
        </div>
      </div>
    </section>

    <section aria-labelledby="showcase-title">
      <div class="wrap">
        <a class="showcase reveal" href="https://eventt.zabedu.ru/" target="_blank" rel="noopener">
          <div>
            <span class="tag-white">Витрина</span>
            <h3 id="showcase-title">Календарь мероприятий #educationlife</h3>
            <p>Здесь педагоги края находят семинары, конференции, конкурсы и курсы института. Проверьте, как выглядит анонс мероприятия вашего отдела, и поделитесь ссылкой с участниками.</p>
            <span class="go">Открыть витрину
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </span>
            <span class="domain">eventt.zabedu.ru</span>
          </div>
          <div class="cal" aria-hidden="true">
            <div class="cal-head"><span id="calMonth">Месяц</span><span>●●●</span></div>
            <div class="cal-grid" id="calGrid"></div>
            <div class="cal-list">
              <div><i></i>Семинары и вебинары</div>
              <div><i></i>Конкурсы профессионального мастерства</div>
              <div><i></i>Курсы и конференции</div>
            </div>
          </div>
        </a>
      </div>
    </section>

    <section id="services" aria-labelledby="services-title">
      <div class="wrap">
        <div class="section-head">
          <div>
            <h2 id="services-title">Сервисы для работы</h2>
            <p>Выберите направление работы. Внешние сервисы открываются в новой вкладке.</p>
          </div>
          <div class="chips" role="group" aria-label="Фильтр по направлению работы">
            <button type="button" class="chip" data-filter="all" aria-pressed="true">Все</button>
            <button type="button" class="chip" data-filter="plan" aria-pressed="false">Планирование</button>
            <button type="button" class="chip" data-filter="events" aria-pressed="false">Мероприятия</button>
            <button type="button" class="chip" data-filter="learn" aria-pressed="false">Обучение</button>
            <button type="button" class="chip" data-filter="info" aria-pressed="false">Информирование</button>
          </div>
        </div>

        <div class="grid" id="grid">
<?php foreach ($ecoServices as $s): if ($s['key'] === 'vitrina') { continue; } $internal = !empty($s['internal']); ?>
          <a class="card reveal<?= $internal ? ' card--home' : '' ?>" style="--c:<?= $h($s['color']) ?>" href="<?= $h(planner_ecosystem_service_url($s)) ?>"<?= $internal ? '' : ' target="_blank" rel="noopener"' ?> data-groups="<?= $h($s['groups']) ?>">
            <div class="card-top">
              <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $s['icon'] ?></svg></span>
              <?php if ($internal): ?><span class="here">Вы на этом сайте</span><?php endif; ?>
              <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
            </div>
            <div>
              <h3><?= $h($s['name']) ?></h3>
              <div class="domain"><?= $h($s['domain']) ?></div>
            </div>
            <p><?= $h($s['desc']) ?></p>
            <div class="tags"><?php foreach ($s['tags'] as $tag): ?><span><?= $h($tag) ?></span><?php endforeach; ?></div>
          </a>
<?php endforeach; ?>
        </div>
        <p class="empty" id="empty">В этой группе пока нет сервисов.</p>
      </div>
    </section>

    <section aria-labelledby="paths-title">
      <div class="wrap">
        <div class="section-head">
          <div>
            <h2 id="paths-title">Рабочие сценарии</h2>
            <p>Как сервисы экосистемы складываются в обычные задачи института.</p>
          </div>
        </div>
        <div class="paths">
          <div class="path reveal">
            <h3>Провести мероприятие</h3>
            <p>Семинар, конференция или вебинар отдела.</p>
            <ol>
              <li><span>Заведите задачу с чек-листом подготовки в разделе <a href="<?= $h(planner_url('/tasks.php')) ?>">«Задачи»</a></span></li>
              <li><span>Подберите помещение в <a href="https://af.zabedu.ru/" target="_blank" rel="noopener">аудиторном фонде</a></span></li>
              <li><span>Проверьте анонс на <a href="https://eventt.zabedu.ru/" target="_blank" rel="noopener">витрине мероприятий</a></span></li>
              <li><span>После мероприятия отметьте задачу выполненной и приложите материалы</span></li>
            </ol>
          </div>
          <div class="path reveal">
            <h3>Запустить курс</h3>
            <p>Повышение квалификации педагогов.</p>
            <ol>
              <li><span>Отметьте в задаче «Учебную работу» и часы — они попадут в план и отчёт</span></li>
              <li><span>Разместите курс на <a href="https://do75.zabedu.ru/" target="_blank" rel="noopener">дистанционной платформе</a></span></li>
            </ol>
          </div>
          <div class="path reveal">
            <h3>Сдать план и отчёт</h3>
            <p>Индивидуальный план и отчёт преподавателя.</p>
            <ol>
              <li><span>Проверьте виды работы и часы в своих задачах</span></li>
              <li><span>Выгрузите «План» или «Отчёт» в разделе <a href="<?= $h(planner_url('/reports.php')) ?>">«Отчёты»</a></span></li>
              <li><span>Положения и формы — в разделе <a href="<?= $h(planner_url('/documents.php')) ?>">«Документы»</a></span></li>
            </ol>
          </div>
        </div>

        <div class="help reveal">
          <div class="help-text">
            <b>Нужна помощь с Вектором?</b>
            <span>Инструкция пользователя лежит в разделе «Документы», а уведомления о задачах можно получать в MAX: укажите ID чата в профиле.</span>
          </div>
          <div class="help-links">
            <a class="btn btn-ghost" href="<?= $h(planner_url('/documents.php?q=' . rawurlencode('Инструкция'))) ?>">Инструкция</a>
            <a class="btn btn-ghost" href="https://max.ru/irozabkrai75_bot" target="_blank" rel="noopener">Бот в MAX</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap">
      <span>© <span id="year">2026</span> ГУ ДПО «Институт развития образования Забайкальского края», Чита</span>
      <a href="https://zabedu.ru/" target="_blank" rel="noopener">zabedu.ru</a>
    </div>
  </footer>

  <script>
    (function () {
      var root = document.documentElement;
      var themeBtn = document.getElementById('themeBtn');
      var themeMeta = document.querySelector('meta[name="theme-color"]');
      function applyTheme(t) {
        root.setAttribute('data-theme', t);
        var label = t === 'dark' ? 'Светлая тема' : 'Тёмная тема';
        themeBtn.setAttribute('aria-label', label);
        themeBtn.setAttribute('title', label);
        themeBtn.setAttribute('aria-pressed', t === 'dark' ? 'true' : 'false');
        if (themeMeta) themeMeta.setAttribute('content', t === 'dark' ? '#0d1419' : '#2fc06e');
      }
      applyTheme(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
      themeBtn.addEventListener('click', function () {
        var t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(t);
        try { localStorage.setItem('eco-theme', t); } catch (e) {}
      });
      if (window.matchMedia) {
        var mq = matchMedia('(prefers-color-scheme: dark)');
        var onSys = function (e) {
          var saved = null;
          try { saved = localStorage.getItem('eco-theme'); } catch (err) {}
          if (saved !== 'light' && saved !== 'dark') applyTheme(e.matches ? 'dark' : 'light');
        };
        if (mq.addEventListener) mq.addEventListener('change', onSys); else if (mq.addListener) mq.addListener(onSys);
      }

      var services = <?= $orbitJson ?>;
      var NS = 'http://www.w3.org/2000/svg';
      var spokes = document.getElementById('spokes');
      var nodes = document.getElementById('nodes');
      function el(tag, attrs, parent) {
        var e = document.createElementNS(NS, tag);
        for (var k in attrs) e.setAttribute(k, attrs[k]);
        if (parent) parent.appendChild(e);
        return e;
      }
      services.forEach(function (s, i) {
        var a = -Math.PI / 2 + i * (2 * Math.PI / services.length);
        var x = 250 + 180 * Math.cos(a), y = 250 + 180 * Math.sin(a);
        el('line', { 'class': 'spoke', x1: 250, y1: 250, x2: x.toFixed(1), y2: y.toFixed(1) }, spokes);
        var g = el('g', { 'class': s.internal ? 'node node--home' : 'node' }, nodes);
        var link = el('a', s.internal ? { href: s.url } : { href: s.url, target: '_blank', rel: 'noopener' }, g);
        el('title', {}, link).textContent = s.name + ' — ' + s.url;
        el('circle', { cx: x.toFixed(1), cy: y.toFixed(1), r: 42 }, link);
        var t = el('text', { x: x.toFixed(1), y: (y - 1).toFixed(1) }, link);
        t.textContent = s.name;
        var d = el('text', { 'class': 'dom', x: x.toFixed(1), y: (y + 13).toFixed(1) }, link);
        d.textContent = s.dom;
      });

      var now = new Date();
      var months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
      document.getElementById('calMonth').textContent = months[now.getMonth()] + ' ' + now.getFullYear();
      document.getElementById('year').textContent = String(now.getFullYear());
      var grid = document.getElementById('calGrid');
      ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'].forEach(function (d) {
        var s = document.createElement('span'); s.className = 'dow'; s.textContent = d; grid.appendChild(s);
      });
      var first = new Date(now.getFullYear(), now.getMonth(), 1);
      var offset = (first.getDay() + 6) % 7;
      var days = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
      for (var i = 0; i < offset; i++) { var b = document.createElement('span'); b.style.visibility = 'hidden'; grid.appendChild(b); }
      var accents = [3, 8, 12, 17, 22, 26];
      for (var dnum = 1; dnum <= days; dnum++) {
        var c = document.createElement('span');
        c.textContent = dnum;
        if (dnum === now.getDate()) c.className = 'ev';
        else if (accents.indexOf(dnum) !== -1) c.className = 'ev2';
        grid.appendChild(c);
      }

      var chips = document.querySelectorAll('.chip');
      var cards = document.querySelectorAll('#grid .card');
      var empty = document.getElementById('empty');
      chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
          var f = chip.getAttribute('data-filter');
          chips.forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
          var shown = 0;
          cards.forEach(function (card) {
            var ok = f === 'all' || (' ' + card.getAttribute('data-groups') + ' ').indexOf(' ' + f + ' ') !== -1;
            card.classList.toggle('is-hidden', !ok);
            if (ok) { shown++; card.classList.add('is-in'); }
          });
          empty.style.display = shown ? 'none' : 'block';
        });
      });

      var revealEls = document.querySelectorAll('.reveal');
      if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
          });
        }, { threshold: 0.12 });
        revealEls.forEach(function (e) { io.observe(e); });
      } else {
        revealEls.forEach(function (e) { e.classList.add('is-in'); });
      }
    })();
  </script>
</body>
</html>
