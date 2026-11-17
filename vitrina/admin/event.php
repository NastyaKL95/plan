<?php
declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/images.php';

$user = admin_require();
$db = vitrina_db();
$T = vitrina_table('events');
$categories = vitrina_categories();
$catIds = array_map('intval', array_column($categories, 'id'));

function event_find(int $id): ?array
{
    $st = vitrina_db()->prepare('SELECT * FROM ' . vitrina_table('events') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Удаляет файлы картинки, если на них больше не ссылается ни одно событие. */
function event_release_images(array $e): void
{
    foreach (['image', 'image_thumb'] as $col) {
        $path = (string) $e[$col];
        if ($path === '') {
            continue;
        }
        $st = vitrina_db()->prepare('SELECT COUNT(*) FROM ' . vitrina_table('events') . " WHERE $col = ? AND id <> ?");
        $st->execute([$path, (int) $e['id']]);
        if ((int) $st->fetchColumn() === 0) {
            vitrina_delete_upload($path);
        }
    }
}

function event_valid_date(string $s): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    return $d !== false && $d->format('Y-m-d') === $s;
}

function event_valid_time(string $s): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s);
}

$returnQs = (string) ($_POST['return'] ?? $_GET['return'] ?? '');
$returnQs = preg_match('/^[\w%=&.+\-\[\]]*$/u', $returnQs) ? $returnQs : '';
$listUrl = 'index.php' . ($returnQs !== '' ? '?' . $returnQs : '');

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$event = $id > 0 ? event_find($id) : null;
if ($id > 0 && $event === null) {
    admin_flash('Событие не найдено — возможно, его уже удалили.', 'error');
    admin_redirect($listUrl);
}

