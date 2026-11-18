<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('X-Content-Type-Options: nosniff');

$user = current_user();
if (!$user) {
    json_response(['ok' => false, 'error' => 'unauthorized'], 401);
}

$loginBlock = planner_login_block_message($user);
if ($loginBlock !== null) {
    unset($_SESSION['user_id']);
    json_response(['ok' => false, 'error' => 'account_blocked', 'message' => $loginBlock], 403);
}

planner_maintenance_enforce_api($user);

planner_require_csrf_for_mutating_api();

$orgId = (int) $user['organization_id'];
$userId = (int) $user['id'];
