<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$uploads = planner_upload_root();
$orgDir = $uploads . '/' . $orgId;
$storageErr = planner_upload_storage_error();

json_response([
    'ok' => true,
    'upload_root' => $uploads,
    'upload_root_exists' => is_dir($uploads),
    'upload_root_writable' => is_dir($uploads) && is_writable($uploads),
    'org_dir' => $orgDir,
    'org_dir_writable' => is_dir($orgDir) ? is_writable($orgDir) : null,
    'storage_error' => $storageErr,
    'task_attachments_table' => planner_table_exists('task_attachments'),
    'php' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'file_uploads' => ini_get('file_uploads'),
    ],
    'finfo_available' => class_exists('finfo'),
]);
