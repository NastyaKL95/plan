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
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Экосистема ИРО Забайкальского края</title>
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
    @media (prefers-color-scheme: dark) {
      :root {
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
    @media (prefers-color-scheme: dark) { .eyebrow { color: #6fe0a0; } }
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
      min-height: 250px;
    }
    .card::before {
      content: ""; position: absolute; inset: 0 0 auto 0; height: 4px; border-radius: var(--radius) var(--radius) 0 0;
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
    @media (prefers-color-scheme: dark) { .card-top .here { color: #6fe0a0; } }
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
      .cal { justify-self: stretch; max-width: none; }
    }
    @media (max-width: 620px) {
      .hero { padding: 40px 0 32px; }
      .grid, .paths { grid-template-columns: 1fr; }
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
  </header>

  <main id="top">
    <section class="hero">
      <div class="wrap">
        <div>
          <span class="eyebrow">Институт развития образования</span>
          <h1>Экосистема <span class="accent">ИРО Забайкальского края</span></h1>
          <p class="lead">Все цифровые сервисы института в одном месте: обучение и методическая поддержка, конкурсы и мероприятия, планирование работы и учёт аудиторий.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="#services">Все сервисы
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            </a>
            <a class="btn btn-ghost" href="https://eventt.zabedu.ru/" target="_blank" rel="noopener">Витрина мероприятий</a>
          </div>
          <div class="stats">
            <div class="stat"><b>7</b><span>сервисов</span></div>
            <div class="stat"><b>1</b><span>образовательное пространство</span></div>
            <div class="stat"><b>24/7</b><span>доступ онлайн</span></div>
          </div>
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

    <section aria-labelledby="showcase-title">
      <div class="wrap">
        <a class="showcase reveal" href="https://eventt.zabedu.ru/" target="_blank" rel="noopener">
          <div>
            <span class="tag-white">Витрина</span>
            <h3 id="showcase-title">Календарь мероприятий #educationlife</h3>
            <p>Главная витрина событий: семинары, конференции, конкурсы и курсы для педагогов края. Смотрите, что проходит сегодня, и выбирайте события на месяц вперёд.</p>
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
            <h2 id="services-title">Сервисы экосистемы</h2>
            <p>Нажмите на карточку, чтобы перейти на сайт. Сервисы открываются в новой вкладке.</p>
          </div>
          <div class="chips" role="group" aria-label="Фильтр по аудитории">
            <button type="button" class="chip" data-filter="all" aria-pressed="true">Все</button>
            <button type="button" class="chip" data-filter="teacher" aria-pressed="false">Педагогам</button>
            <button type="button" class="chip" data-filter="staff" aria-pressed="false">Сотрудникам ИРО</button>
            <button type="button" class="chip" data-filter="learn" aria-pressed="false">Обучение</button>
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
            <h2 id="paths-title">С чего начать</h2>
            <p>Короткие маршруты для разных задач.</p>
          </div>
        </div>
        <div class="paths">
          <div class="path reveal">
            <h3>Педагогу</h3>
            <p>Учиться, участвовать и делиться опытом.</p>
            <ol>
              <li><span>Найдите событие на <a href="https://eventt.zabedu.ru/" target="_blank" rel="noopener">витрине мероприятий</a></span></li>
              <li><span>Пройдите курс на <a href="https://do75.zabedu.ru/" target="_blank" rel="noopener">дистанционной платформе</a></span></li>
              <li><span>Подайте заявку на <a href="https://konkurs.zabedu.ru/" target="_blank" rel="noopener">конкурс или проект</a></span></li>
            </ol>
          </div>
          <div class="path reveal">
            <h3>Сотруднику ИРО</h3>
            <p>Планировать работу и готовить мероприятия.</p>
            <ol>
              <li><span>Ведите задачи и отчёты в <a href="<?= $h($vectorUrl) ?>">«<?= $h($vectorName) ?>»</a></span></li>
              <li><span>Посмотрите занятость помещений в <a href="https://af.zabedu.ru/" target="_blank" rel="noopener">аудиторном фонде</a></span></li>
              <li><span>Проверьте анонс события на <a href="https://eventt.zabedu.ru/" target="_blank" rel="noopener">витрине</a></span></li>
            </ol>
          </div>
          <div class="path reveal">
            <h3>Образовательной организации</h3>
            <p>Быть в курсе и получать поддержку.</p>
            <ol>
              <li><span>Следите за новостями на <a href="https://zabedu.ru/" target="_blank" rel="noopener">портале образования</a></span></li>
              <li><span>Используйте <a href="https://metodnav.zabedu.ru/" target="_blank" rel="noopener">методический навигатор</a></span></li>
              <li><span>Направляйте педагогов на <a href="https://do75.zabedu.ru/" target="_blank" rel="noopener">курсы</a></span></li>
            </ol>
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
