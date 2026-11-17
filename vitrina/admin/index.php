<?php
declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';

$user = admin_require();
$db = vitrina_db();
$categories = vitrina_categories();
$catById = array_column($categories, null, 'id');

$q = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['cat'] ?? 0);
$status = in_array($_GET['status'] ?? '', ['published', 'draft'], true) ? $_GET['status'] : '';
$period = in_array($_GET['period'] ?? '', ['upcoming', 'past'], true) ? $_GET['period'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$today = date('Y-m-d');

$where = [];
$args = [];
if ($q !== '') {
    $where[] = '(e.title LIKE ? OR e.place LIKE ? OR e.description LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($args, $like, $like, $like);
}
if ($cat > 0) {
    $where[] = 'e.category_id = ?';
    $args[] = $cat;
}
if ($status !== '') {
    $where[] = 'e.is_published = ' . ($status === 'published' ? 1 : 0);
}
if ($period === 'upcoming') {
    $where[] = 'e.end_date >= ?';
    $args[] = $today;
} elseif ($period === 'past') {
    $where[] = 'e.end_date < ?';
    $args[] = $today;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order = $period === 'upcoming' ? 'e.start_date, e.start_time' : 'e.start_date DESC, e.start_time DESC';

$st = $db->prepare('SELECT COUNT(*) FROM ' . vitrina_table('events') . " e $whereSql");
$st->execute($args);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);

$st = $db->prepare('SELECT e.* FROM ' . vitrina_table('events') . " e $whereSql ORDER BY $order, e.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$st->execute($args);
$events = $st->fetchAll();

$stats = $db->prepare('SELECT COUNT(*) total, COALESCE(SUM(is_published), 0) published, COALESCE(SUM(end_date >= ? AND is_published = 1), 0) upcoming FROM ' . vitrina_table('events'));
$stats->execute([$today]);
$stats = $stats->fetch();

$months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
$fmt = static function (string $date) use ($months): string {
    $d = new DateTimeImmutable($date);
    return $d->format('j') . ' ' . $months[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
};
$filterUrl = static function (array $change) use ($q, $cat, $status, $period): string {
    $p = array_filter(array_replace(['q' => $q, 'cat' => $cat ?: '', 'status' => $status, 'period' => $period], $change), static fn ($v) => $v !== '' && $v !== null);
    return 'index.php' . ($p ? '?' . http_build_query($p) : '');
};
$returnQs = (string) ($_SERVER['QUERY_STRING'] ?? '');

admin_header('События', $user, 'events');
?>
<div class="a-head">
  <div>
    <h1>События</h1>
    <p class="a-muted">Всего <?= (int) $stats['total'] ?>, опубликовано <?= (int) $stats['published'] ?>, предстоящих на сайте <?= (int) $stats['upcoming'] ?>.</p>
  </div>
  <a class="a-btn a-btn-primary" href="event.php">+ Добавить событие</a>
</div>

<form class="a-filters" method="get">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Поиск по названию, месту, описанию" aria-label="Поиск">
  <select name="cat" aria-label="Тип">
    <option value="">Все типы</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= (int) $c['id'] ?>"<?= $cat === (int) $c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="period" aria-label="Период">
    <option value="">Любые даты</option>
    <option value="upcoming"<?= $period === 'upcoming' ? ' selected' : '' ?>>Предстоящие</option>
    <option value="past"<?= $period === 'past' ? ' selected' : '' ?>>Прошедшие</option>
  </select>
  <select name="status" aria-label="Статус">
    <option value="">Все статусы</option>
    <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Опубликованные</option>
    <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Черновики</option>
  </select>
  <button type="submit" class="a-btn">Найти</button>
  <?php if ($q !== '' || $cat || $status !== '' || $period !== ''): ?><a class="a-link" href="index.php">Сбросить</a><?php endif; ?>
</form>

<?php if ($events === []): ?>
  <div class="a-empty">
    <?php if ($total === 0 && $where === []): ?>
      <h2>Событий пока нет</h2>
      <p>Добавьте первое событие — оно появится на витрине сразу после публикации.</p>
      <a class="a-btn a-btn-primary" href="event.php">+ Добавить событие</a>
    <?php else: ?>
      <p>По этим условиям ничего не нашлось.</p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th class="a-col-img"></th><th>Событие</th><th>Дата</th><th>Статус</th><th class="a-col-actions"><span class="a-sr">Действия</span></th></tr></thead>
      <tbody>
        <?php foreach ($events as $e):
            $c = $catById[(int) $e['category_id']] ?? null;
            $isPast = $e['end_date'] < $today;
            ?>
          <tr<?= $isPast ? ' class="is-past"' : '' ?>>
            <td class="a-col-img">
              <?php if ($e['image_thumb'] !== ''): ?>
                <img src="../uploads/<?= h($e['image_thumb']) ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="a-thumb-ph" style="--c:<?= h($c['color'] ?? '#64748b') ?>"><?= vitrina_icon_svg($c['icon'] ?? 'other', 22) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <a class="a-title" href="event.php?id=<?= (int) $e['id'] ?><?= $returnQs !== '' ? '&amp;return=' . h(rawurlencode($returnQs)) : '' ?>"><?= h($e['title']) ?></a>
              <div class="a-sub">
                <?php if ($c): ?><span class="a-dot" style="--c:<?= h($c['color']) ?>"></span><?= h($c['name']) ?><?php endif; ?>
                <?php if (vitrina_place_text($e) !== ''): ?> · <?= h(vitrina_place_text($e)) ?><?php endif; ?>
              </div>
            </td>
            <td class="a-nowrap">
              <?= h($fmt($e['start_date'])) ?><?= $e['end_date'] !== $e['start_date'] ? ' — ' . h($fmt($e['end_date'])) : '' ?>
              <?php if (vitrina_time_text($e) !== ''): ?><div class="a-sub"><?= h(vitrina_time_text($e)) ?></div><?php endif; ?>
            </td>
            <td>
              <?php if ($e['is_published']): ?><span class="a-badge a-badge-ok">На сайте</span><?php else: ?><span class="a-badge">Черновик</span><?php endif; ?>
              <?php if ($e['is_pinned']): ?><span class="a-badge a-badge-pin" title="Закреплено на главной">★</span><?php endif; ?>
              <?php if ($isPast): ?><div class="a-sub">прошло</div><?php endif; ?>
            </td>
            <td class="a-col-actions">
              <form method="post" action="event.php" class="a-row-actions">
                <?= admin_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                <input type="hidden" name="return" value="<?= h($returnQs) ?>">
                <a class="a-btn a-btn-sm" href="event.php?id=<?= (int) $e['id'] ?><?= $returnQs !== '' ? '&amp;return=' . h(rawurlencode($returnQs)) : '' ?>">Изменить</a>
                <button type="submit" class="a-btn a-btn-sm" name="action" value="toggle"><?= $e['is_published'] ? 'Снять' : 'Опубликовать' ?></button>
                <button type="submit" class="a-btn a-btn-sm a-btn-danger" name="action" value="delete" data-confirm="Удалить событие «<?= h($e['title']) ?>»? Это нельзя отменить.">Удалить</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="a-pager" aria-label="Страницы">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="<?= h($filterUrl(['page' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?php admin_footer();
