<?php
declare(strict_types=1);

function planner_upload_root(): string
{
    return dirname(__DIR__) . '/storage/uploads';
}

function planner_ensure_upload_dir(string $subdir): string
{
    $root = planner_upload_root();
    $path = $root . '/' . trim(str_replace(['..', '\\'], '', $subdir), '/');
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create upload directory');
        }
    }
    return $path;
}

function planner_random_filename(): string
{
    return bin2hex(random_bytes(16));
}

/** null — ок; иначе код ошибки для API (storage_not_writable, cannot_create_storage). */
function planner_upload_storage_error(): ?string
{
    $uploads = planner_upload_root();
    $base = dirname($uploads);
    foreach ([$base, $uploads] as $dir) {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return 'cannot_create_storage';
            }
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
        }
        if (!is_writable($dir)) {
            return 'storage_not_writable';
        }
    }

    return null;
}
