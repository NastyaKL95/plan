<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_has_column('organizations', 'work_year')) {
    json_response(['ok' => false, 'error' => 'schema'], 503);
}

$hasDueTime = planner_table_has_column('organizations', 'default_due_time');
$method = effective_request_method();

if ($method === 'GET') {
    $cols = 'name, work_year';
    if ($hasDueTime) {
        $cols .= ', default_due_time';
    }
    $st = db()->prepare("SELECT {$cols} FROM organizations WHERE id = ? LIMIT 1");
    $st->execute([$orgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    $dueHm = '18:00';
    if ($hasDueTime && isset($row['default_due_time'])) {
        $raw = trim((string) $row['default_due_time']);
        if ($raw !== '' && planner_validate_org_due_time_hm($raw)) {
            $dueHm = $raw;
        }
    }
    $maintenance = planner_maintenance_state($orgId);
    json_response([
        'ok' => true,
        'name' => (string) $row['name'],
        'work_year' => (int) $row['work_year'],
        'default_due_time' => $dueHm,
        'default_due_time_hms' => planner_org_default_due_time_hms($orgId),
        'maintenance_enabled' => $maintenance['enabled'],
        'maintenance_message' => $maintenance['message'],
    ]);
}

if ($method === 'PATCH' || $method === 'PUT') {
    if (!user_may_manage_departments($user)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $body = read_json_body();
    $updates = [];
    $params = [];
    $logParts = [];
    $maintenanceOut = null;

    if (array_key_exists('maintenance_enabled', $body) || array_key_exists('maintenance_message', $body)) {
        if (!user_is_organization_admin($user)) {
            json_response(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $fields = [];
        if (array_key_exists('maintenance_enabled', $body)) {
            $fields['enabled'] = (bool) $body['maintenance_enabled'];
        }
        if (array_key_exists('maintenance_message', $body)) {
            $fields['message'] = (string) $body['maintenance_message'];
        }
        $maintenanceOut = planner_maintenance_save($orgId, $fields);
        $logParts[] = $maintenanceOut['enabled'] ? 'обслуживание включено' : 'обслуживание выключено';
    }

    if (array_key_exists('work_year', $body)) {
        $y = (int) $body['work_year'];
        if ($y < 1990 || $y > 2100) {
            json_response(['ok' => false, 'error' => 'bad_year'], 422);
        }
        $updates[] = 'work_year = ?';
        $params[] = $y;
        $logParts[] = 'год ' . $y;
    }

    if ($hasDueTime && array_key_exists('default_due_time', $body)) {
        $hm = trim((string) $body['default_due_time']);
        if (!planner_validate_org_due_time_hm($hm)) {
            json_response(['ok' => false, 'error' => 'bad_due_time'], 422);
        }
        $updates[] = 'default_due_time = ?';
        $params[] = $hm;
        $logParts[] = 'срок по умолчанию ' . $hm;
    }

    if ($updates === [] && $maintenanceOut === null) {
        json_response(['ok' => false, 'error' => 'no_fields'], 400);
    }

    if ($updates !== []) {
        $params[] = $orgId;
        db()->prepare('UPDATE organizations SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    }
    log_activity(
        $orgId,
        $userId,
        'update',
        'organization',
        $orgId,
        'Настройки организации: ' . implode(', ', $logParts)
    );

    $out = ['ok' => true];
    if (array_key_exists('work_year', $body)) {
        $out['work_year'] = (int) $body['work_year'];
    }
    if ($hasDueTime && array_key_exists('default_due_time', $body)) {
        $out['default_due_time'] = trim((string) $body['default_due_time']);
        $out['default_due_time_hms'] = planner_org_default_due_time_hms($orgId);
    }
    if ($maintenanceOut !== null) {
        $out['maintenance_enabled'] = $maintenanceOut['enabled'];
        $out['maintenance_message'] = $maintenanceOut['message'];
    }
    json_response($out);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
