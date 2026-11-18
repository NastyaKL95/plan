<?php
declare(strict_types=1);

require __DIR__ . '/lib/events.php';

$cfg = vitrina_config();
$events = [];
$categories = [];
$failed = false;
try {
    $events = vitrina_public_events();
    $categories = vitrina_categories();
} catch (PDOException $ex) {
    $failed = true;
    error_log('vitrina: ' . $ex->getMessage());
}
$used = array_flip(array_column($events, 'cat'));
$icons = vitrina_icons();

$payload = [
    'timezone' => $cfg['timezone'],
    'categories' => array_values(array_map(static fn (array $c): array => [
        'key' => $c['slug'],
        'label' => $c['name'],
        'color' => $c['color'],
        'icon' => $c['icon'],
    ], array_filter($categories, static fn (array $c): bool => isset($used[$c['slug']])))),
    'icons' => array_map(static fn (array $i): string => $i[1], $icons),
    'events' => $events,
];

$months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
$fmtDate = static function (string $iso) use ($months): string {
    $d = new DateTimeImmutable($iso);
    return $d->format('j') . ' ' . $months[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
};
$nowIso = (new DateTimeImmutable('now'))->format(DATE_ATOM);
$upcoming = array_values(array_filter($events, static fn ($e) => $e['end'] >= $nowIso));
$noscriptList = $upcoming !== [] ? $upcoming : array_slice(array_reverse($events), 0, 12);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h((string) $cfg['site_name']) ?></title>
  <meta name="description" content="Конференции, семинары, вебинары и конкурсы Института развития образования Забайкальского края: ближайшие события, архив и календарь.">
  <meta property="og:title" content="<?= h((string) $cfg['site_name']) ?>">
  <meta property="og:description" content="Ближайшие события, архив и календарь мероприятий #educationlife.">
  <meta name="theme-color" content="#2fc06e">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/vitrina.css?v=<?= (int) @filemtime(__DIR__ . '/assets/vitrina.css') ?>">
</head>
<body>
  <header class="topbar">
    <div class="wrap">
      <a class="brand" href="./" aria-label="Витрина событий ИРО">
        <img class="brand-mark" src="assets/favicon.svg" alt="" width="36" height="36">
        <span>Витрина событий<small>ИРО Забайкальского края</small></span>
      </a>
      <nav class="top-nav" aria-label="Разделы">
        <a href="#events">События</a>
        <a href="#events" data-view-link="calendar">Календарь</a>
        <a class="top-link" href="<?= h((string) $cfg['ecosystem_url']) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9" stroke-dasharray="3 3"/></svg>
          <span>Экосистема ИРО</span>
        </a>
      </nav>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="wrap">
        <div class="hero-text">
          <span class="eyebrow">#educationlife</span>
          <h1>Витрина событий <span class="accent">ИРО Забайкальского края</span></h1>
          <p class="lead">Конференции, семинары, вебинары и конкурсы для педагогов края. Ближайшие события, архив и календарь мероприятий института в одном месте.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="#events">Смотреть события
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            </a>
            <a class="btn btn-ghost" href="#events" data-view-link="calendar">Календарь</a>
          </div>
          <div class="stats" id="stats" aria-live="polite"></div>
        </div>
        <div class="hero-feature" id="feature"></div>
      </div>
    </section>

    <section id="events" class="events" aria-labelledby="events-title">
      <div class="wrap">
        <div class="section-head">
          <div>
            <h2 id="events-title">События</h2>
            <p id="resultInfo" class="muted"></p>
          </div>
          <div class="seg" role="tablist" aria-label="Вид">
            <button type="button" role="tab" data-view="feed" aria-selected="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4" width="7" height="7" rx="1.5"/><rect x="14" y="4" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
              Лента
            </button>
            <button type="button" role="tab" data-view="calendar" aria-selected="false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
              Календарь
            </button>
          </div>
        </div>

        <div class="toolbar">
          <label class="search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" id="q" placeholder="Поиск по названию, месту, описанию" autocomplete="off" aria-label="Поиск событий">
            <kbd>/</kbd>
          </label>
          <div class="seg seg-when" role="group" aria-label="Период">
            <button type="button" data-when="upcoming" aria-pressed="false">Предстоящие</button>
            <button type="button" data-when="past" aria-pressed="false">Прошедшие</button>
            <button type="button" data-when="all" aria-pressed="true">Все</button>
          </div>
          <select id="year" aria-label="Год"></select>
        </div>
        <div class="chips" id="chips" role="group" aria-label="Тип события"></div>

        <div id="feedView">
          <div id="feed"></div>
          <div class="more-wrap"><button type="button" class="btn btn-ghost" id="more" hidden>Показать ещё</button></div>
        </div>
        <div id="calendarView" hidden>
          <div class="cal-bar">
            <button type="button" class="icon-btn" id="calPrev" aria-label="Предыдущий месяц">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <h3 id="calTitle"></h3>
            <button type="button" class="icon-btn" id="calNext" aria-label="Следующий месяц">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" id="calToday">Сегодня</button>
          </div>
          <div class="cal" id="cal"></div>
        </div>
        <p class="empty" id="empty" hidden>Ничего не нашлось. Попробуйте изменить поиск или фильтры.</p>

        <noscript>
          <div class="grid">
            <?php foreach ($noscriptList as $e): ?>
              <div class="card">
                <div class="card-body">
                  <span class="pill"><?= h($e['catName']) ?></span>
                  <h3><?= h($e['title']) ?></h3>
                  <div class="meta"><?= h($fmtDate($e['start'])) ?><?= $e['place'] !== '' ? ' · ' . h($e['place']) : '' ?></div>
                  <?php if ($e['excerpt'] !== ''): ?><p><?= h($e['excerpt']) ?></p><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </noscript>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap">
      <span>© <?= date('Y') ?> ГУ ДПО «Институт развития образования Забайкальского края», Чита</span>
      <span><a href="<?= h((string) $cfg['ecosystem_url']) ?>">Экосистема ИРО</a> · <a class="admin-link" href="admin/">Вход для редакторов</a></span>
    </div>
  </footer>

  <dialog class="modal" id="modal" aria-labelledby="mTitle">
    <div class="modal-media" id="mMedia"></div>
    <button type="button" class="modal-close" id="mClose" aria-label="Закрыть">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <div class="modal-body">
      <span class="pill" id="mCat"></span>
      <h2 id="mTitle"></h2>
      <div class="modal-meta" id="mMeta"></div>
      <div class="modal-text" id="mText"></div>
      <div class="program" id="mProgram" hidden></div>
      <div class="modal-actions">
        <a class="btn btn-primary" id="mOpen" href="#" target="_blank" rel="noopener">Подробнее</a>
        <a class="btn btn-ghost" id="mIcs" href="#">Добавить в календарь</a>
        <button type="button" class="btn btn-ghost" id="mShare">Скопировать ссылку</button>
      </div>
    </div>
  </dialog>

  <?php if ($failed): ?>
    <div class="banner-error wrap">Витрина временно недоступна. Попробуйте обновить страницу позже.</div>
  <?php endif; ?>

  <script id="vitrina-data" type="application/json"><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script src="assets/vitrina.js?v=<?= (int) @filemtime(__DIR__ . '/assets/vitrina.js') ?>"></script>
</body>
</html>
