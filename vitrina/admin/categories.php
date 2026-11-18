<?php
declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';

$user = admin_require();
$db = vitrina_db();
$T = vitrina_table('categories');
$icons = vitrina_icons();

function category_slug(string $name): string
{
    $map = ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya'];
    $slug = strtr(mb_strtolower($name), $map);
    $slug = trim((string) preg_replace('~[^a-z0-9]+~', '-', $slug), '-');
    return substr($slug !== '' ? $slug : 'type', 0, 48);
}

$errors = [];
$editId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_check_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $st = $db->prepare('SELECT COUNT(*) FROM ' . vitrina_table('events') . ' WHERE category_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            admin_flash('Нельзя удалить тип, пока у него есть события. Сначала переведите их в другой тип.', 'error');
        } else {
            $db->prepare("DELETE FROM $T WHERE id = ?")->execute([$id]);
            admin_flash('Тип удалён.');
        }
        admin_redirect('categories.php');
    }

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $color = strtolower(trim((string) ($_POST['color'] ?? '')));
        $icon = (string) ($_POST['icon'] ?? 'other');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Название обязательно, не длиннее 100 символов.';
        }
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $errors[] = 'Цвет в формате #rrggbb.';
        }
        if (!isset($icons[$icon])) {
            $icon = 'other';
        }
        if (!$errors) {
            if ($id > 0) {
                $db->prepare("UPDATE $T SET name = ?, color = ?, icon = ?, sort_order = ? WHERE id = ?")->execute([$name, $color, $icon, $sort, $id]);
                admin_flash('Тип «' . $name . '» сохранён.');
            } else {
                $base = category_slug($name);
                $slug = $base;
                $exists = $db->prepare("SELECT COUNT(*) FROM $T WHERE slug = ?");
                for ($i = 2; $exists->execute([$slug]) && (int) $exists->fetchColumn() > 0; $i++) {
                    $slug = $base . '-' . $i;
                }
                $db->prepare("INSERT INTO $T (slug, name, color, icon, sort_order) VALUES (?, ?, ?, ?, ?)")->execute([$slug, $name, $color, $icon, $sort]);
                admin_flash('Тип «' . $name . '» добавлен.');
            }
            admin_redirect('categories.php');
        }
        $editId = $id;
    }
}

$rows = $db->query("SELECT c.*, (SELECT COUNT(*) FROM " . vitrina_table('events') . " e WHERE e.category_id = c.id) AS cnt FROM $T c ORDER BY c.sort_order, c.name")->fetchAll();
$nextSort = $rows ? max(array_column($rows, 'sort_order')) + 10 : 10;

$fields = static function (array $c) use ($icons): string {
    ob_start(); ?>
    <label class="a-field">Название во множественном числе<input name="name" required maxlength="100" value="<?= h($c['name']) ?>" placeholder="Например: Мастер-классы"></label>
    <label class="a-field a-field-color">Цвет<input type="color" name="color" value="<?= h($c['color']) ?>"></label>
    <fieldset class="a-field">
      <legend>Иконка</legend>
      <div class="a-icons" style="--c:<?= h($c['color']) ?>">
        <?php foreach ($icons as $key => [$label]): ?>
          <label title="<?= h($label) ?>"><input type="radio" name="icon" value="<?= h($key) ?>"<?= $c['icon'] === $key ? ' checked' : '' ?>><span><?= vitrina_icon_svg($key, 22) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label class="a-field a-field-sort">Порядок<input type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>" step="10"></label>
    <?php return (string) ob_get_clean();
};

admin_header('Типы событий', $user, 'categories');
?>
<div class="a-head">
  <div>
    <h1>Типы событий</h1>
    <p class="a-muted">Типы видны на витрине как фильтры. Цвет и иконка используются в карточках, календаре и заглушках без картинки.</p>
  </div>
</div>
<?php if ($errors): ?><div class="a-flash a-flash-error" role="alert"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="a-cats">
  <?php foreach ($rows as $c): ?>
    <details class="a-card a-cat"<?= $editId === (int) $c['id'] ? ' open' : '' ?>>
      <summary>
        <span class="a-thumb-ph" style="--c:<?= h($c['color']) ?>"><?= vitrina_icon_svg($c['icon'], 22) ?></span>
        <span class="a-cat-name"><b><?= h($c['name']) ?></b><small><?= (int) $c['cnt'] ?> <?= vitrina_plural((int) $c['cnt'], 'событие', 'события', 'событий') ?> · в адресе ?cat=<?= h($c['slug']) ?></small></span>
        <span class="a-link">Изменить</span>
      </summary>
      <form method="post" class="a-cat-form">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <?= $fields($c) ?>
        <div class="a-form-actions">
          <button type="submit" class="a-btn a-btn-primary" name="action" value="save">Сохранить</button>
          <?php if ((int) $c['cnt'] === 0): ?>
            <button type="submit" class="a-btn a-btn-danger" name="action" value="delete" formnovalidate data-confirm="Удалить тип «<?= h($c['name']) ?>»?">Удалить</button>
          <?php else: ?>
            <span class="a-hint">Тип с событиями удалить нельзя.</span>
          <?php endif; ?>
        </div>
      </form>
    </details>
  <?php endforeach; ?>

  <details class="a-card a-cat a-cat-new"<?= $editId === 0 && $errors ? ' open' : '' ?>>
    <summary><span class="a-thumb-ph a-thumb-add">+</span><span class="a-cat-name"><b>Добавить тип</b></span></summary>
    <form method="post" class="a-cat-form">
      <?= admin_csrf_field() ?>
      <?= $fields(['name' => (string) ($_POST['name'] ?? ''), 'color' => '#0ea5e9', 'icon' => 'other', 'sort_order' => $nextSort]) ?>
      <div class="a-form-actions"><button type="submit" class="a-btn a-btn-primary" name="action" value="save">Добавить</button></div>
    </form>
  </details>
</div>
<?php admin_footer();
