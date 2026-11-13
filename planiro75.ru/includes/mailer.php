<?php
declare(strict_types=1);

function planner_mail_config(): array
{
    $cfg = app_config();
    return isset($cfg['mail']) && is_array($cfg['mail']) ? $cfg['mail'] : [];
}

function planner_mail_enabled(): bool
{
    $m = planner_mail_config();
    return !empty($m['enabled'])
        && trim((string) ($m['from_email'] ?? '')) !== ''
        && trim((string) ($m['smtp_host'] ?? '')) !== ''
        && trim((string) ($m['smtp_user'] ?? '')) !== ''
        && trim((string) ($m['smtp_pass'] ?? '')) !== '';
}

function planner_mail_log(string $msg): void
{
    $baseDir = dirname(__DIR__) . '/var/log';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
    }
    @file_put_contents($baseDir . '/mail.log', '[' . date('c') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

function planner_mail_read_response($fp): string
{
    $resp = '';
    while (!feof($fp)) {
        $line = (string) fgets($fp, 1024);
        if ($line === '') {
            break;
        }
        $resp .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $resp;
}

function planner_mail_expect($fp, array $okCodes): string
{
    $resp = planner_mail_read_response($fp);
    $code = (int) substr($resp, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('smtp_bad_response:' . trim($resp));
    }
    return $resp;
}

function planner_mail_cmd($fp, string $cmd, array $okCodes): string
{
    fwrite($fp, $cmd . "\r\n");
    return planner_mail_expect($fp, $okCodes);
}

function planner_mail_header_encode(string $text): string
{
    if ($text === '') {
        return '';
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function planner_send_mail(string $toEmail, string $subject, string $plainBody): bool
{
    $toEmail = trim($toEmail);
    if ($toEmail === '' || strpos($toEmail, '@') === false) {
        return false;
    }
    if (!planner_mail_enabled()) {
        return false;
    }

    $m = planner_mail_config();
    $host = trim((string) ($m['smtp_host'] ?? ''));
    $port = (int) ($m['smtp_port'] ?? 465);
    $enc = strtolower(trim((string) ($m['smtp_encryption'] ?? 'ssl')));
    $user = trim((string) ($m['smtp_user'] ?? ''));
    $pass = (string) ($m['smtp_pass'] ?? '');
    $fromEmail = trim((string) ($m['from_email'] ?? ''));
    $fromName = trim((string) ($m['from_name'] ?? 'Planner'));
    $timeout = max(3, (int) ($m['smtp_timeout'] ?? 12));

    if ($host === '' || $user === '' || $pass === '' || $fromEmail === '') {
        return false;
    }

    $transportHost = $enc === 'ssl' ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        planner_mail_log('connect_error ' . $errno . ' ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, $timeout);

    try {
        planner_mail_expect($fp, [220]);
        planner_mail_cmd($fp, 'EHLO planner.local', [250]);

        if ($enc === 'tls') {
            planner_mail_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('smtp_tls_failed');
            }
            planner_mail_cmd($fp, 'EHLO planner.local', [250]);
        }

        planner_mail_cmd($fp, 'AUTH LOGIN', [334]);
        planner_mail_cmd($fp, base64_encode($user), [334]);
        planner_mail_cmd($fp, base64_encode($pass), [235]);

        planner_mail_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        planner_mail_cmd($fp, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        planner_mail_cmd($fp, 'DATA', [354]);

        $headers = [];
        $headers[] = 'Date: ' . date(DATE_RFC2822);
        $headers[] = 'From: ' . planner_mail_header_encode($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'To: <' . $toEmail . '>';
        $headers[] = 'Subject: ' . planner_mail_header_encode($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'X-Mailer: PlannerSMTP';

        $body = str_replace(["\r\n", "\r"], "\n", $plainBody);
        $body = str_replace("\n.", "\n..", $body);
        $raw = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.";
        fwrite($fp, $raw . "\r\n");
        planner_mail_expect($fp, [250]);
        planner_mail_cmd($fp, 'QUIT', [221]);
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        planner_mail_log('send_error ' . $e->getMessage());
        @fclose($fp);
        return false;
    }
}

function planner_send_mail_deferred(string $toEmail, string $subject, string $plainBody): void
{
    register_shutdown_function(static function () use ($toEmail, $subject, $plainBody): void {
        try {
            if (function_exists('planner_http_release_client')) {
                planner_http_release_client();
            }
            @ignore_user_abort(true);
            @set_time_limit(20);
            planner_send_mail($toEmail, $subject, $plainBody);
        } catch (Throwable $e) {
            planner_mail_log('deferred_send_error ' . $e->getMessage());
        }
    });
}

function planner_mail_queue_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $st = db()->prepare('SHOW TABLES LIKE ?');
        $st->execute(['mail_queue']);
        $exists = (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function planner_mail_queue_push(
    int $orgId,
    string $toEmail,
    string $subject,
    string $plainBody,
    array $meta = []
): bool {
    if (!planner_mail_enabled()) {
        return false;
    }
    $toEmail = trim($toEmail);
    if ($toEmail === '' || strpos($toEmail, '@') === false) {
        return false;
    }
    if (!planner_mail_queue_table_exists()) {
        // Фолбэк для старой схемы: отправить в фоне без очереди.
        planner_send_mail_deferred($toEmail, $subject, $plainBody);
        return true;
    }
    try {
        $st = db()->prepare(
            'INSERT INTO mail_queue
             (organization_id, to_email, subject, body, meta_json, status, attempts, next_attempt_at, created_at, updated_at)
             VALUES (?,?,?,?,?,\'pending\',0,NOW(),NOW(),NOW())'
        );
        $metaJson = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
        $st->execute([$orgId, $toEmail, $subject, $plainBody, $metaJson]);
        return true;
    } catch (Throwable $e) {
        planner_mail_log('queue_push_error ' . $e->getMessage());
        return false;
    }
}

function planner_mail_queue_process(int $limit = 5): array
{
    $out = ['processed' => 0, 'sent' => 0, 'failed' => 0];
    if (!planner_mail_enabled() || !planner_mail_queue_table_exists()) {
        return $out;
    }
    $limit = max(1, min(50, $limit));
    $maxAttempts = 5;

    try {
        $stPick = db()->prepare(
            'SELECT id, organization_id, to_email, subject, body, attempts
             FROM mail_queue
             WHERE status = \'pending\' AND next_attempt_at <= NOW()
             ORDER BY id ASC
             LIMIT ' . $limit
        );
        $stPick->execute();
        $rows = $stPick->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        planner_mail_log('queue_pick_error ' . $e->getMessage());
        return $out;
    }

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        try {
            $stLock = db()->prepare(
                'UPDATE mail_queue
                 SET status = \'sending\', attempts = attempts + 1, updated_at = NOW(), last_error = NULL
                 WHERE id = ? AND status = \'pending\' AND next_attempt_at <= NOW()'
            );
            $stLock->execute([$id]);
            if ($stLock->rowCount() < 1) {
                continue;
            }
        } catch (Throwable $e) {
            planner_mail_log('queue_lock_error #' . $id . ' ' . $e->getMessage());
            continue;
        }

        $out['processed']++;
        $ok = planner_send_mail(
            (string) ($row['to_email'] ?? ''),
            (string) ($row['subject'] ?? ''),
            (string) ($row['body'] ?? '')
        );
        if ($ok) {
            $out['sent']++;
            try {
                db()->prepare(
                    'UPDATE mail_queue
                     SET status = \'sent\', sent_at = NOW(), updated_at = NOW(), last_error = NULL
                     WHERE id = ?'
                )->execute([$id]);
            } catch (Throwable $e) {
                planner_mail_log('queue_mark_sent_error #' . $id . ' ' . $e->getMessage());
            }
            continue;
        }

        $attempts = ((int) ($row['attempts'] ?? 0)) + 1;
        $isFinal = $attempts >= $maxAttempts;
        $delaySec = min(900, 20 * (2 ** max(0, $attempts - 1)));
        $status = $isFinal ? 'failed' : 'pending';
        if ($isFinal) {
            $out['failed']++;
        }
        try {
            db()->prepare(
                'UPDATE mail_queue
                 SET status = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW(),
                     last_error = COALESCE(last_error, \'mail_send_failed\')
                 WHERE id = ?'
            )->execute([$status, $delaySec, $id]);
        } catch (Throwable $e) {
            planner_mail_log('queue_requeue_error #' . $id . ' ' . $e->getMessage());
        }
    }
    return $out;
}

function planner_mail_queue_kick_async(int $limit = 2): void
{
    static $scheduled = false;
    if ($scheduled) {
        return;
    }
    $scheduled = true;
    $limit = max(1, min(20, $limit));
    register_shutdown_function(static function () use ($limit): void {
        try {
            if (function_exists('planner_http_release_client')) {
                planner_http_release_client();
            }
            @ignore_user_abort(true);
            @set_time_limit(20);
            planner_mail_queue_process($limit);
        } catch (Throwable $e) {
            planner_mail_log('queue_kick_error ' . $e->getMessage());
        }
    });
}

