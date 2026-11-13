<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

if (!planner_table_exists('project_portfolios') || !planner_table_exists('portfolio_projects')) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$method = effective_request_method();
if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$body = read_json_body();
$projectId = (int) ($body['project_id'] ?? 0);
$targetPortfolioId = (int) ($body['target_portfolio_id'] ?? 0);
$sourcePortfolioId = (int) ($body['source_portfolio_id'] ?? 0);
$mode = trim((string) ($body['mode'] ?? 'move'));

if ($projectId <= 0) {
    json_response(['ok' => false, 'error' => 'project_id_required'], 422);
}

$chkProject = db()->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1');
$chkProject->execute([$projectId, $orgId]);
if (!$chkProject->fetch(PDO::FETCH_ASSOC)) {
    json_response(['ok' => false, 'error' => 'bad_project'], 422);
}

$checkPf = static function (int $id, int $orgId): bool {
    if ($id <= 0) {
        return false;
    }
    $st = db()->prepare('SELECT id FROM project_portfolios WHERE id = ? AND organization_id = ? LIMIT 1');
    $st->execute([$id, $orgId]);
    return (bool) $st->fetch(PDO::FETCH_ASSOC);
};

if ($targetPortfolioId > 0 && !$checkPf($targetPortfolioId, $orgId)) {
    json_response(['ok' => false, 'error' => 'bad_target_portfolio'], 422);
}
if ($sourcePortfolioId > 0 && !$checkPf($sourcePortfolioId, $orgId)) {
    json_response(['ok' => false, 'error' => 'bad_source_portfolio'], 422);
}

if ($mode === 'detach') {
    if ($sourcePortfolioId > 0) {
        db()->prepare('DELETE FROM portfolio_projects WHERE portfolio_id = ? AND project_id = ?')
            ->execute([$sourcePortfolioId, $projectId]);
    } else {
        db()->prepare('DELETE pp FROM portfolio_projects pp
                       INNER JOIN project_portfolios pf ON pf.id = pp.portfolio_id
                       WHERE pf.organization_id = ? AND pp.project_id = ?')
            ->execute([$orgId, $projectId]);
    }
    json_response(['ok' => true]);
}

if ($mode === 'attach') {
    if ($targetPortfolioId <= 0) {
        json_response(['ok' => false, 'error' => 'target_portfolio_id_required'], 422);
    }
    db()->prepare('INSERT IGNORE INTO portfolio_projects (portfolio_id, project_id) VALUES (?,?)')
        ->execute([$targetPortfolioId, $projectId]);
    json_response(['ok' => true]);
}

// move (default)
if ($sourcePortfolioId > 0) {
    db()->prepare('DELETE FROM portfolio_projects WHERE portfolio_id = ? AND project_id = ?')
        ->execute([$sourcePortfolioId, $projectId]);
}
if ($targetPortfolioId > 0) {
    db()->prepare('INSERT IGNORE INTO portfolio_projects (portfolio_id, project_id) VALUES (?,?)')
        ->execute([$targetPortfolioId, $projectId]);
}
json_response(['ok' => true]);

