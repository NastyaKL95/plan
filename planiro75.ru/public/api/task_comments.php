<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';
require_once dirname(__DIR__, 2) . '/includes/task_extras.php';
require_once dirname(__DIR__, 2) . '/includes/notifications.php';
require_once dirname(__DIR__, 2) . '/includes/max_notify.php';

$taskId = (int) ($_GET['task_id'] ?? 0);
if ($taskId <= 0) {
    json_response(['ok' => false, 'error' => 'task_id_required'], 422);
}

if (!task_visible_to_user($user, $taskId)) {
    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

if (!planner_table_exists('task_comments')) {
    json_response([
        'ok' => true,
        'items' => [],
        'has_more_older' => false,
        'oldest_id' => null,
        'limit' => 80,
    ]);
}

$method = effective_request_method();

if ($method === 'GET') {
    $pageLimit = isset($_GET['limit']) ? (int) $_GET['limit'] : 80;
    $pageLimit = max(1, min(150, $pageLimit));
    $beforeId = isset($_GET['before_id']) ? (int) $_GET['before_id'] : 0;

    $wherePage = 'c.task_id = ?';
    $pageParams = [$taskId];
    if ($beforeId > 0) {
        $wherePage .= ' AND c.id < ?';
        $pageParams[] = $beforeId;
    }

    if (planner_table_exists('task_comment_reads')) {
        $sqlPage =
            'SELECT c.id, c.task_id, c.user_id, c.body, c.created_at, u.name AS user_name,
                    CASE WHEN c.user_id = ? THEN 1
                         WHEN cr.user_id IS NOT NULL THEN 1
                         ELSE 0 END AS is_read
             FROM task_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN task_comment_reads cr ON cr.comment_id = c.id AND cr.user_id = ?
             WHERE ' . $wherePage . '
             ORDER BY c.id DESC
             LIMIT ' . $pageLimit;
        $st = db()->prepare($sqlPage);
        $st->execute(array_merge([$userId, $userId], $pageParams));
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        $items = array_reverse($items);

        $minCommentId = null;
        foreach ($items as $row) {
            $cid = (int) ($row['id'] ?? 0);
            if ($cid > 0 && ($minCommentId === null || $cid < $minCommentId)) {
                $minCommentId = $cid;
            }
        }
        $hasMoreOlder = false;
        if ($minCommentId !== null) {
            $stOlder = db()->prepare('SELECT 1 FROM task_comments WHERE task_id = ? AND id < ? LIMIT 1');
            $stOlder->execute([$taskId, $minCommentId]);
            $hasMoreOlder = (bool) $stOlder->fetch();
        }

        $recipientIds = [];
        $stTask = db()->prepare('SELECT author_id, assignee_id FROM tasks WHERE id = ? AND organization_id = ? LIMIT 1');
        $stTask->execute([$taskId, $orgId]);
        $taskRow = $stTask->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['author_id', 'assignee_id'] as $col) {
            $rid = isset($taskRow[$col]) ? (int) $taskRow[$col] : 0;
            if ($rid > 0 && $rid !== $userId) {
                $recipientIds[$rid] = true;
            }
        }
        if (planner_table_exists('task_participants')) {
            $stPart = db()->prepare('SELECT user_id FROM task_participants WHERE task_id = ?');
            $stPart->execute([$taskId]);
            foreach ($stPart->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                $rid = (int) ($pr['user_id'] ?? 0);
                if ($rid > 0 && $rid !== $userId) {
                    $recipientIds[$rid] = true;
                }
            }
        }
        $recipientList = array_map('intval', array_keys($recipientIds));

        $commentIdsOnPage = [];
        foreach ($items as $row) {
            $cid0 = (int) ($row['id'] ?? 0);
            if ($cid0 > 0) {
                $commentIdsOnPage[] = $cid0;
            }
        }
        $mentionsByComment = [];
        if (planner_table_exists('task_comment_mentions') && $commentIdsOnPage !== []) {
            $phM = implode(',', array_fill(0, count($commentIdsOnPage), '?'));
            $stM = db()->prepare("SELECT comment_id, user_id FROM task_comment_mentions WHERE comment_id IN ($phM)");
            $stM->execute($commentIdsOnPage);
            foreach ($stM->fetchAll(PDO::FETCH_ASSOC) as $mr) {
                $cidM = (int) ($mr['comment_id'] ?? 0);
                $uidM = (int) ($mr['user_id'] ?? 0);
                if ($cidM <= 0 || $uidM <= 0) {
                    continue;
                }
                if (!isset($mentionsByComment[$cidM])) {
                    $mentionsByComment[$cidM] = [];
                }
                $mentionsByComment[$cidM][] = $uidM;
            }
        }

        $mentionUserIdsAll = [];
        foreach ($mentionsByComment as $uids) {
            foreach ($uids as $uu) {
                if ($uu > 0) {
                    $mentionUserIdsAll[$uu] = true;
                }
            }
        }
        $mentionNameMap = [];
        if ($mentionUserIdsAll !== []) {
            $midList = array_map('intval', array_keys($mentionUserIdsAll));
            $phMn = implode(',', array_fill(0, count($midList), '?'));
            $stMn = db()->prepare("SELECT id, name FROM users WHERE organization_id = ? AND id IN ($phMn)");
            $stMn->execute(array_merge([$orgId], $midList));
            foreach ($stMn->fetchAll(PDO::FETCH_ASSOC) as $ur) {
                $mentionNameMap[(int) $ur['id']] = (string) ($ur['name'] ?? '');
            }
        }

        $myCommentIds = [];
        foreach ($items as $row) {
            if ((int) ($row['user_id'] ?? 0) === $userId) {
                $myCommentIds[] = (int) ($row['id'] ?? 0);
            }
        }

        $unionReaderIds = $recipientIds;
        foreach ($myCommentIds as $mcid) {
            foreach ($mentionsByComment[$mcid] ?? [] as $mu) {
                if ($mu > 0 && $mu !== $userId) {
                    $unionReaderIds[$mu] = true;
                }
            }
        }
        $unionReaderList = array_map('intval', array_keys($unionReaderIds));
        $idToName = [];
        if ($unionReaderList !== []) {
            $phUname = implode(',', array_fill(0, count($unionReaderList), '?'));
            $stUn = db()->prepare("SELECT id, name FROM users WHERE organization_id = ? AND id IN ($phUname)");
            $stUn->execute(array_merge([$orgId], $unionReaderList));
            foreach ($stUn->fetchAll(PDO::FETCH_ASSOC) as $ur) {
                $idToName[(int) $ur['id']] = (string) ($ur['name'] ?? '');
            }
        }

        $readMatrix = [];
        if ($myCommentIds !== [] && $unionReaderList !== []) {
            $phC = implode(',', array_fill(0, count($myCommentIds), '?'));
            $phU = implode(',', array_fill(0, count($unionReaderList), '?'));
            $sqlReaders = "SELECT comment_id, user_id
                           FROM task_comment_reads
                           WHERE comment_id IN ($phC) AND user_id IN ($phU)";
            $stReaders = db()->prepare($sqlReaders);
            $stReaders->execute(array_merge($myCommentIds, $unionReaderList));
            foreach ($stReaders->fetchAll(PDO::FETCH_ASSOC) as $rowReader) {
                $cidR = (int) ($rowReader['comment_id'] ?? 0);
                $ridR = (int) ($rowReader['user_id'] ?? 0);
                if ($cidR <= 0 || $ridR <= 0) {
                    continue;
                }
                if (!isset($readMatrix[$cidR])) {
                    $readMatrix[$cidR] = [];
                }
                $readMatrix[$cidR][$ridR] = true;
            }
        }

        foreach ($items as &$it) {
            $cidItem = (int) ($it['id'] ?? 0);
            $it['mentions'] = [];
            foreach ($mentionsByComment[$cidItem] ?? [] as $mu) {
                $it['mentions'][] = [
                    'user_id' => $mu,
                    'name' => $mentionNameMap[$mu] ?? '',
                ];
            }
            $isMine = (int) ($it['user_id'] ?? 0) === $userId;
            if (!$isMine) {
                $it['receipt_state'] = null;
                continue;
            }
            $expected = $recipientIds;
            foreach ($mentionsByComment[$cidItem] ?? [] as $mu) {
                if ($mu > 0 && $mu !== $userId) {
                    $expected[$mu] = true;
                }
            }
            unset($expected[$userId]);
            $expList = array_map('intval', array_keys($expected));
            $expTotal = count($expList);
            if ($expTotal <= 0) {
                $it['receipt_state'] = 2;
                $it['receipt_read_by'] = [];
                continue;
            }
            $readNames = [];
            $seen = 0;
            foreach ($expList as $expUid) {
                if (!empty($readMatrix[$cidItem][$expUid])) {
                    $seen++;
                    $nm = $idToName[$expUid] ?? '';
                    if ($nm !== '') {
                        $readNames[$expUid] = $nm;
                    }
                }
            }
            $it['receipt_read_by'] = array_values($readNames);
            if ($seen <= 0) {
                $it['receipt_state'] = 0;
            } elseif ($seen >= $expTotal) {
                $it['receipt_state'] = 2;
            } else {
                $it['receipt_state'] = 1;
            }
        }
        unset($it);
        json_response([
            'ok' => true,
            'items' => $items,
            'has_more_older' => $hasMoreOlder,
            'oldest_id' => $minCommentId,
            'limit' => $pageLimit,
        ]);
    }

    $sqlPage =
        'SELECT c.id, c.task_id, c.user_id, c.body, c.created_at, u.name AS user_name
         FROM task_comments c
         JOIN users u ON u.id = c.user_id
         WHERE ' . $wherePage . '
         ORDER BY c.id DESC
         LIMIT ' . $pageLimit;
    $st = db()->prepare($sqlPage);
    $st->execute($pageParams);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    $items = array_reverse($items);
    $minCommentId = null;
    foreach ($items as $row) {
        $cid = (int) ($row['id'] ?? 0);
        if ($cid > 0 && ($minCommentId === null || $cid < $minCommentId)) {
            $minCommentId = $cid;
        }
    }
    $hasMoreOlder = false;
    if ($minCommentId !== null) {
        $stOlder = db()->prepare('SELECT 1 FROM task_comments WHERE task_id = ? AND id < ? LIMIT 1');
        $stOlder->execute([$taskId, $minCommentId]);
        $hasMoreOlder = (bool) $stOlder->fetch();
    }
    planner_task_comments_attach_mentions_to_items($items, $orgId);
    json_response([
        'ok' => true,
        'items' => $items,
        'has_more_older' => $hasMoreOlder,
        'oldest_id' => $minCommentId,
        'limit' => $pageLimit,
    ]);
}

