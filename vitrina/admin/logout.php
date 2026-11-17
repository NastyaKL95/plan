<?php
declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';

admin_check_csrf();
$_SESSION = [];
session_destroy();
admin_redirect('login.php');