$errors = [];
$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_check_csrf();

    if ($action === 'delete' && $event !== null) {
        $db->prepare("DELETE FROM $T WHERE id = ?")->execute([$id]);
        event_release_images($event);
        admin_flash('Событие «' . $event['title'] . '» удалено.');
        admin_redirect($listUrl);
    }
    if ($action === 'toggle' && $event !== null) {
        $db->prepare("UPDATE $T SET is_published = 1 - is_published, updated_at = ?, updated_by = ? WHERE id = ?")->execute([vitrina_now(), $user['id'], $id]);
        admin_flash($event['is_published'] ? 'Событие снято с сайта.' : 'Событие опубликовано.');
        admin_redirect($listUrl);
    }
    if ($action === 'duplicate' && $event !== null) {
        $copy = $event;
        unset($copy['id']);
        $copy['title'] = mb_substr($event['title'] . ' (копия)', 0, 300);
        $copy['is_published'] = 0;
        $copy['is_pinned'] = 0;
        $copy['created_at'] = $copy['updated_at'] = vitrina_now();
        $copy['created_by'] = $copy['updated_by'] = $user['id'];
        $cols = array_keys($copy);
        $db->prepare("INSERT INTO $T (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($copy));
        admin_flash('Создана копия в черновиках. Поправьте даты и опубликуйте.');
        admin_redirect('event.php?id=' . (int) $db->lastInsertId());
    }

    if ($action === 'save') {
        $in = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'start_date' => trim((string) ($_POST['start_date'] ?? '')),
            'start_time' => trim((string) ($_POST['start_time'] ?? '')),
            'end_date' => trim((string) ($_POST['end_date'] ?? '')),
            'end_time' => trim((string) ($_POST['end_time'] ?? '')),
            'format' => (string) ($_POST['format'] ?? 'offline'),
            'place' => trim((string) ($_POST['place'] ?? '')),
            'excerpt' => trim((string) preg_replace('~\s+~u', ' ', (string) ($_POST['excerpt'] ?? ''))),
            'description' => trim(str_replace("\r\n", "\n", (string) ($_POST['description'] ?? ''))),
            'link_url' => trim((string) ($_POST['link_url'] ?? '')),
            'link_label' => trim((string) ($_POST['link_label'] ?? '')),
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
        ];
        if ($in['end_date'] === '') {
            $in['end_date'] = $in['start_date'];
        }

        if ($in['title'] === '') {
            $errors['title'] = 'Укажите название.';
        } elseif (mb_strlen($in['title']) > 300) {
            $errors['title'] = 'Название длиннее 300 символов.';
        }
        if (!in_array($in['category_id'], $catIds, true)) {
            $errors['category_id'] = 'Выберите тип события.';
        }
        if (!event_valid_date($in['start_date'])) {
            $errors['start_date'] = 'Укажите дату начала.';
        }
        if (!event_valid_date($in['end_date'])) {
            $errors['end_date'] = 'Некорректная дата окончания.';
        }
        foreach (['start_time', 'end_time'] as $f) {
            if ($in[$f] !== '' && !event_valid_time($in[$f])) {
                $errors[$f] = 'Время в формате ЧЧ:ММ.';
            }
        }
        if ($in['end_time'] !== '' && $in['start_time'] === '' && !isset($errors['end_time'])) {
            $errors['start_time'] = 'Укажите и время начала.';
        }
        if (!array_intersect_key($errors, array_flip(['start_date', 'end_date', 'start_time', 'end_time']))
            && $in['end_date'] . ' ' . ($in['end_time'] ?: '23:59') < $in['start_date'] . ' ' . ($in['start_time'] ?: '00:00')) {
            $errors['end_date'] = 'Окончание раньше начала.';
        }
        if (!isset(VITRINA_FORMATS[$in['format']])) {
            $in['format'] = 'offline';
        }
        if (mb_strlen($in['place']) > 300) {
            $errors['place'] = 'Место длиннее 300 символов.';
        }
        if (mb_strlen($in['excerpt']) > 400) {
            $errors['excerpt'] = 'Анонс длиннее 400 символов.';
        }
        if ($in['link_url'] !== '' && (!preg_match('~^https?://~i', $in['link_url']) || !filter_var($in['link_url'], FILTER_VALIDATE_URL) || strlen($in['link_url']) > 500)) {
            $errors['link_url'] = 'Ссылка должна начинаться с http:// или https://.';
        }
        if (mb_strlen($in['link_label']) > 60) {
            $errors['link_label'] = 'Текст кнопки длиннее 60 символов.';
        }

        $newImage = null;
        $upload = $_FILES['image'] ?? null;
        if (!$errors && $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newImage = vitrina_save_upload($upload);
            } catch (RuntimeException $ex) {
                $errors['image'] = $ex->getMessage();
            }
        }

        if (!$errors) {
            $row = $in;
            $row['start_time'] = $in['start_time'] !== '' ? $in['start_time'] . ':00' : null;
            $row['end_time'] = $in['end_time'] !== '' ? $in['end_time'] . ':00' : null;
            $row['image'] = $event['image'] ?? '';
            $row['image_thumb'] = $event['image_thumb'] ?? '';
            if ($newImage !== null || isset($_POST['remove_image'])) {
                [$row['image'], $row['image_thumb']] = $newImage ?? ['', ''];
            }
            $row['updated_at'] = vitrina_now();
            $row['updated_by'] = $user['id'];

            if ($event === null) {
                $row['created_at'] = $row['updated_at'];
                $row['created_by'] = $user['id'];
                $cols = array_keys($row);
                $db->prepare("INSERT INTO $T (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
                $id = (int) $db->lastInsertId();
            } else {
                $set = implode(', ', array_map(static fn ($c) => "$c = ?", array_keys($row)));
                $db->prepare("UPDATE $T SET $set WHERE id = ?")->execute([...array_values($row), $id]);
                if ($row['image'] !== $event['image']) {
                    event_release_images($event);
                }
            }
            admin_flash($in['is_published'] ? 'Сохранено и опубликовано.' : 'Сохранено в черновиках.');
            admin_redirect('event.php?id=' . $id . ($returnQs !== '' ? '&return=' . rawurlencode($returnQs) : ''));
        }
        $form = $in + ['image' => $event['image'] ?? '', 'image_thumb' => $event['image_thumb'] ?? ''];
    }
}