if ($method === 'POST') {
    $body = read_json_body();
    $text = trim((string) ($body['body'] ?? ''));
    if ($text === '') {
        json_response(['ok' => false, 'error' => 'body_required'], 422);
    }
    $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($len > 16000) {
        json_response(['ok' => false, 'error' => 'body_too_long'], 422);
    }
    $st = db()->prepare('INSERT INTO task_comments (task_id, user_id, body) VALUES (?,?,?)');
    $st->execute([$taskId, $userId, $text]);
    $cid = (int) db()->lastInsertId();
    $mentionIds = planner_parse_comment_mention_user_ids($text, $orgId, $taskId, $userId);
    planner_sync_task_comment_mentions($cid, $mentionIds);
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 200) {
        $preview = mb_substr($text, 0, 200, 'UTF-8') . '…';
    } elseif (strlen($text) > 200) {
        $preview = substr($text, 0, 200) . '…';
    } else {
        $preview = $text;
    }
    log_activity($orgId, $userId, 'comment', 'task', $taskId, 'Комментарий: ' . $preview);
    $brief = planner_task_brief($orgId, $taskId);
    planner_notify_new_comment(
        $orgId,
        $userId,
        $taskId,
        $cid,
        $brief ? (string) $brief['title'] : '',
        $preview
    );
    planner_notify_max_comment_mentions(
        $orgId,
        $userId,
        $taskId,
        $mentionIds,
        $brief ? (string) $brief['title'] : '',
        $preview
    );
    json_response(['ok' => true, 'id' => $cid]);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
