<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const VITRINA_UPLOADS = VITRINA_ROOT . '/uploads';

/**
 * Принимает загруженную картинку, пересохраняет её в JPEG (большая до 1600 px и превью до 720 px)
 * и возвращает пути относительно uploads/. Пересохранение отбрасывает всё, кроме пикселей.
 *
 * @return array{0: string, 1: string}
 */
function vitrina_save_upload(array $file): array
{
    $maxMb = (int) vitrina_config()['upload_max_mb'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(match ((int) $file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Картинка слишком большая.',
            default => 'Не удалось загрузить картинку.',
        });
    }
    if ($file['size'] > $maxMb * 1024 * 1024) {
        throw new RuntimeException('Картинка больше ' . $maxMb . ' МБ.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp', IMAGETYPE_GIF => 'imagecreatefromgif'];
    if ($info === false || !isset($types[$info[2]])) {
        throw new RuntimeException('Подходят только картинки JPG, PNG, WebP или GIF.');
    }
    if ($info[0] * $info[1] > 40_000_000) {
        throw new RuntimeException('Картинка слишком большая по размеру в пикселях.');
    }
    $src = @$types[$info[2]]($file['tmp_name']);
    if (!$src) {
        throw new RuntimeException('Картинка повреждена.');
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle !== 0) {
            $src = imagerotate($src, $angle, 0);
        }
    }

    $dir = date('Y/m');
    if (!is_dir(VITRINA_UPLOADS . '/' . $dir) && !mkdir(VITRINA_UPLOADS . '/' . $dir, 0775, true) && !is_dir(VITRINA_UPLOADS . '/' . $dir)) {
        throw new RuntimeException('Нет прав на запись в папку uploads.');
    }
    $name = $dir . '/' . bin2hex(random_bytes(8));
    vitrina_write_jpeg($src, VITRINA_UPLOADS . '/' . $name . '.jpg', 1600);
    vitrina_write_jpeg($src, VITRINA_UPLOADS . '/' . $name . '-sm.jpg', 720);
    imagedestroy($src);
    return [$name . '.jpg', $name . '-sm.jpg'];
}

function vitrina_write_jpeg(GdImage $src, string $path, int $maxWidth): void
{
    $w = imagesx($src);
    $h = imagesy($src);
    $nw = min($w, $maxWidth);
    $nh = max(1, (int) round($h * $nw / $w));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($dst, true);
    if (!imagejpeg($dst, $path, 84)) {
        throw new RuntimeException('Не удалось сохранить картинку.');
    }
    imagedestroy($dst);
}

function vitrina_delete_upload(string $path): void
{
    if ($path !== '' && preg_match('~^\d{4}/\d{2}/[a-f0-9]{16}(-sm)?\.jpg$~', $path)) {
        @unlink(VITRINA_UPLOADS . '/' . $path);
    }
}