if (!isset($form)) {
    $form = $event ?? [
        'title' => '', 'category_id' => 0, 'start_date' => '', 'start_time' => null, 'end_date' => '', 'end_time' => null,
        'format' => 'offline', 'place' => '', 'excerpt' => '', 'description' => '', 'link_url' => '', 'link_label' => '',
        'is_published' => 1, 'is_pinned' => 0, 'image' => '', 'image_thumb' => '',
    ];
    $form['start_time'] = $form['start_time'] !== null ? substr((string) $form['start_time'], 0, 5) : '';
    $form['end_time'] = $form['end_time'] !== null ? substr((string) $form['end_time'], 0, 5) : '';
}

$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="a-error">' . h($errors[$f]) . '</span>' : '';
$inv = static fn (string $f): string => isset($errors[$f]) ? ' aria-invalid="true"' : '';
$maxMb = (int) vitrina_config()['upload_max_mb'];

admin_header($event ? 'Событие' : 'Новое событие', $user, 'events');
?>
<div class="a-head">
  <div>
    <a class="a-back" href="<?= h($listUrl) ?>">← Все события</a>
    <h1><?= $event ? h($event['title']) : 'Новое событие' ?></h1>
    <?php if ($event): ?>
      <p class="a-muted a-small">Создано <?= h(date('d.m.Y H:i', strtotime($event['created_at']))) ?>, изменено <?= h(date('d.m.Y H:i', strtotime($event['updated_at']))) ?></p>
    <?php endif; ?>
  </div>
  <?php if ($event): ?>
    <div class="a-head-actions">
      <?php if ($event['is_published']): ?><a class="a-btn" href="../#e-<?= (int) $event['id'] ?>" target="_blank" rel="noopener">Открыть на сайте</a><?php endif; ?>
      <form method="post">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
        <input type="hidden" name="return" value="<?= h($returnQs) ?>">
        <button type="submit" class="a-btn" name="action" value="duplicate">Дублировать</button>
        <button type="submit" class="a-btn a-btn-danger" name="action" value="delete" data-confirm="Удалить это событие? Это нельзя отменить.">Удалить</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
  <div class="a-flash a-flash-error" role="alert">Проверьте поля, отмеченные красным.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="a-event-form" novalidate>
  <?= admin_csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int) ($event['id'] ?? 0) ?>">
  <input type="hidden" name="return" value="<?= h($returnQs) ?>">

  <div class="a-col-main">
    <section class="a-card">
      <label class="a-field"><span>Название <span class="a-req">*</span></span>
        <input name="title" required maxlength="300" value="<?= h($form['title']) ?>"<?= $inv('title') ?> placeholder="Например: Региональная конференция «Педагог года»">
        <?= $err('title') ?>
      </label>
      <label class="a-field">Описание
        <textarea name="description" rows="12" placeholder="Программа, спикеры, условия участия. Пустая строка — новый абзац, ссылки станут кликабельными."><?= h($form['description']) ?></textarea>
      </label>
      <label class="a-field">Анонс для карточки
        <textarea name="excerpt" rows="2" maxlength="400" data-counter="400"<?= $inv('excerpt') ?> placeholder="Одно-два предложения. Если оставить пустым, возьмётся начало описания."><?= h($form['excerpt']) ?></textarea>
        <?= $err('excerpt') ?>
      </label>
    </section>

    <section class="a-card">
      <h2>Картинка</h2>
      <div class="a-image">
        <div class="a-image-preview" id="imagePreview">
          <?php if ($form['image'] !== ''): ?><img src="../uploads/<?= h($form['image_thumb'] ?: $form['image']) ?>" alt=""><?php else: ?><span>Нет картинки — на сайте будет цветная заглушка с иконкой типа</span><?php endif; ?>
        </div>
        <div>
          <label class="a-field">Загрузить новую
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-max-mb="<?= $maxMb ?>"<?= $inv('image') ?>>
            <small class="a-hint">JPG, PNG, WebP или GIF до <?= $maxMb ?> МБ. Лучше горизонтальная, 16:9, от 1200 px в ширину.</small>
            <?= $err('image') ?>
          </label>
          <?php if ($form['image'] !== ''): ?>
            <label class="a-check"><input type="checkbox" name="remove_image" value="1"> Убрать картинку</label>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <aside class="a-col-side">
    <section class="a-card">
      <label class="a-check a-check-strong"><input type="checkbox" name="is_published" value="1"<?= $form['is_published'] ? ' checked' : '' ?>> Опубликовать на сайте</label>
      <label class="a-check"><input type="checkbox" name="is_pinned" value="1"<?= $form['is_pinned'] ? ' checked' : '' ?>> Закрепить на главной</label>
      <small class="a-hint">Закреплённое предстоящее событие показывается в большом блоке на первом экране вместо ближайшего.</small>
      <button type="submit" class="a-btn a-btn-primary a-btn-block">Сохранить</button>
    </section>

    <section class="a-card">
      <label class="a-field"><span>Тип события <span class="a-req">*</span></span>
        <select name="category_id" required<?= $inv('category_id') ?>>
          <option value="">— выберите —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= (int) $form['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $err('category_id') ?>
      </label>
      <div class="a-grid2">
        <label class="a-field"><span>Начало <span class="a-req">*</span></span>
          <input type="date" name="start_date" id="startDate" required value="<?= h($form['start_date']) ?>"<?= $inv('start_date') ?>>
          <?= $err('start_date') ?>
        </label>
        <label class="a-field">Время
          <input type="time" name="start_time" value="<?= h($form['start_time']) ?>"<?= $inv('start_time') ?>>
          <?= $err('start_time') ?>
        </label>
        <label class="a-field">Окончание
          <input type="date" name="end_date" id="endDate" value="<?= h($form['end_date']) ?>"<?= $inv('end_date') ?>>
          <?= $err('end_date') ?>
        </label>
        <label class="a-field">Время
          <input type="time" name="end_time" value="<?= h($form['end_time']) ?>"<?= $inv('end_time') ?>>
          <?= $err('end_time') ?>
        </label>
      </div>
      <small class="a-hint">Время читинское. Без времени событие считается на весь день; если окончание не указано, событие однодневное.</small>
    </section>

    <section class="a-card">
      <fieldset class="a-field">
        <legend>Формат</legend>
        <div class="a-seg">
          <?php foreach (VITRINA_FORMATS as $key => $label): ?>
            <label><input type="radio" name="format" value="<?= h($key) ?>"<?= $form['format'] === $key ? ' checked' : '' ?>><span><?= h($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label class="a-field">Место или адрес
        <input name="place" maxlength="300" value="<?= h($form['place']) ?>"<?= $inv('place') ?> placeholder="г. Чита, ул. Красной Звезды, 51а, ауд. 322">
        <?= $err('place') ?>
      </label>
    </section>

    <section class="a-card">
      <label class="a-field">Ссылка для кнопки
        <input type="url" name="link_url" maxlength="500" value="<?= h($form['link_url']) ?>"<?= $inv('link_url') ?> placeholder="https://… регистрация, трансляция, положение">
        <?= $err('link_url') ?>
      </label>
      <label class="a-field">Текст кнопки
        <input name="link_label" maxlength="60" value="<?= h($form['link_label']) ?>"<?= $inv('link_label') ?> placeholder="Регистрация" list="linkLabels">
        <datalist id="linkLabels"><option value="Регистрация"><option value="Трансляция"><option value="Положение"><option value="Программа"><option value="Подробнее"></datalist>
        <?= $err('link_label') ?>
      </label>
    </section>
  </aside>
</form>
<?php admin_footer();
