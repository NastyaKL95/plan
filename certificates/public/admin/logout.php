<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/app.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}
redirect('admin/login.php');
