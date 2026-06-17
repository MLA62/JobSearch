<?php

declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']),
    'samesite' => 'Lax',
]);
session_start();

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Application configuration is missing.');
}
$config = require $configPath;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_password'],
        $config['db_name'],
        (int) $config['db_port']
    );
    $db->set_charset('utf8mb4');
} catch (Throwable $exception) {
    http_response_code(503);
    exit('Database connection failed.');
}

try {
    $column = $db->query("SHOW COLUMNS FROM users LIKE 'last_seen_at'")->fetch_assoc();
    if (!$column) {
        $db->query('ALTER TABLE users ADD COLUMN last_seen_at DATETIME NULL AFTER last_login_at');
    }
    $db->query("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_hash CHAR(64) NOT NULL,
        ip_hash CHAR(64) NULL,
        user_agent_hash CHAR(64) NULL,
        first_seen_at DATETIME NOT NULL,
        last_seen_at DATETIME NOT NULL,
        logged_out_at DATETIME NULL,
        UNIQUE KEY uniq_user_session_hash (session_hash),
        KEY idx_user_sessions_user_active (user_id, logged_out_at, last_seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $exception) {
    // Optional runtime telemetry must not block the app.
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path = '/'): never
{
    header('Location: ' . $path);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): void
{
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function userId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function realUserId(): int
{
    return (int) ($_SESSION['support_admin_user_id'] ?? $_SESSION['user_id'] ?? 0);
}

function sessionPresenceHash(): string
{
    return hash('sha256', session_id());
}

function requestHash(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : hash('sha256', $value);
}

function touchUserPresence(mysqli $db, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $sessionHash = sessionPresenceHash();
        $ipHash = requestHash((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $userAgentHash = requestHash((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_hash, ip_hash, user_agent_hash, first_seen_at, last_seen_at, logged_out_at)
            VALUES (?, ?, ?, ?, NOW(), NOW(), NULL)
            ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), ip_hash=VALUES(ip_hash), user_agent_hash=VALUES(user_agent_hash), last_seen_at=NOW(), logged_out_at=NULL");
        $stmt->bind_param('isss', $userId, $sessionHash, $ipHash, $userAgentHash);
        $stmt->execute();
        $db->query('UPDATE users SET last_seen_at = NOW() WHERE id = ' . $userId);
    } catch (Throwable $exception) {
        // Presence is informational and must never block productive work.
    }
}

function endUserPresenceSession(mysqli $db, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $sessionHash = sessionPresenceHash();
        $stmt = $db->prepare('UPDATE user_sessions SET logged_out_at=NOW(), last_seen_at=NOW() WHERE user_id=? AND session_hash=?');
        $stmt->bind_param('is', $userId, $sessionHash);
        $stmt->execute();
        $db->query('UPDATE users SET last_seen_at = NOW() WHERE id = ' . $userId);
    } catch (Throwable $exception) {
        // Logout must continue even if presence cleanup is unavailable.
    }
}

function isSupportImpersonation(): bool
{
    return !empty($_SESSION['support_admin_user_id']) && !empty($_SESSION['support_target_user_id']);
}

function endSupportImpersonationSession(): void
{
    if (!empty($_SESSION['support_admin_user_id'])) {
        $_SESSION['user_id'] = (int) $_SESSION['support_admin_user_id'];
    }
    unset($_SESSION['support_admin_user_id'], $_SESSION['support_admin_name'], $_SESSION['support_target_user_id'], $_SESSION['support_target_name']);
}

function requireLogin(): void
{
    if (userId() < 1) {
        redirect('/?page=login');
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function outboundEmailEnabled(array $config): bool
{
    return !empty($config['smtp_enabled'])
        && trim((string) ($config['smtp_host'] ?? '')) !== ''
        && trim((string) ($config['mail_from'] ?? '')) !== '';
}

function secretKey(array $config): string
{
    $seed = (string) ($config['app_key'] ?? $config['app_secret'] ?? $config['db_password'] ?? '');
    if ($seed === '') {
        $seed = 'jema-jobs-local-prototype';
    }
    return hash('sha256', $seed, true);
}

function encryptSecret(array $config, string $plain): ?string
{
    if ($plain === '') {
        return null;
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL ist für SMTP-Passwörter nicht verfügbar.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', secretKey($config), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('SMTP-Passwort konnte nicht verschlüsselt werden.');
    }
    return 'v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
}

function decryptSecret(array $config, ?string $stored): string
{
    $stored = (string) $stored;
    if ($stored === '') {
        return '';
    }
    $parts = explode(':', $stored, 4);
    if (count($parts) !== 4 || $parts[0] !== 'v1' || !function_exists('openssl_decrypt')) {
        return '';
    }
    $plain = openssl_decrypt(
        base64_decode($parts[3], true) ?: '',
        'aes-256-gcm',
        secretKey($config),
        OPENSSL_RAW_DATA,
        base64_decode($parts[1], true) ?: '',
        base64_decode($parts[2], true) ?: ''
    );
    return $plain === false ? '' : $plain;
}

function appUrl(array $config): string
{
    return rtrim((string) ($config['app_url'] ?? ''), '/');
}

function absoluteUrl(array $config, string $path): string
{
    return (appUrl($config) ?: '') . $path;
}

function mailFromName(array $config): string
{
    return trim((string) ($config['mail_from_name'] ?? $config['app_name'] ?? 'JeMa Jobs'));
}

function encodeMailHeader(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
        return $value;
    }
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtpReadResponse($stream, array $acceptedCodes): string
{
    $response = '';
    do {
        $line = fgets($stream, 2048);
        if ($line === false) {
            throw new RuntimeException('SMTP-Server hat die Verbindung unerwartet beendet.');
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $acceptedCodes, true)) {
        throw new RuntimeException('SMTP-Fehler: ' . trim($response));
    }
    return $response;
}

function smtpCommand($stream, string $command, array $acceptedCodes): string
{
    if (fwrite($stream, $command . "\r\n") === false) {
        throw new RuntimeException('SMTP-Befehl konnte nicht gesendet werden.');
    }
    return smtpReadResponse($stream, $acceptedCodes);
}

function dotStuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = str_replace("\n.", "\n..", $body);
    if (str_starts_with($body, '.')) {
        $body = '.' . $body;
    }
    return str_replace("\n", "\r\n", $body);
}

function sendSmtpMail(array $config, string $to, string $subject, string $textBody): void
{
    $host = trim((string) ($config['smtp_host'] ?? ''));
    $from = trim((string) ($config['mail_from'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) || $host === '') {
        throw new RuntimeException('SMTP-Konfiguration oder Empfängeradresse ist ungültig.');
    }

    $encryption = strtolower(trim((string) ($config['smtp_encryption'] ?? 'tls')));
    $port = (int) ($config['smtp_port'] ?? ($encryption === 'ssl' ? 465 : 587));
    $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $stream = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!is_resource($stream)) {
        throw new RuntimeException('SMTP-Verbindung fehlgeschlagen: ' . $errstr);
    }
    stream_set_timeout($stream, 30);

    try {
        smtpReadResponse($stream, [220]);
        $domain = parse_url(appUrl($config), PHP_URL_HOST) ?: ($_SERVER['SERVER_NAME'] ?? 'localhost');
        smtpCommand($stream, 'EHLO ' . $domain, [250]);
        if ($encryption === 'tls') {
            smtpCommand($stream, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS konnte nicht aktiviert werden.');
            }
            smtpCommand($stream, 'EHLO ' . $domain, [250]);
        }

        $username = (string) ($config['smtp_username'] ?? '');
        if ($username !== '') {
            smtpCommand($stream, 'AUTH LOGIN', [334]);
            smtpCommand($stream, base64_encode($username), [334]);
            smtpCommand($stream, base64_encode((string) ($config['smtp_password'] ?? '')), [235]);
        }

        smtpCommand($stream, 'MAIL FROM:<' . $from . '>', [250]);
        smtpCommand($stream, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtpCommand($stream, 'DATA', [354]);

        $fromHeader = encodeMailHeader(mailFromName($config)) . ' <' . $from . '>';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . encodeMailHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        smtpCommand($stream, implode("\r\n", $headers) . "\r\n\r\n" . dotStuff($textBody) . "\r\n.", [250]);
        smtpCommand($stream, 'QUIT', [221]);
    } finally {
        fclose($stream);
    }
}

function userSmtpSettings(mysqli $db, array $config, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    try {
        $settings = dbOne($db, 'SELECT * FROM user_smtp_settings WHERE user_id=? AND is_active=1 LIMIT 1', 'i', [$userId]);
    } catch (Throwable) {
        return null;
    }
    if (!$settings || trim((string) ($settings['smtp_host'] ?? '')) === '' || trim((string) ($settings['from_email'] ?? '')) === '') {
        return null;
    }
    return [
        'app_url' => $config['app_url'] ?? '',
        'app_name' => $config['app_name'] ?? 'JeMa Jobs',
        'smtp_enabled' => true,
        'smtp_host' => (string) $settings['smtp_host'],
        'smtp_port' => (int) $settings['smtp_port'],
        'smtp_encryption' => (string) $settings['smtp_encryption'],
        'smtp_username' => (string) ($settings['smtp_username'] ?? ''),
        'smtp_password' => decryptSecret($config, $settings['smtp_password_encrypted'] ?? null),
        'mail_from' => (string) $settings['from_email'],
        'mail_from_name' => trim((string) ($settings['from_name'] ?? '')) ?: mailFromName($config),
    ];
}

function smtpConfigForOwner(mysqli $db, array $config, int $ownerUserId): ?array
{
    return userSmtpSettings($db, $config, $ownerUserId);
}

function mailEnabledForUser(mysqli $db, array $config, int $ownerUserId): bool
{
    return smtpConfigForOwner($db, $config, $ownerUserId) !== null;
}

function logOutboundEmail(mysqli $db, int $userId, string $recipient, string $subject, string $body, string $status, ?string $error = null): void
{
    try {
        $sentAt = $status === 'sent' ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare('INSERT INTO outbound_emails (owner_user_id, recipient_email, subject, body_text, status, sent_at, error_message) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssss', $userId, $recipient, $subject, $body, $status, $sentAt, $error);
        $stmt->execute();
    } catch (Throwable) {
        // Mail logging must never block account recovery.
    }
}

function sendConfiguredMail(mysqli $db, array $config, int $ownerUserId, string $to, string $subject, string $body): bool
{
    $mailConfig = smtpConfigForOwner($db, $config, $ownerUserId);
    if (!$mailConfig) {
        logOutboundEmail($db, $ownerUserId, $to, $subject, $body, 'draft');
        return false;
    }
    try {
        sendSmtpMail($mailConfig, $to, $subject, $body);
        logOutboundEmail($db, $ownerUserId, $to, $subject, $body, 'sent');
        return true;
    } catch (Throwable $exception) {
        logOutboundEmail($db, $ownerUserId, $to, $subject, $body, 'failed', $exception->getMessage());
        throw $exception;
    }
}

function base32Encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (unpack('C*', $bytes) as $byte) {
        $bits .= str_pad(decbin((int) $byte), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $encoded;
}

function base32Decode(string $value): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
    $bits = '';
    foreach (str_split($clean) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $bytes .= chr(bindec($chunk));
        }
    }
    return $bytes;
}

function generateTotpSecret(): string
{
    return base32Encode(random_bytes(20));
}

function totpCode(string $secret, ?int $time = null): string
{
    $counter = intdiv($time ?? time(), 30);
    $binaryCounter = pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000);
    $hash = hash_hmac('sha1', $binaryCounter, base32Decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function verifyTotpCode(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return false;
    }
    $now = time();
    foreach ([-30, 0, 30] as $offset) {
        if (hash_equals(totpCode($secret, $now + $offset), $code)) {
            return true;
        }
    }
    return false;
}

function totpUri(array $config, array $user, string $secret): string
{
    $issuer = rawurlencode((string) ($config['app_name'] ?? 'JeMa Jobs'));
    $label = rawurlencode(($config['app_name'] ?? 'JeMa Jobs') . ':' . (string) $user['email']);
    return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . $issuer . '&algorithm=SHA1&digits=6&period=30';
}

function activeTotpMethod(mysqli $db, int $userId): ?array
{
    return dbOne($db, "SELECT * FROM two_factor_methods WHERE user_id=? AND method='totp' AND verified_at IS NOT NULL ORDER BY is_primary DESC, id DESC LIMIT 1", 'i', [$userId]);
}

function shareToken(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function shareTokenHash(string $token): string
{
    return hash('sha256', $token);
}

function deviceHash(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500));
}

function activeGuestShare(mysqli $db, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $share = dbOne(
        $db,
        'SELECT * FROM guest_shares WHERE token_hash=? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1',
        's',
        [shareTokenHash($token)]
    );
    if ($share) {
        $stmt = $db->prepare('UPDATE guest_shares SET last_accessed_at=NOW() WHERE id=?');
        $id = (int) $share['id'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    return $share;
}

function shareAllowsTarget(array $share, string $targetType, int $targetId): bool
{
    if (($share['target_type'] ?? '') === 'area') {
        return true;
    }
    return ($share['target_type'] ?? '') === $targetType && (int) ($share['target_id'] ?? 0) === $targetId;
}

function storageUsageBytes(mysqli $db, int $userId): int
{
    $documents = dbOne($db, 'SELECT COALESCE(SUM(file_size),0) total FROM user_documents WHERE user_id=? AND deleted_at IS NULL', 'i', [$userId]);
    $generated = dbOne($db, 'SELECT COALESCE(SUM(file_size),0) total FROM generated_files WHERE owner_user_id=? AND (expires_at IS NULL OR expires_at > NOW())', 'i', [$userId]);
    return (int) ($documents['total'] ?? 0) + (int) ($generated['total'] ?? 0);
}

function bytesLabel(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 1) . ' KB';
}

function cleanupPreview(mysqli $db, int $userId, string $cutoffDate): array
{
    $jobs = dbOne($db, "SELECT COUNT(*) c FROM jobs WHERE owner_user_id=? AND deleted_at IS NULL AND updated_at < ? AND status IN ('rejected','closed')", 'is', [$userId, $cutoffDate . ' 00:00:00']);
    $apps = dbOne($db, "SELECT COUNT(*) c FROM applications WHERE user_id=? AND deleted_at IS NULL AND updated_at < ? AND status IN ('rejected','withdrawn','closed')", 'is', [$userId, $cutoffDate . ' 00:00:00']);
    $documents = dbOne($db, "SELECT COUNT(*) c, COALESCE(SUM(file_size),0) bytes FROM user_documents WHERE user_id=? AND deleted_at IS NULL AND is_current=0 AND updated_at < ?", 'is', [$userId, $cutoffDate . ' 00:00:00']);
    return [
        'jobs' => (int) ($jobs['c'] ?? 0),
        'applications' => (int) ($apps['c'] ?? 0),
        'old_document_versions' => (int) ($documents['c'] ?? 0),
        'document_bytes' => (int) ($documents['bytes'] ?? 0),
    ];
}

function csvResponse(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

function pdfEscape(string $value): string
{
    $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

function pdfResponse(string $filename, string $title, array $headers, array $rows): never
{
    $objects = [];
    $pages = [];
    $fontObjectNo = 3;

    $pageWidth = 842;
    $pageHeight = 595;
    $margin = 32;
    $tableWidth = $pageWidth - ($margin * 2);
    $headerHeight = 24;
    $rowHeight = 23;
    $tableTop = 500;
    $rowsPerPage = 18;
    $columnCount = max(1, count($headers));
    $weights = [];
    foreach ($headers as $index => $header) {
        $max = mb_strlen((string) $header);
        foreach (array_slice($rows, 0, 60) as $row) {
            $max = max($max, mb_strlen((string) ($row[$index] ?? '')));
        }
        $weights[] = max(5, min(26, $max));
    }
    $weightTotal = array_sum($weights) ?: $columnCount;
    $widths = [];
    $remaining = $tableWidth;
    foreach ($weights as $index => $weight) {
        $width = $index === $columnCount - 1 ? $remaining : round($tableWidth * ($weight / $weightTotal), 2);
        $width = max(42, $width);
        $widths[] = $width;
        $remaining -= $width;
    }
    if ($remaining < 0) {
        $scale = $tableWidth / array_sum($widths);
        foreach ($widths as $index => $width) {
            $widths[$index] = round($width * $scale, 2);
        }
    }

    $chunks = array_chunk($rows ?: [array_fill(0, $columnCount, '')], $rowsPerPage);
    $pageTotal = count($chunks);
    foreach ($chunks as $pageIndex => $chunk) {
        $content = "0.09 0.13 0.16 rg\nBT /F1 18 Tf {$margin} 548 Td (" . pdfEscape($title) . ") Tj ET\n";
        $content .= "0.39 0.45 0.48 rg\nBT /F1 8 Tf {$margin} 529 Td (Erstellt am " . pdfEscape(date('d.m.Y H:i')) . " | Seite " . ($pageIndex + 1) . " von {$pageTotal}) Tj ET\n";

        $x = $margin;
        $headerY = $tableTop - $headerHeight;
        $content .= "0.91 0.94 0.95 rg {$margin} {$headerY} {$tableWidth} {$headerHeight} re f\n";
        $content .= "0.62 0.67 0.70 RG 0.7 w {$margin} {$headerY} {$tableWidth} {$headerHeight} re S\n";
        foreach ($headers as $index => $header) {
            $width = $widths[$index] ?? ($tableWidth / $columnCount);
            $text = mb_strimwidth((string) $header, 0, max(4, (int) floor(($width - 10) / 4.5)), '...');
            $content .= "0.09 0.13 0.16 rg\nBT /F1 8.5 Tf " . ($x + 5) . ' ' . ($headerY + 9) . ' Td (' . pdfEscape($text) . ") Tj ET\n";
            if ($index > 0) {
                $content .= "0.62 0.67 0.70 RG {$x} {$headerY} m {$x} " . ($headerY + $headerHeight) . " l S\n";
            }
            $x += $width;
        }

        foreach ($chunk as $rowIndex => $row) {
            $rowY = $headerY - (($rowIndex + 1) * $rowHeight);
            $fill = $rowIndex % 2 === 0 ? '1 1 1' : '0.97 0.98 0.98';
            $content .= "{$fill} rg {$margin} {$rowY} {$tableWidth} {$rowHeight} re f\n";
            $content .= "0.82 0.85 0.86 RG 0.45 w {$margin} {$rowY} {$tableWidth} {$rowHeight} re S\n";
            $x = $margin;
            for ($index = 0; $index < $columnCount; $index++) {
                $width = $widths[$index] ?? ($tableWidth / $columnCount);
                $value = is_array($row) ? ($row[$index] ?? '') : '';
                $text = mb_strimwidth((string) $value, 0, max(4, (int) floor(($width - 10) / 4.1)), '...');
                if ($index > 0) {
                    $content .= "0.82 0.85 0.86 RG {$x} {$rowY} m {$x} " . ($rowY + $rowHeight) . " l S\n";
                }
                $content .= "0.10 0.13 0.16 rg\nBT /F1 8 Tf " . ($x + 5) . ' ' . ($rowY + 8) . ' Td (' . pdfEscape($text) . ") Tj ET\n";
                $x += $width;
            }
        }
        $contentNo = count($objects) + 4;
        $pageNo = $contentNo + 1;
        $objects[$contentNo] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        $objects[$pageNo] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 {$fontObjectNo} 0 R >> >> /Contents {$contentNo} 0 R >>";
        $pages[] = $pageNo . ' 0 R';
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pages) . '] /Count ' . count($pages) . ' >>';
    $objects[$fontObjectNo] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= max(array_keys($objects)); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function sfSessionKey(string $context): string
{
    return 'sf_' . preg_replace('/[^a-z0-9_]/i', '_', $context);
}

function sfState(string $context, array $fields, array $defaults = []): array
{
    $key = sfSessionKey($context);
    $_SESSION[$key] ??= [
        'filters' => [],
        'sort' => [
            'field' => (string) ($defaults['sort'] ?? array_key_first($fields)),
            'dir' => strtolower((string) ($defaults['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ],
    ];
    $state = is_array($_SESSION[$key]) ? $_SESSION[$key] : ['filters' => [], 'sort' => []];
    $state['filters'] = is_array($state['filters'] ?? null) ? $state['filters'] : [];
    $state['sort'] = is_array($state['sort'] ?? null) ? $state['sort'] : [];

    if ((string) ($_GET['sf_context'] ?? '') === $context) {
        if (!empty($_GET['sf_reset'])) {
            $state = ['filters' => [], 'sort' => ['field' => (string) ($defaults['sort'] ?? array_key_first($fields)), 'dir' => strtolower((string) ($defaults['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc']];
        } else {
            $field = (string) ($_GET['sf_field'] ?? '');
            if (isset($fields[$field])) {
                $filter = trim((string) ($_GET['sf_filter'] ?? ''));
                if ($filter === '') {
                    unset($state['filters'][$field]);
                } else {
                    $state['filters'][$field] = $filter;
                }
                $sort = strtolower((string) ($_GET['sf_sort'] ?? ''));
                if (in_array($sort, ['asc', 'desc'], true)) {
                    $state['sort'] = ['field' => $field, 'dir' => $sort];
                } elseif ($sort === 'none' && (string) ($state['sort']['field'] ?? '') === $field) {
                    $state['sort'] = [];
                }
            }
        }
        $_SESSION[$key] = $state;
    }

    $state['filters'] = array_intersect_key($state['filters'], $fields);
    if ($state['sort'] !== [] && !isset($fields[(string) ($state['sort']['field'] ?? '')])) {
        $state['sort'] = [
            'field' => (string) ($defaults['sort'] ?? array_key_first($fields)),
            'dir' => strtolower((string) ($defaults['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }
    if ($state['sort'] !== [] && !in_array(strtolower((string) ($state['sort']['dir'] ?? 'asc')), ['asc', 'desc'], true)) {
        $state['sort']['dir'] = 'asc';
    }
    $_SESSION[$key] = $state;
    return $state;
}

function sfApplySql(array $state, array $fields, string &$types, array &$values): string
{
    $clauses = [];
    foreach ((array) ($state['filters'] ?? []) as $field => $filter) {
        $filter = trim((string) $filter);
        if ($filter === '' || !isset($fields[$field])) {
            continue;
        }
        $expr = (string) ($fields[$field]['expr'] ?? '');
        if ($expr === '') {
            continue;
        }
        $clauses[] = $expr . ' LIKE ?';
        $types .= 's';
        $values[] = '%' . $filter . '%';
    }
    return $clauses ? ' AND ' . implode(' AND ', $clauses) : '';
}

function sfOrderSql(array $state, array $fields, string $fallbackField): string
{
    $field = (string) ($state['sort']['field'] ?? $fallbackField);
    if (($state['sort'] ?? []) === []) {
        return '';
    }
    if (!isset($fields[$field])) {
        $field = $fallbackField;
    }
    $expr = (string) ($fields[$field]['expr'] ?? '');
    if ($expr === '') {
        return '';
    }
    $dir = strtolower((string) ($state['sort']['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    return ' ORDER BY ' . $expr . ' ' . $dir;
}

function sfApplyRows(array $rows, array $state, array $fields): array
{
    $filters = (array) ($state['filters'] ?? []);
    if ($filters) {
        $rows = array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            foreach ($filters as $field => $filter) {
                $filter = mb_strtolower(trim((string) $filter));
                if ($filter !== '' && !str_contains(mb_strtolower((string) ($row[$field] ?? '')), $filter)) {
                    return false;
                }
            }
            return true;
        }));
    }
    $sortField = (string) ($state['sort']['field'] ?? '');
    $dir = strtolower((string) ($state['sort']['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    if ($sortField !== '' && isset($fields[$sortField])) {
        usort($rows, static function (array $a, array $b) use ($sortField, $dir): int {
            $result = strnatcasecmp((string) ($a[$sortField] ?? ''), (string) ($b[$sortField] ?? ''));
            return $dir === 'desc' ? -$result : $result;
        });
    }
    return $rows;
}

function sfHiddenInputs(array $preserve): string
{
    $html = '';
    foreach ($preserve as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $html .= '<input type="hidden" name="' . e((string) $name) . '" value="' . e((string) $value) . '">';
    }
    return $html;
}

function sfHeader(string $context, string $field, string $label, array $state, array $preserve = []): string
{
    $currentFilter = (string) (($state['filters'][$field] ?? ''));
    $isSort = (string) ($state['sort']['field'] ?? '') === $field;
    $sortDir = $isSort ? strtolower((string) ($state['sort']['dir'] ?? 'asc')) : '';
    $active = $currentFilter !== '' || $isSort;
    $summary = '<summary class="sf-button ' . ($active ? 'is-active' : '') . '" title="Sortieren und filtern">' . ($isSort ? ($sortDir === 'desc' ? 'v' : '^') : '=') . ($currentFilter !== '' ? '*' : '') . '</summary>';
    $html = '<th><div class="sf-head"><span>' . e($label) . '</span><details class="sf-menu">' . $summary;
    $html .= '<form method="get" class="sf-form">' . sfHiddenInputs($preserve);
    $html .= '<input type="hidden" name="sf_context" value="' . e($context) . '"><input type="hidden" name="sf_field" value="' . e($field) . '">';
    $html .= '<label>Filter<input name="sf_filter" value="' . e($currentFilter) . '" placeholder="' . e($label) . ' enthält"></label>';
    $html .= '<label>Sortierung<select name="sf_sort"><option value="none">Keine</option><option value="asc" ' . ($sortDir === 'asc' ? 'selected' : '') . '>Aufsteigend</option><option value="desc" ' . ($sortDir === 'desc' ? 'selected' : '') . '>Absteigend</option></select></label>';
    $html .= '<div class="actions"><button class="primary">Anwenden</button><button name="sf_filter" value="">Filter löschen</button></div>';
    $html .= '</form></details></div></th>';
    return $html;
}

function sfToolbar(string $context, array $state, array $preserve = [], array $fields = []): string
{
    $count = count((array) ($state['filters'] ?? []));
    $sort = (array) ($state['sort'] ?? []);
    $label = $count > 0 ? $count . ' Filter aktiv' : 'Keine Feldfilter';
    if (!empty($sort['field'])) {
        $fieldLabel = (string) ($fields[(string) $sort['field']]['label'] ?? $sort['field']);
        $label .= ' · Sort ' . $fieldLabel . ' ' . (strtolower((string) ($sort['dir'] ?? 'asc')) === 'desc' ? 'absteigend' : 'aufsteigend');
    }
    return '<form method="get" class="sf-toolbar">' . sfHiddenInputs($preserve) . '<input type="hidden" name="sf_context" value="' . e($context) . '"><input type="hidden" name="sf_reset" value="1"><span>' . e($label) . '</span><button>Sort/Filter zurücksetzen</button></form>';
}

function reportBaseOptions(): array
{
    return ['jobs'=>'Jobs','applications'=>'Bewerbungen','companies'=>'Firmen','contacts'=>'Kontakte','documents'=>'Dokumente','calendar'=>'Kalender'];
}

function reportDisplayOptions(): array
{
    return ['table'=>'Tabelle','list'=>'Liste','cards'=>'Karten','preview'=>'Vorschau','calendar_day'=>'Kalendertag','calendar_week'=>'Kalenderwoche','calendar_month'=>'Kalendermonat'];
}

function reportOpenUrl(array $report): string
{
    $base = (string) ($report['base_entity'] ?? 'jobs');
    $display = (string) ($report['display_type'] ?? 'table');
    $page = ['jobs'=>'jobs','applications'=>'applications','companies'=>'companies','contacts'=>'contacts','documents'=>'documents','calendar'=>'calendar'][$base] ?? 'jobs';
    $params = ['page' => $page];
    if (in_array($page, ['jobs', 'applications'], true) && in_array($display, ['cards', 'table'], true)) {
        $params['view'] = $display;
    }
    return '/?' . http_build_query($params);
}

function reportExportType(array $report): ?string
{
    $base = (string) ($report['base_entity'] ?? '');
    return in_array($base, ['jobs', 'applications', 'companies', 'contacts', 'documents'], true) ? $base : null;
}

function reportFieldOptions(string $base): array
{
    return match ($base) {
        'applications' => ['title'=>'Job','company'=>'Firma','status'=>'Status','channel'=>'Kanal','application_url'=>'Online-URL','reference_number'=>'Referenz','applied_at'=>'Gesendet','next_action'=>'Pendent','next_action_at'=>'Fällig'],
        'companies' => ['name'=>'Firma','city'=>'Ort','website'=>'Website','is_intermediary'=>'Vermittler','updated_at'=>'Aktualisiert'],
        'contacts' => ['name'=>'Kontakt','company'=>'Firma','email'=>'E-Mail','phone'=>'Telefon','position'=>'Funktion','open_logs'=>'Pendent','updated_at'=>'Aktualisiert'],
        'documents' => ['title'=>'Dokument','type'=>'Typ','filename'=>'Datei','scope'=>'Bereich','version'=>'Version','created_at'=>'Erstellt'],
        'calendar' => ['starts_at'=>'Zeit','title'=>'Ereignis','type'=>'Typ','status'=>'Status','meta'=>'Bezug'],
        default => ['title'=>'Titel','company'=>'Firma','location'=>'Ort','status'=>'Status','workplace_type'=>'Arbeitsmodell','updated_at'=>'Aktualisiert'],
    };
}

function reportDefaultColumns(string $base): array
{
    return array_slice(array_keys(reportFieldOptions($base)), 0, 6);
}

function reportStatusOptions(string $base): array
{
    return match ($base) {
        'applications' => ['draft'=>'Entwurf','ready'=>'Bereit','sent'=>'Gesendet','confirmed'=>'Bestätigt','interview'=>'Interview','assessment'=>'Assessment','offer'=>'Angebot','accepted'=>'Angenommen','rejected'=>'Absage','withdrawn'=>'Zurückgezogen','closed'=>'Abgeschlossen'],
        'contacts' => ['open'=>'Offene/geplante Logs','none'=>'Ohne offene Logs'],
        'calendar' => ['planned'=>'Geplant','completed'=>'Erledigt','cancelled'=>'Abgebrochen'],
        default => ['open'=>'Offen','interesting'=>'Interessant','applied'=>'Beworben','interview'=>'Interview','offer'=>'Angebot','rejected'=>'Absage','closed'=>'Geschlossen'],
    };
}

function saveReportSettings(mysqli $db, int $reportId, string $base): void
{
    $db->query('DELETE FROM saved_report_columns WHERE report_id=' . $reportId);
    $db->query('DELETE FROM saved_report_filters WHERE report_id=' . $reportId);
    $db->query('DELETE FROM saved_report_sorts WHERE report_id=' . $reportId);

    $fields = reportFieldOptions($base);
    $columns = array_values(array_filter((array) ($_POST['report_columns'] ?? []), static fn($field): bool => isset($fields[(string)$field])));
    if (!$columns) {
        $columns = reportDefaultColumns($base);
    }
    $columnStmt = $db->prepare('INSERT INTO saved_report_columns (report_id, field_name, label, sort_order, is_visible) VALUES (?, ?, ?, ?, 1)');
    foreach ($columns as $index => $field) {
        $field = (string) $field;
        $label = $fields[$field];
        $order = $index + 1;
        $columnStmt->bind_param('issi', $reportId, $field, $label, $order);
        $columnStmt->execute();
    }

    $filterStmt = $db->prepare('INSERT INTO saved_report_filters (report_id, field_name, operator, value_json, sort_order) VALUES (?, ?, ?, ?, ?)');
    $order = 1;
    $query = trim((string) ($_POST['report_q'] ?? ''));
    if ($query !== '') {
        $field = 'q';
        $operator = 'contains';
        $value = json_encode($query, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $filterStmt->bind_param('isssi', $reportId, $field, $operator, $value, $order);
        $filterStmt->execute();
        $order++;
    }
    $status = trim((string) ($_POST['report_status'] ?? ''));
    if ($status !== '' && isset(reportStatusOptions($base)[$status])) {
        $field = 'status';
        $operator = 'eq';
        $value = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $filterStmt->bind_param('isssi', $reportId, $field, $operator, $value, $order);
        $filterStmt->execute();
    }

    $sortField = (string) ($_POST['report_sort'] ?? '');
    if (!isset($fields[$sortField])) {
        $sortField = $columns[0] ?? array_key_first($fields);
    }
    $direction = strtolower((string) ($_POST['report_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    $priority = 1;
    $sortStmt = $db->prepare('INSERT INTO saved_report_sorts (report_id, field_name, direction, priority) VALUES (?, ?, ?, ?)');
    $sortStmt->bind_param('issi', $reportId, $sortField, $direction, $priority);
    $sortStmt->execute();
}

function loadReportSettings(mysqli $db, int $reportId, string $base): array
{
    $columns = dbAll($db, 'SELECT field_name, label FROM saved_report_columns WHERE report_id=? AND is_visible=1 ORDER BY sort_order, id', 'i', [$reportId]);
    $filters = dbAll($db, 'SELECT field_name, value_json FROM saved_report_filters WHERE report_id=? ORDER BY sort_order, id', 'i', [$reportId]);
    $sort = dbOne($db, 'SELECT field_name, direction FROM saved_report_sorts WHERE report_id=? ORDER BY priority, id LIMIT 1', 'i', [$reportId]);
    $selected = $columns ? array_map(static fn(array $row): string => (string)$row['field_name'], $columns) : reportDefaultColumns($base);
    $filterValues = [];
    foreach ($filters as $filter) {
        $filterValues[(string)$filter['field_name']] = json_decode((string)$filter['value_json'], true) ?: '';
    }
    return ['columns'=>$selected, 'filters'=>$filterValues, 'sort'=>$sort ?: ['field_name'=>$selected[0] ?? 'title', 'direction'=>'asc']];
}

function reportDataset(mysqli $db, int $userId, array $report, array $settings, array $currentUser): array
{
    $base = (string) ($report['base_entity'] ?? 'jobs');
    $fields = reportFieldOptions($base);
    $columns = array_values(array_filter((array)$settings['columns'], static fn($field): bool => isset($fields[(string)$field])));
    if (!$columns) {
        $columns = reportDefaultColumns($base);
    }
    $headers = array_map(static fn(string $field): string => $fields[$field], $columns);
    $filters = (array) ($settings['filters'] ?? []);
    $q = strtolower((string) ($filters['q'] ?? ''));
    $status = (string) ($filters['status'] ?? '');

    $rows = match ($base) {
        'applications' => dbAll($db, 'SELECT a.status, a.channel, a.application_url, a.reference_number, a.applied_at, a.next_action, a.next_action_at, j.title, c.name company FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL', 'i', [$userId]),
        'companies' => dbAll($db, 'SELECT name, city, website, is_intermediary, updated_at FROM companies WHERE owner_user_id=? AND deleted_at IS NULL', 'i', [$userId]),
        'contacts' => dbAll($db, 'SELECT CONCAT(c.first_name, " ", c.last_name) name, co.name company, c.email, COALESCE(NULLIF(c.phone,""), c.mobile) phone, c.position, c.updated_at, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=c.id AND l.status IN ("open","planned")) open_logs FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND c.deleted_at IS NULL', 'i', [$userId]),
        'documents' => dbAll($db, 'SELECT d.title, dt.name_key type, d.original_filename filename, d.scope, d.version, d.created_at FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.deleted_at IS NULL', 'i', [$userId]),
        'calendar' => calendarEventRows($db, $userId, (new DateTimeImmutable('-30 days'))->setTime(0, 0), (new DateTimeImmutable('+90 days'))->setTime(23, 59, 59)),
        default => dbAll($db, 'SELECT j.title, c.name company, j.location_text location, j.status, j.workplace_type, j.updated_at FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL', 'i', [$userId]),
    };

    $rows = array_values(array_filter($rows, static function(array $row) use ($q, $status, $base): bool {
        if ($status !== '') {
            if ($base === 'contacts') {
                $hasOpen = (int)($row['open_logs'] ?? 0) > 0;
                if (($status === 'open' && !$hasOpen) || ($status === 'none' && $hasOpen)) {
                    return false;
                }
            } elseif ((string)($row['status'] ?? '') !== $status) {
                return false;
            }
        }
        if ($q === '') {
            return true;
        }
        return str_contains(strtolower(implode(' ', array_map('strval', $row))), $q);
    }));

    $sort = (array) ($settings['sort'] ?? []);
    $sortField = isset($fields[(string)($sort['field_name'] ?? '')]) ? (string)$sort['field_name'] : ($columns[0] ?? array_key_first($fields));
    $dir = strtolower((string)($sort['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    usort($rows, static function(array $a, array $b) use ($sortField, $dir): int {
        $result = strcmp((string)($a[$sortField] ?? ''), (string)($b[$sortField] ?? ''));
        return $dir === 'desc' ? -$result : $result;
    });

    $data = [];
    foreach ($rows as $row) {
        $data[] = array_map(static function(string $field) use ($row, $currentUser): string {
            $value = $row[$field] ?? '';
            if (in_array($field, ['updated_at','created_at','applied_at','next_action_at','starts_at'], true)) {
                return displayDateTime($value ?: null, $currentUser);
            }
            if ($field === 'is_intermediary') {
                return !empty($value) ? 'Ja' : 'Nein';
            }
            return (string) $value;
        }, $columns);
    }

    return [$headers, $data];
}

function calendarViewOptions(): array
{
    return ['agenda'=>'Agenda','day'=>'Tagesplan','workweek'=>'Arbeitswoche','week'=>'Wochenplan','month'=>'Monatsplan'];
}

function calendarAnchorDate(array $user): DateTimeImmutable
{
    $timezone = new DateTimeZone((string) ($user['timezone'] ?? 'Europe/Zurich'));
    $date = (string) ($_GET['date'] ?? '');
    try {
        return $date !== '' ? new DateTimeImmutable($date, $timezone) : new DateTimeImmutable('today', $timezone);
    } catch (Throwable) {
        return new DateTimeImmutable('today', $timezone);
    }
}

function calendarRange(string $view, DateTimeImmutable $anchor): array
{
    return match ($view) {
        'day' => [$anchor->setTime(0, 0), $anchor->setTime(23, 59, 59), '-1 day', '+1 day'],
        'workweek' => [$anchor->modify('monday this week')->setTime(0, 0), $anchor->modify('friday this week')->setTime(23, 59, 59), '-1 week', '+1 week'],
        'week' => [$anchor->modify('monday this week')->setTime(0, 0), $anchor->modify('sunday this week')->setTime(23, 59, 59), '-1 week', '+1 week'],
        'month' => [$anchor->modify('first day of this month')->setTime(0, 0), $anchor->modify('last day of this month')->setTime(23, 59, 59), '-1 month', '+1 month'],
        default => [$anchor->setTime(0, 0), $anchor->modify('+60 days')->setTime(23, 59, 59), '-30 days', '+30 days'],
    };
}

function calendarEventRows(mysqli $db, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $startSql = $start->format('Y-m-d H:i:s');
    $endSql = $end->format('Y-m-d H:i:s');
    $rows = [];
    foreach (dbAll($db, 'SELECT ce.id, ce.title, ce.event_type, ce.starts_at, ce.ends_at, ce.status, ce.notes, ce.application_id, ce.contact_id, j.title job_title, c.name company_name FROM calendar_events ce LEFT JOIN applications a ON a.id=ce.application_id LEFT JOIN jobs j ON j.id=a.job_id LEFT JOIN companies c ON c.id=j.company_id WHERE ce.owner_user_id=? AND ce.starts_at BETWEEN ? AND ? ORDER BY ce.starts_at ASC', 'iss', [$userId, $startSql, $endSql]) as $event) {
        $rows[] = [
            'source' => 'calendar',
            'id' => (int) $event['id'],
            'title' => (string) $event['title'],
            'type' => (string) $event['event_type'],
            'status' => (string) $event['status'],
            'starts_at' => (string) $event['starts_at'],
            'ends_at' => (string) ($event['ends_at'] ?: date('Y-m-d H:i:s', strtotime((string) $event['starts_at']) + 1800)),
            'meta' => trim((string) (($event['job_title'] ?? '') . (($event['company_name'] ?? '') ? ' · ' . $event['company_name'] : ''))),
            'notes' => (string) ($event['notes'] ?? ''),
            'href' => !empty($event['application_id']) ? '/?page=applications&edit=' . (int) $event['application_id'] . '#application-form' : '#event-' . (int) $event['id'],
        ];
    }
    foreach (dbAll($db, 'SELECT a.id, a.next_action, a.next_action_at, j.title job_title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL AND a.next_action_at BETWEEN ? AND ? ORDER BY a.next_action_at ASC', 'iss', [$userId, $startSql, $endSql]) as $todo) {
        $rows[] = [
            'source' => 'application',
            'id' => (int) $todo['id'],
            'title' => (string) ($todo['next_action'] ?: 'Pendent'),
            'type' => 'Pendent',
            'status' => 'open',
            'starts_at' => (string) $todo['next_action_at'],
            'ends_at' => date('Y-m-d H:i:s', strtotime((string) $todo['next_action_at']) + 1800),
            'meta' => trim((string) ($todo['job_title'] . ' · ' . $todo['company_name'])),
            'notes' => '',
            'href' => '/?page=applications&edit=' . (int) $todo['id'] . '#application-form',
        ];
    }
    foreach (dbAll($db, 'SELECT l.id, l.contact_id, l.channel, l.status, l.subject, l.follow_up_at, c.first_name, c.last_name, co.name company_name FROM contact_logs l JOIN contacts c ON c.id=l.contact_id JOIN companies co ON co.id=l.company_id WHERE l.owner_user_id=? AND l.follow_up_at BETWEEN ? AND ? ORDER BY l.follow_up_at ASC', 'iss', [$userId, $startSql, $endSql]) as $log) {
        $rows[] = [
            'source' => 'contact_log',
            'id' => (int) $log['id'],
            'title' => (string) ($log['subject'] ?: 'Kontakt nachfassen'),
            'type' => contactLogChannelOptions()[(string)$log['channel']] ?? (string) $log['channel'],
            'status' => (string) $log['status'],
            'starts_at' => (string) $log['follow_up_at'],
            'ends_at' => date('Y-m-d H:i:s', strtotime((string) $log['follow_up_at']) + 1800),
            'meta' => trim((string) ($log['first_name'] . ' ' . $log['last_name'] . ' · ' . $log['company_name'])),
            'notes' => '',
            'href' => '/?page=contacts&edit_contact=' . (int) $log['contact_id'] . '#contact-log',
        ];
    }
    usort($rows, static fn(array $a, array $b): int => strcmp((string)$a['starts_at'], (string)$b['starts_at']));
    return $rows;
}

function calendarIcsEscape(string $value): string
{
    return str_replace(["\\", "\r\n", "\n", ",", ";"], ["\\\\", "\\n", "\\n", "\\,", "\\;"], $value);
}

function calendarIcsResponse(string $filename, array $events): never
{
    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//JeMa Jobs//Calendar//DE\r\nCALSCALE:GREGORIAN\r\n";
    foreach ($events as $event) {
        $start = gmdate('Ymd\THis\Z', strtotime((string)$event['starts_at']));
        $end = gmdate('Ymd\THis\Z', strtotime((string)$event['ends_at']));
        $uid = calendarIcsEscape($event['source'] . '-' . $event['id'] . '@jobs.jema.business');
        $summary = calendarIcsEscape((string)$event['title']);
        $description = calendarIcsEscape(trim((string)$event['meta'] . "\n" . (string)$event['notes']));
        $ics .= "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:{$start}\r\nDTEND:{$end}\r\nSUMMARY:{$summary}\r\nDESCRIPTION:{$description}\r\nEND:VEVENT\r\n";
    }
    $ics .= "END:VCALENDAR\r\n";
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . strlen($ics));
    echo $ics;
    exit;
}

function localeForCountry(?string $countryCode): string
{
    return match (strtoupper((string) $countryCode)) {
        'CH' => 'de_CH',
        'DE' => 'de_DE',
        'AT' => 'de_AT',
        'FR' => 'fr_FR',
        'IT' => 'it_IT',
        'ES' => 'es_ES',
        'PT' => 'pt_PT',
        'GB' => 'en_GB',
        default => 'de_CH',
    };
}

function displayDateTime(?string $value, ?array $user = null, bool $withTime = true): string
{
    if (!$value) {
        return '';
    }
    try {
        $timezone = new DateTimeZone((string) ($user['timezone'] ?? 'Europe/Zurich'));
        $date = new DateTimeImmutable($value, $timezone);
        $locale = localeForCountry($user['country_code'] ?? 'CH');
        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::MEDIUM,
                $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
                $timezone->getName()
            );
            $formatted = $formatter->format($date);
            if ($formatted !== false) {
                return $formatted;
            }
        }
        return $date->format($withTime ? 'd.m.Y H:i' : 'd.m.Y');
    } catch (Throwable) {
        return (string) $value;
    }
}

function storageRoot(): string
{
    return __DIR__ . '/storage/documents';
}

function ensureDocumentStorage(int $userId): string
{
    $root = storageRoot();
    if (!is_dir($root)) {
        mkdir($root, 0775, true);
    }
    $deny = dirname($root) . '/.htaccess';
    if (!is_file($deny)) {
        file_put_contents($deny, "Require all denied\n");
    }
    $dir = $root . '/' . $userId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function uploadDocumentFile(array $file, int $userId): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload fehlgeschlagen.');
    }
    if ((int) $file['size'] > 25 * 1024 * 1024) {
        throw new RuntimeException('Datei ist grösser als 25 MB.');
    }
    $original = basename((string) $file['name']);
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','jpg','jpeg','png','txt'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Dateityp ist noch nicht erlaubt.');
    }
    $dir = ensureDocumentStorage($userId);
    $name = bin2hex(random_bytes(18)) . '.' . $extension;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }
    $mime = function_exists('mime_content_type') ? (mime_content_type($target) ?: 'application/octet-stream') : 'application/octet-stream';
    return [
        'original' => $original,
        'path' => 'storage/documents/' . $userId . '/' . $name,
        'mime' => $mime,
        'size' => filesize($target) ?: (int) $file['size'],
        'sha256' => hash_file('sha256', $target),
    ];
}

function dbOne(mysqli $db, string $sql, string $types = '', array $values = []): ?array
{
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    $rows = statementRows($stmt, 1);
    return $rows[0] ?? null;
}

function dbAll(mysqli $db, string $sql, string $types = '', array $values = []): array
{
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    return statementRows($stmt);
}

function statementRows(mysqli_stmt $stmt, ?int $limit = null): array
{
    $metadata = $stmt->result_metadata();
    if (!$metadata) {
        return [];
    }

    $row = [];
    $references = [];
    foreach ($metadata->fetch_fields() as $field) {
        $row[$field->name] = null;
        $references[] = &$row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $references);

    $rows = [];
    while ($stmt->fetch()) {
        $copy = [];
        foreach ($row as $name => $value) {
            $copy[$name] = $value;
        }
        $rows[] = $copy;
        if ($limit !== null && count($rows) >= $limit) {
            break;
        }
    }
    return $rows;
}

function isAdmin(mysqli $db, int $userId, array $config = []): bool
{
    if ($userId < 1) {
        return false;
    }
    $user = dbOne($db, 'SELECT email FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$userId]);
    $adminEmails = array_map('strtolower', (array) ($config['admin_emails'] ?? ['admin@jema.business']));
    if ($user && in_array(strtolower((string) $user['email']), $adminEmails, true)) {
        return true;
    }
    $role = dbOne(
        $db,
        "SELECT 1 is_admin FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.code='admin' LIMIT 1",
        'i',
        [$userId]
    );
    return (bool) $role;
}

function activeSupportGrant(mysqli $db, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    try {
        return dbOne($db, 'SELECT * FROM support_access_grants WHERE user_id=? AND revoked_at IS NULL ORDER BY granted_at DESC LIMIT 1', 'i', [$userId]);
    } catch (Throwable) {
        return null;
    }
}

function userLabel(array $user): string
{
    $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
    return $name !== '' ? $name : (string) ($user['email'] ?? 'Benutzer');
}

function audit(mysqli $db, int $userId, string $action, string $entityType, int $entityId, ?array $old, ?array $new): void
{
    $stmt = $db->prepare(
        'INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) '
        . 'VALUES (?, ?, ?, ?, ?, ?, INET6_ATON(?), ?)'
    );
    $oldJson = $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $newJson = $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $stmt->bind_param('ississss', $userId, $action, $entityType, $entityId, $oldJson, $newJson, $ip, $agent);
    $stmt->execute();
}

function applicationNextActionChoices(): array
{
    return [
        'Unterlagen prüfen',
        'Motivationsschreiben erstellen',
        'E-Mail-Betreff und Begleittext erstellen',
        'Bewerbung senden',
        'Eingang bestätigen lassen',
        'Nachfassen',
        'Interview vorbereiten',
        'Referenzen nachreichen',
        'Angebot prüfen',
        'Absage verarbeiten',
        'Archivieren',
    ];
}

function applicationPrompt(mysqli $db, int $userId, int $applicationId, array $currentUser): string
{
    $application = dbOne($db, 'SELECT a.*, j.title job_title, j.location_text, j.status job_status, j.workplace_type, j.engagement_type, j.contract_term, j.source_url, SUBSTRING(j.description,1,65535) job_description, c.name company_name, c.website company_website, c.phone company_phone, c.address_line1, c.address_line2, c.postal_code, c.city company_city, c.region company_region, c.country_code company_country, i.name intermediary_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$applicationId, $userId]);
    if (!$application) {
        return '';
    }
    $preference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [$userId]) ?: [];
    $languages = dbAll($db, 'SELECT language_name, cefr_level FROM user_language_skills WHERE user_id=? ORDER BY language_name', 'i', [$userId]);
    $contacts = dbAll($db, 'SELECT co.name company_name, c.first_name, c.last_name, c.position, c.department, c.email, c.phone, c.mobile, c.notes FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND (c.application_id=? OR c.job_id=? OR c.company_id=? OR c.company_id=?) AND c.deleted_at IS NULL ORDER BY co.name, c.last_name, c.first_name', 'iiiii', [$userId, $applicationId, (int)$application['job_id'], (int)$application['company_id'], (int)($application['intermediary_company_id'] ?? 0)]);
    $logs = dbAll($db, 'SELECT channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE owner_user_id=? AND application_id=? ORDER BY occurred_at DESC LIMIT 20', 'ii', [$userId, $applicationId]);
    $documents = dbAll($db, "SELECT d.scope, d.title, d.version, d.original_filename, dt.code type_code FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id JOIN document_types dt ON dt.id=d.document_type_id WHERE ad.application_id=? AND d.user_id=? AND d.deleted_at IS NULL ORDER BY d.scope DESC, d.title", 'ii', [$applicationId, $userId]);
    $history = dbAll($db, 'SELECT old_status, new_status, comment, changed_at FROM application_status_history WHERE application_id=? ORDER BY changed_at DESC', 'i', [$applicationId]);

    $lines = [
        'Erstelle für diese Bewerbung drei Texte:',
        '1. einen prägnanten E-Mail-Betreff',
        '2. einen kurzen professionellen E-Mail-Begleittext',
        '3. ein individuelles Motivationsschreiben',
        '',
        'Sprache/Ton: ' . (documentLanguageChoices()[$currentUser['preferred_language'] ?? 'de'] ?? 'Deutsch') . ', professionell, klar, natürlich, nicht übertrieben.',
        'Bitte keine Fakten erfinden. Wenn eine Information fehlt, formuliere neutral oder markiere sie als Platzhalter.',
        '',
        '=== Bewerberprofil ===',
        'Name: ' . trim((string)($currentUser['first_name'] ?? '') . ' ' . (string)($currentUser['last_name'] ?? '')),
        'E-Mail: ' . (string)($currentUser['email'] ?? ''),
        'Telefon: ' . trim((string)($currentUser['phone'] ?? '') . ' ' . (string)($currentUser['mobile'] ?? '')),
        'Ort/Region/Land: ' . trim((string)($currentUser['city'] ?? '') . ' / ' . (string)($currentUser['region'] ?? '') . ' / ' . (string)($currentUser['country_code'] ?? '')),
        'Sprachen: ' . ($languages ? implode(', ', array_map(static fn(array $row): string => $row['language_name'] . ' ' . $row['cefr_level'], $languages)) : 'keine erfasst'),
        '',
        '=== Job-Referenzen / Wünsche ===',
        'Gewünschte Rollen: ' . (string)($preference['desired_roles'] ?? ''),
        'Gewünschte Orte: ' . (string)($preference['desired_locations'] ?? ''),
        'Arbeitsmodell: ' . (string)($preference['remote_preference'] ?? ''),
        'Stellenarten: ' . (string)($preference['employment_types'] ?? ''),
        'Pensum: ' . trim((string)($preference['workload_min'] ?? '') . ' - ' . (string)($preference['workload_max'] ?? '') . '%'),
        'Lohnvorstellung: ' . trim((string)($preference['salary_min'] ?? '') . ' ' . (string)($preference['salary_currency'] ?? '') . ' / ' . (string)($preference['salary_period'] ?? '')),
        'Benefits/PK/Extras: ' . (string)($preference['desired_benefits'] ?? ''),
        'Ausschlüsse: ' . (string)($preference['excluded_industries'] ?? ''),
        'Notizen: ' . (string)($preference['notes'] ?? ''),
        '',
        '=== Stelle ===',
        'Jobtitel: ' . (string)$application['job_title'],
        'Firma: ' . (string)$application['company_name'],
        'Vermittlerfirma: ' . (string)($application['intermediary_name'] ?? ''),
        'Ort: ' . (string)$application['location_text'],
        'Arbeitsort-Modell: ' . (string)$application['workplace_type'],
        'Stellentyp: ' . (string)$application['engagement_type'] . ' / ' . (string)$application['contract_term'],
        'Quelle: ' . (string)$application['source_url'],
        'Beschreibung: ' . (string)$application['job_description'],
        '',
        '=== Firma ===',
        'Website: ' . (string)$application['company_website'],
        'Telefon: ' . (string)$application['company_phone'],
        'Adresse: ' . trim((string)$application['address_line1'] . "\n" . (string)$application['address_line2'] . "\n" . (string)$application['postal_code'] . ' ' . (string)$application['company_city']),
        'Region/Land: ' . (string)$application['company_region'] . ' / ' . (string)$application['company_country'],
        '',
        '=== Bewerbung ===',
        'Status: ' . (string)$application['status'],
        'Kanal: ' . (string)$application['channel'],
        'Gesendet am: ' . displayDateTime($application['applied_at'] ?? null, $currentUser),
        'Online-Bewerbungs-URL: ' . (string)($application['application_url'] ?? ''),
        'Portal / Konto-Hinweis: ' . (string)($application['portal_account'] ?? ''),
        'Referenznummer: ' . (string)($application['reference_number'] ?? ''),
        'Online-Notizen: ' . (string)($application['online_notes'] ?? ''),
        'Nächster Schritt: ' . (string)$application['next_action'],
        'Fällig am: ' . displayDateTime($application['next_action_at'] ?? null, $currentUser),
        'Bestehender Betreff: ' . (string)$application['email_subject'],
        'Bestehender Begleittext: ' . (string)$application['email_body'],
        'Interne Notizen: ' . (string)$application['notes'],
        '',
        '=== Kontakte ===',
    ];
    foreach ($contacts as $contact) {
        $lines[] = trim($contact['company_name'] . ': ' . $contact['first_name'] . ' ' . $contact['last_name'] . ', ' . $contact['position'] . ' ' . $contact['department'] . ', ' . $contact['email'] . ', ' . $contact['phone'] . ' ' . $contact['mobile'] . ', Notizen: ' . $contact['notes']);
    }
    if (!$contacts) {
        $lines[] = 'keine Kontakte erfasst';
    }
    $lines[] = '';
    $lines[] = '=== Kontakt-Log ===';
    foreach ($logs as $log) {
        $lines[] = displayDateTime($log['occurred_at'] ?? null, $currentUser) . ' · ' . $log['channel'] . ' · ' . $log['direction'] . ' · ' . $log['status'] . ' · ' . $log['subject'] . ' · ' . $log['body'] . ' · Ergebnis: ' . $log['outcome'] . ' · Wiedervorlage: ' . displayDateTime($log['follow_up_at'] ?? null, $currentUser);
    }
    if (!$logs) {
        $lines[] = 'keine Kontaktaktivitäten erfasst';
    }
    $lines[] = '';
    $lines[] = '=== Zugeordnete Dokumente ===';
    foreach ($documents as $document) {
        $lines[] = ($document['scope'] === 'profile' ? 'Stammdaten' : 'Bewerbungsdaten') . ' · ' . documentTypeLabel((string)$document['type_code'], (string)($currentUser['preferred_language'] ?? 'de')) . ' · ' . $document['title'] . ' · v' . $document['version'] . ' · ' . $document['original_filename'];
    }
    if (!$documents) {
        $lines[] = 'keine Dokumente zugeordnet';
    }
    $lines[] = '';
    $lines[] = '=== Statusverlauf ===';
    foreach ($history as $entry) {
        $lines[] = displayDateTime($entry['changed_at'] ?? null, $currentUser) . ' · ' . $entry['old_status'] . ' -> ' . $entry['new_status'] . ' · ' . $entry['comment'];
    }
    if (!$history) {
        $lines[] = 'kein Statusverlauf vorhanden';
    }
    $lines[] = '';
    $lines[] = 'Ausgabe bitte mit Überschriften: E-Mail-Betreff, E-Mail-Begleittext, Motivationsschreiben.';

    return trim(implode("\n", $lines));
}

function matchJob(array $job): array
{
    $score = 50;
    $reasons = [];
    if (($job['workplace_type'] ?? '') === 'remote') {
        $score += 15;
        $reasons[] = 'Remote-Arbeit möglich';
    }
    if (!empty($job['salary_min'])) {
        $score += 10;
        $reasons[] = 'Lohn ist angegeben';
    }
    if (!empty($job['description'])) {
        $score += 10;
        $reasons[] = 'Detaillierte Ausschreibung';
    }
    if (($job['status'] ?? '') === 'interesting') {
        $score += 15;
        $reasons[] = 'Als interessant markiert';
    }
    return [min(100, $score), $reasons ?: ['Noch nicht genügend Daten für eine Detailbewertung']];
}

function plainText(string $value): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

function publicHttpUrl(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
        return false;
    }
    $records = dns_get_record($parts['host'], DNS_A | DNS_AAAA);
    if (!$records) {
        return false;
    }
    foreach ($records as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? '';
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
    }
    return true;
}

function findJobPosting(mixed $value): ?array
{
    if (!is_array($value)) {
        return null;
    }
    $type = $value['@type'] ?? null;
    if ($type === 'JobPosting' || (is_array($type) && in_array('JobPosting', $type, true))) {
        return $value;
    }
    foreach ($value as $child) {
        $found = findJobPosting($child);
        if ($found) {
            return $found;
        }
    }
    return null;
}

function importFromUrl(string $url): array
{
    if (!publicHttpUrl($url) || !function_exists('curl_init')) {
        throw new RuntimeException('Die URL ist nicht erreichbar oder aus Sicherheitsgründen nicht erlaubt.');
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'JeMaJobs/0.1 (+https://jobs.jema.business)',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $html = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $finalUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($curl);
    curl_close($curl);
    if (!is_string($html) || $status >= 400 || strlen($html) > 5_000_000) {
        throw new RuntimeException($error ?: 'Die Stellenanzeige konnte nicht gelesen werden.');
    }

    $document = new DOMDocument();
    @$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    $xpath = new DOMXPath($document);
    $job = null;
    foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
        try {
            $json = json_decode((string) $script->textContent, true, 64, JSON_THROW_ON_ERROR);
            $job = findJobPosting($json);
            if ($job) {
                break;
            }
        } catch (Throwable) {
        }
    }

    $title = plainText((string) ($job['title'] ?? ''));
    $company = plainText((string) ($job['hiringOrganization']['name'] ?? ''));
    $description = plainText((string) ($job['description'] ?? ''));
    $location = plainText((string) ($job['jobLocation']['address']['addressLocality'] ?? ''));

    if ($title === '') {
        $node = $xpath->query('//meta[@property="og:title"]/@content')->item(0) ?: $xpath->query('//title')->item(0);
        $title = plainText((string) ($node?->nodeValue ?? ''));
    }
    if ($description === '') {
        $node = $xpath->query('//meta[@name="description"]/@content')->item(0);
        $description = plainText((string) ($node?->nodeValue ?? ''));
    }
    return compact('title', 'company', 'location', 'description') + ['source_url' => $finalUrl ?: $url];
}

function importFromText(string $text): array
{
    $text = trim($text);
    $lines = preg_split('/\R/u', $text) ?: [];
    $values = ['title' => '', 'company' => '', 'location' => '', 'description' => $text, 'source_url' => ''];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if ($values['title'] === '') {
            $values['title'] = $line;
        }
        foreach (['title' => 'Titel|Position|Stelle', 'company' => 'Firma|Unternehmen|Arbeitgeber', 'location' => 'Ort|Standort|Arbeitsort'] as $field => $labels) {
            if (preg_match('/^(?:' . $labels . ')\s*:\s*(.+)$/iu', $line, $match)) {
                $values[$field] = trim($match[1]);
            }
        }
        if ($values['source_url'] === '' && preg_match('~https?://\S+~i', $line, $match)) {
            $values['source_url'] = rtrim($match[0], '.,;)');
        }
    }
    return $values;
}

function originalPdfStatusLabel(?string $status): string
{
    return match ($status) {
        'rendered' => 'Original-PDF bereit',
        'pending' => 'Original-PDF ausstehend',
        'failed' => 'Original-PDF fehlgeschlagen',
        default => 'Kein Original-PDF',
    };
}

function timezoneChoices(): array
{
    return [
        'Europa' => [
            'Europe/Zurich' => 'Zürich / Schweiz',
            'Europe/Berlin' => 'Berlin / Deutschland',
            'Europe/Vienna' => 'Wien / Österreich',
            'Europe/Paris' => 'Paris / Frankreich',
            'Europe/Rome' => 'Rom / Italien',
            'Europe/Madrid' => 'Madrid / Spanien',
            'Europe/Lisbon' => 'Lissabon / Portugal',
            'Europe/London' => 'London / UK',
        ],
        'Amerika' => [
            'America/New_York' => 'New York',
            'America/Chicago' => 'Chicago',
            'America/Denver' => 'Denver',
            'America/Los_Angeles' => 'Los Angeles',
            'America/Sao_Paulo' => 'São Paulo',
        ],
        'Afrika' => [
            'Africa/Casablanca' => 'Casablanca',
            'Africa/Johannesburg' => 'Johannesburg',
        ],
        'Asien' => [
            'Asia/Dubai' => 'Dubai',
            'Asia/Singapore' => 'Singapur',
            'Asia/Tokyo' => 'Tokio',
        ],
        'Ozeanien' => [
            'Australia/Sydney' => 'Sydney',
        ],
    ];
}

function countryChoices(): array
{
    return [
        'CH' => 'Schweiz',
        'LI' => 'Liechtenstein',
        'DE' => 'Deutschland',
        'AT' => 'Österreich',
        'FR' => 'Frankreich',
        'IT' => 'Italien',
        'ES' => 'Spanien',
        'PT' => 'Portugal',
        'GB' => 'Vereinigtes Königreich',
        'US' => 'USA',
        'BR' => 'Brasilien',
    ];
}

function regionChoices(): array
{
    return [
        'CH' => [
            'Seeland',
            'Mittelland',
            'Zürcher Oberland',
            'Zürich Stadt',
            'Ostschweiz',
            'Rheintal',
            'Nordwestschweiz',
            'Zentralschweiz',
            'Berner Oberland',
            'Genferseeregion',
            'Tessin',
            'Graubünden',
        ],
        'LI' => ['Oberland', 'Unterland'],
        'DE' => ['Baden-Württemberg', 'Bayern', 'Rhein-Main', 'Rheinland', 'Ruhrgebiet', 'Berlin/Brandenburg', 'Hamburg/Norddeutschland'],
        'AT' => ['Vorarlberg', 'Tirol', 'Salzburg', 'Oberösterreich', 'Wien', 'Steiermark'],
        'FR' => ['Grand Est', 'Auvergne-Rhône-Alpes', 'Île-de-France', 'Provence-Alpes-Côte d’Azur'],
        'IT' => ['Lombardei', 'Piemont', 'Südtirol/Trentino', 'Venetien'],
        'ES' => ['Katalonien', 'Madrid', 'Valencia', 'Andalusien'],
        'PT' => ['Norte', 'Centro', 'Lissabon', 'Alentejo', 'Algarve'],
        'GB' => ['Greater London', 'South East England', 'North West England', 'Scotland'],
        'US' => ['Northeast', 'Midwest', 'South', 'West Coast'],
        'BR' => ['Sudeste', 'Sul', 'Nordeste', 'Centro-Oeste', 'Norte'],
    ];
}

function countryForRegion(string $regionKey): array
{
    if (!str_contains($regionKey, '|')) {
        return [null, null];
    }
    [$country, $region] = explode('|', $regionKey, 2);
    $choices = regionChoices();
    if (!isset($choices[$country]) || !in_array($region, $choices[$country], true)) {
        return [null, null];
    }
    return [$country, $region];
}

function currencyForCountry(?string $countryCode): string
{
    return match ($countryCode) {
        'CH', 'LI' => 'CHF',
        'GB' => 'GBP',
        'US' => 'USD',
        'BR' => 'BRL',
        default => 'EUR',
    };
}

function salaryLabel(array $row, ?string $currencyOverride = null): string
{
    $min = $row['salary_min'] ?? null;
    $max = $row['salary_max'] ?? null;
    if ($min === null && $max === null) {
        return '';
    }
    $currency = (string)($currencyOverride ?: ($row['salary_currency'] ?? 'CHF'));
    $period = ['hour'=>'pro Stunde','month'=>'pro Monat','year'=>'pro Jahr'][(string)($row['salary_period'] ?? 'year')] ?? 'pro Jahr';
    $format = static fn($value): string => rtrim(rtrim(number_format((float)$value, 2, '.', "'"), '0'), '.');
    return $format($min ?? $max) . ' ' . $currency . ' ' . $period;
}

function europeanLanguageChoices(): array
{
    return [
        'de' => 'Deutsch',
        'en' => 'Englisch',
        'fr' => 'Französisch',
        'it' => 'Italienisch',
        'es' => 'Spanisch',
        'pt' => 'Portugiesisch',
        'nl' => 'Niederländisch',
        'da' => 'Dänisch',
        'sv' => 'Schwedisch',
        'no' => 'Norwegisch',
        'fi' => 'Finnisch',
        'is' => 'Isländisch',
        'ga' => 'Irisch',
        'cy' => 'Walisisch',
        'pl' => 'Polnisch',
        'cs' => 'Tschechisch',
        'sk' => 'Slowakisch',
        'sl' => 'Slowenisch',
        'hr' => 'Kroatisch',
        'bs' => 'Bosnisch',
        'sr' => 'Serbisch',
        'bg' => 'Bulgarisch',
        'ro' => 'Rumänisch',
        'hu' => 'Ungarisch',
        'el' => 'Griechisch',
        'tr' => 'Türkisch',
        'uk' => 'Ukrainisch',
        'ru' => 'Russisch',
        'et' => 'Estnisch',
        'lv' => 'Lettisch',
        'lt' => 'Litauisch',
        'mt' => 'Maltesisch',
        'sq' => 'Albanisch',
        'ca' => 'Katalanisch',
        'eu' => 'Baskisch',
        'gl' => 'Galicisch',
        'lb' => 'Luxemburgisch',
        'rm' => 'Rätoromanisch',
    ];
}

function documentTypeLabel(string $code, string $language = 'de'): string
{
    $labels = [
        'de' => [
            'cv' => 'Lebenslauf',
            'certificate' => 'Zeugnis / Zertifikat',
            'reference_letter' => 'Referenzschreiben',
            'diploma' => 'Diplom / Abschluss',
            'cover_letter' => 'Motivationsschreiben',
            'portfolio' => 'Portfolio / Arbeitsprobe',
            'other' => 'Sonstiges',
        ],
        'en' => [
            'cv' => 'CV',
            'certificate' => 'Certificate / testimonial',
            'reference_letter' => 'Reference letter',
            'diploma' => 'Diploma / degree',
            'cover_letter' => 'Cover letter',
            'portfolio' => 'Portfolio / work sample',
            'other' => 'Other',
        ],
        'es' => [
            'cv' => 'Currículum',
            'certificate' => 'Certificado / referencia laboral',
            'reference_letter' => 'Carta de referencia',
            'diploma' => 'Diploma / título',
            'cover_letter' => 'Carta de motivación',
            'portfolio' => 'Portafolio / muestra de trabajo',
            'other' => 'Otro',
        ],
        'pt' => [
            'cv' => 'Currículo',
            'certificate' => 'Certificado / declaração',
            'reference_letter' => 'Carta de referência',
            'diploma' => 'Diploma / formação',
            'cover_letter' => 'Carta de apresentação',
            'portfolio' => 'Portfólio / amostra de trabalho',
            'other' => 'Outro',
        ],
    ];
    $language = isset($labels[$language]) ? $language : 'de';
    return $labels[$language][$code] ?? $labels[$language]['other'];
}

function documentPurposeForType(string $code): string
{
    return match ($code) {
        'cv' => 'cv',
        'cover_letter' => 'cover_letter',
        'certificate', 'diploma' => 'certificate',
        'reference_letter' => 'reference',
        'portfolio' => 'portfolio',
        default => 'other',
    };
}

function documentPurposeLabel(string $purpose, string $language = 'de'): string
{
    $code = match ($purpose) {
        'cv' => 'cv',
        'cover_letter' => 'cover_letter',
        'certificate' => 'certificate',
        'reference' => 'reference_letter',
        'portfolio' => 'portfolio',
        default => 'other',
    };
    return documentTypeLabel($code, $language);
}

function documentLanguageChoices(): array
{
    return ['de' => 'Deutsch', 'es' => 'Español', 'pt' => 'Português', 'en' => 'English'];
}

function allowedDocumentTypeCodes(string $scope): array
{
    if ($scope === 'application') {
        return ['cover_letter', 'other'];
    }
    return ['cv', 'certificate', 'reference_letter', 'diploma', 'portfolio', 'other'];
}

function documentTypesForScope(array $types, string $scope): array
{
    $allowed = allowedDocumentTypeCodes($scope);
    return array_values(array_filter($types, static fn (array $type): bool => in_array((string) $type['code'], $allowed, true)));
}

function contactLogChannelOptions(): array
{
    return [
        'email' => 'E-Mail über App',
        'external_email' => 'E-Mail extern',
        'onsite' => 'Vor Ort',
        'phone' => 'Telefon',
        'video' => 'Video Call',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'message' => 'Nachricht',
        'letter' => 'Brief',
        'note' => 'Notiz',
        'other' => 'Andere',
    ];
}

function contactLogStatusOptions(): array
{
    return ['planned'=>'Geplant','open'=>'Offen','done'=>'Erledigt','cancelled'=>'Abgebrochen'];
}

function saveContactLogAttachment(mysqli $db, int $userId, int $contactId, int $logId, string $contactName, string $subject): ?int
{
    $file = $_FILES['log_attachment'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $type = dbOne($db, "SELECT id FROM document_types WHERE code='other' LIMIT 1");
    if (!$type) {
        throw new RuntimeException('Dokumenttyp für Anhänge fehlt.');
    }
    $uploaded = uploadDocumentFile($file, $userId);
    $scope = 'profile';
    $documentTypeId = (int) $type['id'];
    $languageCode = null;
    $applicationId = null;
    $jobId = null;
    $title = 'Kontakt-Log: ' . mb_strimwidth($subject !== '' ? $subject : $contactName, 0, 150, '...');
    $description = 'Anhang zu Kontakt ' . $contactName . ' / Kontakt-Log ' . $logId;
    $validFrom = null;
    $validUntil = null;
    $version = 1;
    $stmt = $db->prepare('INSERT INTO user_documents (user_id, document_type_id, language_code, scope, application_id, job_id, title, description, original_filename, storage_path, mime_type, file_size, sha256, valid_from, valid_until, version, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $stmt->bind_param('iissiisssssisssi', $userId, $documentTypeId, $languageCode, $scope, $applicationId, $jobId, $title, $description, $uploaded['original'], $uploaded['path'], $uploaded['mime'], $uploaded['size'], $uploaded['sha256'], $validFrom, $validUntil, $version);
    $stmt->execute();
    $documentId = (int) $stmt->insert_id;
    $extractStatus = in_array($uploaded['mime'], ['text/plain','application/pdf'], true) ? 'pending' : 'skipped';
    $textStmt = $db->prepare('INSERT INTO document_texts (user_document_id, extraction_status, language_code) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE extraction_status=VALUES(extraction_status), language_code=VALUES(language_code)');
    $textStmt->bind_param('iss', $documentId, $extractStatus, $languageCode);
    $textStmt->execute();
    $linkStmt = $db->prepare('INSERT IGNORE INTO contact_log_documents (contact_log_id, user_document_id) VALUES (?, ?)');
    $linkStmt->bind_param('ii', $logId, $documentId);
    $linkStmt->execute();
    audit($db, $userId, 'create', 'contact_log_attachment', $documentId, null, ['contact_id'=>$contactId,'contact_log_id'=>$logId,'title'=>$title]);
    return $documentId;
}

function contactLogAttachments(mysqli $db, int $userId, int $contactId): array
{
    $rows = dbAll($db, 'SELECT cld.contact_log_id, d.id, d.original_filename FROM contact_log_documents cld JOIN contact_logs l ON l.id=cld.contact_log_id JOIN user_documents d ON d.id=cld.user_document_id WHERE l.owner_user_id=? AND l.contact_id=? AND d.deleted_at IS NULL ORDER BY d.created_at DESC', 'ii', [$userId, $contactId]);
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int)$row['contact_log_id']][] = $row;
    }
    return $grouped;
}

function contactLogFormHtml(?array $entry, int $applicationId, int $contactId, array $channels, array $statuses): string
{
    $isEdit = (bool) $entry;
    $occurred = $entry && !empty($entry['occurred_at']) ? date('Y-m-d\TH:i', strtotime((string)$entry['occurred_at'])) : date('Y-m-d\TH:i');
    $followUp = $entry && !empty($entry['follow_up_at']) ? date('Y-m-d\TH:i', strtotime((string)$entry['follow_up_at'])) : '';
    ob_start();
    ?>
    <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <?php if($applicationId > 0): ?><input type="hidden" name="application_id" value="<?= $applicationId ?>"><?php endif; ?>
        <input type="hidden" name="contact_id" value="<?= $contactId ?>">
        <?php if($isEdit): ?><input type="hidden" name="log_id" value="<?= (int)$entry['id'] ?>"><?php endif; ?>
        <div class="three">
            <label>Kanal<select name="log_channel"><?php foreach($channels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($entry['channel'] ?? '')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Richtung<select name="direction"><?php foreach(['outgoing'=>'Ausgehend','incoming'=>'Eingehend','internal'=>'Intern'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($entry['direction'] ?? 'outgoing')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Status<select name="log_status"><?php foreach($statuses as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($entry['status'] ?? 'open')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="two"><label>Datum/Uhrzeit<input type="datetime-local" name="occurred_at" value="<?= e($occurred) ?>" required></label><label>Wiedervorlage<input type="datetime-local" name="follow_up_at" value="<?= e($followUp) ?>"></label></div>
        <label>Betreff<input name="subject" value="<?= e($entry['subject'] ?? '') ?>"></label>
        <label>Mitteilung<textarea name="log_body" rows="5"><?= e($entry['body'] ?? '') ?></textarea></label>
        <div class="two"><label>Ergebnis / nächster Schritt<input name="outcome" value="<?= e($entry['outcome'] ?? '') ?>"></label><label>Anhang<input type="file" name="log_attachment"></label></div>
        <div class="actions"><button class="primary" name="action" value="<?= $isEdit ? 'update_contact_log' : 'save_contact_log' ?>"><?= $isEdit ? 'Aktivität aktualisieren' : 'Aktivität speichern' ?></button><?php if($isEdit): ?><a class="button" href="<?= e($applicationId > 0 ? '/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contact-log' : '/?page=contacts&edit_contact=' . $contactId . '#contact-log') ?>">Abbrechen</a><?php endif; ?></div>
    </form>
    <?php
    return (string) ob_get_clean();
}

function contactLogTimelineHtml(array $logs, array $attachments, array $channels, array $statuses, array $currentUser, int $applicationId = 0): string
{
    ob_start();
    ?>
    <div class="log-timeline">
        <?php foreach($logs as $entry): ?><article class="log-status-<?= e($entry['status']) ?>">
            <div><strong><?= e($entry['subject'] ?: ($channels[$entry['channel']] ?? ucfirst((string)$entry['channel']))) ?></strong><span><?= e(($statuses[$entry['status']] ?? $entry['status']).' · '.($channels[$entry['channel']] ?? $entry['channel']).' · '.$entry['direction'].' · '.displayDateTime($entry['occurred_at'], $currentUser)) ?></span></div>
            <?php if($entry['body']): ?><p><?= nl2br(e($entry['body'])) ?></p><?php endif; ?>
            <?php if($entry['outcome']): ?><small>Ergebnis / nächster Schritt: <?= e($entry['outcome']) ?></small><?php endif; ?>
            <?php if($entry['follow_up_at']): ?><small>Wiedervorlage: <?= e(displayDateTime($entry['follow_up_at'], $currentUser)) ?></small><?php endif; ?>
            <?php foreach(($attachments[(int)$entry['id']] ?? []) as $doc): ?><small>Anhang: <a href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e($doc['original_filename']) ?></a></small><?php endforeach; ?>
            <div class="actions"><a href="<?= e($applicationId > 0 ? '/?page=applications&edit=' . $applicationId . '&contact=' . (int)$entry['contact_id'] . '&edit_log=' . (int)$entry['id'] . '#contact-log' : '/?page=contacts&edit_contact=' . (int)$entry['contact_id'] . '&edit_log=' . (int)$entry['id'] . '#contact-log') ?>">Bearbeiten</a><form method="post" onsubmit="return confirm('Kontaktaktivität löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="log_id" value="<?= (int)$entry['id'] ?>"><button name="action" value="delete_contact_log">Löschen</button></form></div>
        </article><?php endforeach; ?>
        <?php if(!$logs): ?><p class="empty">Noch keine Kontaktaktivitäten.</p><?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

$page = (string) ($_GET['page'] ?? (userId() ? 'dashboard' : 'login'));
$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($action === 'register') {
        $email = strtolower(trim((string) $_POST['email']));
        $first = trim((string) $_POST['first_name']);
        $last = trim((string) $_POST['last_name']);
        $password = (string) $_POST['password'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || $first === '' || $last === '') {
            flash('Bitte gültige Daten und mindestens 10 Passwortzeichen eingeben.', 'danger');
            redirect('/?page=register');
        }
        try {
            $emailNeedsVerification = outboundEmailEnabled($config);
            $stmt = $db->prepare(
                "INSERT INTO users (email, password_hash, status, preferred_language, first_name, last_name, email_verified_at) "
                . "VALUES (?, ?, 'active', 'de', ?, ?, " . ($emailNeedsVerification ? 'NULL' : 'NOW()') . ")"
            );
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('ssss', $email, $hash, $first, $last);
            $stmt->execute();
            $newUserId = (int) $stmt->insert_id;
            audit($db, $newUserId, 'create', 'user', $newUserId, null, ['email' => $email]);
            unset($_SESSION['email_verify_link'], $_SESSION['email_verify_notice']);
            if ($emailNeedsVerification) {
                $token = bin2hex(random_bytes(32));
                $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
                $tokenType = 'email_verify';
                $tokenHash = hash('sha256', $token);
                $tokenStmt = $db->prepare('INSERT INTO auth_tokens (user_id, token_type, token_hash, expires_at) VALUES (?, ?, ?, ?)');
                $tokenStmt->bind_param('isss', $newUserId, $tokenType, $tokenHash, $expiresAt);
                $tokenStmt->execute();
                $verifyLink = absoluteUrl($config, '/?page=verify_email&token=' . urlencode($token));
                $subject = 'E-Mail fur JeMa Jobs bestaetigen';
                $body = "Hallo {$first}\n\nbitte bestaetige deine E-Mail-Adresse fur JeMa Jobs innerhalb von 24 Stunden:\n" . $verifyLink . "\n\nWenn du dich nicht registriert hast, kannst du diese Nachricht ignorieren.\n";
                try {
                    sendSmtpMail($config, $email, $subject, $body);
                    logOutboundEmail($db, $newUserId, $email, $subject, $body, 'sent');
                    $_SESSION['email_verify_notice'] = 'Registrierung gespeichert. Bitte bestätige deine E-Mail-Adresse über den Link in deinem Postfach.';
                } catch (Throwable $exception) {
                    logOutboundEmail($db, $newUserId, $email, $subject, $body, 'failed', $exception->getMessage());
                    $_SESSION['email_verify_notice'] = 'Registrierung gespeichert, aber die Bestätigungs-E-Mail konnte nicht versendet werden. Bitte System-SMTP-Konfiguration prüfen.';
                }
            } else {
                $_SESSION['email_verify_notice'] = 'Registrierung gespeichert. E-Mail-Versand ist deaktiviert; das Konto wurde für die Testphase direkt bestätigt.';
            }
            flash($emailNeedsVerification ? 'Registrierung gespeichert. Bitte E-Mail bestätigen.' : 'Registrierung gespeichert. Du kannst dich jetzt direkt anmelden.');
            redirect('/?page=login');
        } catch (mysqli_sql_exception $exception) {
            flash('Diese E-Mail-Adresse ist bereits registriert.', 'danger');
            redirect('/?page=register');
        }
    }

    if ($action === 'login') {
        $email = strtolower(trim((string) $_POST['email']));
        $password = (string) $_POST['password'];
        $user = dbOne($db, 'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', 's', [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('E-Mail oder Passwort ist falsch.', 'danger');
            redirect('/?page=login');
        }
        if (in_array((string) $user['status'], ['locked', 'disabled'], true)) {
            flash('Dieses Konto ist gesperrt oder deaktiviert.', 'warning');
            redirect('/?page=login');
        }
        if (empty($user['email_verified_at'])) {
            flash('Bitte bestätige zuerst deine E-Mail-Adresse.', 'warning');
            redirect('/?page=login');
        }
        $totp = activeTotpMethod($db, (int) $user['id']);
        if ($totp) {
            session_regenerate_id(true);
            $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
            $_SESSION['pending_2fa_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            redirect('/?page=two_factor');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $db->query('UPDATE users SET last_login_at = NOW(), last_seen_at = NOW() WHERE id = ' . (int) $user['id']);
        touchUserPresence($db, (int) $user['id']);
        audit($db, (int) $user['id'], 'login', 'user', (int) $user['id'], null, null);
        redirect('/');
    }

    if ($action === 'verify_two_factor') {
        $pendingUserId = (int) ($_SESSION['pending_2fa_user_id'] ?? 0);
        $user = $pendingUserId ? dbOne($db, 'SELECT * FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$pendingUserId]) : null;
        $totp = $user ? activeTotpMethod($db, $pendingUserId) : null;
        if (!$user || !$totp || !verifyTotpCode((string) $totp['secret_encrypted'], (string) ($_POST['totp_code'] ?? ''))) {
            flash('2FA-Code ist ungültig.', 'danger');
            redirect('/?page=two_factor');
        }
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_user_name']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $pendingUserId;
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $db->query('UPDATE users SET last_login_at = NOW(), last_seen_at = NOW() WHERE id = ' . $pendingUserId);
        touchUserPresence($db, $pendingUserId);
        $stmt = $db->prepare('UPDATE two_factor_methods SET last_used_at=NOW() WHERE id=?');
        $methodId = (int) $totp['id'];
        $stmt->bind_param('i', $methodId);
        $stmt->execute();
        audit($db, $pendingUserId, 'login', 'user', $pendingUserId, null, ['two_factor' => 'totp']);
        redirect('/');
    }

    if ($action === 'request_password_reset') {
        $email = strtolower(trim((string) $_POST['email']));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? dbOne($db, "SELECT id, status FROM users WHERE email=? AND deleted_at IS NULL AND status NOT IN ('locked','disabled')", 's', [$email])
            : null;
        unset($_SESSION['password_reset_link']);
        unset($_SESSION['password_reset_notice']);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $tokenType = 'password_reset';
            $stmt = $db->prepare('UPDATE auth_tokens SET consumed_at=NOW() WHERE user_id=? AND token_type=? AND consumed_at IS NULL');
            $stmt->bind_param('is', $user['id'], $tokenType);
            $stmt->execute();
            $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO auth_tokens (user_id, token_type, token_hash, expires_at) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $user['id'], $tokenType, $tokenHash, $expiresAt);
            $stmt->execute();
            $resetPath = '/?page=reset_password&token=' . urlencode($token);
            $resetLink = (appUrl($config) ?: '') . $resetPath;
            $subject = 'Passwort fur JeMa Jobs zurucksetzen';
            $body = "Hallo\n\nfur dein JeMa Jobs Konto wurde ein Passwort-Reset angefordert.\n\nBitte offne diesen Link innerhalb von 60 Minuten:\n" . $resetLink . "\n\nWenn du den Reset nicht angefordert hast, kannst du diese Nachricht ignorieren.\n";
            if (mailEnabledForUser($db, $config, (int) $user['id'])) {
                try {
                    sendConfiguredMail($db, $config, (int) $user['id'], $email, $subject, $body);
                    $_SESSION['password_reset_notice'] = 'Reset-Link wurde per E-Mail versendet.';
                } catch (Throwable $exception) {
                    $_SESSION['password_reset_notice'] = 'E-Mail konnte nicht versendet werden. Bitte eigene SMTP-Konfiguration prüfen.';
                }
            } else {
                $_SESSION['password_reset_link'] = $resetLink;
                $_SESSION['password_reset_notice'] = 'Reset-Link wurde erstellt.';
            }
            audit($db, (int) $user['id'], 'other', 'auth_token', (int) $stmt->insert_id, null, ['token_type' => 'password_reset']);
        } else {
            $_SESSION['password_reset_notice'] = 'Testphase: Für diese E-Mail wurde kein aktives Konto gefunden.';
        }
        flash('Falls das Konto existiert, wurde ein Zurücksetzen vorbereitet.', 'success');
        redirect('/?page=forgot_password&sent=1');
    }

    if ($action === 'reset_password') {
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if ($token === '' || strlen($password) < 10 || $password !== $confirm) {
            flash('Token ungültig oder Passwörter passen nicht zusammen. Mindestens 10 Zeichen.', 'danger');
            redirect('/?page=reset_password&token=' . urlencode($token));
        }
        $tokenHash = hash('sha256', $token);
        $reset = dbOne(
            $db,
            "SELECT t.id token_id, t.user_id, u.status
               FROM auth_tokens t
               JOIN users u ON u.id=t.user_id
              WHERE t.token_type='password_reset'
                AND t.token_hash=?
                AND t.consumed_at IS NULL
                AND t.expires_at > NOW()
                AND u.deleted_at IS NULL
              LIMIT 1",
            's',
            [$tokenHash]
        );
        if (!$reset || in_array((string) $reset['status'], ['locked', 'disabled'], true)) {
            flash('Dieser Link ist ungültig oder abgelaufen.', 'danger');
            redirect('/?page=forgot_password');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password_hash=?, failed_login_count=0, locked_until=NULL WHERE id=?');
        $stmt->bind_param('si', $hash, $reset['user_id']);
        $stmt->execute();
        $stmt = $db->prepare('UPDATE auth_tokens SET consumed_at=NOW() WHERE id=?');
        $stmt->bind_param('i', $reset['token_id']);
        $stmt->execute();
        audit($db, (int) $reset['user_id'], 'update', 'user', (int) $reset['user_id'], null, ['password_reset' => true]);
        unset($_SESSION['password_reset_link']);
        flash('Passwort wurde geändert. Du kannst dich jetzt anmelden.');
        redirect('/?page=login');
    }

    if ($action === 'logout') {
        endUserPresenceSession($db, realUserId());
        session_destroy();
        redirect('/?page=login');
    }

    requireLogin();

    if ($action === 'stop_admin_support') {
        if (!isSupportImpersonation()) {
            redirect('/');
        }
        $targetId = userId();
        $adminId = realUserId();
        audit($db, $targetId, 'other', 'support_access', $targetId, null, ['admin_user_id' => $adminId, 'ended' => true]);
        endSupportImpersonationSession();
        flash('Support-Umgebung beendet. Du bist wieder in deinem Admin-Konto.');
        redirect('/?page=admin_users');
    }

    if ($action === 'grant_admin_support') {
        if (isSupportImpersonation()) {
            flash('Support-Freigaben können während einer Support-Sitzung nicht geändert werden.', 'warning');
            redirect('/?page=profile#support-access');
        }
        $uid = userId();
        $stmt = $db->prepare('UPDATE support_access_grants SET revoked_at=NOW(), revoked_by_user_id=? WHERE user_id=? AND revoked_at IS NULL');
        $stmt->bind_param('ii', $uid, $uid);
        $stmt->execute();
        $stmt = $db->prepare('INSERT INTO support_access_grants (user_id, granted_by_user_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $uid, $uid);
        $stmt->execute();
        audit($db, $uid, 'create', 'support_access', (int) $stmt->insert_id, null, ['granted' => true]);
        flash('ADMIN Support ist freigegeben. Ein Administrator kann sich jetzt in deine Umgebung einklinken.');
        redirect('/?page=profile#support-access');
    }

    if ($action === 'revoke_admin_support') {
        if (isSupportImpersonation()) {
            flash('Support-Freigaben können während einer Support-Sitzung nicht geändert werden.', 'warning');
            redirect('/?page=profile#support-access');
        }
        $uid = userId();
        $stmt = $db->prepare('UPDATE support_access_grants SET revoked_at=NOW(), revoked_by_user_id=? WHERE user_id=? AND revoked_at IS NULL');
        $stmt->bind_param('ii', $uid, $uid);
        $stmt->execute();
        audit($db, $uid, 'delete', 'support_access', $uid, null, ['revoked' => true]);
        flash('ADMIN Support wurde widerrufen.');
        redirect('/?page=profile#support-access');
    }

    if ($action === 'admin_start_support') {
        $adminId = realUserId();
        if (!isAdmin($db, $adminId, $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        if (isSupportImpersonation()) {
            flash('Beende zuerst die aktuelle Support-Umgebung.', 'warning');
            redirect('/');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === $adminId) {
            flash('Das eigene Konto kann nicht als Support-Umgebung geöffnet werden.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, "SELECT id, email, first_name, last_name, status FROM users WHERE id=? AND deleted_at IS NULL AND status NOT IN ('locked','disabled')", 'i', [$targetUserId]);
        $grant = activeSupportGrant($db, $targetUserId);
        if (!$target || !$grant) {
            flash('Für diesen Benutzer liegt keine aktive ADMIN-Support-Freigabe vor.', 'danger');
            redirect('/?page=admin_users');
        }
        $admin = dbOne($db, 'SELECT first_name, last_name, email FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$adminId]) ?: [];
        session_regenerate_id(true);
        $_SESSION['support_admin_user_id'] = $adminId;
        $_SESSION['support_admin_name'] = userLabel($admin);
        $_SESSION['support_target_user_id'] = $targetUserId;
        $_SESSION['support_target_name'] = userLabel($target);
        $_SESSION['user_id'] = $targetUserId;
        $_SESSION['user_name'] = userLabel($target);
        audit($db, $targetUserId, 'other', 'support_access', (int) $grant['id'], null, ['admin_user_id' => $adminId, 'started' => true]);
        flash('Support-Umgebung geöffnet: ' . userLabel($target));
        redirect('/');
    }

    if ($action === 'admin_update_user') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === realUserId()) {
            flash('Das eigene Admin-Konto kann hier nicht geändert werden.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, 'SELECT id, email, first_name, last_name, status FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        if (!$target) {
            flash('Benutzer nicht gefunden.', 'danger');
            redirect('/?page=admin_users');
        }
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $firstName === '' || $lastName === '') {
            flash('Name und gültige E-Mail sind erforderlich.', 'danger');
            redirect('/?page=admin_users');
        }
        $status = in_array($_POST['status'] ?? '', ['invited','active','locked','disabled'], true) ? (string) $_POST['status'] : (string) $target['status'];
        try {
            $stmt = $db->prepare('UPDATE users SET email=?, first_name=?, last_name=?, status=?, email_verified_at=CASE WHEN ?="active" THEN COALESCE(email_verified_at, NOW()) ELSE email_verified_at END WHERE id=?');
            $stmt->bind_param('sssssi', $email, $firstName, $lastName, $status, $status, $targetUserId);
            $stmt->execute();
        } catch (mysqli_sql_exception) {
            flash('Diese E-Mail-Adresse ist bereits vergeben.', 'danger');
            redirect('/?page=admin_users');
        }

        $adminRole = dbOne($db, "SELECT id FROM roles WHERE code='admin' LIMIT 1");
        $isAdminTarget = !empty($_POST['is_admin']);
        if ($adminRole) {
            $roleId = (int) $adminRole['id'];
            if ($isAdminTarget) {
                $stmt = $db->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)');
                $uid = realUserId();
                $stmt->bind_param('iii', $targetUserId, $roleId, $uid);
                $stmt->execute();
            } else {
                $stmt = $db->prepare('DELETE FROM user_roles WHERE user_id=? AND role_id=?');
                $stmt->bind_param('ii', $targetUserId, $roleId);
                $stmt->execute();
            }
        }
        audit($db, realUserId(), 'update', 'user', $targetUserId, $target, ['email' => $email, 'first_name' => $firstName, 'last_name' => $lastName, 'status' => $status, 'is_admin' => $isAdminTarget]);
        flash('Benutzer aktualisiert.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_reset_user_password') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === realUserId()) {
            flash('Das eigene Admin-Passwort bitte über Passwort vergessen oder Profil-Sicherheit ändern.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, 'SELECT id, email, status FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        $password = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        if (!$target || strlen($password) < 10 || $password !== $confirm) {
            flash('Benutzer nicht gefunden oder Passwort ungültig. Mindestens 10 Zeichen und beide Felder gleich.', 'danger');
            redirect('/?page=admin_users');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password_hash=?, failed_login_count=0, locked_until=NULL WHERE id=?');
        $stmt->bind_param('si', $hash, $targetUserId);
        $stmt->execute();
        $stmt = $db->prepare("UPDATE auth_tokens SET consumed_at=NOW() WHERE user_id=? AND token_type='password_reset' AND consumed_at IS NULL");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        audit($db, realUserId(), 'update', 'user', $targetUserId, ['admin_password_reset' => true], ['target_email' => $target['email']]);
        flash('Passwort wurde zurückgesetzt.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_delete_user') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === realUserId()) {
            flash('Das eigene Admin-Konto kann hier nicht gelöscht werden.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, 'SELECT id, email, status FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        if (!$target) {
            flash('Benutzer nicht gefunden.', 'danger');
            redirect('/?page=admin_users');
        }
        $adminEmails = array_map('strtolower', (array) ($config['admin_emails'] ?? ['admin@jema.business']));
        if (in_array(strtolower((string) $target['email']), $adminEmails, true)) {
            flash('Ein Config-Admin kann hier nicht gelöscht werden.', 'warning');
            redirect('/?page=admin_users');
        }
        $stmt = $db->prepare('UPDATE users SET deleted_at=NOW(), status="disabled" WHERE id=?');
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $stmt = $db->prepare('UPDATE auth_tokens SET consumed_at=NOW() WHERE user_id=? AND consumed_at IS NULL');
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        audit($db, realUserId(), 'delete', 'user', $targetUserId, $target, ['soft_delete' => true]);
        flash('Benutzer wurde gelöscht.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_reset_user_2fa') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === realUserId()) {
            flash('Die eigene 2FA bitte im Profil verwalten.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, 'SELECT id, email FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        if (!$target) {
            flash('Benutzer nicht gefunden.', 'danger');
            redirect('/?page=admin_users');
        }
        $stmt = $db->prepare('DELETE FROM two_factor_methods WHERE user_id=?');
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $stmt = $db->prepare("UPDATE auth_tokens SET consumed_at=NOW() WHERE user_id=? AND token_type='two_factor' AND consumed_at IS NULL");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        audit($db, realUserId(), 'delete', 'user', $targetUserId, ['two_factor_reset' => true], ['target_email' => $target['email']]);
        flash('2FA wurde für den Benutzer zurückgesetzt.');
        redirect('/?page=admin_users');
    }

    if ($action === 'enable_totp') {
        $code = (string) ($_POST['totp_code'] ?? '');
        $secret = (string) ($_SESSION['totp_setup_secret'] ?? '');
        if ($secret === '' || !verifyTotpCode($secret, $code)) {
            flash('Der 2FA-Code passt nicht. Bitte erneut versuchen.', 'danger');
            redirect('/?page=profile#security');
        }
        $uid = userId();
        $stmt = $db->prepare("DELETE FROM two_factor_methods WHERE user_id=? AND method='totp'");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $label = 'Authenticator-App';
        $stmt = $db->prepare("INSERT INTO two_factor_methods (user_id, method, label, secret_encrypted, is_primary, verified_at) VALUES (?, 'totp', ?, ?, 1, NOW())");
        $stmt->bind_param('iss', $uid, $label, $secret);
        $stmt->execute();
        unset($_SESSION['totp_setup_secret']);
        audit($db, $uid, 'create', 'user', $uid, null, ['two_factor' => 'totp']);
        flash('2FA per Authenticator-App ist aktiviert.');
        redirect('/?page=profile#security');
    }

    if ($action === 'disable_totp') {
        $uid = userId();
        $stmt = $db->prepare("DELETE FROM two_factor_methods WHERE user_id=? AND method='totp'");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        unset($_SESSION['totp_setup_secret']);
        audit($db, $uid, 'delete', 'user', $uid, ['two_factor' => 'totp'], null);
        flash('2FA wurde deaktiviert.');
        redirect('/?page=profile#security');
    }

    if ($action === 'create_share') {
        $targetType = in_array($_POST['target_type'] ?? '', ['area','company','job','application','contact','document','report'], true) ? (string) $_POST['target_type'] : 'area';
        $targetId = $targetType === 'area' ? null : max(1, (int) ($_POST['target_id'] ?? 0));
        $recipient = strtolower(trim((string) ($_POST['recipient_email'] ?? '')));
        $permission = in_array($_POST['permission'] ?? '', ['view','comment','edit'], true) ? (string) $_POST['permission'] : 'view';
        $downloadPolicy = in_array($_POST['download_policy'] ?? '', ['none','original','pdf','both'], true) ? (string) $_POST['download_policy'] : 'none';
        $title = trim((string) ($_POST['title'] ?? '')) ?: 'Freigabe';
        $expiresAt = trim((string) ($_POST['expires_at'] ?? '')) ?: null;
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || ($targetType !== 'area' && !$targetId)) {
            flash('Empfänger und Ziel der Freigabe sind erforderlich.', 'danger');
            redirect('/?page=sharing');
        }
        if ($expiresAt) {
            $expiresAt = str_replace('T', ' ', $expiresAt) . (strlen($expiresAt) === 16 ? ':00' : '');
        }
        $token = shareToken();
        $hash = shareTokenHash($token);
        $watermark = !empty($_POST['watermark_enabled']) ? 1 : 0;
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO guest_shares (owner_user_id, token_hash, title, target_type, target_id, recipient_email, permission, download_policy, watermark_enabled, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssisssis', $uid, $hash, $title, $targetType, $targetId, $recipient, $permission, $downloadPolicy, $watermark, $expiresAt);
        $stmt->execute();
        $shareId = (int) $stmt->insert_id;
        $link = absoluteUrl($config, '/?page=guest&token=' . urlencode($token));
        $_SESSION['last_share_link'] = $link;
        $subject = 'JeMa Jobs Freigabe';
        $body = "Hallo\n\nes wurde ein JeMa Jobs Bereich für dich freigegeben:\n" . $link . "\n\nDiese Freigabe ist persönlich und kann widerrufen werden.\n";
        try {
            if (sendConfiguredMail($db, $config, $uid, $recipient, $subject, $body)) {
                flash('Freigabe erstellt und per E-Mail versendet.');
            } else {
                flash('Freigabe erstellt. Eigener SMTP-Versand ist nicht aktiv; Link wird angezeigt.', 'warning');
            }
        } catch (Throwable) {
            flash('Freigabe erstellt. E-Mail konnte nicht versendet werden; Link wird angezeigt.', 'warning');
        }
        audit($db, $uid, 'create', 'guest_share', $shareId, null, ['target_type' => $targetType, 'target_id' => $targetId, 'recipient_email' => $recipient]);
        redirect('/?page=sharing');
    }

    if ($action === 'revoke_share') {
        $id = (int) ($_POST['share_id'] ?? 0);
        $old = dbOne($db, 'SELECT * FROM guest_shares WHERE id=? AND owner_user_id=? AND revoked_at IS NULL', 'ii', [$id, userId()]);
        if ($old) {
            $stmt = $db->prepare('UPDATE guest_shares SET revoked_at=NOW() WHERE id=? AND owner_user_id=?');
            $uid = userId();
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'guest_share', $id, $old, ['revoked' => true]);
            flash('Freigabe widerrufen.');
        }
        redirect('/?page=sharing');
    }

    if ($action === 'save_translation') {
        $entityType = in_array($_POST['entity_type'] ?? '', ['company','job','contact','application','document'], true) ? (string) $_POST['entity_type'] : 'job';
        $entityId = max(1, (int) ($_POST['entity_id'] ?? 0));
        $targetLanguage = in_array($_POST['target_language'] ?? '', ['de','en','es','pt'], true) ? (string) $_POST['target_language'] : 'de';
        $title = trim((string) ($_POST['translation_title'] ?? '')) ?: null;
        $body = trim((string) ($_POST['translation_body'] ?? ''));
        if ($body === '') {
            flash('Übersetzungstext ist erforderlich.', 'danger');
            redirect('/?page=translations');
        }
        $uid = userId();
        $existing = dbOne($db, 'SELECT COALESCE(MAX(version),0) v FROM record_translations WHERE owner_user_id=? AND entity_type=? AND entity_id=? AND target_language=?', 'isis', [$uid, $entityType, $entityId, $targetLanguage]);
        $version = (int) ($existing['v'] ?? 0) + 1;
        $stmt = $db->prepare('UPDATE record_translations SET is_current=0 WHERE owner_user_id=? AND entity_type=? AND entity_id=? AND target_language=?');
        $stmt->bind_param('isis', $uid, $entityType, $entityId, $targetLanguage);
        $stmt->execute();
        $stmt = $db->prepare('INSERT INTO record_translations (owner_user_id, entity_type, entity_id, target_language, title, body, version, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('isisssi', $uid, $entityType, $entityId, $targetLanguage, $title, $body, $version);
        $stmt->execute();
        audit($db, $uid, 'create', 'record_translation', (int) $stmt->insert_id, null, ['entity_type' => $entityType, 'entity_id' => $entityId, 'target_language' => $targetLanguage, 'version' => $version]);
        flash('Übersetzung gespeichert.');
        redirect('/?page=translations');
    }

    if ($action === 'save_calendar_event') {
        $title = trim((string) ($_POST['event_title'] ?? ''));
        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $eventType = in_array($_POST['event_type'] ?? '', ['task','follow_up','interview','deadline','meeting','reminder','other'], true) ? (string) $_POST['event_type'] : 'reminder';
        if ($title === '' || $startsAt === '') {
            flash('Titel und Startzeit sind erforderlich.', 'danger');
            redirect('/?page=calendar');
        }
        $startsAt = str_replace('T', ' ', $startsAt) . (strlen($startsAt) === 16 ? ':00' : '');
        $applicationId = (int) ($_POST['application_id'] ?? 0) ?: null;
        $notes = trim((string) ($_POST['event_notes'] ?? '')) ?: null;
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO calendar_events (owner_user_id, application_id, title, event_type, starts_at, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissss', $uid, $applicationId, $title, $eventType, $startsAt, $notes);
        $stmt->execute();
        audit($db, $uid, 'create', 'calendar_event', (int) $stmt->insert_id, null, ['title' => $title, 'starts_at' => $startsAt]);
        flash('Kalendereintrag gespeichert.');
        redirect('/?page=calendar');
    }

    if ($action === 'save_report') {
        $name = trim((string) ($_POST['report_name'] ?? ''));
        $description = trim((string) ($_POST['report_description'] ?? '')) ?: null;
        $baseEntity = array_key_exists((string) ($_POST['base_entity'] ?? ''), reportBaseOptions()) ? (string) $_POST['base_entity'] : 'jobs';
        $displayType = array_key_exists((string) ($_POST['display_type'] ?? ''), reportDisplayOptions()) ? (string) $_POST['display_type'] : 'table';
        if ($name === '') {
            flash('Report-Name ist erforderlich.', 'danger');
            redirect('/?page=reports');
        }
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO saved_reports (owner_user_id, name, description, base_entity, display_type, is_shared) VALUES (?, ?, ?, ?, ?, 0)');
        $stmt->bind_param('issss', $uid, $name, $description, $baseEntity, $displayType);
        $stmt->execute();
        $reportId = (int) $stmt->insert_id;
        saveReportSettings($db, $reportId, $baseEntity);
        audit($db, $uid, 'create', 'saved_report', $reportId, null, ['name' => $name, 'base_entity' => $baseEntity, 'display_type' => $displayType]);
        flash('Report gespeichert.');
        redirect('/?page=reports');
    }

    if ($action === 'update_report') {
        $id = (int) ($_POST['report_id'] ?? 0);
        $name = trim((string) ($_POST['report_name'] ?? ''));
        $description = trim((string) ($_POST['report_description'] ?? '')) ?: null;
        $baseEntity = array_key_exists((string) ($_POST['base_entity'] ?? ''), reportBaseOptions()) ? (string) $_POST['base_entity'] : 'jobs';
        $displayType = array_key_exists((string) ($_POST['display_type'] ?? ''), reportDisplayOptions()) ? (string) $_POST['display_type'] : 'table';
        $old = dbOne($db, 'SELECT id, name, description, base_entity, display_type FROM saved_reports WHERE id=? AND owner_user_id=?', 'ii', [$id, userId()]);
        if (!$old || $name === '') {
            flash('Report konnte nicht aktualisiert werden.', 'danger');
            redirect('/?page=reports');
        }
        $uid = userId();
        $stmt = $db->prepare('UPDATE saved_reports SET name=?, description=?, base_entity=?, display_type=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('ssssii', $name, $description, $baseEntity, $displayType, $id, $uid);
        $stmt->execute();
        saveReportSettings($db, $id, $baseEntity);
        audit($db, $uid, 'update', 'saved_report', $id, $old, ['name' => $name, 'description' => $description, 'base_entity' => $baseEntity, 'display_type' => $displayType]);
        flash('Report aktualisiert.');
        redirect('/?page=reports&edit_report=' . $id);
    }

    if ($action === 'delete_report') {
        $id = (int) ($_POST['report_id'] ?? 0);
        $old = dbOne($db, 'SELECT id, name, base_entity, display_type FROM saved_reports WHERE id=? AND owner_user_id=?', 'ii', [$id, userId()]);
        if ($old) {
            $uid = userId();
            $stmt = $db->prepare('DELETE FROM saved_reports WHERE id=? AND owner_user_id=?');
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'saved_report', $id, $old, null);
        }
        flash('Report gelöscht.');
        redirect('/?page=reports');
    }

    if ($action === 'request_cleanup') {
        $cutoff = trim((string) ($_POST['cutoff_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff)) {
            flash('Stichtag ist erforderlich.', 'danger');
            redirect('/?page=privacy');
        }
        $preview = cleanupPreview($db, userId(), $cutoff);
        $previewJson = json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $status = 'requested';
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO cleanup_requests (user_id, cutoff_date, status, preview_json, requested_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->bind_param('isss', $uid, $cutoff, $status, $previewJson);
        $stmt->execute();
        audit($db, $uid, 'create', 'cleanup_request', (int) $stmt->insert_id, null, $preview);
        flash('Cleanup-Anfrage mit Vorschau erstellt.');
        redirect('/?page=privacy');
    }

    if ($action === 'preview_import') {
        $payload = trim((string) ($_POST['import_payload'] ?? ''));
        if ($payload === '') {
            flash('Bitte eine Stellen-URL oder den Ausschreibungstext einfügen.', 'danger');
            redirect('/?page=jobs');
        }
        $importLines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $payload))));
        if (count($importLines) > 1) {
            $created = 0; $skipped = 0; $failed = 0; $uid = userId();
            foreach ($importLines as $line) {
                try {
                    $draft = filter_var($line, FILTER_VALIDATE_URL) ? importFromUrl($line) : importFromText($line);
                    $sourceUrl = filter_var($line, FILTER_VALIDATE_URL) ? $line : (string) ($draft['source_url'] ?? '');
                    if ($sourceUrl !== '' && dbOne($db, 'SELECT id FROM jobs WHERE owner_user_id=? AND source_url=? AND deleted_at IS NULL LIMIT 1', 'is', [$uid, $sourceUrl])) {
                        $skipped++;
                        continue;
                    }
                    $companyName = trim((string) ($draft['company'] ?? '')) ?: 'Neue Firma aus Import';
                    $company = dbOne($db, 'SELECT id FROM companies WHERE owner_user_id=? AND name=? AND deleted_at IS NULL LIMIT 1', 'is', [$uid, $companyName]);
                    if ($company) {
                        $companyId = (int) $company['id'];
                    } else {
                        $empty = '';
                        $stmt = $db->prepare('INSERT INTO companies (owner_user_id, name, city, website) VALUES (?, ?, ?, ?)');
                        $stmt->bind_param('isss', $uid, $companyName, $empty, $empty);
                        $stmt->execute();
                        $companyId = (int) $stmt->insert_id;
                        audit($db, $uid, 'create', 'company', $companyId, null, ['name' => $companyName]);
                    }
                    $title = trim((string) ($draft['title'] ?? '')) ?: 'Job aus Import';
                    $location = trim((string) ($draft['location'] ?? $draft['location_text'] ?? ''));
                    $description = trim((string) ($draft['description'] ?? $line));
                    $status = 'open'; $workplace = 'unknown'; $engagement = 'permanent'; $term = 'unknown';
                    $pdfStatus = $sourceUrl !== '' ? 'pending' : 'none';
                    $pdfRequestedAt = $sourceUrl !== '' ? date('Y-m-d H:i:s') : null;
                    $stmt = $db->prepare('INSERT INTO jobs (owner_user_id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, source_url, original_pdf_status, original_pdf_requested_at, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('iissssssssss', $uid, $companyId, $title, $location, $status, $workplace, $engagement, $term, $sourceUrl, $pdfStatus, $pdfRequestedAt, $description);
                    $stmt->execute();
                    $jobId = (int) $stmt->insert_id;
                    audit($db, $uid, 'create', 'job', $jobId, null, ['title' => $title, 'company_id' => $companyId, 'source_url' => $sourceUrl]);
                    $created++;
                } catch (Throwable $exception) {
                    $failed++;
                }
            }
            flash($created . ' Jobs importiert, ' . $skipped . ' Dubletten übersprungen, ' . $failed . ' fehlgeschlagen.');
            redirect('/?page=jobs');
        }
        try {
            $_SESSION['import_draft'] = filter_var($payload, FILTER_VALIDATE_URL)
                ? importFromUrl($payload)
                : importFromText($payload);
            flash('Import gelesen. Bitte Vorschlag prüfen und speichern.');
        } catch (Throwable $exception) {
            flash('Import nicht möglich: ' . $exception->getMessage(), 'danger');
        }
        redirect('/?page=jobs#new');
    }

    if ($action === 'save_profile') {
        $uid = userId();
        $storedUser = dbOne($db, 'SELECT first_name,last_name,preferred_language,timezone,phone,mobile,city,region,country_code FROM users WHERE id=?', 'i', [$uid]);
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last = trim((string) ($_POST['last_name'] ?? ''));
        $language = array_key_exists((string) ($_POST['preferred_language'] ?? ''), documentLanguageChoices())
            ? (string) $_POST['preferred_language']
            : (string) ($storedUser['preferred_language'] ?? 'de');
        $validTimezones = array_keys(array_merge(...array_values(timezoneChoices())));
        $timezone = in_array($_POST['timezone'] ?? '', $validTimezones, true) ? (string) $_POST['timezone'] : 'Europe/Zurich';
        $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
        $mobile = trim((string) ($_POST['mobile'] ?? '')) ?: null;
        $city = trim((string) ($_POST['city'] ?? '')) ?: null;
        [$country, $region] = countryForRegion((string) ($_POST['region_key'] ?? ''));
        if ($first === '' || $last === '') {
            flash('Vorname und Nachname sind erforderlich.', 'danger');
            redirect('/?page=profile');
        }
        $old = $storedUser;
        $stmt = $db->prepare('UPDATE users SET first_name=?, last_name=?, preferred_language=?, timezone=?, phone=?, mobile=?, city=?, region=?, country_code=? WHERE id=?');
        $stmt->bind_param('sssssssssi', $first, $last, $language, $timezone, $phone, $mobile, $city, $region, $country, $uid);
        $stmt->execute();
        $allowedRemote = ['onsite','hybrid','remote','any'];
        $allowedEmployment = ['full_time','part_time','temporary','contract','internship','freelance'];
        $allowedSalaryPeriods = ['hour','month','year'];
        $desiredRoles = trim((string) ($_POST['desired_roles'] ?? '')) ?: null;
        $desiredLocations = trim((string) ($_POST['desired_locations'] ?? '')) ?: null;
        $remotePreference = in_array($_POST['remote_preference'] ?? '', $allowedRemote, true) ? (string) $_POST['remote_preference'] : 'any';
        $employmentTypes = array_values(array_intersect((array) ($_POST['employment_types'] ?? []), $allowedEmployment));
        $employmentTypeSet = $employmentTypes ? implode(',', $employmentTypes) : null;
        $workloadMin = $_POST['workload_min'] !== '' ? min(100, max(0, (int) $_POST['workload_min'])) : null;
        $workloadMax = $_POST['workload_max'] !== '' ? min(100, max(0, (int) $_POST['workload_max'])) : null;
        $salaryMin = $_POST['salary_min'] !== '' ? (float) $_POST['salary_min'] : null;
        $salaryMax = null;
        $salaryCurrency = currencyForCountry($country ?: ($storedUser['country_code'] ?? 'CH'));
        $salaryPeriod = in_array($_POST['salary_period'] ?? '', $allowedSalaryPeriods, true) ? (string) $_POST['salary_period'] : 'year';
        $desiredLevel = trim((string) ($_POST['desired_level'] ?? '')) ?: null;
        $desiredBenefits = trim((string) ($_POST['desired_benefits'] ?? '')) ?: null;
        $excludedIndustries = trim((string) ($_POST['excluded_industries'] ?? '')) ?: null;
        $willingToRelocate = !empty($_POST['willing_to_relocate']) ? 1 : 0;
        $travelPercentage = $_POST['travel_percentage'] !== '' ? min(100, max(0, (int) $_POST['travel_percentage'])) : null;
        $availableFrom = trim((string) ($_POST['available_from'] ?? '')) ?: null;
        $preferenceNotes = trim((string) ($_POST['preference_notes'] ?? '')) ?: null;
        $oldPreference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [$uid]);
        if ($oldPreference) {
            $preferenceId = (int) $oldPreference['id'];
            $prefStmt = $db->prepare('UPDATE user_preferences SET desired_roles=?, desired_locations=?, remote_preference=?, employment_types=?, workload_min=?, workload_max=?, salary_min=?, salary_max=?, salary_currency=?, salary_period=?, desired_level=?, desired_benefits=?, excluded_industries=?, willing_to_relocate=?, travel_percentage=?, available_from=?, notes=? WHERE id=? AND user_id=?');
            $prefStmt->bind_param('ssssiiddsssssiissii', $desiredRoles, $desiredLocations, $remotePreference, $employmentTypeSet, $workloadMin, $workloadMax, $salaryMin, $salaryMax, $salaryCurrency, $salaryPeriod, $desiredLevel, $desiredBenefits, $excludedIndustries, $willingToRelocate, $travelPercentage, $availableFrom, $preferenceNotes, $preferenceId, $uid);
            $prefStmt->execute();
        } else {
            $title = 'Default';
            $prefStmt = $db->prepare('INSERT INTO user_preferences (user_id, title, desired_roles, desired_locations, remote_preference, employment_types, workload_min, workload_max, salary_min, salary_max, salary_currency, salary_period, desired_level, desired_benefits, excluded_industries, willing_to_relocate, travel_percentage, available_from, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $prefStmt->bind_param('isssssiiddsssssiiss', $uid, $title, $desiredRoles, $desiredLocations, $remotePreference, $employmentTypeSet, $workloadMin, $workloadMax, $salaryMin, $salaryMax, $salaryCurrency, $salaryPeriod, $desiredLevel, $desiredBenefits, $excludedIndustries, $willingToRelocate, $travelPercentage, $availableFrom, $preferenceNotes);
            $prefStmt->execute();
        }
        $languageCodes = (array) ($_POST['language_codes'] ?? []);
        $languageLevels = (array) ($_POST['language_levels'] ?? []);
        $removeLanguageIndexes = array_map('intval', (array) ($_POST['remove_language_indexes'] ?? []));
        $validLevels = ['A1','A2','B1','B2','C1','C2'];
        $languageChoices = europeanLanguageChoices();
        $languageSkillMap = [];
        $languageErrors = [];
        $seenLanguageCodes = [];
        foreach ($languageCodes as $index => $code) {
            $code = (string) $code;
            $level = (string) ($languageLevels[$index] ?? '');
            if (in_array((int)$index, $removeLanguageIndexes, true) || ($code === '' && $level === '')) {
                continue;
            }
            if (!isset($languageChoices[$code]) || !in_array($level, $validLevels, true)) {
                $languageErrors[] = 'Bitte bei jedem Sprachrecord Sprache und Niveau auswählen.';
                continue;
            }
            if (isset($seenLanguageCodes[$code])) {
                $languageErrors[] = 'Sprache "' . $languageChoices[$code] . '" ist doppelt erfasst.';
                continue;
            }
            $seenLanguageCodes[$code] = true;
            $languageSkillMap[$code] = $level;
        }
        if (!$languageSkillMap) {
            $languageErrors[] = 'Bitte mindestens eine Sprache erfassen.';
        }
        if (!in_array('C2', $languageSkillMap, true)) {
            $languageErrors[] = 'Bitte mindestens eine Muttersprache mit Niveau C2 erfassen.';
        }
        if ($languageErrors) {
            flash(implode(' ', array_unique($languageErrors)), 'danger');
            redirect('/?page=profile');
        }
        $db->query('DELETE FROM user_language_skills WHERE user_id=' . $uid);
        $skillStmt = $db->prepare('INSERT INTO user_language_skills (user_id, language_code, language_name, cefr_level) VALUES (?, ?, ?, ?)');
        $savedLanguageSkills = [];
        foreach ($languageSkillMap as $code => $level) {
            $name = $languageChoices[$code];
            $skillStmt->bind_param('isss', $uid, $code, $name, $level);
            $skillStmt->execute();
            $savedLanguageSkills[$code] = $level;
        }
        $_SESSION['user_name'] = $first . ' ' . $last;
        audit($db, $uid, 'update', 'profile', $uid, $old, ['first_name'=>$first,'last_name'=>$last,'preferred_language'=>$language,'timezone'=>$timezone,'language_skills'=>$savedLanguageSkills]);
        flash('Profil gespeichert.');
        redirect('/?page=profile');
    }

    if ($action === 'save_smtp_settings' || $action === 'test_smtp_settings') {
        requireLogin();
        $uid = userId();
        $host = trim((string) ($_POST['smtp_host'] ?? ''));
        $port = (int) ($_POST['smtp_port'] ?? 587);
        $encryption = in_array((string) ($_POST['smtp_encryption'] ?? 'tls'), ['tls', 'ssl', 'none'], true) ? (string) $_POST['smtp_encryption'] : 'tls';
        $username = trim((string) ($_POST['smtp_username'] ?? '')) ?: null;
        $password = (string) ($_POST['smtp_password'] ?? '');
        $fromEmail = strtolower(trim((string) ($_POST['from_email'] ?? '')));
        $fromName = trim((string) ($_POST['from_name'] ?? '')) ?: null;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        if ($host === '' || $port < 1 || $port > 65535 || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            flash('SMTP-Host, Port und Absender-E-Mail sind erforderlich.', 'danger');
            redirect('/?page=profile#smtp');
        }
        $existing = dbOne($db, 'SELECT smtp_password_encrypted FROM user_smtp_settings WHERE user_id=? LIMIT 1', 'i', [$uid]);
        $encryptedPassword = $existing['smtp_password_encrypted'] ?? null;
        if ($password !== '') {
            $encryptedPassword = encryptSecret($config, $password);
        }
        if ($existing) {
            $stmt = $db->prepare('UPDATE user_smtp_settings SET smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_username=?, smtp_password_encrypted=?, from_email=?, from_name=?, is_active=? WHERE user_id=?');
            $stmt->bind_param('sisssssii', $host, $port, $encryption, $username, $encryptedPassword, $fromEmail, $fromName, $isActive, $uid);
            $stmt->execute();
        } else {
            $stmt = $db->prepare('INSERT INTO user_smtp_settings (user_id, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password_encrypted, from_email, from_name, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isisssssi', $uid, $host, $port, $encryption, $username, $encryptedPassword, $fromEmail, $fromName, $isActive);
            $stmt->execute();
        }
        audit($db, $uid, 'update', 'user_smtp_settings', $uid, null, ['smtp_host' => $host, 'from_email' => $fromEmail, 'is_active' => $isActive]);
        if ($action === 'test_smtp_settings') {
            try {
                $smtpTestUser = dbOne($db, 'SELECT email FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$uid]);
                sendConfiguredMail($db, $config, $uid, (string) ($smtpTestUser['email'] ?? $fromEmail), 'JeMa Jobs SMTP-Test', "Hallo\n\nDer E-Mail-Versand über deine SMTP-Konfiguration funktioniert.\n");
                flash('SMTP gespeichert und Test-E-Mail versendet.');
            } catch (Throwable $exception) {
                flash('SMTP gespeichert, Test-E-Mail fehlgeschlagen: ' . $exception->getMessage(), 'danger');
            }
        } else {
            flash('SMTP-Einstellungen gespeichert.');
        }
        redirect('/?page=profile#smtp');
    }

    if ($action === 'upload_document') {
        $uid = userId();
        $replaceId = (int) ($_POST['replace_document_id'] ?? 0);
        $documentTypeId = (int) ($_POST['document_type_id'] ?? 0);
        $languageCode = array_key_exists((string) ($_POST['document_language'] ?? ''), documentLanguageChoices()) ? (string) $_POST['document_language'] : null;
        $title = trim((string) ($_POST['document_title'] ?? ''));
        $description = trim((string) ($_POST['document_description'] ?? '')) ?: null;
        $validFrom = trim((string) ($_POST['valid_from'] ?? '')) ?: null;
        $validUntil = trim((string) ($_POST['valid_until'] ?? '')) ?: null;
        $scope = ($_POST['document_scope'] ?? '') === 'application' ? 'application' : 'profile';
        $applicationId = $scope === 'application' ? (int) ($_POST['application_id'] ?? 0) : null;
        $application = null;
        $jobId = null;
        $returnTo = (string) ($_POST['document_return'] ?? '');
        $redirectTarget = $scope === 'application' && $applicationId ? '/?page=applications&edit=' . $applicationId . '#documents' : ($returnTo === 'documents' ? '/?page=documents' : '/?page=profile#documents');
        if ($scope === 'application') {
            $application = dbOne($db, 'SELECT a.id, a.job_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL AND j.deleted_at IS NULL', 'ii', [$applicationId, $uid]);
            if (!$application) {
                flash('Bewerbung nicht gefunden oder der Job ist nicht mehr verfügbar.', 'danger');
                redirect('/?page=applications');
            }
            $jobId = (int) $application['job_id'];
        }
        $type = dbOne($db, 'SELECT id, code FROM document_types WHERE id=?', 'i', [$documentTypeId]);
        if (!$type || !in_array((string) $type['code'], allowedDocumentTypeCodes($scope), true) || $title === '') {
            flash('Dokumenttyp und Titel sind erforderlich.', 'danger');
            redirect($redirectTarget);
        }
        try {
            $uploaded = uploadDocumentFile($_FILES['user_document'] ?? [], $uid);
            $version = 1;
            if ($replaceId > 0) {
                $oldDoc = dbOne($db, 'SELECT id, title, document_type_id, version, scope, application_id FROM user_documents WHERE id=? AND user_id=? AND scope=? AND deleted_at IS NULL', 'iis', [$replaceId, $uid, $scope]);
                if ($oldDoc) {
                    if ($scope === 'application' && (int) ($oldDoc['application_id'] ?? 0) !== $applicationId) {
                        flash('Diese Version gehört zu einer anderen Bewerbung.', 'danger');
                        redirect($redirectTarget);
                    }
                    $documentTypeId = (int) $oldDoc['document_type_id'];
                    $title = (string) $oldDoc['title'];
                    $version = (int) $oldDoc['version'] + 1;
                    $stmt = $db->prepare('UPDATE user_documents SET is_current=0 WHERE id=? AND user_id=? AND scope=?');
                    $stmt->bind_param('iis', $oldDoc['id'], $uid, $scope);
                    $stmt->execute();
                }
            } else {
                $existing = $scope === 'application'
                    ? dbOne($db, 'SELECT MAX(version) max_version FROM user_documents WHERE user_id=? AND scope=? AND application_id=? AND document_type_id=? AND title=?', 'isiis', [$uid, $scope, $applicationId, $documentTypeId, $title])
                    : dbOne($db, 'SELECT MAX(version) max_version FROM user_documents WHERE user_id=? AND scope=? AND document_type_id=? AND title=?', 'isis', [$uid, $scope, $documentTypeId, $title]);
                $version = ((int) ($existing['max_version'] ?? 0)) + 1;
                if ($scope === 'application') {
                    $stmt = $db->prepare('UPDATE user_documents SET is_current=0 WHERE user_id=? AND scope=? AND application_id=? AND document_type_id=? AND title=? AND deleted_at IS NULL');
                    $stmt->bind_param('isiis', $uid, $scope, $applicationId, $documentTypeId, $title);
                    $stmt->execute();
                } else {
                    $stmt = $db->prepare('UPDATE user_documents SET is_current=0 WHERE user_id=? AND scope=? AND document_type_id=? AND title=? AND deleted_at IS NULL');
                    $stmt->bind_param('isis', $uid, $scope, $documentTypeId, $title);
                    $stmt->execute();
                }
            }
            $stmt = $db->prepare('INSERT INTO user_documents (user_id, document_type_id, language_code, scope, application_id, job_id, title, description, original_filename, storage_path, mime_type, file_size, sha256, valid_from, valid_until, version, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->bind_param('iissiisssssisssi', $uid, $documentTypeId, $languageCode, $scope, $applicationId, $jobId, $title, $description, $uploaded['original'], $uploaded['path'], $uploaded['mime'], $uploaded['size'], $uploaded['sha256'], $validFrom, $validUntil, $version);
            $stmt->execute();
            $newDocumentId = (int) $stmt->insert_id;
            $extractStatus = in_array($uploaded['mime'], ['text/plain','application/pdf'], true) ? 'pending' : 'skipped';
            $textStmt = $db->prepare('INSERT INTO document_texts (user_document_id, extraction_status, language_code) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE extraction_status=VALUES(extraction_status), language_code=VALUES(language_code)');
            $textStmt->bind_param('iss', $newDocumentId, $extractStatus, $languageCode);
            $textStmt->execute();
            if ($scope === 'application') {
                $purpose = documentPurposeForType((string) $type['code']);
                $linkStmt = $db->prepare('INSERT IGNORE INTO application_documents (application_id, user_document_id, purpose, sort_order) VALUES (?, ?, ?, 0)');
                $linkStmt->bind_param('iis', $applicationId, $newDocumentId, $purpose);
                $linkStmt->execute();
            }
            audit($db, $uid, 'create', 'user_document', $newDocumentId, null, ['title'=>$title,'version'=>$version,'scope'=>$scope,'application_id'=>$applicationId]);
            flash('Dokument gespeichert.');
        } catch (Throwable $exception) {
            flash('Dokument konnte nicht gespeichert werden: ' . $exception->getMessage(), 'danger');
        }
        redirect($redirectTarget);
    }

    if ($action === 'update_document') {
        $id = (int) ($_POST['document_id'] ?? 0);
        $uid = userId();
        $documentTypeId = (int) ($_POST['document_type_id'] ?? 0);
        $languageCode = array_key_exists((string) ($_POST['document_language'] ?? ''), documentLanguageChoices()) ? (string) $_POST['document_language'] : null;
        $title = trim((string) ($_POST['document_title'] ?? ''));
        $description = trim((string) ($_POST['document_description'] ?? '')) ?: null;
        $validFrom = trim((string) ($_POST['valid_from'] ?? '')) ?: null;
        $validUntil = trim((string) ($_POST['valid_until'] ?? '')) ?: null;
        $old = dbOne($db, "SELECT id, document_type_id, language_code, title, description, valid_from, valid_until FROM user_documents WHERE id=? AND user_id=? AND scope='profile' AND deleted_at IS NULL", 'ii', [$id, $uid]);
        $type = dbOne($db, 'SELECT id, code FROM document_types WHERE id=?', 'i', [$documentTypeId]);
        if (!$old || !$type || !in_array((string) $type['code'], allowedDocumentTypeCodes('profile'), true) || $title === '') {
            flash('Dokument konnte nicht aktualisiert werden.', 'danger');
            redirect('/?page=documents');
        }
        $stmt = $db->prepare('UPDATE user_documents SET document_type_id=?, language_code=?, title=?, description=?, valid_from=?, valid_until=? WHERE id=? AND user_id=?');
        $stmt->bind_param('isssssii', $documentTypeId, $languageCode, $title, $description, $validFrom, $validUntil, $id, $uid);
        $stmt->execute();
        audit($db, $uid, 'update', 'user_document', $id, $old, ['document_type_id'=>$documentTypeId,'language_code'=>$languageCode,'title'=>$title,'description'=>$description,'valid_from'=>$validFrom,'valid_until'=>$validUntil]);
        flash('Dokument aktualisiert.');
        redirect('/?page=documents&edit_document=' . $id);
    }

    if ($action === 'delete_document') {
        $id = (int) ($_POST['id'] ?? 0);
        $uid = userId();
        $old = dbOne($db, 'SELECT id,title,version,scope,application_id FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$id, $uid]);
        if ($old) {
            $stmt = $db->prepare('UPDATE user_documents SET deleted_at=NOW(), is_current=0 WHERE id=? AND user_id=?');
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            $stmt = $db->prepare('DELETE FROM application_documents WHERE user_document_id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            audit($db, $uid, 'delete', 'user_document', $id, $old, null);
            flash('Dokument gelöscht.');
        }
        $returnTo = (string) ($_POST['document_return'] ?? '');
        $target = $old && ($old['scope'] ?? '') === 'application' && !empty($old['application_id'])
            ? '/?page=applications&edit=' . (int) $old['application_id'] . '#documents'
            : ($returnTo === 'documents' ? '/?page=documents' : '/?page=profile#documents');
        redirect($target);
    }

    if ($action === 'attach_application_document') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $documentId = (int) ($_POST['user_document_id'] ?? 0);
        $purpose = in_array($_POST['purpose'] ?? '', ['cv','cover_letter','certificate','reference','portfolio','other'], true) ? (string) $_POST['purpose'] : 'other';
        $application = dbOne($db, 'SELECT id FROM applications WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$applicationId, userId()]);
        $document = dbOne($db, "SELECT d.id, dt.code type_code FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.id=? AND d.user_id=? AND d.scope='profile' AND d.is_current=1 AND d.deleted_at IS NULL", 'ii', [$documentId, userId()]);
        if ($application && $document) {
            $purpose = documentPurposeForType((string) $document['type_code']);
            $stmt = $db->prepare('INSERT IGNORE INTO application_documents (application_id, user_document_id, purpose, sort_order) VALUES (?, ?, ?, 0)');
            $stmt->bind_param('iis', $applicationId, $documentId, $purpose);
            $stmt->execute();
            audit($db, userId(), 'create', 'application_document', $documentId, null, ['application_id'=>$applicationId,'purpose'=>$purpose]);
            flash('Dokument der Bewerbung zugeordnet.');
        }
        redirect('/?page=applications&edit=' . $applicationId . '#documents');
    }

    if ($action === 'detach_application_document') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $documentId = (int) ($_POST['user_document_id'] ?? 0);
        $stmt = $db->prepare('DELETE ad FROM application_documents ad JOIN applications a ON a.id=ad.application_id WHERE ad.application_id=? AND ad.user_document_id=? AND a.user_id=?');
        $uid = userId();
        $stmt->bind_param('iii', $applicationId, $documentId, $uid);
        $stmt->execute();
        audit($db, $uid, 'delete', 'application_document', $documentId, ['application_id'=>$applicationId], null);
        flash('Dokument-Zuordnung entfernt.');
        redirect('/?page=applications&edit=' . $applicationId . '#documents');
    }

    if ($action === 'save_company') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) $_POST['name']);
        $city = trim((string) $_POST['city']);
        $website = trim((string) $_POST['website']);
        $phone = trim((string) ($_POST['company_phone'] ?? ''));
        $addressLines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($_POST['address'] ?? '')))));
        $addressLine1 = $addressLines[0] ?? '';
        $addressLine2 = implode("\n", array_slice($addressLines, 1));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
        [$countryCode, $region] = countryForRegion((string) ($_POST['company_region_key'] ?? ''));
        $countryCode = $countryCode ?? '';
        $region = $region ?? '';
        $isIntermediary = !empty($_POST['is_intermediary']) ? 1 : 0;
        if ($name === '') {
            flash('Firmenname ist erforderlich.', 'danger');
            redirect('/?page=companies');
        }
        if ($id > 0) {
            $old = dbOne($db, 'SELECT * FROM companies WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
            if (!$old) {
                http_response_code(404); exit('Not found');
            }
            $stmt = $db->prepare('UPDATE companies SET name = ?, city = ?, website = ?, phone = ?, address_line1 = ?, address_line2 = ?, postal_code = ?, region = ?, country_code = ?, is_intermediary = ? WHERE id = ? AND owner_user_id = ?');
            $uid = userId();
            $stmt->bind_param('sssssssssiii', $name, $city, $website, $phone, $addressLine1, $addressLine2, $postalCode, $region, $countryCode, $isIntermediary, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'company', $id, $old, ['name' => $name, 'city' => $city, 'website' => $website, 'phone' => $phone, 'address_line1' => $addressLine1, 'is_intermediary' => $isIntermediary]);
        } else {
            $stmt = $db->prepare('INSERT INTO companies (owner_user_id, name, city, website, phone, address_line1, address_line2, postal_code, region, country_code, is_intermediary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('isssssssssi', $uid, $name, $city, $website, $phone, $addressLine1, $addressLine2, $postalCode, $region, $countryCode, $isIntermediary);
            $stmt->execute();
            $id = (int) $stmt->insert_id;
            audit($db, userId(), 'create', 'company', $id, null, ['name' => $name, 'city' => $city, 'website' => $website, 'phone' => $phone, 'address_line1' => $addressLine1, 'is_intermediary' => $isIntermediary]);
        }
        flash('Firma gespeichert.');
        redirect('/?page=companies');
    }

    if ($action === 'delete_company') {
        $id = (int) $_POST['id'];
        $old = dbOne($db, 'SELECT * FROM companies WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
        if ($old) {
            $stmt = $db->prepare('UPDATE companies SET deleted_at = NOW() WHERE id = ? AND owner_user_id = ?');
            $uid = userId(); $stmt->bind_param('ii', $id, $uid); $stmt->execute();
            audit($db, userId(), 'delete', 'company', $id, $old, null);
        }
        flash('Firma gelöscht.');
        redirect('/?page=companies');
    }

    if ($action === 'save_job') {
        $id = (int) ($_POST['id'] ?? 0);
        $companyId = (int) $_POST['company_id'];
        $newCompanyName = trim((string) ($_POST['new_company_name'] ?? ''));
        $title = trim((string) $_POST['title']);
        $location = trim((string) $_POST['location_text']);
        $description = trim((string) $_POST['description']);
        $status = (string) $_POST['status'];
        $workplace = (string) $_POST['workplace_type'];
        $engagementType = in_array($_POST['engagement_type'] ?? '', ['permanent','temporary'], true) ? (string) $_POST['engagement_type'] : 'permanent';
        $contractTerm = in_array($_POST['contract_term'] ?? '', ['open_ended','fixed_term','unknown'], true) ? (string) $_POST['contract_term'] : 'unknown';
        $fixedTermStart = trim((string) ($_POST['fixed_term_start'] ?? '')) ?: null;
        $fixedTermEnd = trim((string) ($_POST['fixed_term_end'] ?? '')) ?: null;
        if ($contractTerm !== 'fixed_term') { $fixedTermStart = null; $fixedTermEnd = null; }
        $sourceUrl = trim((string) $_POST['source_url']);
        $salaryMin = trim((string)($_POST['salary_min'] ?? '')) !== '' ? (float)$_POST['salary_min'] : null;
        $salaryMax = null;
        $salaryCurrency = currencyForCountry($currentUser['country_code'] ?? 'CH');
        $salaryPeriod = in_array((string)($_POST['salary_period'] ?? ''), ['hour','month','year'], true) ? (string)$_POST['salary_period'] : 'year';
        if ($companyId < 1 && $newCompanyName !== '') {
            $existingCompany = dbOne($db, 'SELECT id FROM companies WHERE owner_user_id = ? AND name = ? AND deleted_at IS NULL', 'is', [userId(), $newCompanyName]);
            if ($existingCompany) {
                $companyId = (int) $existingCompany['id'];
            } else {
                $companyStmt = $db->prepare('INSERT INTO companies (owner_user_id, name) VALUES (?, ?)');
                $uid = userId();
                $companyStmt->bind_param('is', $uid, $newCompanyName);
                $companyStmt->execute();
                $companyId = (int) $companyStmt->insert_id;
                audit($db, userId(), 'create', 'company', $companyId, null, ['name' => $newCompanyName, 'source' => 'job_import']);
            }
        }
        $company = dbOne($db, 'SELECT id FROM companies WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$companyId, userId()]);
        if (!$company || $title === '') {
            flash('Firma und Jobtitel sind erforderlich.', 'danger'); redirect('/?page=jobs');
        }
        $duplicate = dbOne(
            $db,
            'SELECT id, title FROM jobs WHERE owner_user_id = ? AND company_id = ? AND title = ? AND id <> ? AND deleted_at IS NULL',
            'iisi', [userId(), $companyId, $title, $id]
        );
        if ($duplicate && empty($_POST['confirm_duplicate'])) {
            flash('Mögliche Dublette gefunden: ' . $duplicate['title'] . '. Zum Speichern Dublette bestätigen.', 'warning');
            redirect('/?page=jobs&duplicate=1');
        }
        if ($id > 0) {
            $old = dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, salary_min, salary_max, salary_currency, salary_period, source_url, original_pdf_status, original_pdf_requested_at, original_pdf_rendered_at, original_pdf_error, SUBSTRING(description,1,65535) description FROM jobs WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
            if (!$old) { http_response_code(404); exit('Not found'); }
            $pdfStatus = (string) ($old['original_pdf_status'] ?? 'none');
            $pdfRequestedAt = $old['original_pdf_requested_at'] ?? null;
            $pdfRenderedAt = $old['original_pdf_rendered_at'] ?? null;
            $pdfError = $old['original_pdf_error'] ?? null;
            if ($sourceUrl === '') {
                $pdfStatus = 'none';
                $pdfRequestedAt = null;
                $pdfRenderedAt = null;
                $pdfError = null;
            } elseif ($sourceUrl !== (string) ($old['source_url'] ?? '')) {
                $pdfStatus = 'pending';
                $pdfRequestedAt = date('Y-m-d H:i:s');
                $pdfRenderedAt = null;
                $pdfError = null;
            }
            $stmt = $db->prepare('UPDATE jobs SET company_id=?, title=?, location_text=?, description=?, status=?, workplace_type=?, engagement_type=?, contract_term=?, fixed_term_start=?, fixed_term_end=?, salary_min=?, salary_max=?, salary_currency=?, salary_period=?, source_url=?, original_pdf_status=?, original_pdf_requested_at=?, original_pdf_rendered_at=?, original_pdf_error=? WHERE id=? AND owner_user_id=?');
            $uid = userId();
            $stmt->bind_param('isssssssssddsssssssii', $companyId, $title, $location, $description, $status, $workplace, $engagementType, $contractTerm, $fixedTermStart, $fixedTermEnd, $salaryMin, $salaryMax, $salaryCurrency, $salaryPeriod, $sourceUrl, $pdfStatus, $pdfRequestedAt, $pdfRenderedAt, $pdfError, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'job', $id, $old, ['title' => $title, 'status' => $status, 'salary_min' => $salaryMin, 'salary_max' => $salaryMax, 'original_pdf_status' => $pdfStatus]);
        } else {
            $pdfStatus = $sourceUrl !== '' ? 'pending' : 'none';
            $pdfRequestedAt = $sourceUrl !== '' ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare('INSERT INTO jobs (owner_user_id, company_id, title, location_text, description, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, salary_min, salary_max, salary_currency, salary_period, source_url, original_pdf_status, original_pdf_requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('iisssssssssddsssss', $uid, $companyId, $title, $location, $description, $status, $workplace, $engagementType, $contractTerm, $fixedTermStart, $fixedTermEnd, $salaryMin, $salaryMax, $salaryCurrency, $salaryPeriod, $sourceUrl, $pdfStatus, $pdfRequestedAt);
            $stmt->execute();
            $id = (int) $stmt->insert_id;
            audit($db, userId(), 'create', 'job', $id, null, ['title' => $title, 'status' => $status, 'salary_min' => $salaryMin, 'salary_max' => $salaryMax, 'original_pdf_status' => $pdfStatus]);
        }
        flash('Job gespeichert.');
        unset($_SESSION['import_draft']);
        redirect('/?page=jobs');
    }

    if ($action === 'delete_job') {
        $id = (int) $_POST['id'];
        $old = dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, source_url, SUBSTRING(description,1,65535) description FROM jobs WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
        if ($old) {
            $stmt = $db->prepare('UPDATE jobs SET deleted_at = NOW() WHERE id = ? AND owner_user_id = ?');
            $uid = userId(); $stmt->bind_param('ii', $id, $uid); $stmt->execute();
            $stmt = $db->prepare("UPDATE user_documents SET deleted_at=NOW(), is_current=0 WHERE user_id=? AND scope='application' AND job_id=? AND deleted_at IS NULL");
            $stmt->bind_param('ii', $uid, $id);
            $stmt->execute();
            audit($db, userId(), 'delete', 'job', $id, $old, null);
        }
        flash('Job gelöscht.');
        redirect('/?page=jobs');
    }

    if ($action === 'start_application') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $job = dbOne($db, 'SELECT id, title FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$jobId, userId()]);
        if (!$job) {
            flash('Diese Stelle ist nicht mehr verfügbar.', 'danger');
            redirect('/?page=jobs');
        }
        $existing = dbOne($db, 'SELECT id FROM applications WHERE user_id=? AND job_id=? AND deleted_at IS NULL', 'ii', [userId(), $jobId]);
        if ($existing) {
            redirect('/?page=applications&edit=' . (int) $existing['id'] . '#application-form');
        }
        $uid = userId();
        try {
            $db->begin_transaction();
            $stmt = $db->prepare("INSERT INTO applications (user_id, job_id, status, channel, next_action) VALUES (?, ?, 'draft', 'other', 'Unterlagen vorbereiten')");
            $stmt->bind_param('ii', $uid, $jobId);
            $stmt->execute();
            $applicationId = (int) $stmt->insert_id;
            $history = $db->prepare("INSERT INTO application_status_history (application_id, changed_by, old_status, new_status, comment) VALUES (?, ?, NULL, 'draft', 'Bewerbung angelegt')");
            $history->bind_param('ii', $applicationId, $uid);
            $history->execute();
            audit($db, $uid, 'create', 'application', $applicationId, null, ['job_id' => $jobId, 'status' => 'draft']);
            $db->commit();
            flash('Bewerbung angelegt. Ergänze jetzt Unterlagen und nächsten Schritt.');
            redirect('/?page=applications&edit=' . $applicationId . '#application-form');
        } catch (Throwable $exception) {
            try { $db->rollback(); } catch (Throwable) {}
            flash('Bewerbung konnte nicht gestartet werden. Bitte erneut versuchen.', 'danger');
            redirect('/?page=jobs&edit=' . $jobId . '#new');
        }
    }

    if ($action === 'set_intermediary') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $intermediaryCompanyId = (int) ($_POST['intermediary_company_id'] ?? 0);
        $application = dbOne($db, 'SELECT a.id, a.primary_contact_id, j.company_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$applicationId, userId()]);
        if (!$application) { http_response_code(404); exit('Not found'); }
        if ($intermediaryCompanyId > 0) {
            $intermediary = dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND is_intermediary=1 AND deleted_at IS NULL', 'ii', [$intermediaryCompanyId, userId()]);
            if (!$intermediary || $intermediaryCompanyId === (int)$application['company_id']) { $intermediaryCompanyId = 0; }
        }
        $uid=userId();
        if ($intermediaryCompanyId > 0) {
            $relationship = $db->prepare("INSERT INTO company_relationships (owner_user_id, intermediary_company_id, client_company_id, relationship_type) VALUES (?, ?, ?, 'recruitment_agency') ON DUPLICATE KEY UPDATE deleted_at=NULL, updated_at=NOW()");
            $clientCompanyId=(int)$application['company_id'];
            $relationship->bind_param('iii', $uid, $intermediaryCompanyId, $clientCompanyId);
            $relationship->execute();
        }
        $stmt=$db->prepare('UPDATE applications SET intermediary_company_id=NULLIF(?,0) WHERE id=? AND user_id=?');
        $stmt->bind_param('iii', $intermediaryCompanyId, $applicationId, $uid);
        $stmt->execute();
        audit($db, $uid, 'update', 'application_intermediary', $applicationId, null, ['intermediary_company_id'=>$intermediaryCompanyId]);
        flash($intermediaryCompanyId ? 'Vermittlerfirma zugeordnet.' : 'Vermittlerfirma entfernt.');
        redirect('/?page=applications&edit=' . $applicationId . '#companies');
    }

    if ($action === 'save_contact') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $application = dbOne($db, 'SELECT a.id, a.job_id, a.intermediary_company_id, j.company_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$applicationId, userId()]);
        if (!$application) { http_response_code(404); exit('Not found'); }
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $email = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $linkedin = trim((string) ($_POST['linkedin_url'] ?? ''));
        $language = in_array($_POST['preferred_language'] ?? '', ['de','en','es','pt'], true) ? (string) $_POST['preferred_language'] : null;
        $notes = trim((string) ($_POST['contact_notes'] ?? ''));
        if ($firstName === '' || $lastName === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('Vorname, Nachname und eine gültige E-Mail-Adresse sind erforderlich.', 'danger');
            redirect('/?page=applications&edit=' . $applicationId . '#contacts');
        }
        $uid = userId(); $employerCompanyId = (int) $application['company_id']; $jobId = (int) $application['job_id'];
        $companyId = (int) ($_POST['contact_company_id'] ?? $employerCompanyId);
        $allowedCompanyIds = [$employerCompanyId];
        if (!empty($application['intermediary_company_id'])) { $allowedCompanyIds[] = (int) $application['intermediary_company_id']; }
        if (!in_array($companyId, $allowedCompanyIds, true)) { http_response_code(422); exit('Invalid contact company.'); }
        if ($contactId > 0) {
            $old = dbOne($db, 'SELECT * FROM contacts WHERE id=? AND owner_user_id=? AND company_id=? AND deleted_at IS NULL', 'iii', [$contactId, $uid, $companyId]);
            if (!$old) { http_response_code(404); exit('Not found'); }
            $stmt = $db->prepare('UPDATE contacts SET application_id=?, job_id=?, first_name=?, last_name=?, position=?, department=?, email=?, phone=?, mobile=?, linkedin_url=?, preferred_language=?, notes=? WHERE id=? AND owner_user_id=?');
            $stmt->bind_param('iissssssssssii', $applicationId, $jobId, $firstName, $lastName, $position, $department, $email, $phone, $mobile, $linkedin, $language, $notes, $contactId, $uid);
            $stmt->execute();
            audit($db, $uid, 'update', 'contact', $contactId, $old, ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email,'application_id'=>$applicationId,'job_id'=>$jobId]);
        } else {
            $stmt = $db->prepare('INSERT INTO contacts (owner_user_id, company_id, application_id, job_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iiiissssssssss', $uid, $companyId, $applicationId, $jobId, $firstName, $lastName, $position, $department, $email, $phone, $mobile, $linkedin, $language, $notes);
            $stmt->execute();
            $contactId = (int) $stmt->insert_id;
            audit($db, $uid, 'create', 'contact', $contactId, null, ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email,'application_id'=>$applicationId,'job_id'=>$jobId]);
        }
        if (!empty($_POST['set_primary'])) {
            $stmt = $db->prepare('UPDATE applications SET primary_contact_id=? WHERE id=? AND user_id=?');
            $stmt->bind_param('iii', $contactId, $applicationId, $uid);
            $stmt->execute();
        }
        flash('Kontakt gespeichert.');
        redirect('/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contacts');
    }

    if ($action === 'save_job_contact') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $job = dbOne($db, 'SELECT id, company_id FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$jobId, userId()]);
        if (!$job) { http_response_code(404); exit('Not found'); }
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $email = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $linkedin = trim((string) ($_POST['linkedin_url'] ?? ''));
        $language = in_array($_POST['preferred_language'] ?? '', ['de','en','es','pt'], true) ? (string) $_POST['preferred_language'] : null;
        $notes = trim((string) ($_POST['contact_notes'] ?? ''));
        if ($firstName === '' || $lastName === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('Vorname, Nachname und eine gültige E-Mail-Adresse sind erforderlich.', 'danger');
            redirect('/?page=jobs&edit=' . $jobId . '#job-contacts');
        }
        $uid = userId();
        $companyId = (int) $job['company_id'];
        $stmt = $db->prepare('INSERT INTO contacts (owner_user_id, company_id, job_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiissssssssss', $uid, $companyId, $jobId, $firstName, $lastName, $position, $department, $email, $phone, $mobile, $linkedin, $language, $notes);
        $stmt->execute();
        $contactId = (int) $stmt->insert_id;
        audit($db, $uid, 'create', 'contact', $contactId, null, ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email,'job_id'=>$jobId]);
        flash('Kontakt zur Stelle gespeichert.');
        redirect('/?page=jobs&edit=' . $jobId . '#job-contacts');
    }

    if ($action === 'update_contact_global') {
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $companyId = (int) ($_POST['contact_company_id'] ?? 0);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $email = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $linkedin = trim((string) ($_POST['linkedin_url'] ?? ''));
        $language = in_array($_POST['preferred_language'] ?? '', ['de','en','es','pt'], true) ? (string) $_POST['preferred_language'] : null;
        $notes = trim((string) ($_POST['contact_notes'] ?? ''));
        $uid = userId();
        $old = dbOne($db, 'SELECT id, company_id, first_name, last_name, email FROM contacts WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$contactId, $uid]);
        $company = dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$companyId, $uid]);
        if (!$old || !$company || $firstName === '' || $lastName === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('Kontakt konnte nicht gespeichert werden. Bitte Pflichtfelder prüfen.', 'danger');
            redirect('/?page=contacts&edit_contact=' . $contactId);
        }
        $stmt = $db->prepare('UPDATE contacts SET company_id=?, first_name=?, last_name=?, position=?, department=?, email=?, phone=?, mobile=?, linkedin_url=?, preferred_language=?, notes=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('issssssssssii', $companyId, $firstName, $lastName, $position, $department, $email, $phone, $mobile, $linkedin, $language, $notes, $contactId, $uid);
        $stmt->execute();
        audit($db, $uid, 'update', 'contact', $contactId, $old, ['company_id'=>$companyId,'first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email]);
        flash('Kontakt aktualisiert.');
        redirect('/?page=contacts&edit_contact=' . $contactId);
    }

    if ($action === 'save_contact_log') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $relation = $applicationId > 0
            ? dbOne($db, 'SELECT a.id application_id, a.job_id, c.company_id, c.first_name, c.last_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN contacts c ON c.id=? AND c.owner_user_id=a.user_id AND c.deleted_at IS NULL AND (c.company_id=j.company_id OR c.company_id=a.intermediary_company_id OR c.application_id=a.id OR c.job_id=a.job_id) WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'iii', [$contactId, $applicationId, userId()])
            : dbOne($db, 'SELECT application_id, job_id, company_id, first_name, last_name FROM contacts WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$contactId, userId()]);
        if (!$relation) { http_response_code(404); exit('Not found'); }
        $allowedChannels = array_keys(contactLogChannelOptions());
        $allowedDirections = ['incoming','outgoing','internal'];
        $allowedLogStatuses = array_keys(contactLogStatusOptions());
        $channel = in_array($_POST['log_channel'] ?? '', $allowedChannels, true) ? (string) $_POST['log_channel'] : 'note';
        $direction = in_array($_POST['direction'] ?? '', $allowedDirections, true) ? (string) $_POST['direction'] : 'internal';
        $logStatus = in_array($_POST['log_status'] ?? '', $allowedLogStatuses, true) ? (string) $_POST['log_status'] : 'done';
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['log_body'] ?? ''));
        $occurredAt = trim((string) ($_POST['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $followUpAt = trim((string) ($_POST['follow_up_at'] ?? '')) ?: null;
        $outcome = trim((string) ($_POST['outcome'] ?? ''));
        $occurredAt = str_replace('T', ' ', $occurredAt) . (strlen($occurredAt) === 16 ? ':00' : '');
        if ($followUpAt) { $followUpAt = str_replace('T', ' ', $followUpAt) . (strlen($followUpAt) === 16 ? ':00' : ''); }
        $stmt = $db->prepare('INSERT INTO contact_logs (owner_user_id, contact_id, company_id, application_id, job_id, channel, direction, status, subject, body, occurred_at, follow_up_at, outcome) VALUES (?, ?, ?, NULLIF(?,0), NULLIF(?,0), ?, ?, ?, ?, ?, ?, ?, ?)');
        $uid=userId(); $companyId=(int)$relation['company_id']; $jobId=(int)($relation['job_id'] ?? 0); $logApplicationId=(int)($relation['application_id'] ?? 0);
        $stmt->bind_param('iiiiissssssss', $uid, $contactId, $companyId, $logApplicationId, $jobId, $channel, $direction, $logStatus, $subject, $body, $occurredAt, $followUpAt, $outcome);
        $stmt->execute();
        $logId=(int)$stmt->insert_id;
        $attachmentId = null;
        try {
            $attachmentId = saveContactLogAttachment($db, $uid, $contactId, $logId, trim((string)$relation['first_name'] . ' ' . (string)$relation['last_name']), $subject);
        } catch (Throwable $exception) {
            flash('Kontaktaktivität gespeichert, aber Anhang konnte nicht abgelegt werden: ' . $exception->getMessage(), 'warning');
            redirect($applicationId > 0 ? '/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contact-log' : '/?page=contacts&edit_contact=' . $contactId . '#contact-log');
        }
        audit($db, $uid, 'create', 'contact_log', $logId, null, ['contact_id'=>$contactId,'application_id'=>$logApplicationId,'channel'=>$channel,'direction'=>$direction,'status'=>$logStatus,'subject'=>$subject]);
        flash('Kontaktaktivität gespeichert.');
        redirect($applicationId > 0 ? '/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contact-log' : '/?page=contacts&edit_contact=' . $contactId . '#contact-log');
    }

    if ($action === 'update_contact_log') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        $uid = userId();
        $old = dbOne($db, 'SELECT id, contact_id, application_id, status, subject FROM contact_logs WHERE id=? AND owner_user_id=?', 'ii', [$logId, $uid]);
        if (!$old) { http_response_code(404); exit('Not found'); }
        $allowedChannels = array_keys(contactLogChannelOptions());
        $allowedDirections = ['incoming','outgoing','internal'];
        $allowedLogStatuses = array_keys(contactLogStatusOptions());
        $channel = in_array($_POST['log_channel'] ?? '', $allowedChannels, true) ? (string) $_POST['log_channel'] : 'note';
        $direction = in_array($_POST['direction'] ?? '', $allowedDirections, true) ? (string) $_POST['direction'] : 'internal';
        $logStatus = in_array($_POST['log_status'] ?? '', $allowedLogStatuses, true) ? (string) $_POST['log_status'] : 'done';
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['log_body'] ?? ''));
        $occurredAt = trim((string) ($_POST['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $followUpAt = trim((string) ($_POST['follow_up_at'] ?? '')) ?: null;
        $outcome = trim((string) ($_POST['outcome'] ?? ''));
        $occurredAt = str_replace('T', ' ', $occurredAt) . (strlen($occurredAt) === 16 ? ':00' : '');
        if ($followUpAt) { $followUpAt = str_replace('T', ' ', $followUpAt) . (strlen($followUpAt) === 16 ? ':00' : ''); }
        $stmt = $db->prepare('UPDATE contact_logs SET channel=?, direction=?, status=?, subject=?, body=?, occurred_at=?, follow_up_at=?, outcome=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('ssssssssii', $channel, $direction, $logStatus, $subject, $body, $occurredAt, $followUpAt, $outcome, $logId, $uid);
        $stmt->execute();
        $contact = dbOne($db, 'SELECT first_name, last_name FROM contacts WHERE id=? AND owner_user_id=?', 'ii', [(int)$old['contact_id'], $uid]);
        try {
            saveContactLogAttachment($db, $uid, (int)$old['contact_id'], $logId, trim((string)($contact['first_name'] ?? '') . ' ' . (string)($contact['last_name'] ?? '')), $subject);
        } catch (Throwable $exception) {
            flash('Kontaktaktivität aktualisiert, aber Anhang konnte nicht abgelegt werden: ' . $exception->getMessage(), 'warning');
            redirect((int)($old['application_id'] ?? 0) > 0 ? '/?page=applications&edit=' . (int)$old['application_id'] . '&contact=' . (int)$old['contact_id'] . '#contact-log' : '/?page=contacts&edit_contact=' . (int)$old['contact_id'] . '#contact-log');
        }
        audit($db, $uid, 'update', 'contact_log', $logId, $old, ['status'=>$logStatus,'subject'=>$subject]);
        flash('Kontaktaktivität aktualisiert.');
        redirect((int)($old['application_id'] ?? 0) > 0 ? '/?page=applications&edit=' . (int)$old['application_id'] . '&contact=' . (int)$old['contact_id'] . '#contact-log' : '/?page=contacts&edit_contact=' . (int)$old['contact_id'] . '#contact-log');
    }

    if ($action === 'delete_contact_log') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        $uid = userId();
        $old = dbOne($db, 'SELECT id, contact_id, application_id, subject, status FROM contact_logs WHERE id=? AND owner_user_id=?', 'ii', [$logId, $uid]);
        if ($old) {
            $stmt = $db->prepare('DELETE FROM contact_logs WHERE id=? AND owner_user_id=?');
            $stmt->bind_param('ii', $logId, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'contact_log', $logId, $old, null);
            flash('Kontaktaktivität gelöscht.');
            redirect((int)($old['application_id'] ?? 0) > 0 ? '/?page=applications&edit=' . (int)$old['application_id'] . '&contact=' . (int)$old['contact_id'] . '#contact-log' : '/?page=contacts&edit_contact=' . (int)$old['contact_id'] . '#contact-log');
        }
        flash('Kontaktaktivität nicht gefunden.', 'danger');
        redirect('/?page=contacts');
    }

    if ($action === 'save_application') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = dbOne($db, 'SELECT id, job_id, intermediary_company_id, primary_contact_id, status, next_action, next_action_at FROM applications WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$id, userId()]);
        if (!$old) { http_response_code(404); exit('Not found'); }
        $allowedStatuses = ['draft','ready','sent','confirmed','interview','assessment','offer','accepted','rejected','withdrawn','closed'];
        $allowedChannels = ['email','portal','website','mail','referral','other'];
        $status = in_array($_POST['status'] ?? '', $allowedStatuses, true) ? (string) $_POST['status'] : 'draft';
        $channel = in_array($_POST['channel'] ?? '', $allowedChannels, true) ? (string) $_POST['channel'] : null;
        $appliedAt = trim((string) ($_POST['applied_at'] ?? '')) ?: null;
        $nextActionInput = trim((string) ($_POST['next_action'] ?? ''));
        $nextAction = in_array($nextActionInput, applicationNextActionChoices(), true) ? $nextActionInput : null;
        $nextActionAt = trim((string) ($_POST['next_action_at'] ?? '')) ?: null;
        $emailSubject = trim((string) ($_POST['email_subject'] ?? '')) ?: null;
        $emailBody = trim((string) ($_POST['email_body'] ?? '')) ?: null;
        $applicationUrl = trim((string) ($_POST['application_url'] ?? '')) ?: null;
        if ($applicationUrl !== null && !filter_var($applicationUrl, FILTER_VALIDATE_URL)) {
            flash('Online-Bewerbungs-URL ist ungültig.', 'danger');
            redirect('/?page=applications&edit=' . $id . '#application-form');
        }
        $portalAccount = trim((string) ($_POST['portal_account'] ?? '')) ?: null;
        $referenceNumber = trim((string) ($_POST['reference_number'] ?? '')) ?: null;
        $onlineNotes = trim((string) ($_POST['online_notes'] ?? '')) ?: null;
        $coverLetter = trim((string) ($_POST['cover_letter_text'] ?? '')) ?: null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $primaryContactId = (int) ($_POST['primary_contact_id'] ?? 0);
        $intermediaryCompanyId = (int) ($_POST['intermediary_company_id'] ?? 0);
        if ($intermediaryCompanyId > 0) {
            $jobCompany = dbOne($db, 'SELECT company_id FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$old['job_id'], userId()]);
            $intermediary = dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND is_intermediary=1 AND deleted_at IS NULL', 'ii', [$intermediaryCompanyId, userId()]);
            if (!$jobCompany || !$intermediary || $intermediaryCompanyId === (int)$jobCompany['company_id']) {
                $intermediaryCompanyId = 0;
            } else {
                $relationship = $db->prepare("INSERT INTO company_relationships (owner_user_id, intermediary_company_id, client_company_id, relationship_type) VALUES (?, ?, ?, 'recruitment_agency') ON DUPLICATE KEY UPDATE deleted_at=NULL, updated_at=NOW()");
                $uid = userId(); $clientCompanyId = (int)$jobCompany['company_id'];
                $relationship->bind_param('iii', $uid, $intermediaryCompanyId, $clientCompanyId);
                $relationship->execute();
            }
        }
        if ($primaryContactId > 0) {
            $validContact = dbOne($db, 'SELECT c.id FROM contacts c JOIN jobs j ON j.id=? WHERE c.id=? AND c.owner_user_id=? AND c.deleted_at IS NULL AND (c.company_id=j.company_id OR c.company_id=?)', 'iiii', [(int)$old['job_id'], $primaryContactId, userId(), $intermediaryCompanyId]);
            if (!$validContact) { $primaryContactId = 0; }
        }
        if ($appliedAt) { $appliedAt = str_replace('T', ' ', $appliedAt) . (strlen($appliedAt) === 16 ? ':00' : ''); }
        if ($nextActionAt) { $nextActionAt = str_replace('T', ' ', $nextActionAt) . (strlen($nextActionAt) === 16 ? ':00' : ''); }
        if ($status === 'sent' && !$appliedAt) { $appliedAt = date('Y-m-d H:i:s'); }
        $stmt = $db->prepare('UPDATE applications SET intermediary_company_id=NULLIF(?,0), primary_contact_id=NULLIF(?,0), status=?, channel=?, applied_at=?, next_action=?, next_action_at=?, application_url=?, portal_account=?, reference_number=?, online_notes=?, email_subject=?, email_body=?, cover_letter_text=?, notes=? WHERE id=? AND user_id=?');
        $uid = userId();
        $stmt->bind_param('iisssssssssssssii', $intermediaryCompanyId, $primaryContactId, $status, $channel, $appliedAt, $nextAction, $nextActionAt, $applicationUrl, $portalAccount, $referenceNumber, $onlineNotes, $emailSubject, $emailBody, $coverLetter, $notes, $id, $uid);
        $stmt->execute();
        if ($old['status'] !== $status) {
            $comment = trim((string) ($_POST['status_comment'] ?? '')) ?: null;
            $history = $db->prepare('INSERT INTO application_status_history (application_id, changed_by, old_status, new_status, comment) VALUES (?, ?, ?, ?, ?)');
            $history->bind_param('iisss', $id, $uid, $old['status'], $status, $comment);
            $history->execute();
            $jobStatus = ['sent'=>'applied','confirmed'=>'applied','interview'=>'interview','assessment'=>'interview','offer'=>'offer','accepted'=>'offer','rejected'=>'rejected','withdrawn'=>'closed','closed'=>'closed'][$status] ?? null;
            if ($jobStatus) {
                $jobStmt = $db->prepare('UPDATE jobs SET status=? WHERE id=? AND owner_user_id=?');
                $jobId = (int) $old['job_id'];
                $jobStmt->bind_param('sii', $jobStatus, $jobId, $uid);
                $jobStmt->execute();
            }
        }
        audit($db, $uid, 'update', 'application', $id, ['status' => $old['status']], ['status' => $status, 'channel' => $channel, 'application_url' => $applicationUrl, 'reference_number' => $referenceNumber, 'next_action' => $nextAction, 'next_action_at' => $nextActionAt]);
        flash('Bewerbung gespeichert.');
        redirect('/?page=applications&edit=' . $id . '#application-form');
    }

    if ($action === 'send_application_email') {
        $id = (int) ($_POST['id'] ?? 0);
        $application = dbOne(
            $db,
            'SELECT a.id, a.primary_contact_id, a.email_subject, SUBSTRING(a.email_body,1,65535) email_body, a.status, j.title, c.name company_name, ct.email contact_email, ct.first_name contact_first_name, ct.last_name contact_last_name
               FROM applications a
               JOIN jobs j ON j.id=a.job_id
               JOIN companies c ON c.id=j.company_id
               LEFT JOIN contacts ct ON ct.id=a.primary_contact_id AND ct.owner_user_id=a.user_id AND ct.deleted_at IS NULL
              WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL
              LIMIT 1',
            'ii',
            [$id, userId()]
        );
        $recipient = strtolower(trim((string) ($_POST['recipient_email'] ?? ($application['contact_email'] ?? ''))));
        $subject = trim((string) ($_POST['email_subject'] ?? ($application['email_subject'] ?? '')));
        $body = trim((string) ($_POST['email_body'] ?? ($application['email_body'] ?? '')));
        if (!$application || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
            flash('Empfänger, Betreff und Begleittext sind erforderlich.', 'danger');
            redirect('/?page=applications&edit=' . $id . '#application-form');
        }
        $uid = userId();
        $sent = false;
        try {
            $sent = sendConfiguredMail($db, $config, $uid, $recipient, $subject, $body);
        } catch (Throwable) {
            flash('E-Mail konnte nicht versendet werden. Bitte eigene SMTP-Konfiguration prüfen.', 'danger');
            redirect('/?page=applications&edit=' . $id . '#application-form');
        }
        if ($sent) {
            $now = date('Y-m-d H:i:s');
            $sentStatus = 'sent';
            $nextAction = 'Eingang bestätigen lassen';
            $stmt = $db->prepare('UPDATE applications SET status=?, channel="email", applied_at=COALESCE(applied_at, ?), next_action=?, email_subject=?, email_body=? WHERE id=? AND user_id=?');
            $stmt->bind_param('sssssii', $sentStatus, $now, $nextAction, $subject, $body, $id, $uid);
            $stmt->execute();
            $history = $db->prepare('INSERT INTO application_status_history (application_id, changed_by, old_status, new_status, comment) VALUES (?, ?, ?, ?, ?)');
            $comment = 'Bewerbungs-E-Mail versendet an ' . $recipient;
            $oldStatus = (string) $application['status'];
            $history->bind_param('iisss', $id, $uid, $oldStatus, $sentStatus, $comment);
            $history->execute();
            audit($db, $uid, 'send', 'outbound_email', $id, null, ['recipient' => $recipient, 'application_id' => $id]);
            flash('Bewerbungs-E-Mail wurde versendet.');
        } else {
            flash('Eigener SMTP-Versand ist nicht aktiv. E-Mail wurde als Entwurf protokolliert.', 'warning');
        }
        redirect('/?page=applications&edit=' . $id . '#application-form');
    }

    if ($action === 'delete_application') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = dbOne($db, 'SELECT id, job_id, status, next_action, next_action_at FROM applications WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$id, userId()]);
        if ($old) {
            $stmt = $db->prepare('UPDATE applications SET deleted_at=NOW() WHERE id=? AND user_id=?');
            $uid = userId();
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            $stmt = $db->prepare("UPDATE user_documents SET deleted_at=NOW(), is_current=0 WHERE user_id=? AND scope='application' AND application_id=? AND deleted_at IS NULL");
            $stmt->bind_param('ii', $uid, $id);
            $stmt->execute();
            audit($db, $uid, 'delete', 'application', $id, $old, null);
        }
        flash('Bewerbung gelöscht.');
        redirect('/?page=applications');
    }
}

$currentUser = userId() ? dbOne($db, 'SELECT * FROM users WHERE id = ?', 'i', [userId()]) : null;
$currentUserIsAdmin = $currentUser ? isAdmin($db, realUserId(), $config) : false;
if ($currentUser) {
    touchUserPresence($db, realUserId());
    if (realUserId() === userId()) {
        $currentUser['last_seen_at'] = date('Y-m-d H:i:s');
    }
}
if (isSupportImpersonation()) {
    $targetId = (int) ($_SESSION['support_target_user_id'] ?? 0);
    if ($targetId !== userId() || !activeSupportGrant($db, $targetId) || !$currentUser) {
        endSupportImpersonationSession();
        flash('ADMIN Support wurde beendet, weil die Freigabe nicht mehr aktiv ist.', 'warning');
        redirect('/?page=admin_users');
    }
}
if ($currentUser && in_array($currentUser['timezone'], timezone_identifiers_list(), true)) {
    date_default_timezone_set($currentUser['timezone']);
}
if ($page === 'verify_email') {
    $token = trim((string) ($_GET['token'] ?? ''));
    $reset = null;
    if ($token !== '') {
        $tokenHash = hash('sha256', $token);
        $reset = dbOne(
            $db,
            "SELECT t.id token_id, t.user_id
               FROM auth_tokens t
               JOIN users u ON u.id=t.user_id
              WHERE t.token_type='email_verify'
                AND t.token_hash=?
                AND t.consumed_at IS NULL
                AND t.expires_at > NOW()
                AND u.deleted_at IS NULL
              LIMIT 1",
            's',
            [$tokenHash]
        );
    }
    if ($reset) {
        $stmt = $db->prepare('UPDATE users SET email_verified_at=COALESCE(email_verified_at, NOW()), status="active" WHERE id=?');
        $stmt->bind_param('i', $reset['user_id']);
        $stmt->execute();
        $stmt = $db->prepare('UPDATE auth_tokens SET consumed_at=NOW() WHERE id=?');
        $stmt->bind_param('i', $reset['token_id']);
        $stmt->execute();
        audit($db, (int) $reset['user_id'], 'update', 'user', (int) $reset['user_id'], null, ['email_verified' => true]);
        flash('E-Mail-Adresse bestätigt. Du kannst dich jetzt anmelden.');
    } else {
        flash('Dieser Bestätigungslink ist ungültig oder abgelaufen.', 'danger');
    }
    redirect('/?page=login');
}
if ($page === 'document_download') {
    requireLogin();
    $documentId = (int) ($_GET['id'] ?? 0);
    $document = dbOne($db, 'SELECT original_filename, storage_path, mime_type, file_size FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$documentId, userId()]);
    $path = $document ? realpath(__DIR__ . '/' . $document['storage_path']) : false;
    $root = realpath(storageRoot());
    if (!$document || !$path || !$root || !str_starts_with($path, $root) || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Length: ' . (string) $document['file_size']);
    header('Content-Disposition: attachment; filename="' . addslashes($document['original_filename']) . '"');
    readfile($path);
    exit;
}
if ($page === 'guest_download') {
    $token = (string) ($_GET['token'] ?? '');
    $share = activeGuestShare($db, $token);
    $documentId = (int) ($_GET['id'] ?? 0);
    if (!$share || !in_array((string) $share['download_policy'], ['original','both'], true)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $document = dbOne($db, 'SELECT id, user_id, application_id, job_id, original_filename, storage_path, mime_type, file_size FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$documentId, (int) $share['owner_user_id']]);
    if (!$document) {
        http_response_code(404);
        exit('Not found');
    }
    $allowed = shareAllowsTarget($share, 'document', (int) $document['id'])
        || (($share['target_type'] ?? '') === 'application' && (int) ($document['application_id'] ?? 0) === (int) $share['target_id'])
        || (($share['target_type'] ?? '') === 'job' && (int) ($document['job_id'] ?? 0) === (int) $share['target_id'])
        || (($share['target_type'] ?? '') === 'area');
    if (!$allowed) {
        http_response_code(403);
        exit('Forbidden');
    }
    $path = realpath(__DIR__ . '/' . $document['storage_path']);
    $root = realpath(storageRoot());
    if (!$path || !$root || !str_starts_with($path, $root) || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    audit($db, (int) $share['owner_user_id'], 'read', 'guest_document', (int) $document['id'], null, ['share_id' => (int) $share['id']]);
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Length: ' . (string) $document['file_size']);
    header('Content-Disposition: attachment; filename="' . addslashes($document['original_filename']) . '"');
    readfile($path);
    exit;
}
if ($page === 'export_csv') {
    requireLogin();
    $type = (string) ($_GET['type'] ?? 'jobs');
    if ($type === 'applications') {
        $rows = dbAll($db, 'SELECT a.id, j.title, c.name company, a.status, a.channel, a.applied_at, a.next_action, a.next_action_at FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC', 'i', [userId()]);
        csvResponse('bewerbungen.csv', ['Job','Firma','Status','Kanal','Gesendet','Nächster Schritt','Fällig'], array_map(static fn(array $r): array => [$r['title'], $r['company'], $r['status'], $r['channel'], $r['applied_at'], $r['next_action'], $r['next_action_at']], $rows));
    }
    if ($type === 'audit') {
        $rows = dbAll($db, 'SELECT action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 1000', 'i', [userId()]);
        csvResponse('audit.csv', ['Aktion','Typ','Zeit'], array_map(static fn(array $r): array => [$r['action'], $r['entity_type'], $r['created_at']], $rows));
    }
    $rows = dbAll($db, 'SELECT j.id, j.title, c.name company, j.location_text, j.status, j.workplace_type, j.updated_at FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.updated_at DESC', 'i', [userId()]);
    csvResponse('jobs.csv', ['Titel','Firma','Ort','Status','Arbeitsmodell','Aktualisiert'], array_map(static fn(array $r): array => [$r['title'], $r['company'], $r['location_text'], $r['status'], $r['workplace_type'], $r['updated_at']], $rows));
}
if ($page === 'export_pdf') {
    requireLogin();
    $type = (string) ($_GET['type'] ?? 'jobs');
    if ($type === 'report') {
        $reportId = (int) ($_GET['report_id'] ?? 0);
        $report = dbOne($db, 'SELECT id, name, base_entity FROM saved_reports WHERE id=? AND owner_user_id=?', 'ii', [$reportId, userId()]);
        if (!$report) { http_response_code(404); exit('Not found'); }
        $settings = loadReportSettings($db, $reportId, (string)$report['base_entity']);
        [$headers, $rows] = reportDataset($db, userId(), $report, $settings, $currentUser);
        pdfResponse('report-' . $reportId . '.pdf', (string)$report['name'], $headers, $rows);
    }
    if ($type === 'applications') {
        $rows = dbAll($db, 'SELECT a.id, j.title, c.name company, a.status, a.channel, a.applied_at, a.next_action FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC', 'i', [userId()]);
        pdfResponse('bewerbungen.pdf', 'Bewerbungen', ['Job','Firma','Status','Kanal','Gesendet','Nächster Schritt'], array_map(static fn(array $r): array => [$r['title'], $r['company'], $r['status'], $r['channel'], $r['applied_at'], $r['next_action']], $rows));
    }
    if ($type === 'companies') {
        $rows = dbAll($db, 'SELECT id, name, city, phone, website, updated_at FROM companies WHERE owner_user_id=? AND deleted_at IS NULL ORDER BY name', 'i', [userId()]);
        pdfResponse('firmen.pdf', 'Firmen', ['Name','Ort','Telefon','Website','Aktualisiert'], array_map(static fn(array $r): array => [$r['name'], $r['city'], $r['phone'], $r['website'], $r['updated_at']], $rows));
    }
    if ($type === 'contacts') {
        $rows = dbAll($db, 'SELECT ct.id, ct.first_name, ct.last_name, c.name company_name, ct.email, ct.phone FROM contacts ct JOIN companies c ON c.id=ct.company_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL ORDER BY c.name, ct.last_name', 'i', [userId()]);
        pdfResponse('kontakte.pdf', 'Kontakte', ['Vorname','Nachname','Firma','E-Mail','Telefon'], array_map(static fn(array $r): array => [$r['first_name'], $r['last_name'], $r['company_name'], $r['email'], $r['phone']], $rows));
    }
    if ($type === 'documents') {
        $rows = dbAll($db, 'SELECT d.id, d.title, dt.code type_code, d.version, d.original_filename, d.file_size, d.created_at FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.deleted_at IS NULL ORDER BY d.created_at DESC', 'i', [userId()]);
        pdfResponse('dokumente.pdf', 'Dokumente', ['Titel','Typ','Version','Datei','Größe','Datum'], array_map(static fn(array $r): array => [$r['title'], $r['type_code'], 'v'.$r['version'], $r['original_filename'], bytesLabel((int)$r['file_size']), $r['created_at']], $rows));
    }
    $rows = dbAll($db, 'SELECT j.id, j.title, c.name company, j.location_text, j.status, j.workplace_type, j.updated_at FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.updated_at DESC', 'i', [userId()]);
    pdfResponse('jobs.pdf', 'Jobs', ['Titel','Firma','Ort','Status','Arbeitsmodell','Aktualisiert'], array_map(static fn(array $r): array => [$r['title'], $r['company'], $r['location_text'], $r['status'], $r['workplace_type'], $r['updated_at']], $rows));
}
if ($page === 'export_ics') {
    requireLogin();
    $view = array_key_exists((string) ($_GET['view'] ?? 'agenda'), calendarViewOptions()) ? (string) $_GET['view'] : 'agenda';
    $anchor = calendarAnchorDate($currentUser ?? []);
    [$rangeStart, $rangeEnd] = calendarRange($view, $anchor);
    calendarIcsResponse('jema-kalender-' . $view . '.ics', calendarEventRows($db, userId(), $rangeStart, $rangeEnd));
}
if ($currentUser && in_array($page, ['login', 'register', 'forgot_password', 'reset_password', 'two_factor'], true)) {
    redirect('/?page=dashboard');
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$companies = userId() ? dbAll($db, 'SELECT * FROM companies WHERE owner_user_id = ? AND deleted_at IS NULL ORDER BY name', 'i', [userId()]) : [];
$supportGrant = $currentUser ? activeSupportGrant($db, userId()) : null;
$supportImpersonating = isSupportImpersonation();
$bodyClasses = array_filter([
    $supportGrant ? 'support-granted' : '',
    $supportImpersonating ? 'support-impersonating' : '',
]);
$appVersion = (string) ($config['app_version'] ?? '0.14.7');

?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['app_name']) ?></title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/app.css?v=<?= e($appVersion) ?>">
</head>
<body class="<?= e(implode(' ', $bodyClasses)) ?>">
<header class="topbar <?= $supportGrant ? 'topbar-support-granted' : '' ?> <?= $supportImpersonating ? 'topbar-support-admin' : '' ?>">
    <a class="brand" href="/"><img src="/assets/favicon.svg" alt="" width="32" height="32"> <span>JeMa <strong>Jobs</strong></span></a>
    <?php if ($currentUser): ?>
        <button class="menu-button" type="button" onclick="document.body.classList.toggle('nav-open')">Menü</button>
        <nav class="menubar" aria-label="Hauptmenü">
            <div class="menu-group"><button type="button" class="menu-trigger">Datei</button><div class="menu-panel"><a href="/?page=dashboard">Übersicht</a><div class="submenu"><button type="button">Stammdaten</button><div class="submenu-panel"><a href="/?page=profile">Profil</a><a href="/?page=documents">Dokumente</a><a href="/?page=translations">Übersetzungen</a></div></div><a href="/?page=privacy">Datenschutz</a><a href="/?page=audit">Log</a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger">CRM</button><div class="menu-panel"><a href="/?page=companies">Firmen</a><a href="/?page=contacts">Kontakte</a><a href="/?page=sharing">Freigaben</a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger">Bewerbung</button><div class="menu-panel"><a href="/?page=jobs">Jobs</a><a href="/?page=applications">Bewerbungen</a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger">Planung</button><div class="menu-panel"><a href="/?page=pendents">Pendent</a><a href="/?page=calendar">Kalender</a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger">Auswertung</button><div class="menu-panel"><a href="/?page=reports">Reports</a><a href="/?page=export_pdf&type=jobs">Jobs PDF</a><a href="/?page=export_pdf&type=applications">Bewerbungen PDF</a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger">Konto</button><div class="menu-panel"><a href="/?page=profile">Profil</a><?php if ($currentUserIsAdmin): ?><a href="/?page=admin_users">Benutzerverwaltung</a><?php endif; ?></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger">Hilfe</button><div class="menu-panel menu-panel-right"><a href="/?page=help">Hilfe</a><a href="/?page=about">Über</a></div></div>
        </nav>
        <?php if($supportImpersonating): ?>
            <div class="support-context"><strong>ADMIN Support</strong><span><?= e((string)($_SESSION['support_admin_name'] ?? 'Admin')) ?> in Umgebung <?= e((string)($_SESSION['support_target_name'] ?? userLabel($currentUser))) ?></span></div>
        <?php elseif($supportGrant): ?>
            <div class="support-context"><strong>ADMIN Support freigegeben</strong><span>Administratoren können sich einklinken.</span></div>
        <?php endif; ?>
        <div class="topbar-actions">
            <?php if($supportImpersonating): ?><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="stop_admin_support">Support beenden</button></form><?php endif; ?>
            <form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="logout">Abmelden</button></form>
        </div>
    <?php endif; ?>
</header>
<main class="container">
<?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<?php if ($page === 'login' && !$currentUser): ?>
    <section class="auth-card">
        <p class="eyebrow">Willkommen zurück</p><h1>Anmelden</h1>
        <?php if(!empty($_SESSION['email_verify_notice'])): ?>
            <div class="alert warning"><?= e($_SESSION['email_verify_notice']) ?></div>
            <?php unset($_SESSION['email_verify_notice'], $_SESSION['email_verify_link']); ?>
        <?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label>E-Mail<input type="email" name="email" required></label>
            <label>Passwort<input type="password" name="password" required></label>
            <button class="primary" name="action" value="login">Anmelden</button>
        </form>
        <p>Noch kein Konto? <a href="/?page=register">Registrieren</a></p>
        <p><a href="/?page=forgot_password">Passwort vergessen?</a></p>
    </section>
<?php elseif ($page === 'two_factor' && !$currentUser): ?>
    <?php if (empty($_SESSION['pending_2fa_user_id'])) { redirect('/?page=login'); } ?>
    <section class="auth-card">
        <p class="eyebrow">Sicherheit</p><h1>2FA-Code</h1>
        <p class="meta-line">Gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label>Authenticator-Code<input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
            <button class="primary" name="action" value="verify_two_factor">Bestätigen</button>
        </form>
        <p><a href="/?page=login">Zur Anmeldung</a></p>
    </section>
<?php elseif ($page === 'forgot_password' && !$currentUser): ?>
    <section class="auth-card">
        <p class="eyebrow">Konto</p><h1>Passwort vergessen</h1>
        <?php if(!empty($_SESSION['password_reset_link'])): ?>
            <div class="alert warning"><strong>Testphase:</strong> E-Mail-Versand ist deaktiviert. Nutze diesen Link einmalig: <a href="<?= e($_SESSION['password_reset_link']) ?>">Passwort zurücksetzen</a><input value="<?= e($_SESSION['password_reset_link']) ?>" readonly onclick="this.select()"></div>
        <?php elseif(!empty($_SESSION['password_reset_notice'])): ?>
            <div class="alert warning"><?= e($_SESSION['password_reset_notice']) ?></div>
        <?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label>E-Mail<input type="email" name="email" required></label>
            <button class="primary" name="action" value="request_password_reset">Link erstellen</button>
        </form>
        <p><a href="/?page=login">Zur Anmeldung</a></p>
    </section>
<?php elseif ($page === 'reset_password' && !$currentUser): ?>
    <?php $resetToken = trim((string) ($_GET['token'] ?? '')); ?>
    <section class="auth-card">
        <p class="eyebrow">Konto</p><h1>Neues Passwort</h1>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="token" value="<?= e($resetToken) ?>">
            <label>Neues Passwort<input type="password" name="password" minlength="10" required></label>
            <label>Passwort wiederholen<input type="password" name="password_confirm" minlength="10" required></label>
            <button class="primary" name="action" value="reset_password">Passwort ändern</button>
        </form>
        <p><a href="/?page=login">Zur Anmeldung</a></p>
    </section>
<?php elseif ($page === 'register' && !$currentUser): ?>
    <section class="auth-card">
        <p class="eyebrow">Privates Job-CRM</p><h1>Konto erstellen</h1>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <div class="two"><label>Vorname<input name="first_name" required></label><label>Nachname<input name="last_name" required></label></div>
            <label>E-Mail<input type="email" name="email" required></label>
            <label>Passwort<input type="password" name="password" minlength="10" required></label>
            <button class="primary" name="action" value="register">Registrieren</button>
        </form>
        <p><a href="/?page=login">Zur Anmeldung</a></p>
    </section>
<?php elseif ($page === 'guest'): ?>
    <?php
    $guestToken = (string) ($_GET['token'] ?? '');
    $share = activeGuestShare($db, $guestToken);
    if (!$share) {
        http_response_code(404);
        echo '<section class="auth-card"><h1>Freigabe nicht verfügbar</h1><p>Dieser Link ist ungültig, abgelaufen oder wurde widerrufen.</p></section>';
    } else {
        $guestSessionId = null;
        $device = deviceHash();
        $session = dbOne($db, 'SELECT id FROM guest_sessions WHERE share_id=? AND device_hash=? AND revoked_at IS NULL', 'is', [(int)$share['id'], $device]);
        if ($session) {
            $guestSessionId = (int) $session['id'];
            $stmt = $db->prepare('UPDATE guest_sessions SET last_seen_at=NOW() WHERE id=?');
            $stmt->bind_param('i', $guestSessionId);
            $stmt->execute();
        } else {
            $stmt = $db->prepare('INSERT INTO guest_sessions (share_id, recipient_email, device_hash, verified_at, last_seen_at) VALUES (?, ?, ?, NOW(), NOW())');
            $stmt->bind_param('iss', $share['id'], $share['recipient_email'], $device);
            $stmt->execute();
            $guestSessionId = (int) $stmt->insert_id;
        }
        $ownerId = (int) $share['owner_user_id'];
        $targetType = (string) $share['target_type'];
        $targetId = (int) ($share['target_id'] ?? 0);
        $guestJobs = [];
        $guestApplications = [];
        $guestDocuments = [];
        if ($targetType === 'area') {
            $guestJobs = dbAll($db, 'SELECT j.id, j.title, j.location_text, j.status, c.name company_name FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.updated_at DESC LIMIT 50', 'i', [$ownerId]);
            $guestApplications = dbAll($db, 'SELECT a.id, a.status, a.next_action, a.next_action_at, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC LIMIT 50', 'i', [$ownerId]);
        } elseif ($targetType === 'job') {
            $guestJobs = dbAll($db, 'SELECT j.id, j.title, j.location_text, j.status, c.name company_name, SUBSTRING(j.description,1,65535) description FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.id=? AND j.owner_user_id=? AND j.deleted_at IS NULL', 'ii', [$targetId, $ownerId]);
            $guestDocuments = dbAll($db, 'SELECT id, title, original_filename, file_size FROM user_documents WHERE user_id=? AND job_id=? AND deleted_at IS NULL ORDER BY created_at DESC', 'ii', [$ownerId, $targetId]);
        } elseif ($targetType === 'application') {
            $guestApplications = dbAll($db, 'SELECT a.id, a.status, a.next_action, a.next_action_at, SUBSTRING(a.cover_letter_text,1,65535) cover_letter_text, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$targetId, $ownerId]);
            $guestDocuments = dbAll($db, "SELECT d.id, d.title, d.original_filename, d.file_size FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id WHERE ad.application_id=? AND d.user_id=? AND d.deleted_at IS NULL ORDER BY d.created_at DESC", 'ii', [$targetId, $ownerId]);
        } elseif ($targetType === 'document') {
            $guestDocuments = dbAll($db, 'SELECT id, title, original_filename, file_size FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$targetId, $ownerId]);
        }
        $guestTranslations = dbAll($db, 'SELECT entity_type, entity_id, target_language, title, SUBSTRING(body,1,65535) body, version FROM record_translations WHERE owner_user_id=? AND is_current=1 ORDER BY updated_at DESC LIMIT 20', 'i', [$ownerId]);
        ?>
        <div class="page-head"><div><p class="eyebrow">Freigabe</p><h1><?= e($share['title']) ?></h1></div><span><?= e($share['permission']) ?> · Download <?= e($share['download_policy']) ?></span></div>
        <?php if(!empty($share['watermark_enabled'])): ?><p class="filter-note">Persönliche Freigabe für <?= e($share['recipient_email']) ?>. Downloads können nachvollzogen werden.</p><?php endif; ?>
        <?php if($guestJobs): ?><section class="panel table-wrap"><h2>Jobs</h2><table><thead><tr><th>Titel</th><th>Firma</th><th>Ort</th><th>Status</th></tr></thead><tbody><?php foreach($guestJobs as $job): ?><tr><td><strong><?= e($job['title']) ?></strong><?php if(!empty($job['description'])): ?><small><?= e(mb_strimwidth((string)$job['description'],0,220,'...')) ?></small><?php endif; ?></td><td><?= e($job['company_name']) ?></td><td><?= e($job['location_text']) ?></td><td><?= e($job['status']) ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
        <?php if($guestApplications): ?><section class="panel table-wrap"><h2>Bewerbungen</h2><table><thead><tr><th>Job</th><th>Firma</th><th>Status</th><th>Nächster Schritt</th></tr></thead><tbody><?php foreach($guestApplications as $app): ?><tr><td><strong><?= e($app['title']) ?></strong><?php if(!empty($app['cover_letter_text'])): ?><small><?= nl2br(e(mb_strimwidth((string)$app['cover_letter_text'],0,300,'...'))) ?></small><?php endif; ?></td><td><?= e($app['company_name']) ?></td><td><?= e($app['status']) ?></td><td><?= e($app['next_action']) ?><?php if($app['next_action_at']): ?><small><?= e(displayDateTime($app['next_action_at'])) ?></small><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
        <?php if($guestDocuments): ?><section class="panel table-wrap"><h2>Dokumente</h2><table><thead><tr><th>Dokument</th><th>Datei</th><th>Größe</th><th>Download</th></tr></thead><tbody><?php foreach($guestDocuments as $doc): ?><tr><td><?= e($doc['title']) ?></td><td><?= e($doc['original_filename']) ?></td><td><?= e(bytesLabel((int)$doc['file_size'])) ?></td><td><?php if(in_array((string)$share['download_policy'], ['original','both'], true)): ?><a href="/?page=guest_download&token=<?= e(urlencode($guestToken)) ?>&id=<?= (int)$doc['id'] ?>">Download</a><?php else: ?>gesperrt<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
        <?php if($guestTranslations): ?><section class="panel"><h2>Übersetzungen</h2><div class="log-timeline"><?php foreach($guestTranslations as $translation): ?><article><div><strong><?= e($translation['title'] ?: $translation['entity_type'].' #'.$translation['entity_id']) ?></strong><span><?= e($translation['target_language']) ?> · v<?= (int)$translation['version'] ?></span></div><p><?= nl2br(e($translation['body'])) ?></p></article><?php endforeach; ?></div></section><?php endif; ?>
    <?php } ?>
<?php else: requireLogin(); ?>
    <?php if ($page === 'dashboard'): 
        $stats = [
            ['label' => 'Jobs', 'value' => dbOne($db, 'SELECT COUNT(*) c FROM jobs WHERE owner_user_id=? AND deleted_at IS NULL', 'i', [userId()])['c'], 'href' => '/?page=jobs'],
            ['label' => 'Firmen', 'value' => count($companies), 'href' => '/?page=companies'],
            ['label' => 'Bewerbungen', 'value' => dbOne($db, 'SELECT COUNT(*) c FROM applications WHERE user_id=? AND deleted_at IS NULL', 'i', [userId()])['c'], 'href' => '/?page=applications'],
            ['label' => 'Pendent', 'value' => dbOne($db, 'SELECT COUNT(*) c FROM applications WHERE user_id=? AND next_action_at IS NOT NULL AND deleted_at IS NULL', 'i', [userId()])['c'], 'href' => '/?page=pendents'],
        ]; ?>
        <div class="hero"><div><p class="eyebrow">Guten Tag, <?= e($currentUser['first_name']) ?></p><h1>Deine Jobs. Dein Prozess.</h1><p>Privat, strukturiert und auf allen Geräten nutzbar.</p></div><a class="button primary" href="/?page=jobs#new">Job erfassen</a></div>
        <div class="stats"><?php foreach ($stats as $stat): ?><a class="stat-link" href="<?= e($stat['href']) ?>"><article><strong><?= e((string) $stat['value']) ?></strong><span><?= e($stat['label']) ?></span></article></a><?php endforeach; ?></div>
        <section class="panel"><h2>Nächste Schritte</h2><p>Erfasse zuerst eine Firma und danach passende Stellen. Bei „Job erfassen“ ist auch ein Schnellimport von einer oder mehreren Stellen gleichzeitig möglich; die Firma wird bei Bedarf automatisch erzeugt. Die App berechnet einen transparenten Basis-Match und erkennt mögliche Dubletten.</p></section>
    <?php elseif ($page === 'pendents'): ?>
        <?php
        $pendentSfFields = [
            'due_at'=>['label'=>'Fällig'],
            'type'=>['label'=>'Bereich'],
            'title'=>['label'=>'Titel'],
            'status'=>['label'=>'Status'],
            'ref'=>['label'=>'Bezug'],
        ];
        $pendentSf = sfState('pendents', $pendentSfFields, ['sort'=>'due_at','dir'=>'asc']);
        $pendentPreserve = ['page'=>'pendents'];
        $now = new DateTimeImmutable('now', new DateTimeZone((string)($currentUser['timezone'] ?? 'Europe/Zurich')));
        $todayStart = $now->setTime(0, 0)->format('Y-m-d H:i:s');
        $pendents = [];
        foreach (dbAll($db, 'SELECT a.id, a.status, a.next_action title, a.next_action_at due_at, j.title job_title, c.name company FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL AND a.next_action_at IS NOT NULL', 'i', [userId()]) as $row) {
            $pendents[] = ['type'=>'Bewerbung','status'=>(string)$row['status'],'title'=>(string)($row['title'] ?: 'Pendent'),'due_at'=>(string)$row['due_at'],'ref'=>$row['job_title'].' · '.$row['company'],'href'=>'/?page=applications&edit='.(int)$row['id'].'#application-form'];
        }
        foreach (dbAll($db, 'SELECT l.id, l.contact_id, l.status, l.subject title, l.follow_up_at due_at, c.first_name, c.last_name, co.name company FROM contact_logs l JOIN contacts c ON c.id=l.contact_id JOIN companies co ON co.id=l.company_id WHERE l.owner_user_id=? AND l.follow_up_at IS NOT NULL AND l.status IN ("open","planned")', 'i', [userId()]) as $row) {
            $pendents[] = ['type'=>'Kontakt','status'=>(string)$row['status'],'title'=>(string)($row['title'] ?: 'Kontakt nachfassen'),'due_at'=>(string)$row['due_at'],'ref'=>trim($row['first_name'].' '.$row['last_name'].' · '.$row['company']),'href'=>'/?page=contacts&edit_contact='.(int)$row['contact_id'].'#contact-log'];
        }
        foreach (dbAll($db, 'SELECT ce.id, ce.title, ce.event_type, ce.status, ce.starts_at due_at, ce.application_id, j.title job_title, c.name company FROM calendar_events ce LEFT JOIN applications a ON a.id=ce.application_id LEFT JOIN jobs j ON j.id=a.job_id LEFT JOIN companies c ON c.id=j.company_id WHERE ce.owner_user_id=? AND ce.status="planned"', 'i', [userId()]) as $row) {
            $pendents[] = ['type'=>'Kalender','status'=>(string)$row['event_type'],'title'=>(string)$row['title'],'due_at'=>(string)$row['due_at'],'ref'=>trim((string)($row['job_title'] ?? '').' · '.(string)($row['company'] ?? ''), ' ·'),'href'=>!empty($row['application_id'])?'/?page=applications&edit='.(int)$row['application_id'].'#application-form':'/?page=calendar'];
        }
        $pendents = sfApplyRows($pendents, $pendentSf, $pendentSfFields);
        ?>
        <div class="page-head"><div><p class="eyebrow">Pendent</p><h1>Pendent-Zentrale</h1></div><span><?= count($pendents) ?> Einträge</span></div>
        <div class="actions export-actions"><?= sfToolbar('pendents', $pendentSf, $pendentPreserve, $pendentSfFields) ?></div>
        <section class="panel table-wrap"><table><thead><tr><?= sfHeader('pendents','due_at','Fällig',$pendentSf,$pendentPreserve) ?><?= sfHeader('pendents','type','Bereich',$pendentSf,$pendentPreserve) ?><?= sfHeader('pendents','title','Titel',$pendentSf,$pendentPreserve) ?><?= sfHeader('pendents','status','Status',$pendentSf,$pendentPreserve) ?><?= sfHeader('pendents','ref','Bezug',$pendentSf,$pendentPreserve) ?><th>Aktion</th></tr></thead><tbody><?php foreach($pendents as $item): $isOverdue=(string)$item['due_at'] < $todayStart; ?><tr class="<?= $isOverdue ? 'is-overdue' : '' ?>"><td><?= e(displayDateTime($item['due_at'], $currentUser)) ?></td><td><?= e($item['type']) ?></td><td><strong><?= e($item['title']) ?></strong></td><td><?= e($item['status']) ?></td><td><?= e($item['ref']) ?></td><td><a href="<?= e($item['href']) ?>">Öffnen</a></td></tr><?php endforeach; ?><?php if(!$pendents): ?><tr><td colspan="6" class="empty">Keine Pendent-Einträge für diese Auswahl.</td></tr><?php endif; ?></tbody></table></section>
    <?php elseif ($page === 'sharing'): ?>
        <?php
        $shares = dbAll($db, 'SELECT * FROM guest_shares WHERE owner_user_id=? ORDER BY created_at DESC', 'i', [userId()]);
        $shareTargets = [
            'area' => 'Ganzer Bereich',
            'job' => 'Ein Job',
            'application' => 'Eine Bewerbung',
            'document' => 'Ein Dokument',
        ];
        foreach ($shares as &$shareRow) {
            $shareRow['target_label'] = (string) ($shareTargets[$shareRow['target_type']] ?? $shareRow['target_type']);
            $shareRow['status_label'] = $shareRow['revoked_at'] ? 'widerrufen' : (($shareRow['expires_at'] && strtotime((string)$shareRow['expires_at']) < time()) ? 'abgelaufen' : 'aktiv');
        }
        unset($shareRow);
        $shareSfFields = [
            'title'=>['label'=>'Titel'],
            'target_label'=>['label'=>'Ziel'],
            'recipient_email'=>['label'=>'Empfänger'],
            'status_label'=>['label'=>'Status'],
        ];
        $shareSf = sfState('sharing', $shareSfFields, ['sort'=>'title','dir'=>'asc']);
        $sharePreserve = ['page'=>'sharing'];
        $shares = sfApplyRows($shares, $shareSf, $shareSfFields);
        ?>
        <div class="page-head"><div><p class="eyebrow">Zusammenarbeit</p><h1>Freigaben</h1></div><span><?= count($shares) ?> Links</span></div>
        <?php if(!empty($_SESSION['last_share_link'])): ?><div class="alert warning"><strong>Freigabelink:</strong> <a href="<?= e($_SESSION['last_share_link']) ?>"><?= e($_SESSION['last_share_link']) ?></a><input value="<?= e($_SESSION['last_share_link']) ?>" readonly onclick="this.select()"></div><?php unset($_SESSION['last_share_link']); endif; ?>
        <div class="split"><section class="panel"><h2>Neue Freigabe</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label>Titel<input name="title" placeholder="z. B. Bewerbung Review"></label>
            <label>Empfänger-E-Mail<input type="email" name="recipient_email" required></label>
            <div class="two"><label>Ziel<select name="target_type"><?php foreach($shareTargets as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>Datensatz<input type="number" min="1" name="target_id" placeholder="leer bei ganzem Bereich"></label></div>
            <div class="two"><label>Recht<select name="permission"><option value="view">Nur ansehen</option><option value="comment">Kommentieren</option><option value="edit">Bearbeiten vorbereitet</option></select></label><label>Download<select name="download_policy"><option value="none">Kein Download</option><option value="original">Original</option><option value="pdf">PDF vorbereitet</option><option value="both">Original und PDF</option></select></label></div>
            <label>Ablauf<input type="datetime-local" name="expires_at"></label>
            <label class="check"><input type="checkbox" name="watermark_enabled" value="1" checked> Wasserzeichen / persönliche Nachverfolgung</label>
            <button class="primary" name="action" value="create_share">Freigabe erstellen</button>
        </form></section>
        <section class="panel table-wrap"><h2>Aktive und frühere Links</h2><div class="actions export-actions"><?= sfToolbar('sharing', $shareSf, $sharePreserve, $shareSfFields) ?></div><table><thead><tr><?= sfHeader('sharing','title','Titel',$shareSf,$sharePreserve) ?><?= sfHeader('sharing','target_label','Ziel',$shareSf,$sharePreserve) ?><?= sfHeader('sharing','recipient_email','Empfänger',$shareSf,$sharePreserve) ?><?= sfHeader('sharing','status_label','Status',$shareSf,$sharePreserve) ?><th>Aktionen</th></tr></thead><tbody><?php foreach($shares as $share): ?><tr><td><strong><?= e($share['title']) ?></strong><small><?= e($share['permission']) ?> · Download <?= e($share['download_policy']) ?></small></td><td><?= e($share['target_label']) ?> #<?= e((string)$share['target_id']) ?></td><td><?= e($share['recipient_email']) ?></td><td><?= e($share['status_label']) ?><small><?= e($share['last_accessed_at'] ? 'letzter Zugriff '.displayDateTime($share['last_accessed_at'], $currentUser) : 'noch kein Zugriff') ?></small></td><td><?php if(!$share['revoked_at']): ?><form method="post" onsubmit="return confirm('Freigabe widerrufen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="share_id" value="<?= (int)$share['id'] ?>"><button name="action" value="revoke_share">Widerrufen</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$shares): ?><tr><td colspan="5" class="empty">Noch keine Freigaben.</td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'reports'): ?>
        <?php
        $reports = dbAll($db, 'SELECT id, name, description, base_entity, display_type, updated_at FROM saved_reports WHERE owner_user_id=? ORDER BY updated_at DESC', 'i', [userId()]);
        $editReportId = (int) ($_GET['edit_report'] ?? 0);
        $editReport = $editReportId > 0 ? dbOne($db, 'SELECT id, name, description, base_entity, display_type FROM saved_reports WHERE id=? AND owner_user_id=?', 'ii', [$editReportId, userId()]) : null;
        $reportEditMissing = $editReportId > 0 && !$editReport;
        $reportBaseOptions = reportBaseOptions();
        $reportDisplayOptions = reportDisplayOptions();
        foreach ($reports as &$reportRow) {
            $reportRow['base_label'] = (string) ($reportBaseOptions[$reportRow['base_entity']] ?? $reportRow['base_entity']);
            $reportRow['display_label'] = (string) ($reportDisplayOptions[$reportRow['display_type']] ?? $reportRow['display_type']);
        }
        unset($reportRow);
        $reportListSfFields = [
            'name'=>['label'=>'Name'],
            'base_label'=>['label'=>'Basis'],
            'display_label'=>['label'=>'Ansicht'],
            'updated_at'=>['label'=>'Aktualisiert'],
        ];
        $reportListSf = sfState('reports', $reportListSfFields, ['sort'=>'updated_at','dir'=>'desc']);
        $reportListPreserve = ['page'=>'reports', 'edit_report'=>$editReportId ?: ''];
        $reports = sfApplyRows($reports, $reportListSf, $reportListSfFields);
        $reportBase = (string)($editReport['base_entity'] ?? 'jobs');
        $reportFields = reportFieldOptions($reportBase);
        $reportSettings = $editReport ? loadReportSettings($db, (int)$editReport['id'], $reportBase) : ['columns'=>reportDefaultColumns($reportBase), 'filters'=>[], 'sort'=>['field_name'=>array_key_first($reportFields), 'direction'=>'asc']];
        $reportStatuses = reportStatusOptions($reportBase);
        ?>
        <div class="page-head"><div><p class="eyebrow">Auswertung</p><h1>Reports & Exporte</h1></div><span><?= count($reports) ?> Reports</span></div>
        <?php if($reportEditMissing): ?><div class="alert warning">Dieser Report wurde nicht gefunden oder gehört nicht zu deinem Konto.</div><?php endif; ?>
        <div class="reports-layout">
            <section class="panel report-editor-panel" id="report-editor">
                <h2><?= $editReport ? 'Report bearbeiten' : 'Report speichern' ?></h2>
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <?php if($editReport): ?><input type="hidden" name="report_id" value="<?= (int)$editReport['id'] ?>"><?php endif; ?>
                    <label>Name<input name="report_name" value="<?= e($editReport['name'] ?? '') ?>" required></label>
                    <label>Beschreibung<textarea name="report_description" rows="3"><?= e($editReport['description'] ?? '') ?></textarea></label>
                    <div class="two">
                        <label>Basis<select name="base_entity"><?php foreach($reportBaseOptions as $v=>$l): ?><option value="<?= e($v) ?>" <?= $reportBase===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                        <label>Ansicht<select name="display_type"><?php foreach($reportDisplayOptions as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($editReport['display_type'] ?? 'table')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <fieldset class="report-config"><legend>Spalten</legend><?php foreach($reportFields as $field=>$label): ?><label class="check"><input type="checkbox" name="report_columns[]" value="<?= e($field) ?>" <?= in_array($field, $reportSettings['columns'], true)?'checked':'' ?>> <?= e($label) ?></label><?php endforeach; ?></fieldset>
                    <div class="two"><label>Filtertext<input name="report_q" value="<?= e((string)($reportSettings['filters']['q'] ?? '')) ?>" placeholder="Text in allen Spalten"></label><label>Status<select name="report_status"><option value="">Alle</option><?php foreach($reportStatuses as $v=>$l): ?><option value="<?= e($v) ?>" <?= (string)($reportSettings['filters']['status'] ?? '')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label></div>
                    <div class="two"><label>Sortieren nach<select name="report_sort"><?php foreach($reportFields as $field=>$label): ?><option value="<?= e($field) ?>" <?= (string)($reportSettings['sort']['field_name'] ?? '')===$field?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label>Richtung<select name="report_dir"><option value="asc" <?= ($reportSettings['sort']['direction'] ?? 'asc')==='asc'?'selected':'' ?>>Aufsteigend</option><option value="desc" <?= ($reportSettings['sort']['direction'] ?? '')==='desc'?'selected':'' ?>>Absteigend</option></select></label></div>
                    <div class="actions"><button class="primary" name="action" value="<?= $editReport ? 'update_report' : 'save_report' ?>"><?= $editReport ? 'Änderungen speichern' : 'Speichern' ?></button><?php if($editReport): ?><a class="button" href="/?page=reports">Neu</a><a class="button" href="/?page=export_pdf&type=report&report_id=<?= (int)$editReport['id'] ?>">PDF</a><?php endif; ?></div>
                </form>
                <div class="actions export-actions"><?= sfToolbar('reports', $reportListSf, $reportListPreserve, $reportListSfFields) ?><a class="button" href="/?page=export_csv&type=jobs">Jobs CSV</a><a class="button" href="/?page=export_pdf&type=jobs">Jobs PDF</a><a class="button" href="/?page=export_csv&type=applications">Bewerbungen CSV</a><a class="button" href="/?page=export_pdf&type=applications">Bewerbungen PDF</a><a class="button" href="/?page=export_csv&type=audit">Audit CSV</a></div>
            </section>
            <section class="panel table-wrap"><h2>Gespeicherte Reports</h2><table><thead><tr><?= sfHeader('reports','name','Name',$reportListSf,$reportListPreserve) ?><?= sfHeader('reports','base_label','Basis',$reportListSf,$reportListPreserve) ?><?= sfHeader('reports','display_label','Ansicht',$reportListSf,$reportListPreserve) ?><?= sfHeader('reports','updated_at','Aktualisiert',$reportListSf,$reportListPreserve) ?><th>Aktionen</th></tr></thead><tbody><?php foreach($reports as $report): ?><tr class="<?= $editReport && (int)$editReport['id']===(int)$report['id'] ? 'is-selected' : '' ?>"><td><strong><?= e($report['name']) ?></strong><small><?= e($report['description']) ?></small></td><td><?= e($report['base_label']) ?></td><td><?= e($report['display_label']) ?></td><td><?= e(displayDateTime($report['updated_at'], $currentUser)) ?></td><td class="actions"><a href="<?= e(reportOpenUrl($report)) ?>">Anzeigen</a><a href="/?page=reports&edit_report=<?= (int)$report['id'] ?>#report-editor">Bearbeiten</a><a href="/?page=export_pdf&type=report&report_id=<?= (int)$report['id'] ?>">PDF</a><form method="post" onsubmit="return confirm('Report wirklich löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><button name="action" value="delete_report">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$reports): ?><tr><td colspan="5" class="empty">Noch keine Reports gespeichert.</td></tr><?php endif; ?></tbody></table></section>
        </div>
    <?php elseif ($page === 'calendar'): ?>
        <?php
        $calendarViews = calendarViewOptions();
        $calendarView = array_key_exists((string)($_GET['view'] ?? 'agenda'), $calendarViews) ? (string)$_GET['view'] : 'agenda';
        $anchor = calendarAnchorDate($currentUser);
        [$rangeStart, $rangeEnd, $prevStep, $nextStep] = calendarRange($calendarView, $anchor);
        $calendarEvents = calendarEventRows($db, userId(), $rangeStart, $rangeEnd);
        $calendarSfFields = [
            'starts_at'=>['label'=>'Zeit'],
            'title'=>['label'=>'Ereignis'],
            'type'=>['label'=>'Typ'],
            'status'=>['label'=>'Status'],
            'meta'=>['label'=>'Bezug'],
        ];
        $calendarSf = sfState('calendar_agenda', $calendarSfFields, ['sort'=>'starts_at','dir'=>'asc']);
        $calendarPreserve = ['page'=>'calendar', 'view'=>'agenda', 'date'=>$anchor->format('Y-m-d')];
        if ($calendarView === 'agenda') {
            $calendarEvents = sfApplyRows($calendarEvents, $calendarSf, $calendarSfFields);
        }
        $prevDate = $anchor->modify($prevStep)->format('Y-m-d');
        $nextDate = $anchor->modify($nextStep)->format('Y-m-d');
        $weekNo = $anchor->format('W');
        $weekdayNames = ['Mon'=>'Mo','Tue'=>'Di','Wed'=>'Mi','Thu'=>'Do','Fri'=>'Fr','Sat'=>'Sa','Sun'=>'So'];
        $hours = range(7, 19);
        $eventsByDate = [];
        foreach ($calendarEvents as $entry) {
            $eventsByDate[substr((string)$entry['starts_at'], 0, 10)][] = $entry;
        }
        $viewUrl = static fn(string $view, string $date): string => '/?page=calendar&view=' . urlencode($view) . '&date=' . urlencode($date);
        $newStart = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string)($_GET['new_start'] ?? '')) ? (string)$_GET['new_start'] : '';
        $newEntryUrl = static fn(string $dateTime): string => '/?page=calendar&view=' . urlencode($calendarView) . '&date=' . urlencode(substr($dateTime, 0, 10)) . '&new_start=' . urlencode($dateTime) . '#new-calendar-entry';
        $icsUrl = '/?page=export_ics&view=' . urlencode($calendarView) . '&date=' . urlencode($anchor->format('Y-m-d'));
        $headline = match($calendarView) {
            'day' => $anchor->format('d.m.Y') . ' · KW ' . $weekNo,
            'workweek' => $rangeStart->format('d.m.') . ' - ' . $rangeEnd->format('d.m.Y') . ' · KW ' . $weekNo,
            'week' => $rangeStart->format('d.m.') . ' - ' . $rangeEnd->format('d.m.Y') . ' · KW ' . $weekNo,
            'month' => $anchor->format('m.Y'),
            default => $rangeStart->format('d.m.Y') . ' - ' . $rangeEnd->format('d.m.Y'),
        };
        $renderEvent = static function(array $entry): string {
            $time = date('H:i', strtotime((string)$entry['starts_at']));
            return '<a class="calendar-event" href="' . e((string)$entry['href']) . '"><strong>' . e($time . ' ' . (string)$entry['title']) . '</strong><span>' . e((string)$entry['type'] . ((string)$entry['meta'] !== '' ? ' · ' . (string)$entry['meta'] : '')) . '</span></a>';
        };
        $appsForCalendar = dbAll($db, 'SELECT a.id, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC LIMIT 100', 'i', [userId()]);
        ?>
        <div class="page-head"><div><p class="eyebrow">Zeitplan</p><h1>Kalender & Erinnerungen</h1></div><span><?= e($headline) ?> · <?= count($calendarEvents) ?> Einträge</span></div>
        <div class="calendar-toolbar"><div class="actions"><a class="button" href="<?= e($viewUrl($calendarView, $prevDate)) ?>">Zurück</a><a class="button" href="<?= e($viewUrl($calendarView, (new DateTimeImmutable('today'))->format('Y-m-d'))) ?>">Heute</a><a class="button" href="<?= e($viewUrl($calendarView, $nextDate)) ?>">Vor</a><a class="button" href="<?= e($icsUrl) ?>">ICS</a></div><form method="get" class="actions"><input type="hidden" name="page" value="calendar"><input type="hidden" name="date" value="<?= e($anchor->format('Y-m-d')) ?>"><select name="view" onchange="this.form.submit()"><?php foreach($calendarViews as $value=>$label): ?><option value="<?= e($value) ?>" <?= $calendarView===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></form></div>
        <section class="panel calendar-panel matrix-first">
        <?php if($calendarView === 'agenda'): ?>
            <h2>Agenda</h2><div class="actions export-actions"><?= sfToolbar('calendar_agenda', $calendarSf, $calendarPreserve, $calendarSfFields) ?></div><div class="table-wrap"><table><thead><tr><?= sfHeader('calendar_agenda','starts_at','Zeit',$calendarSf,$calendarPreserve) ?><?= sfHeader('calendar_agenda','title','Ereignis',$calendarSf,$calendarPreserve) ?><?= sfHeader('calendar_agenda','type','Typ',$calendarSf,$calendarPreserve) ?><?= sfHeader('calendar_agenda','status','Status',$calendarSf,$calendarPreserve) ?><?= sfHeader('calendar_agenda','meta','Bezug',$calendarSf,$calendarPreserve) ?></tr></thead><tbody><?php foreach($calendarEvents as $event): ?><tr><td><?= e(displayDateTime($event['starts_at'], $currentUser)) ?></td><td><a href="<?= e($event['href']) ?>"><?= e($event['title']) ?></a></td><td><?= e($event['type']) ?></td><td><?= e($event['status']) ?></td><td><?= e($event['meta']) ?></td></tr><?php endforeach; ?><?php if(!$calendarEvents): ?><tr><td colspan="5" class="empty">Keine Termine oder Wiedervorlagen.</td></tr><?php endif; ?></tbody></table></div>
        <?php elseif(in_array($calendarView, ['day','workweek','week'], true)): ?>
            <?php $days=[]; for($d=$rangeStart; $d <= $rangeEnd; $d=$d->modify('+1 day')) { $days[]=$d; } ?>
            <h2><?= $calendarView==='day' ? 'Tagesplan' : ($calendarView==='workweek' ? 'Arbeitswochenplan' : 'Wochenplan') ?> · KW <?= e($weekNo) ?></h2><div class="time-grid" style="--day-count:<?= count($days) ?>;--matrix-min:<?= count($days) === 1 ? '520px' : (count($days) === 5 ? '980px' : '1180px') ?>"><div class="time-head"></div><?php foreach($days as $day): ?><div class="time-day-head"><?= e(($weekdayNames[$day->format('D')] ?? $day->format('D')) . ', ' . $day->format('d.m.')) ?></div><?php endforeach; ?><?php foreach($hours as $hour): ?><div class="time-slot"><?= sprintf('%02d:00', $hour) ?></div><?php foreach($days as $day): $dateKey=$day->format('Y-m-d'); $slotStart=$dateKey.'T'.sprintf('%02d:00',$hour); $hourEvents=array_values(array_filter($eventsByDate[$dateKey] ?? [], static fn(array $ev): bool => (int)date('G', strtotime((string)$ev['starts_at'])) === $hour)); ?><div class="time-cell"><a class="calendar-add" href="<?= e($newEntryUrl($slotStart)) ?>">+</a><?php foreach($hourEvents as $event): ?><?= $renderEvent($event) ?><?php endforeach; ?></div><?php endforeach; ?><?php endforeach; ?></div>
        <?php else: ?>
            <?php $monthStart=$rangeStart->modify('monday this week'); $monthEnd=$rangeEnd->modify('sunday this week'); $monthDays=[]; for($d=$monthStart; $d <= $monthEnd; $d=$d->modify('+1 day')) { $monthDays[]=$d; } ?>
            <h2>Monatsplan <?= e($anchor->format('m.Y')) ?></h2><div class="month-grid"><div class="month-week-head">KW</div><?php foreach(['Mo','Di','Mi','Do','Fr','Sa','So'] as $wd): ?><div class="month-day-head"><?= e($wd) ?></div><?php endforeach; ?><?php foreach(array_chunk($monthDays,7) as $week): ?><div class="month-week-no"><?= e($week[0]->format('W')) ?></div><?php foreach($week as $day): $dateKey=$day->format('Y-m-d'); ?><div class="month-day <?= $day->format('m')===$anchor->format('m')?'':'is-muted' ?>"><div class="month-day-top"><strong><?= e($day->format('d.m.')) ?></strong><a class="calendar-add" href="<?= e($newEntryUrl($dateKey.'T09:00')) ?>">+</a></div><?php foreach(($eventsByDate[$dateKey] ?? []) as $event): ?><?= $renderEvent($event) ?><?php endforeach; ?></div><?php endforeach; ?><?php endforeach; ?></div>
        <?php endif; ?>
        </section><details class="panel calendar-entry-panel" id="new-calendar-entry" <?= $newStart !== '' ? 'open' : '' ?>><summary>Eintrag erstellen</summary><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label>Titel<input name="event_title" required></label><label>Typ<select name="event_type"><option value="reminder">Erinnerung</option><option value="follow_up">Nachfassen</option><option value="interview">Interview</option><option value="deadline">Frist</option><option value="meeting">Termin</option><option value="task">Aufgabe</option></select></label><label>Start<input type="datetime-local" name="starts_at" value="<?= e($newStart) ?>" required></label><label>Bewerbung<select name="application_id"><option value="0">Keine Verknüpfung</option><?php foreach($appsForCalendar as $app): ?><option value="<?= (int)$app['id'] ?>"><?= e($app['title'].' · '.$app['company_name']) ?></option><?php endforeach; ?></select></label><label>Notizen<textarea name="event_notes" rows="3"></textarea></label><button class="primary" name="action" value="save_calendar_event">Speichern</button></form></details>
    <?php elseif ($page === 'translations'): ?>
        <?php $translations = dbAll($db, 'SELECT id, entity_type, entity_id, target_language, title, SUBSTRING(body,1,65535) body, version, is_current, updated_at FROM record_translations WHERE owner_user_id=? ORDER BY updated_at DESC LIMIT 100', 'i', [userId()]); ?>
        <div class="page-head"><div><p class="eyebrow">Sprache</p><h1>Übersetzungen</h1></div><span><?= count($translations) ?> Versionen</span></div>
        <div class="split"><section class="panel"><h2>Übersetzung speichern</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><div class="two"><label>Typ<select name="entity_type"><option value="job">Job</option><option value="company">Firma</option><option value="application">Bewerbung</option><option value="contact">Kontakt</option><option value="document">Dokument</option></select></label><label>Datensatz<input type="number" min="1" name="entity_id" required></label></div><label>Zielsprache<select name="target_language"><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label><label>Titel<input name="translation_title"></label><label>Übersetzung<textarea name="translation_body" rows="8" required></textarea></label><button class="primary" name="action" value="save_translation">Speichern</button></form></section><section class="panel"><h2>Gespeicherte Übersetzungen</h2><div class="log-timeline"><?php foreach($translations as $translation): ?><article><div><strong><?= e($translation['title'] ?: ucfirst((string)$translation['entity_type'])) ?></strong><span><?= e($translation['target_language']) ?> · v<?= (int)$translation['version'] ?><?= $translation['is_current'] ? ' · aktuell' : '' ?></span></div><p><?= nl2br(e(mb_strimwidth((string)$translation['body'],0,500,'...'))) ?></p></article><?php endforeach; ?><?php if(!$translations): ?><p class="empty">Noch keine Übersetzungen gespeichert.</p><?php endif; ?></div></section></div>
    <?php elseif ($page === 'privacy'): ?>
        <?php
        $usage = storageUsageBytes($db, userId());
        $quota = dbOne($db, 'SELECT quota_bytes FROM storage_quotas WHERE user_id=?', 'i', [userId()]);
        $quotaBytes = (int) ($quota['quota_bytes'] ?? 5368709120);
        $cleanupRequests = dbAll($db, 'SELECT id, cutoff_date, status, SUBSTRING(preview_json,1,65535) preview_json, created_at FROM cleanup_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 20', 'i', [userId()]);
        foreach ($cleanupRequests as &$cleanupRow) {
            $preview = json_decode((string)$cleanupRow['preview_json'], true) ?: [];
            $cleanupRow['preview_text'] = 'Jobs: ' . (int)($preview['jobs'] ?? 0) . ' · Bewerbungen: ' . (int)($preview['applications'] ?? 0) . ' · alte Dokumentversionen: ' . (int)($preview['old_document_versions'] ?? 0) . ' · ' . bytesLabel((int)($preview['document_bytes'] ?? 0));
        }
        unset($cleanupRow);
        $cleanupSfFields = [
            'cutoff_date'=>['label'=>'Stichtag'],
            'status'=>['label'=>'Status'],
            'preview_text'=>['label'=>'Vorschau'],
            'created_at'=>['label'=>'Erstellt'],
        ];
        $cleanupSf = sfState('cleanup_requests', $cleanupSfFields, ['sort'=>'created_at','dir'=>'desc']);
        $cleanupPreserve = ['page'=>'privacy'];
        $cleanupRequests = sfApplyRows($cleanupRequests, $cleanupSf, $cleanupSfFields);
        ?>
        <div class="page-head"><div><p class="eyebrow">Datenschutz</p><h1>Speicher, Exporte, Cleanup</h1></div><span><?= e(bytesLabel($usage)) ?> / <?= e(bytesLabel($quotaBytes)) ?></span></div>
        <div class="split"><section class="panel"><h2>Speicherquote</h2><p><?= e(number_format($quotaBytes > 0 ? ($usage / $quotaBytes) * 100 : 0, 1)) ?>% genutzt.</p><progress max="<?= (int)$quotaBytes ?>" value="<?= (int)$usage ?>" style="width:100%"></progress><div class="actions"><a class="button" href="/?page=export_csv&type=audit">Audit exportieren</a><a class="button" href="/?page=export_csv&type=applications">Bewerbungen exportieren</a></div></section><section class="panel"><h2>Cleanup anfragen</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label>Daten älter als<input type="date" name="cutoff_date" value="<?= e((new DateTimeImmutable('-6 months'))->format('Y-m-d')) ?>" required></label><button class="primary" name="action" value="request_cleanup">Vorschau erstellen und anfragen</button></form></section></div>
        <section class="panel table-wrap"><h2>Cleanup-Anfragen</h2><div class="actions export-actions"><?= sfToolbar('cleanup_requests', $cleanupSf, $cleanupPreserve, $cleanupSfFields) ?></div><table><thead><tr><?= sfHeader('cleanup_requests','cutoff_date','Stichtag',$cleanupSf,$cleanupPreserve) ?><?= sfHeader('cleanup_requests','status','Status',$cleanupSf,$cleanupPreserve) ?><?= sfHeader('cleanup_requests','preview_text','Vorschau',$cleanupSf,$cleanupPreserve) ?><?= sfHeader('cleanup_requests','created_at','Erstellt',$cleanupSf,$cleanupPreserve) ?></tr></thead><tbody><?php foreach($cleanupRequests as $request): ?><tr><td><?= e($request['cutoff_date']) ?></td><td><?= e($request['status']) ?></td><td><small><?= e($request['preview_text']) ?></small></td><td><?= e(displayDateTime($request['created_at'], $currentUser)) ?></td></tr><?php endforeach; ?><?php if(!$cleanupRequests): ?><tr><td colspan="4" class="empty">Noch keine Cleanup-Anfragen.</td></tr><?php endif; ?></tbody></table></section>
    <?php elseif ($page === 'admin_users'): ?>
        <?php
        if (!$currentUserIsAdmin) {
            http_response_code(403);
            exit('Forbidden');
        }
        $adminEmails = array_map('strtolower', (array) ($config['admin_emails'] ?? ['admin@jema.business']));
        $users = dbAll(
            $db,
            "SELECT u.id, u.email, u.status, u.first_name, u.last_name, u.created_at, u.last_login_at, u.last_seen_at, u.email_verified_at,
                    (SELECT COUNT(*)
                       FROM user_sessions us
                      WHERE us.user_id=u.id
                        AND us.logged_out_at IS NULL
                        AND us.last_seen_at >= NOW() - INTERVAL 10 MINUTE) active_session_count,
                    (SELECT MAX(us.last_seen_at)
                       FROM user_sessions us
                      WHERE us.user_id=u.id
                        AND us.logged_out_at IS NULL) session_last_seen_at,
                    (SELECT GROUP_CONCAT(r.code ORDER BY r.code)
                       FROM user_roles ur
                       JOIN roles r ON r.id=ur.role_id
                      WHERE ur.user_id=u.id) role_codes,
                    (SELECT COUNT(*) FROM jobs j WHERE j.owner_user_id=u.id AND j.deleted_at IS NULL) job_count,
                    (SELECT COUNT(*) FROM applications a WHERE a.user_id=u.id AND a.deleted_at IS NULL) application_count,
                    (SELECT COUNT(*) FROM user_documents d WHERE d.user_id=u.id AND d.deleted_at IS NULL) document_count,
                    (SELECT COUNT(*) FROM two_factor_methods tf WHERE tf.user_id=u.id AND tf.verified_at IS NOT NULL) two_factor_count,
                    (SELECT sag.granted_at FROM support_access_grants sag WHERE sag.user_id=u.id AND sag.revoked_at IS NULL ORDER BY sag.granted_at DESC LIMIT 1) support_granted_at
               FROM users u
              WHERE u.deleted_at IS NULL
           ORDER BY FIELD(u.status, 'active', 'invited', 'locked', 'disabled'), u.created_at DESC"
        );
        foreach ($users as &$userRow) {
            $roleCodesForRow = array_filter(explode(',', (string) ($userRow['role_codes'] ?? '')));
            $isConfigAdminForRow = in_array(strtolower((string) $userRow['email']), $adminEmails, true);
            $lastSeenTs = strtotime((string)($userRow['last_seen_at'] ?? '')) ?: 0;
            $sessionLastSeenTs = strtotime((string)($userRow['session_last_seen_at'] ?? '')) ?: 0;
            $lastActivityTs = max($lastSeenTs, $sessionLastSeenTs);
            $userRow['last_activity_at'] = $sessionLastSeenTs > 0 ? $userRow['session_last_seen_at'] : $userRow['last_seen_at'];
            $userRow['online_label'] = ((int)($userRow['active_session_count'] ?? 0) > 0 || $lastActivityTs >= time() - 600) ? 'Online' : 'Offline';
            $userRow['full_name'] = trim((string)$userRow['first_name'].' '.(string)$userRow['last_name']);
            $userRow['usage_label'] = (int)$userRow['job_count'] . ' Jobs · ' . (int)$userRow['application_count'] . ' Bewerbungen · ' . (int)$userRow['document_count'] . ' Dokumente';
            $userRow['access_label'] = (($isConfigAdminForRow || in_array('admin', $roleCodesForRow, true)) ? 'Admin' : 'Benutzer') . ' · ' . $userRow['online_label'];
            if (!empty($userRow['support_granted_at'])) {
                $userRow['access_label'] .= ' · ADMIN Support frei';
            }
        }
        unset($userRow);
        $allUsers = $users;
        $adminUserSfFields = [
            'full_name'=>['label'=>'Benutzer'],
            'status'=>['label'=>'Status'],
            'usage_label'=>['label'=>'Nutzung'],
            'access_label'=>['label'=>'Zugriff'],
        ];
        $adminUserSf = sfState('admin_users', $adminUserSfFields, ['sort'=>'status','dir'=>'asc']);
        $adminUserPreserve = ['page'=>'admin_users', 'manage_user'=>(int) ($_GET['manage_user'] ?? 0) ?: ''];
        $users = sfApplyRows($users, $adminUserSf, $adminUserSfFields);
        $managedUserId = (int) ($_GET['manage_user'] ?? 0);
        $managedUser = null;
        foreach ($allUsers as $candidate) {
            if ((int) $candidate['id'] === $managedUserId) {
                $managedUser = $candidate;
                break;
            }
        }
        ?>
        <div class="page-head"><div><p class="eyebrow">Administration</p><h1>Benutzer</h1></div><span><?= count($users) ?> Konten</span></div>
        <section class="panel">
            <div class="section-head"><div><p class="eyebrow">Verwaltung</p><h2>Benutzer bearbeiten</h2></div></div>
            <?php if(!$managedUser): ?>
                <p class="meta-line">Wähle bei einem Benutzer „Verwalten“, um Name, E-Mail, Status, Admin-Rechte, Passwort oder Löschung zu bearbeiten.</p>
            <?php else: $managedRoleCodes = array_filter(explode(',', (string) ($managedUser['role_codes'] ?? ''))); $managedIsConfigAdmin = in_array(strtolower((string) $managedUser['email']), $adminEmails, true); $managedIsAdmin = $managedIsConfigAdmin || in_array('admin', $managedRoleCodes, true); $managedIsSelf = (int) $managedUser['id'] === realUserId(); ?>
                <h3><?= e(trim((string)$managedUser['first_name'].' '.(string)$managedUser['last_name'])) ?></h3>
                <?php if($managedIsSelf): ?>
                    <p class="alert warning">Eigenes Admin-Konto geschützt. Persönliche Daten bearbeitest Du über „Profil“.</p>
                <?php else: ?>
                    <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>">
                        <div class="three"><label>Vorname<input name="first_name" value="<?= e($managedUser['first_name']) ?>" required></label><label>Nachname<input name="last_name" value="<?= e($managedUser['last_name']) ?>" required></label><label>E-Mail<input type="email" name="email" value="<?= e($managedUser['email']) ?>" required></label></div>
                        <div class="two"><label>Status<select name="status"><?php foreach(['active'=>'Aktiv','invited'=>'Eingeladen/Test offen','locked'=>'Gesperrt','disabled'=>'Deaktiviert'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $managedUser['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label class="check"><input type="checkbox" name="is_admin" value="1" <?= $managedIsAdmin?'checked':'' ?> <?= $managedIsConfigAdmin?'disabled':'' ?>> Admin-Rechte</label></div>
                        <?php if($managedIsConfigAdmin): ?><input type="hidden" name="is_admin" value="1"><?php endif; ?>
                        <button class="primary" name="action" value="admin_update_user">Benutzerdaten speichern</button>
                    </form>
                    <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>">
                        <div class="two"><label>Neues Passwort<input type="password" name="new_password" minlength="10" required></label><label>Passwort wiederholen<input type="password" name="new_password_confirm" minlength="10" required></label></div>
                        <button name="action" value="admin_reset_user_password">Passwort setzen</button>
                    </form>
                    <?php if((int)($managedUser['two_factor_count'] ?? 0) > 0): ?>
                        <form method="post" onsubmit="return confirm('2FA für diesen Benutzer zurücksetzen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>"><button name="action" value="admin_reset_user_2fa">2FA zurücksetzen</button></form>
                    <?php endif; ?>
                    <?php if(!$managedIsConfigAdmin): ?>
                        <form method="post" onsubmit="return confirm('Benutzer wirklich löschen? Die Daten werden aus der aktiven Ansicht entfernt.')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>"><button name="action" value="admin_delete_user">Benutzer löschen</button></form>
                    <?php endif; ?>
                    <?php if(!empty($managedUser['support_granted_at'])): ?>
                        <form method="post" onsubmit="return confirm('In diese Benutzerumgebung einklinken?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>"><button class="primary" name="action" value="admin_start_support">In Umgebung einklinken</button></form>
                    <?php else: ?>
                        <p class="meta-line">ADMIN Support nicht freigegeben.</p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="panel table-wrap"><div class="actions export-actions"><?= sfToolbar('admin_users', $adminUserSf, $adminUserPreserve, $adminUserSfFields) ?></div><table><thead><tr><?= sfHeader('admin_users','full_name','Benutzer',$adminUserSf,$adminUserPreserve) ?><?= sfHeader('admin_users','status','Status',$adminUserSf,$adminUserPreserve) ?><?= sfHeader('admin_users','usage_label','Nutzung',$adminUserSf,$adminUserPreserve) ?><?= sfHeader('admin_users','access_label','Zugriff',$adminUserSf,$adminUserPreserve) ?><th>Aktionen</th></tr></thead><tbody>
            <?php foreach($users as $user): $roleCodes = array_filter(explode(',', (string) ($user['role_codes'] ?? ''))); $isConfigAdmin = in_array(strtolower((string) $user['email']), $adminEmails, true); $isUserAdmin = $isConfigAdmin || in_array('admin', $roleCodes, true); $isSelf = (int) $user['id'] === realUserId(); ?>
                <tr class="<?= $isSelf ? 'is-selected' : '' ?>">
                    <td><strong><?= e(trim((string)$user['first_name'].' '.(string)$user['last_name'])) ?></strong><span class="badge <?= ($user['online_label'] ?? '') === 'Online' ? 'role-badge' : '' ?>"><?= e($user['online_label'] ?? 'Offline') ?></span><small><?= e($user['email']) ?></small><small>Registriert: <?= e(displayDateTime($user['created_at'], $currentUser)) ?></small><small><a href="/?page=admin_users&manage_user=<?= (int)$user['id'] ?>">Verwalten</a></small></td>
                    <td><span class="badge"><?= e($user['status']) ?></span><?php if($user['email_verified_at']): ?><small>verifiziert: <?= e(displayDateTime($user['email_verified_at'], $currentUser)) ?></small><?php else: ?><small>nicht verifiziert</small><?php endif; ?><?php if((int)$user['two_factor_count'] > 0): ?><small>2FA aktiv</small><?php else: ?><small>2FA nicht aktiv</small><?php endif; ?><?php if($user['last_login_at']): ?><small>letzter Login: <?= e(displayDateTime($user['last_login_at'], $currentUser)) ?></small><?php endif; ?><?php if($user['last_activity_at']): ?><small>zuletzt aktiv: <?= e(displayDateTime($user['last_activity_at'], $currentUser)) ?></small><?php endif; ?></td>
                    <td><small><?= (int)$user['job_count'] ?> Jobs</small><small><?= (int)$user['application_count'] ?> Bewerbungen</small><small><?= (int)$user['document_count'] ?> Dokumente</small></td>
                    <td><small><?= $isUserAdmin ? 'Admin' : 'Benutzer' ?></small><?php if($isConfigAdmin): ?><small>Config-Admin</small><?php endif; ?><?php if(!empty($user['support_granted_at'])): ?><small>ADMIN Support frei seit <?= e(displayDateTime($user['support_granted_at'], $currentUser)) ?></small><?php endif; ?></td>
                    <td>
                        <?php if($isSelf): ?>
                            <span class="meta-line">Eigenes Konto geschützt</span>
                        <?php else: ?>
                            <form method="post" class="actions"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input name="first_name" value="<?= e($user['first_name']) ?>" placeholder="Vorname" required>
                                <input name="last_name" value="<?= e($user['last_name']) ?>" placeholder="Nachname" required>
                                <input type="email" name="email" value="<?= e($user['email']) ?>" placeholder="E-Mail" required>
                                <select name="status"><?php foreach(['active'=>'Aktiv','invited'=>'Eingeladen/Test offen','locked'=>'Gesperrt','disabled'=>'Deaktiviert'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $user['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                <label class="check"><input type="checkbox" name="is_admin" value="1" <?= $isUserAdmin?'checked':'' ?> <?= $isConfigAdmin?'disabled':'' ?>> Admin</label>
                                <?php if($isConfigAdmin): ?><input type="hidden" name="is_admin" value="1"><?php endif; ?>
                                <button class="primary" name="action" value="admin_update_user">Speichern</button>
                            </form>
                            <form method="post" class="actions"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="password" name="new_password" minlength="10" placeholder="Neues Passwort" required>
                                <input type="password" name="new_password_confirm" minlength="10" placeholder="Wiederholen" required>
                                <button name="action" value="admin_reset_user_password">Passwort setzen</button>
                            </form>
                            <?php if((int)$user['two_factor_count'] > 0): ?>
                                <form method="post" class="actions" onsubmit="return confirm('2FA für diesen Benutzer zurücksetzen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button name="action" value="admin_reset_user_2fa">2FA zurücksetzen</button></form>
                            <?php endif; ?>
                            <?php if(!$isConfigAdmin): ?>
                                <form method="post" class="actions" onsubmit="return confirm('Benutzer wirklich löschen? Die Daten werden aus der aktiven Ansicht entfernt.')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button name="action" value="admin_delete_user">Löschen</button></form>
                            <?php endif; ?>
                            <?php if(!empty($user['support_granted_at'])): ?>
                                <form method="post" class="actions" onsubmit="return confirm('In diese Benutzerumgebung einklinken?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button class="primary" name="action" value="admin_start_support">Einklinken</button></form>
                            <?php else: ?>
                                <span class="meta-line">Kein ADMIN Support</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></section>
    <?php elseif ($page === 'profile'): ?>
        <?php
        $preference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [userId()]) ?: [];
        $selectedEmploymentTypes = array_filter(explode(',', (string)($preference['employment_types'] ?? '')));
        $languageSkills = [];
        foreach (dbAll($db, 'SELECT language_code, cefr_level FROM user_language_skills WHERE user_id=? ORDER BY language_name', 'i', [userId()]) as $skill) {
            $languageSkills[$skill['language_code']] = $skill['cefr_level'];
        }
        $documentTypes = dbAll($db, 'SELECT id, code, name_key FROM document_types ORDER BY id');
        $profileDocumentTypes = documentTypesForScope($documentTypes, 'profile');
        $profileDocuments = dbAll($db, "SELECT d.*, dt.code type_code, dt.name_key type_name FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope='profile' AND d.deleted_at IS NULL ORDER BY d.is_current DESC, d.title, d.version DESC", 'i', [userId()]);
        $profileCurrency = currencyForCountry($currentUser['country_code'] ?? 'CH');
        $userLanguage = (string) ($currentUser['preferred_language'] ?? 'de');
        $languageChoices = europeanLanguageChoices();
        $totpMethod = activeTotpMethod($db, userId());
        if (!$totpMethod && empty($_SESSION['totp_setup_secret'])) {
            $_SESSION['totp_setup_secret'] = generateTotpSecret();
        }
        $totpSetupSecret = (string) ($_SESSION['totp_setup_secret'] ?? '');
        $totpSetupUri = $totpSetupSecret ? totpUri($config, $currentUser, $totpSetupSecret) : '';
        $smtpSettings = dbOne($db, 'SELECT smtp_host, smtp_port, smtp_encryption, smtp_username, from_email, from_name, is_active, updated_at FROM user_smtp_settings WHERE user_id=? LIMIT 1', 'i', [userId()]) ?: [];
        ?>
        <div class="page-head"><div><p class="eyebrow">Konto</p><h1>Eigenes Profil</h1></div><span><?= e($currentUser['email']) ?></span></div>
        <section class="panel" id="security">
            <div class="section-head"><div><p class="eyebrow">Sicherheit</p><h2>2FA / Authenticator</h2></div><span><?= $totpMethod ? 'Aktiv' : 'Noch nicht aktiv' ?></span></div>
            <?php if($totpMethod): ?>
                <p class="meta-line">2FA ist aktiv. Beim nächsten Login wird zusätzlich der Code aus deiner Authenticator-App verlangt.</p>
                <form method="post" onsubmit="return confirm('2FA wirklich deaktivieren?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="disable_totp">2FA deaktivieren</button></form>
            <?php else: ?>
                <p class="meta-line">Scanne den QR-Code mit einer Authenticator-App und bestätige danach den 6-stelligen Code.</p>
                <div class="totp-setup">
                    <div class="totp-qr" data-qr-text="<?= e($totpSetupUri) ?>" aria-label="QR-Code für Authenticator-App"></div>
                    <div class="stack">
                        <label>Setup-Schlüssel<input value="<?= e($totpSetupSecret) ?>" readonly onclick="this.select()"></label>
                        <details class="totp-manual">
                            <summary>Manuelle Eingabe anzeigen</summary>
                            <label>otpauth URI<input value="<?= e($totpSetupUri) ?>" readonly onclick="this.select()"></label>
                        </details>
                    </div>
                </div>
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <label>6-stelliger Code<input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
                    <button class="primary" name="action" value="enable_totp">2FA aktivieren</button>
                </form>
            <?php endif; ?>
        </section>
        <section class="panel" id="support-access">
            <div class="section-head"><div><p class="eyebrow">Support</p><h2>ADMIN Support</h2></div><span><?= $supportGrant ? 'Freigegeben' : 'Nicht freigegeben' ?></span></div>
            <?php if($supportImpersonating): ?>
                <p class="alert warning">Diese Umgebung wird gerade im ADMIN Support verwendet. Änderungen an der Freigabe sind währenddessen gesperrt.</p>
            <?php elseif($supportGrant): ?>
                <p class="meta-line">Du hast ADMIN Support seit <?= e(displayDateTime((string)$supportGrant['granted_at'], $currentUser)) ?> freigegeben. Ein Administrator kann sich wie du in deine Umgebung einklinken, bis du widerrufst.</p>
                <form method="post" onsubmit="return confirm('ADMIN Support wirklich widerrufen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="revoke_admin_support">ADMIN Support widerrufen</button></form>
            <?php else: ?>
                <p class="meta-line">Erlaube ADMIN Support nur, wenn ein Administrator deine Umgebung 1:1 sehen und bedienen soll. Du kannst die Freigabe jederzeit widerrufen.</p>
                <form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button class="primary" name="action" value="grant_admin_support">ADMIN Support erlauben</button></form>
            <?php endif; ?>
        </section>
        <section class="panel" id="smtp">
            <div class="section-head"><div><p class="eyebrow">E-Mail</p><h2>Eigener SMTP-Versand</h2></div><span><?= !empty($smtpSettings['is_active']) ? 'Aktiv' : 'Nicht aktiv' ?></span></div>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <div class="three">
                    <label>SMTP-Host<input name="smtp_host" value="<?= e($smtpSettings['smtp_host'] ?? '') ?>" placeholder="smtp.example.com" required></label>
                    <label>Port<input type="number" min="1" max="65535" name="smtp_port" value="<?= e((string)($smtpSettings['smtp_port'] ?? 587)) ?>" required></label>
                    <label>Verschlüsselung<select name="smtp_encryption"><?php foreach(['tls'=>'STARTTLS','ssl'=>'SSL/TLS','none'=>'Keine'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($smtpSettings['smtp_encryption'] ?? 'tls')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="two">
                    <label>SMTP-Benutzer<input name="smtp_username" value="<?= e($smtpSettings['smtp_username'] ?? '') ?>" autocomplete="username"></label>
                    <label>SMTP-Passwort<input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= empty($smtpSettings) ? '' : 'Leer lassen = unverändert' ?>"></label>
                </div>
                <div class="two">
                    <label>Absender-E-Mail<input type="email" name="from_email" value="<?= e($smtpSettings['from_email'] ?? $currentUser['email']) ?>" required></label>
                    <label>Absender-Name<input name="from_name" value="<?= e($smtpSettings['from_name'] ?? trim((string)$currentUser['first_name'] . ' ' . (string)$currentUser['last_name'])) ?>"></label>
                </div>
                <label class="check"><input type="checkbox" name="is_active" value="1" <?= !empty($smtpSettings['is_active']) ? 'checked' : '' ?>> SMTP für meinen Versand aktivieren</label>
                <div class="actions"><button class="primary" name="action" value="save_smtp_settings">SMTP speichern</button><button name="action" value="test_smtp_settings">Speichern & testen</button></div>
                <p class="meta-line">Passwort-Reset, Freigaben und Bewerbungs-E-Mails werden über diese SMTP-Daten verschickt, sobald sie aktiv sind.</p>
            </form>
        </section>
        <section class="panel"><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <div class="two"><label>Vorname<input name="first_name" value="<?= e($currentUser['first_name']) ?>" required></label><label>Nachname<input name="last_name" value="<?= e($currentUser['last_name']) ?>" required></label></div>
            <label>E-Mail<input value="<?= e($currentUser['email']) ?>" disabled><small>Login-E-Mail. Der Versand läuft über deine SMTP-Einstellungen.</small></label>
            <label>App- und Dokumentensprache<select name="preferred_language"><?php foreach(documentLanguageChoices() as $code=>$label): ?><option value="<?= e($code) ?>" <?= $userLanguage===$code?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><small>Steuert App-Texte, Dokumenttyp-Bezeichnungen, Prompts und neue Dokumente.</small></label>
            <label>Zeitzone<select name="timezone"><?php foreach(timezoneChoices() as $continent=>$zones): ?><optgroup label="<?= e($continent) ?>"><?php foreach($zones as $zone=>$label): ?><option value="<?= e($zone) ?>" <?= $currentUser['timezone']===$zone?'selected':'' ?>><?= e($label) ?> (<?= e($zone) ?>)</option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
            <div class="two"><label>Telefon<input name="phone" value="<?= e($currentUser['phone']) ?>"></label><label>Mobil<input name="mobile" value="<?= e($currentUser['mobile']) ?>"></label></div>
            <div class="three"><label>Ort<input name="city" value="<?= e($currentUser['city']) ?>"></label><label>Region<select name="region_key" id="profile-region"><option value="">Nicht gewählt</option><?php foreach(regionChoices() as $countryCode=>$regions): ?><optgroup label="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>"><?php foreach($regions as $region): $selectedRegion = $currentUser['region']===$region && $currentUser['country_code']===$countryCode; ?><option value="<?= e($countryCode . '|' . $region) ?>" data-country="<?= e($countryCode) ?>" data-country-name="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>" data-currency="<?= e(currencyForCountry($countryCode)) ?>" <?= $selectedRegion?'selected':'' ?>><?= e($region) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><label>Land<output id="profile-country-display" class="readonly-value"><?= e(countryChoices()[$currentUser['country_code']] ?? '') ?></output></label></div>
            <div class="history"><h3>Sprachkenntnisse</h3><p class="meta-line">Sprachrekords einzeln hinzufügen. Jede Sprache darf nur einmal vorkommen; mindestens eine Sprache muss C2 Muttersprache sein.</p></div>
            <div class="language-records">
                <?php $languageRecordIndex = 0; foreach($languageSkills as $code=>$level): ?>
                    <div class="language-record">
                        <label>Sprache<select name="language_codes[]"><option value="">Sprache wählen</option><?php foreach($languageChoices as $choiceCode=>$label): ?><option value="<?= e($choiceCode) ?>" <?= $code===$choiceCode?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <label>Niveau<select name="language_levels[]"><?php foreach(['A1'=>'A1 Anfänger','A2'=>'A2 Grundlagen','B1'=>'B1 Mittelstufe','B2'=>'B2 Selbständig','C1'=>'C1 Fachkundig','C2'=>'C2 Muttersprache'] as $levelValue=>$levelLabel): ?><option value="<?= e($levelValue) ?>" <?= $level===$levelValue?'selected':'' ?>><?= e($levelLabel) ?></option><?php endforeach; ?></select></label>
                        <label class="check"><input type="checkbox" name="remove_language_indexes[]" value="<?= $languageRecordIndex ?>"> Löschen</label>
                    </div>
                <?php $languageRecordIndex++; ?>
                <?php endforeach; ?>
                <?php if(!$languageSkills): ?><p class="empty compact-empty">Noch keine Sprachkenntnisse erfasst.</p><?php endif; ?>
                <div class="language-add">
                    <label>Sprache hinzufügen<select name="language_codes[]"><option value="">Sprache wählen</option><?php foreach($languageChoices as $code=>$label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label>Niveau<select name="language_levels[]"><option value="">Niveau wählen</option><?php foreach(['A1'=>'A1 Anfänger','A2'=>'A2 Grundlagen','B1'=>'B1 Mittelstufe','B2'=>'B2 Selbständig','C1'=>'C1 Fachkundig','C2'=>'C2 Muttersprache'] as $level=>$levelLabel): ?><option value="<?= e($level) ?>"><?= e($levelLabel) ?></option><?php endforeach; ?></select></label>
                    <button class="primary" name="action" value="save_profile">Hinzufügen / speichern</button>
                </div>
            </div>
            <div class="history"><h3>Job-Referenzen</h3><p class="meta-line">Diese Angaben steuern später Matching, Listen und Vorschläge.</p></div>
            <label>Gewünschte Tätigkeiten / Rollen<textarea name="desired_roles" rows="3" placeholder="z. B. Administration, Kundendienst, Lager, Verkauf"><?= e($preference['desired_roles'] ?? '') ?></textarea></label>
            <label>Gewünschte Orte / Lage<textarea name="desired_locations" rows="2" placeholder="z. B. Biel/Bienne, Seeland, ÖV gut erreichbar"><?= e($preference['desired_locations'] ?? '') ?></textarea></label>
            <div class="two"><label>Arbeitsmodell<select name="remote_preference"><?php foreach(['any'=>'Egal','onsite'=>'Vor Ort','hybrid'=>'Hybrid','remote'=>'Remote'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($preference['remote_preference'] ?? 'any')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><label>Level / Lage<input name="desired_level" value="<?= e($preference['desired_level'] ?? '') ?>" placeholder="z. B. Einstieg, Fachkraft, Teamleitung"></label></div>
            <fieldset class="check"><legend>Stellenarten</legend><?php foreach(['full_time'=>'Vollzeit','part_time'=>'Teilzeit','temporary'=>'Temporär','contract'=>'Befristet/Vertrag','internship'=>'Praktikum','freelance'=>'Freelance'] as $v=>$l): ?><label><input type="checkbox" name="employment_types[]" value="<?= $v ?>" <?= in_array($v, $selectedEmploymentTypes, true)?'checked':'' ?>> <?= $l ?></label><?php endforeach; ?></fieldset>
            <div class="two"><label>Pensum min. %<input type="number" min="0" max="100" name="workload_min" value="<?= e((string)($preference['workload_min'] ?? '')) ?>"></label><label>Pensum max. %<input type="number" min="0" max="100" name="workload_max" value="<?= e((string)($preference['workload_max'] ?? '')) ?>"></label></div>
            <div class="salary-row"><label>Lohn <span class="salary-currency-display"><?= e($profileCurrency) ?></span><input type="number" min="0" step="0.01" name="salary_min" value="<?= e((string)($preference['salary_min'] ?? '')) ?>"></label><label>Format<select name="salary_period"><?php foreach(['hour'=>'pro Stunde','month'=>'pro Monat','year'=>'pro Jahr'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($preference['salary_period'] ?? 'year')===$v?'selected':'' ?>><?= $l ?> · <?= e($profileCurrency) ?></option><?php endforeach; ?></select></label></div>
            <label>Verfügbar ab<input type="date" name="available_from" value="<?= e($preference['available_from'] ?? '') ?>"></label>
            <label>PK / Extras / Benefits<textarea name="desired_benefits" rows="2" placeholder="z. B. gute PK, ÖV-Beitrag, Schichtzulagen, Weiterbildung"><?= e($preference['desired_benefits'] ?? '') ?></textarea></label>
            <label>Ausschlüsse<textarea name="excluded_industries" rows="2" placeholder="Branchen, Tätigkeiten oder Bedingungen, die nicht passen"><?= e($preference['excluded_industries'] ?? '') ?></textarea></label>
            <div class="two"><label class="check"><input type="checkbox" name="willing_to_relocate" value="1" <?= !empty($preference['willing_to_relocate'])?'checked':'' ?>> Umzug möglich</label><label>Reiseanteil max. %<input type="number" min="0" max="100" name="travel_percentage" value="<?= e((string)($preference['travel_percentage'] ?? '')) ?>"></label></div>
            <label>Notizen zu Job-Referenzen<textarea name="preference_notes" rows="3"><?= e($preference['notes'] ?? '') ?></textarea></label>
            <button class="primary" name="action" value="save_profile">Profil speichern</button>
        </form></section>
        <section class="panel" id="documents"><div class="section-head"><div><p class="eyebrow">Stammdaten</p><h2>Dokumenten-Management</h2></div><span><?= count($profileDocuments) ?> Versionen</span></div><div class="split inner-split"><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_scope" value="profile"><label>Neue Version von<select name="replace_document_id"><option value="0">Neues Stammdaten-Dokument</option><?php foreach($profileDocuments as $doc): if(!(int)$doc['is_current']) continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label><label>Dokumenttyp<select name="document_type_id"><?php foreach($profileDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label><label>Titel<input name="document_title" required placeholder="z. B. Lebenslauf Deutsch"></label><label>Sprache<select name="document_language"><option value="">Nicht gewählt</option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= $v ?>" <?= $v===$userLanguage?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><div class="two"><label>Gültig ab<input type="date" name="valid_from"></label><label>Gültig bis<input type="date" name="valid_until"></label></div><label>Beschreibung<textarea name="document_description" rows="3"></textarea></label><label>Datei<input type="file" name="user_document" required></label><button class="primary" name="action" value="upload_document">Stammdaten-Dokument speichern</button></form><div class="table-wrap"><table><thead><tr><th>Dokument</th><th>Typ</th><th>Version</th><th>Aktionen</th></tr></thead><tbody><?php foreach($profileDocuments as $doc): ?><tr class="<?= (int)$doc['is_current'] ? 'is-selected' : '' ?>"><td><strong><a class="record-link" href="/?page=documents&edit_document=<?= (int)$doc['id'] ?>#document-editor"><?= e($doc['title']) ?></a></strong><small><?= e($doc['original_filename']) ?></small></td><td><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?><small><?= e($doc['language_code']) ?></small></td><td>v<?= (int)$doc['version'] ?><?= (int)$doc['is_current'] ? ' · aktuell' : '' ?></td><td class="actions"><a href="/?page=documents&edit_document=<?= (int)$doc['id'] ?>#document-editor">Bearbeiten</a><a href="/?page=document_download&id=<?= (int)$doc['id'] ?>">Download</a><form method="post" onsubmit="return confirm('Dokument löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_return" value="documents"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$profileDocuments): ?><tr><td colspan="4" class="empty">Noch keine Stammdaten-Dokumente vorhanden.</td></tr><?php endif; ?></tbody></table></div></div></section>
        <script>
        (() => {
            const region = document.getElementById('profile-region');
            const country = document.getElementById('profile-country-display');
            const currencies = document.querySelectorAll('.salary-currency-display');
            if (!region || !country) return;
            const syncCountry = () => {
                const selected = region.options[region.selectedIndex];
                country.textContent = selected ? (selected.dataset.countryName || '') : '';
                currencies.forEach((item) => { item.textContent = selected ? (selected.dataset.currency || 'CHF') : 'CHF'; });
            };
            region.addEventListener('change', syncCountry);
            syncCountry();
        })();
        </script>
    <?php elseif ($page === 'companies'): ?>
        <?php
        $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT * FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
        $companySfFields = ['name'=>['label'=>'Firma','expr'=>'c.name'], 'address'=>['label'=>'Adresse / Telefon','expr'=>'CONCAT_WS(" ", c.address_line1, c.address_line2, c.city, c.phone)'], 'role'=>['label'=>'Rolle / Vermittlung','expr'=>'IF(c.is_intermediary=1, "Vermittler", "Direkt")'], 'links'=>['label'=>'Verknüpfungen','expr'=>'CAST(c.updated_at AS CHAR)']];
        $companySf = sfState('companies', $companySfFields, ['sort'=>'name','dir'=>'asc']);
        $companyPreserve = ['page'=>'companies', 'edit'=>$_GET['edit'] ?? ''];
        $companySql='SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id AND j.owner_user_id=c.owner_user_id AND j.deleted_at IS NULL) job_count, (SELECT COUNT(*) FROM contacts ct WHERE ct.company_id=c.id AND ct.owner_user_id=c.owner_user_id AND ct.deleted_at IS NULL) contact_count, (SELECT COUNT(*) FROM applications a JOIN jobs j2 ON j2.id=a.job_id WHERE a.user_id=c.owner_user_id AND a.deleted_at IS NULL AND (j2.company_id=c.id OR a.intermediary_company_id=c.id)) application_count, (SELECT GROUP_CONCAT(DISTINCT CONCAT(client.id, "::", client.name) ORDER BY client.name SEPARATOR "||") FROM company_relationships cr JOIN companies client ON client.id=cr.client_company_id WHERE cr.owner_user_id=c.owner_user_id AND cr.intermediary_company_id=c.id AND cr.deleted_at IS NULL AND client.deleted_at IS NULL) mediated_clients, (SELECT GROUP_CONCAT(DISTINCT CONCAT(intermediary.id, "::", intermediary.name) ORDER BY intermediary.name SEPARATOR "||") FROM company_relationships cr JOIN companies intermediary ON intermediary.id=cr.intermediary_company_id WHERE cr.owner_user_id=c.owner_user_id AND cr.client_company_id=c.id AND cr.deleted_at IS NULL AND intermediary.deleted_at IS NULL) mediated_by FROM companies c WHERE c.owner_user_id=? AND c.deleted_at IS NULL'; $companyTypes='i'; $companyVals=[userId()];
        $companySql .= sfApplySql($companySf, $companySfFields, $companyTypes, $companyVals);
        $companySql .= sfOrderSql($companySf, $companySfFields, 'name');
        $companyRows = dbAll($db, $companySql, $companyTypes, $companyVals);
        ?>
        <div class="page-head"><div><p class="eyebrow">CRM</p><h1>Firmen</h1></div><span><?= count($companyRows) ?> Einträge</span></div>
        <div class="actions export-actions"><?= sfToolbar('companies', $companySf, ['page'=>'companies'], $companySfFields) ?><a class="button" href="/?page=export_pdf&type=companies">PDF</a></div>
        <div class="split"><section class="panel"><h2><?= $edit ? 'Firma bearbeiten' : 'Neue Firma' ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><label>Name<input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label><label class="check"><input type="checkbox" name="is_intermediary" value="1" <?= !empty($edit['is_intermediary'])?'checked':'' ?>> Möglicher Vermittler / Personalvermittler</label><label>Haupttelefon<input name="company_phone" value="<?= e($edit['phone'] ?? '') ?>"></label><label>Adresse<textarea name="address" rows="3" placeholder="Strasse und Nummer&#10;Adresszusatz"><?= e(trim((string)($edit['address_line1'] ?? '') . "\n" . (string)($edit['address_line2'] ?? ''))) ?></textarea></label><div class="two"><label>PLZ<input name="postal_code" value="<?= e($edit['postal_code'] ?? '') ?>"></label><label>Ort<input name="city" value="<?= e($edit['city'] ?? '') ?>"></label></div><div class="two"><label>Region<select name="company_region_key" id="company-region"><option value="">Nicht gewählt</option><?php foreach(regionChoices() as $countryCode=>$regions): ?><optgroup label="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>"><?php foreach($regions as $region): $selectedRegion = ($edit['region'] ?? '')===$region && ($edit['country_code'] ?? '')===$countryCode; ?><option value="<?= e($countryCode . '|' . $region) ?>" data-country="<?= e($countryCode) ?>" data-country-name="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>" <?= $selectedRegion?'selected':'' ?>><?= e($region) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><label>Land<output id="company-country-display" class="readonly-value"><?= e(countryChoices()[$edit['country_code'] ?? ''] ?? 'Ergibt sich aus Region') ?></output></label></div><label>Website<input type="url" name="website" value="<?= e($edit['website'] ?? '') ?>"></label><button class="primary" name="action" value="save_company">Speichern</button></form></section>
        <section class="panel table-wrap"><table><thead><tr><?= sfHeader('companies','name','Firma',$companySf,$companyPreserve) ?><?= sfHeader('companies','address','Adresse / Telefon',$companySf,$companyPreserve) ?><?= sfHeader('companies','role','Rolle / Vermittlung',$companySf,$companyPreserve) ?><?= sfHeader('companies','links','Verknüpfungen',$companySf,$companyPreserve) ?><th>Aktionen</th></tr></thead><tbody><?php foreach($companyRows as $company): ?><tr class="<?= $edit && (int)$edit['id']===(int)$company['id']?'is-selected':'' ?>"><td><strong><a class="record-link" href="/?page=companies&edit=<?= (int)$company['id'] ?>"><?= e($company['name']) ?></a></strong><small><?= e($company['website']) ?></small></td><td><?php if($company['address_line1']): ?><small><?= nl2br(e(trim((string)$company['address_line1'] . "\n" . (string)$company['address_line2']))) ?></small><?php endif; ?><?php if($company['city']): ?><small><?= e($company['city']) ?></small><?php endif; ?><?php if($company['phone']): ?><small><?= e($company['phone']) ?></small><?php endif; ?></td><td class="relationship-cell"><?php if(!empty($company['is_intermediary']) || $company['mediated_clients']): ?><span class="badge role-badge">Vermittler</span><?php endif; ?><?php if($company['mediated_clients']): ?><small>Vermittelt: <?php foreach(explode('||', $company['mediated_clients']) as $entry): [$id,$name]=array_pad(explode('::',$entry,2),2,''); ?><a href="/?page=companies&edit=<?= (int)$id ?>"><?= e($name) ?></a><?php endforeach; ?></small><?php endif; ?><?php if($company['mediated_by']): ?><span class="badge">Vermittelt</span><small>durch: <?php foreach(explode('||', $company['mediated_by']) as $entry): [$id,$name]=array_pad(explode('::',$entry,2),2,''); ?><a href="/?page=companies&edit=<?= (int)$id ?>"><?= e($name) ?></a><?php endforeach; ?></small><?php endif; ?><?php if(empty($company['is_intermediary']) && !$company['mediated_clients'] && !$company['mediated_by']): ?><small>Direkte Firma / keine Vermittlung erfasst</small><?php endif; ?></td><td class="link-list"><a href="/?page=jobs&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['job_count'] ?> Jobs</a><a href="/?page=applications&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['application_count'] ?> Bewerbungen</a><a href="/?page=contacts&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['contact_count'] ?> Kontakte</a></td><td class="actions"><form method="post" onsubmit="return confirm('Firma löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$company['id'] ?>"><button name="action" value="delete_company">Löschen</button></form></td></tr><?php endforeach; ?></tbody></table></section></div>
        <script>
        (() => {
            const region = document.getElementById('company-region');
            const country = document.getElementById('company-country-display');
            if (!region || !country) return;
            const syncCountry = () => {
                const selected = region.options[region.selectedIndex];
                country.textContent = selected ? (selected.dataset.countryName || 'Ergibt sich aus Region') : 'Ergibt sich aus Region';
            };
            region.addEventListener('change', syncCountry);
            syncCountry();
        })();
        </script>
    <?php elseif ($page === 'jobs'): ?>
        <?php
        $companyFilter = (int)($_GET['company_id'] ?? 0); $jobView = ($_GET['view'] ?? 'cards') === 'table' ? 'table' : 'cards';
        $jobSfFields = ['title'=>['label'=>'Titel','expr'=>'j.title'], 'company'=>['label'=>'Firma','expr'=>'c.name'], 'location'=>['label'=>'Ort','expr'=>'j.location_text'], 'status'=>['label'=>'Status','expr'=>'j.status'], 'match'=>['label'=>'Match','expr'=>'j.updated_at']];
        $jobSf = sfState('jobs', $jobSfFields, ['sort'=>'title','dir'=>'asc']);
        $jobPreserve = ['page'=>'jobs', 'view'=>$jobView, 'company_id'=>$companyFilter ?: '', 'edit'=>$_GET['edit'] ?? ''];
        $sql = 'SELECT j.id, j.company_id, j.title, j.location_text, j.status, j.workplace_type, j.engagement_type, j.contract_term, j.fixed_term_start, j.fixed_term_end, j.source_url, j.original_pdf_status, j.original_pdf_requested_at, j.original_pdf_rendered_at, j.original_pdf_error, j.salary_min, j.salary_max, j.salary_currency, j.salary_period, SUBSTRING(j.description,1,65535) description, j.updated_at, c.name company_name, (SELECT d.id FROM user_documents d WHERE d.user_id=j.owner_user_id AND d.job_id=j.id AND d.title="Originale Stellenausschreibung" AND d.deleted_at IS NULL ORDER BY d.created_at DESC LIMIT 1) original_document_id FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL'; $types='i'; $vals=[userId()];
        if ($companyFilter > 0) { $sql .= ' AND j.company_id=?'; $types.='i'; $vals[]=$companyFilter; }
        $sql .= sfApplySql($jobSf, $jobSfFields, $types, $vals);
        $sql .= sfOrderSql($jobSf, $jobSfFields, 'title');
        $jobs=dbAll($db,$sql,$types,$vals);
        $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, salary_min, salary_max, salary_currency, salary_period, source_url, original_pdf_status, SUBSTRING(description,1,65535) description FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
        $draft = is_array($_SESSION['import_draft'] ?? null) ? $_SESSION['import_draft'] : [];
        $form = $edit ?: $draft;
        $draftCompany = trim((string) ($draft['company'] ?? ''));
        $matchedCompanyId = 0;
        foreach ($companies as $candidate) {
            if ($draftCompany !== '' && mb_strtolower($candidate['name']) === mb_strtolower($draftCompany)) {
                $matchedCompanyId = (int) $candidate['id'];
                break;
            }
        }
        $jobCurrency = currencyForCountry($currentUser['country_code'] ?? 'CH');
        $jobContacts = $edit ? dbAll($db, 'SELECT c.id, c.job_id, c.first_name, c.last_name, c.position, c.department, c.email, c.phone, c.mobile, co.name company_name, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=c.id AND l.owner_user_id=c.owner_user_id) log_count FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND c.deleted_at IS NULL AND (c.job_id=? OR c.company_id=?) ORDER BY CASE WHEN c.job_id=? THEN 0 ELSE 1 END, c.last_name, c.first_name', 'iiii', [userId(), (int)$edit['id'], (int)$edit['company_id'], (int)$edit['id']]) : [];
        ?>
        <div class="page-head"><div><p class="eyebrow">Stellen-Pipeline</p><h1>Jobs</h1></div><span><?= count($jobs) ?> Treffer</span></div>
        <section class="panel import-panel"><h2>Schnellimport</h2><p>Eine Stellen-URL, kopierten E-Mail-/Ausschreibungstext oder mehrere Joblinks einfügen. Bei mehreren Links: ein Link pro Zeile. Original-PDFs werden nur mit echter Browser-Renderung abgelegt.</p><form method="post" class="import-form"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><textarea name="import_payload" rows="4" placeholder="https://…&#10;https://…&#10;oder Titel, Firma, Ort und Ausschreibungstext" required></textarea><button class="primary" name="action" value="preview_import">Vorschlag erstellen</button></form></section>
        <div class="actions export-actions"><?= sfToolbar('jobs', $jobSf, ['page'=>'jobs', 'view'=>$jobView, 'company_id'=>$companyFilter ?: ''], $jobSfFields) ?><a class="button" href="/?page=jobs&view=cards<?= $companyFilter ? '&company_id=' . (int)$companyFilter : '' ?>">Karten</a><a class="button" href="/?page=jobs&view=table<?= $companyFilter ? '&company_id=' . (int)$companyFilter : '' ?>">Tabelle</a><a class="button" href="/?page=export_pdf&type=jobs">PDF</a></div>
        <div class="split"><section class="panel" id="new"><h2><?= $edit ? 'Job bearbeiten' : ($draft ? 'Import prüfen' : 'Job erfassen') ?></h2><form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <label>Firma<select name="company_id"><option value="0">Neue Firma aus Import</option><?php foreach($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($form['company_id']??$matchedCompanyId)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
            <label>Neue Firma<input name="new_company_name" value="<?= e($matchedCompanyId ? '' : $draftCompany) ?>" placeholder="Nur ausfüllen, wenn die Firma noch fehlt"></label>
            <label>Jobtitel<input name="title" value="<?= e($form['title'] ?? '') ?>" required></label>
            <div class="two"><label>Ort<input name="location_text" value="<?= e($form['location_text'] ?? $form['location'] ?? '') ?>"></label><label>Arbeitsmodell<select name="workplace_type"><?php foreach(['unknown'=>'Unbekannt','onsite'=>'Vor Ort','hybrid'=>'Hybrid','remote'=>'Remote'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($form['workplace_type']??'unknown')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label></div>
            <div class="two"><label>Stellenart<select name="engagement_type"><option value="permanent" <?= ($form['engagement_type']??'permanent')==='permanent'?'selected':'' ?>>Dauerstelle</option><option value="temporary" <?= ($form['engagement_type']??'permanent')==='temporary'?'selected':'' ?>>Temporärstelle</option></select></label><label>Vertragsdauer<select name="contract_term"><option value="unknown" <?= ($form['contract_term']??'unknown')==='unknown'?'selected':'' ?>>Noch unbekannt</option><option value="open_ended" <?= ($form['contract_term']??'unknown')==='open_ended'?'selected':'' ?>>Unbefristet</option><option value="fixed_term" <?= ($form['contract_term']??'unknown')==='fixed_term'?'selected':'' ?>>Befristet</option></select></label></div>
            <div class="two"><label>Befristet von<input type="date" name="fixed_term_start" value="<?= e($form['fixed_term_start'] ?? '') ?>"></label><label>Befristet bis<input type="date" name="fixed_term_end" value="<?= e($form['fixed_term_end'] ?? '') ?>"></label></div>
            <div class="salary-row"><label>Lohn <span><?= e($jobCurrency) ?></span><input type="number" min="0" step="0.01" name="salary_min" value="<?= e((string)($form['salary_min'] ?? '')) ?>"></label><label>Format<select name="salary_period"><?php foreach(['hour'=>'pro Stunde','month'=>'pro Monat','year'=>'pro Jahr'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($form['salary_period'] ?? 'year')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label></div>
            <label>Status<select name="status"><?php foreach(['open'=>'Offen','interesting'=>'Interessant','applied'=>'Beworben','interview'=>'Interview','offer'=>'Angebot','rejected'=>'Absage','closed'=>'Geschlossen'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($form['status']??'open')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
            <label>Quell-URL<input type="url" name="source_url" value="<?= e($form['source_url'] ?? '') ?>"><?php if(!empty($form['source_url'])): ?><small><a href="<?= e($form['source_url']) ?>" target="_blank" rel="noopener">Quell-URL öffnen</a></small><?php endif; ?></label><label>Beschreibung<textarea name="description" rows="6"><?= e($form['description'] ?? '') ?></textarea></label>
            <?php if(!empty($_GET['duplicate'])): ?><label class="check"><input type="checkbox" name="confirm_duplicate" value="1" required> Als separate Stelle speichern</label><?php endif; ?><button class="primary" name="action" value="save_job">Speichern</button>
        </form></section>
        <?php if($edit): ?><section class="panel" id="job-contacts"><div class="section-head"><div><p class="eyebrow">Kontakte</p><h2>Kontakte zur Stelle</h2></div><a href="/?page=contacts&company_id=<?= (int)$edit['company_id'] ?>">Alle Firmenkontakte</a></div><div class="split inner-split"><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$edit['id'] ?>"><div class="two"><label>Vorname<input name="first_name" required></label><label>Nachname<input name="last_name" required></label></div><div class="two"><label>Funktion<input name="position"></label><label>Abteilung<input name="department"></label></div><label>E-Mail<input type="email" name="contact_email"></label><div class="two"><label>Telefon<input name="phone"></label><label>Mobil<input name="mobile"></label></div><label>LinkedIn<input type="url" name="linkedin_url"></label><label>Sprache<select name="preferred_language"><option value="">Nicht gewählt</option><?php foreach(['de'=>'Deutsch','en'=>'English','es'=>'Español','pt'=>'Português'] as $v=>$l): ?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label><label>Notizen<textarea name="contact_notes" rows="3"></textarea></label><button class="primary" name="action" value="save_job_contact">Kontakt speichern</button></form><div class="contact-list"><?php foreach($jobContacts as $contact): ?><article class="<?= (int)$contact['job_id']===(int)$edit['id']?'is-primary':'' ?>"><small><?= e($contact['company_name']) ?><?= (int)$contact['job_id']===(int)$edit['id'] ? ' · Stelle' : ' · Firma' ?></small><strong><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>#contact-log"><?= e($contact['first_name'].' '.$contact['last_name']) ?></a></strong><span><?= e($contact['position'] ?: $contact['department']) ?></span><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?><small><?= e($contact['phone'] ?: $contact['mobile']) ?></small><div class="actions"><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>">Bearbeiten</a><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>#contact-log">Kontakt-Log</a></div></article><?php endforeach; ?><?php if(!$jobContacts): ?><p class="empty">Noch keine Kontakte zur Firma oder Stelle.</p><?php endif; ?></div></div></section><?php endif; ?>
        <?php if($jobView === 'table'): ?><section class="panel table-wrap"><table><thead><tr><?= sfHeader('jobs','title','Titel',$jobSf,$jobPreserve) ?><?= sfHeader('jobs','company','Firma',$jobSf,$jobPreserve) ?><?= sfHeader('jobs','location','Ort',$jobSf,$jobPreserve) ?><?= sfHeader('jobs','status','Status',$jobSf,$jobPreserve) ?><?= sfHeader('jobs','match','Match',$jobSf,$jobPreserve) ?><th>Aktionen</th></tr></thead><tbody><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); $jobSalaryLabel=salaryLabel($job,$jobCurrency); ?><tr><td><strong><a href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new"><?= e($job['title']) ?></a></strong><small><?= e(mb_strimwidth((string)$job['description'],0,120,'...')) ?></small></td><td><a href="/?page=companies&edit=<?= (int)$job['company_id'] ?>"><?= e($job['company_name']) ?></a></td><td><?= e($job['location_text']) ?></td><td><?= e($job['status']) ?><small><?= e($job['engagement_type']) ?> · <?= e($job['contract_term']) ?></small><?php if($jobSalaryLabel !== ''): ?><small>Lohn: <?= e($jobSalaryLabel) ?></small><?php endif; ?></td><td><?= $score ?>%</td><td class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button name="action" value="start_application">Bewerbung starten</button></form><a href="/?page=applications&job_id=<?= (int)$job['id'] ?>">Bewerbungen</a></td></tr><?php endforeach; ?><?php if(!$jobs): ?><tr><td colspan="6" class="empty">Keine Treffer.</td></tr><?php endif; ?></tbody></table></section><?php else: ?><section class="cards"><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); $jobSalaryLabel=salaryLabel($job,$jobCurrency); ?><article class="job-card <?= $edit && (int)$edit['id']===(int)$job['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($job['status']) ?></span><span class="score"><?= $score ?>%</span></div><h3><a class="record-link" href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new"><?= e($job['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$job['company_id'] ?>"><?= e($job['company_name']) ?></a> · <?= e($job['location_text']) ?></p><p class="meta-line"><?= $job['engagement_type']==='temporary'?'Temporärstelle':'Dauerstelle' ?> · <?= ['open_ended'=>'unbefristet','fixed_term'=>'befristet','unknown'=>'Dauer offen'][$job['contract_term']] ?? 'Dauer offen' ?></p><?php if($jobSalaryLabel !== ''): ?><p class="meta-line">Lohn: <?= e($jobSalaryLabel) ?></p><?php endif; ?><?php if(($job['original_pdf_status'] ?? 'none') !== 'rendered' && !empty($job['source_url'])): ?><p class="meta-line"><?= e(originalPdfStatusLabel((string)($job['original_pdf_status'] ?? 'none'))) ?><?php if(!empty($job['original_pdf_error'])): ?> · <?= e(mb_strimwidth((string)$job['original_pdf_error'],0,90,'...')) ?><?php endif; ?></p><?php endif; ?><p><?= e(mb_strimwidth((string)$job['description'],0,180,'...')) ?></p><details><summary>Warum <?= $score ?>%?</summary><ul><?php foreach($reasons as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul></details><div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button class="primary-link" name="action" value="start_application">Bewerbung starten</button></form><a href="/?page=applications&job_id=<?= (int)$job['id'] ?>">Bewerbungen</a><?php if(!empty($job['original_document_id'])): ?><a href="/?page=document_download&id=<?= (int)$job['original_document_id'] ?>">Original-PDF</a><?php elseif(!empty($job['source_url'])): ?><span class="meta-line">Original-PDF ausstehend</span><?php endif; ?><form method="post" onsubmit="return confirm('Job löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$job['id'] ?>"><button name="action" value="delete_job">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$jobs): ?><div class="empty">Noch keine passenden Jobs vorhanden.</div><?php endif; ?></section><?php endif; ?></div>
    <?php elseif ($page === 'applications'): ?>
        <?php
        $appCompanyFilter=(int)($_GET['company_id'] ?? 0); $appJobFilter=(int)($_GET['job_id'] ?? 0); $todoOnly=!empty($_GET['todo']); $appView=($_GET['view'] ?? 'cards') === 'table' ? 'table' : 'cards';
        $appSfFields = ['title'=>['label'=>'Job','expr'=>'j.title'], 'company'=>['label'=>'Firma','expr'=>'c.name'], 'status'=>['label'=>'Status','expr'=>'a.status'], 'channel'=>['label'=>'Kanal','expr'=>'a.channel'], 'next_action'=>['label'=>'Nächster Schritt','expr'=>'CONCAT_WS(" ", a.next_action, a.next_action_at)']];
        $appSf = sfState('applications', $appSfFields, ['sort'=>'title','dir'=>'asc']);
        $appPreserve = ['page'=>'applications', 'view'=>$appView, 'company_id'=>$appCompanyFilter ?: '', 'job_id'=>$appJobFilter ?: '', 'todo'=>$todoOnly ? '1' : '', 'edit'=>$_GET['edit'] ?? ''];
        $appSql='SELECT a.id, a.job_id, a.intermediary_company_id, a.status, a.applied_at, a.channel, a.next_action, a.next_action_at, a.updated_at, j.title, j.company_id, c.name company_name, i.name intermediary_company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.user_id=? AND a.deleted_at IS NULL'; $appTypes='i'; $appVals=[userId()];
        if($appCompanyFilter>0){ $appSql.=' AND (j.company_id=? OR a.intermediary_company_id=?)'; $appTypes.='ii'; array_push($appVals,$appCompanyFilter,$appCompanyFilter); }
        if($appJobFilter>0){ $appSql.=' AND a.job_id=?'; $appTypes.='i'; $appVals[]=$appJobFilter; }
        if($todoOnly){ $appSql.=' AND a.next_action_at IS NOT NULL'; }
        $appSql .= sfApplySql($appSf, $appSfFields, $appTypes, $appVals);
        $appSql .= sfOrderSql($appSf, $appSfFields, 'title');
        $apps=dbAll($db,$appSql,$appTypes,$appVals);
        $applicationEdit = isset($_GET['edit']) ? dbOne($db, 'SELECT a.id, a.job_id, a.intermediary_company_id, a.primary_contact_id, a.status, a.applied_at, a.channel, a.next_action, a.next_action_at, a.application_url, a.portal_account, a.reference_number, SUBSTRING(a.online_notes,1,65535) online_notes, a.email_subject, SUBSTRING(a.email_body,1,65535) email_body, SUBSTRING(a.cover_letter_text,1,65535) cover_letter_text, SUBSTRING(a.notes,1,65535) notes, j.company_id, j.title, c.name company_name, i.name intermediary_company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
        $history = $applicationEdit ? dbAll($db, 'SELECT old_status, new_status, comment, changed_at FROM application_status_history WHERE application_id=? ORDER BY changed_at DESC', 'i', [(int)$applicationEdit['id']]) : [];
        $contacts = $applicationEdit ? dbAll($db, 'SELECT c.id, c.company_id, c.application_id, c.job_id, c.first_name, c.last_name, c.position, c.department, c.email, c.phone, c.mobile, c.linkedin_url, c.preferred_language, c.notes, co.name contact_company_name FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND (c.company_id=? OR c.company_id=? OR c.application_id=? OR c.job_id=?) AND c.deleted_at IS NULL ORDER BY co.name, c.last_name, c.first_name', 'iiiii', [userId(), (int)$applicationEdit['company_id'], (int)($applicationEdit['intermediary_company_id'] ?? 0), (int)$applicationEdit['id'], (int)$applicationEdit['job_id']]) : [];
        $selectedContactId = (int) ($_GET['contact'] ?? ($applicationEdit['primary_contact_id'] ?? 0));
        $contactEdit = $selectedContactId > 0 && $applicationEdit ? dbOne($db, 'SELECT id, company_id, application_id, job_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes FROM contacts WHERE id=? AND owner_user_id=? AND (company_id=? OR company_id=? OR application_id=? OR job_id=?) AND deleted_at IS NULL', 'iiiiii', [$selectedContactId, userId(), (int)$applicationEdit['company_id'], (int)($applicationEdit['intermediary_company_id'] ?? 0), (int)$applicationEdit['id'], (int)$applicationEdit['job_id']]) : null;
        $contactLogs = $contactEdit ? dbAll($db, 'SELECT id, contact_id, application_id, job_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE owner_user_id=? AND contact_id=? ORDER BY CASE status WHEN "open" THEN 1 WHEN "planned" THEN 2 WHEN "done" THEN 3 ELSE 4 END, COALESCE(follow_up_at, occurred_at) ASC', 'ii', [userId(), (int)$contactEdit['id']]) : [];
        $contactAttachments = $contactEdit ? contactLogAttachments($db, userId(), (int)$contactEdit['id']) : [];
        $editLogId = (int) ($_GET['edit_log'] ?? 0);
        $editLog = $editLogId > 0 ? dbOne($db, 'SELECT id, contact_id, application_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE id=? AND owner_user_id=? AND contact_id=?', 'iii', [$editLogId, userId(), (int)($contactEdit['id'] ?? 0)]) : null;
        $documentTypes = $applicationEdit ? dbAll($db, 'SELECT id, code, name_key FROM document_types ORDER BY id') : [];
        $applicationDocumentTypes = $applicationEdit ? documentTypesForScope($documentTypes, 'application') : [];
        $applicationDocuments = $applicationEdit ? dbAll($db, "SELECT ad.purpose, d.id, d.scope, d.title, d.version, d.original_filename, d.created_at, d.file_size, dt.code type_code, dt.name_key type_name FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id JOIN document_types dt ON dt.id=d.document_type_id WHERE ad.application_id=? AND d.user_id=? AND ((d.scope='application' AND d.application_id=?) OR d.scope='profile') AND d.deleted_at IS NULL ORDER BY ad.sort_order, d.scope DESC, d.is_current DESC, d.title, d.version DESC", 'iii', [(int)$applicationEdit['id'], userId(), (int)$applicationEdit['id']]) : [];
        $applicationProfileDocuments = $applicationEdit ? dbAll($db, "SELECT d.id, d.title, d.version, d.original_filename, dt.code type_code FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope='profile' AND d.is_current=1 AND d.deleted_at IS NULL ORDER BY d.title, d.version DESC", 'i', [userId()]) : [];
        $intermediaryCompanies = $applicationEdit ? array_values(array_filter($companies, static fn (array $company): bool => !empty($company['is_intermediary']) && (int)$company['id'] !== (int)$applicationEdit['company_id'])) : [];
        $userLanguage = (string) ($currentUser['preferred_language'] ?? 'de');
        $nextActionChoices = applicationNextActionChoices();
        $coverLetterPrompt = $applicationEdit && trim((string)($applicationEdit['cover_letter_text'] ?? '')) === ''
            ? applicationPrompt($db, userId(), (int)$applicationEdit['id'], $currentUser)
            : '';
        $applicationStatuses=['draft'=>'Entwurf','ready'=>'Bereit','sent'=>'Gesendet','confirmed'=>'Bestätigt','interview'=>'Interview','assessment'=>'Assessment','offer'=>'Angebot','accepted'=>'Angenommen','rejected'=>'Absage','withdrawn'=>'Zurückgezogen','closed'=>'Abgeschlossen'];
        $contactLogStatuses=contactLogStatusOptions();
        $contactLogChannels=contactLogChannelOptions();
        $channels=['email'=>'E-Mail','portal'=>'Jobportal','website'=>'Karriereseite','mail'=>'Post','referral'=>'Empfehlung','other'=>'Andere'];
        ?>
        <div class="page-head"><div><p class="eyebrow">Pipeline</p><h1>Bewerbungen</h1></div><span><?= count($apps) ?> Einträge</span></div>
        <div class="actions export-actions"><?= sfToolbar('applications', $appSf, ['page'=>'applications', 'view'=>$appView, 'company_id'=>$appCompanyFilter ?: '', 'job_id'=>$appJobFilter ?: '', 'todo'=>$todoOnly ? '1' : ''], $appSfFields) ?><a class="button" href="/?page=applications&view=cards<?= $appCompanyFilter ? '&company_id=' . (int)$appCompanyFilter : '' ?><?= $appJobFilter ? '&job_id=' . (int)$appJobFilter : '' ?><?= $todoOnly ? '&todo=1' : '' ?>">Karten</a><a class="button" href="/?page=applications&view=table<?= $appCompanyFilter ? '&company_id=' . (int)$appCompanyFilter : '' ?><?= $appJobFilter ? '&job_id=' . (int)$appJobFilter : '' ?><?= $todoOnly ? '&todo=1' : '' ?>">Tabelle</a><a class="button" href="/?page=export_pdf&type=applications">PDF</a></div>
        <?php if($appCompanyFilter || $appJobFilter || $todoOnly): ?><p class="filter-note">Gefilterte Ansicht · <a href="/?page=applications">Alle Bewerbungen anzeigen</a></p><?php endif; ?>
        <?php if ($applicationEdit): ?><section class="panel company-path" id="companies"><div><p class="eyebrow">Firmenbeziehung</p><h2><?= e($applicationEdit['company_name']) ?><?php if($applicationEdit['intermediary_company_name']): ?> <span>über <?= e($applicationEdit['intermediary_company_name']) ?></span><?php endif; ?></h2><p>Die Stelle gehört zur Kunden-/Arbeitgeberfirma. Optional kann eine Vermittler- oder Temporärfirma dazwischengeschaltet sein.</p></div><form method="post" class="company-path-form"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>"><label>Vermittlerfirma<select name="intermediary_company_id"><option value="0">Direktbewerbung ohne Vermittler</option><?php foreach($intermediaryCompanies as $company): ?><option value="<?= (int)$company['id'] ?>" <?= (int)$applicationEdit['intermediary_company_id']===(int)$company['id']?'selected':'' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select><small>Auswahl zeigt nur Firmen, die in der Firmenmaske als möglicher Vermittler markiert sind.</small></label><button class="primary" name="action" value="set_intermediary">Zuordnung speichern</button></form></section><?php endif; ?>
        <?php if ($applicationEdit): ?>
        <section class="panel application-editor" id="application-form">
            <div class="section-head">
                <div><p class="eyebrow"><?= e($applicationEdit['company_name']) ?></p><h2><?= e($applicationEdit['title']) ?></h2></div>
                <a href="/?page=applications">Schließen</a>
            </div>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= (int)$applicationEdit['id'] ?>">
                <div class="three">
                    <label>Status<select name="status"><?php foreach($applicationStatuses as $v=>$l): ?><option value="<?= $v ?>" <?= $applicationEdit['status']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
                    <label>Kanal<select name="channel"><option value="">Nicht gewählt</option><?php foreach($channels as $v=>$l): ?><option value="<?= $v ?>" <?= $applicationEdit['channel']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
                    <label>Gesendet am<input type="datetime-local" name="applied_at" value="<?= e($applicationEdit['applied_at'] ? date('Y-m-d\TH:i', strtotime($applicationEdit['applied_at'])) : '') ?>"></label>
                </div>
                <div class="history"><h3>Online-Bewerbung / Portal</h3><p class="meta-line">Viele Bewerbungen laufen über Portale oder Karriereseiten. Speichere hier den Link, Referenz und Upload-Hinweise. Keine Passwörter hinterlegen.</p></div>
                <label>Online-Bewerbungs-URL<input type="url" name="application_url" value="<?= e($applicationEdit['application_url'] ?? '') ?>" placeholder="https://..."><?php if(!empty($applicationEdit['application_url'])): ?><small><a href="<?= e($applicationEdit['application_url']) ?>" target="_blank" rel="noopener">Online-Bewerbung öffnen</a></small><?php endif; ?></label>
                <div class="two"><label>Portal / Login-Hinweis<input name="portal_account" value="<?= e($applicationEdit['portal_account'] ?? '') ?>" placeholder="z. B. Firmenportal, JobCloud, LinkedIn / Konto-E-Mail"></label><label>Referenznummer<input name="reference_number" value="<?= e($applicationEdit['reference_number'] ?? '') ?>" placeholder="Bestätigungs- oder Job-Referenz"></label></div>
                <label>Online-Notizen<textarea name="online_notes" rows="3" placeholder="Welche Dokumente hochgeladen, Fragen beantwortet, Bestätigung erhalten, Login-Hinweise ohne Passwort"><?= e($applicationEdit['online_notes'] ?? '') ?></textarea></label>
                <label>Hauptkontakt<select name="primary_contact_id"><option value="0">Noch kein Kontakt gewählt</option><?php foreach($contacts as $contact): ?><option value="<?= (int)$contact['id'] ?>" <?= (int)$applicationEdit['primary_contact_id']===(int)$contact['id']?'selected':'' ?>><?= e($contact['first_name'].' '.$contact['last_name'].($contact['position'] ? ' · '.$contact['position'] : '')) ?></option><?php endforeach; ?></select></label>
                <div class="two">
                    <label>Nächster Schritt<select name="next_action"><option value="">Kein nächster Schritt</option><?php foreach($nextActionChoices as $choice): ?><option value="<?= e($choice) ?>" <?= ($applicationEdit['next_action'] ?? '')===$choice?'selected':'' ?>><?= e($choice) ?></option><?php endforeach; ?></select><?php if(($applicationEdit['next_action'] ?? '') && !in_array((string)$applicationEdit['next_action'], $nextActionChoices, true)): ?><small>Bisheriger Freitext: <?= e($applicationEdit['next_action']) ?></small><?php endif; ?></label>
                    <label>Fällig am<input type="datetime-local" name="next_action_at" value="<?= e($applicationEdit['next_action_at'] ? date('Y-m-d\TH:i', strtotime($applicationEdit['next_action_at'])) : '') ?>"></label>
                </div>
                <label>Kommentar zur Statusänderung<input name="status_comment" placeholder="Optional, wird im Verlauf gespeichert"></label>
                <?php if(!mailEnabledForUser($db, $config, userId())): ?><p class="app-note">Lege im Profil eigene SMTP-Daten an, um E-Mails direkt aus der App zu versenden. Bis dahin wird der Entwurf nur protokolliert.</p><?php endif; ?>
                <label>E-Mail-Empfänger<input type="email" name="recipient_email" value="<?= e($contactEdit['email'] ?? '') ?>" placeholder="Kontakt auswählen oder Adresse eintragen"></label>
                <label>E-Mail-Betreff<input name="email_subject" value="<?= e($applicationEdit['email_subject'] ?? '') ?>"></label>
                <label>E-Mail-Begleittext<textarea name="email_body" rows="4"><?= e($applicationEdit['email_body'] ?? '') ?></textarea></label>
                <label>Motivationsschreiben<textarea name="cover_letter_text" rows="<?= $coverLetterPrompt ? 16 : 7 ?>"><?= e($applicationEdit['cover_letter_text'] ?: $coverLetterPrompt) ?></textarea><?php if($coverLetterPrompt): ?><small>Das Feld enthält einen ChatGPT-Prompt, weil noch kein Motivationsschreiben gespeichert ist. Kopieren, in ChatGPT verwenden, Ergebnis hier einfügen und speichern.</small><?php endif; ?></label>
                <label>Interne Notizen<textarea name="notes" rows="4"><?= e($applicationEdit['notes'] ?? '') ?></textarea></label>
                <button class="primary" name="action" value="save_application">Bewerbung speichern</button>
                <button name="action" value="send_application_email">E-Mail senden</button>
            </form>
            <?php if($history): ?><div class="history"><h3>Statusverlauf</h3><?php foreach($history as $entry): ?><article><strong><?= e($applicationStatuses[$entry['new_status']] ?? $entry['new_status']) ?></strong><span><?= e(displayDateTime($entry['changed_at'], $currentUser)) ?></span><?php if($entry['comment']): ?><p><?= e($entry['comment']) ?></p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
        </section>
        <section class="panel contact-log" id="documents">
            <div class="section-head"><div><p class="eyebrow">Bewerbungsdaten</p><h2>Bewerbungsdokumente</h2></div><a href="/?page=profile#documents">Stammdaten im Profil</a></div>
            <div class="split inner-split">
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>">
                    <label>Stammdaten übernehmen<select name="user_document_id"><?php foreach($applicationProfileDocuments as $doc): ?><option value="<?= (int)$doc['id'] ?>"><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?> · <?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label>
                    <button class="primary" name="action" value="attach_application_document" <?= !$applicationProfileDocuments ? 'disabled' : '' ?>>Stammdaten der Bewerbung zuordnen</button>
                    <?php if(!$applicationProfileDocuments): ?><p class="empty">Noch keine aktuellen Stammdaten im Profil vorhanden.</p><?php endif; ?>
                </form>
                <form method="post" enctype="multipart/form-data" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="document_scope" value="application">
                    <input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>">
                    <label>Neue Version von<select name="replace_document_id"><option value="0">Neues Bewerbungsdokument</option><?php foreach($applicationDocuments as $doc): if($doc['scope'] !== 'application') continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label>
                    <label>Dokumenttyp<select name="document_type_id"><?php foreach($applicationDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label>
                    <input type="hidden" name="purpose" value="cover_letter">
                    <label>Titel<input name="document_title" required placeholder="z. B. Motivationsschreiben <?= e($applicationEdit['company_name']) ?>"></label>
                    <label>Sprache<select name="document_language"><option value="">Nicht gewählt</option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= $v ?>" <?= $v===$userLanguage?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
                    <label>Beschreibung<textarea name="document_description" rows="3"></textarea></label>
                    <label>Datei<input type="file" name="user_document" required></label>
                    <button class="primary" name="action" value="upload_document">Bewerbungsdokument speichern</button>
                </form>
            </div>
            <div class="log-timeline">
                <?php foreach($applicationDocuments as $doc): ?><article>
                    <div><strong><a class="record-link" href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></a></strong><span><?= e(documentPurposeLabel((string)$doc['purpose'], $userLanguage)) ?> · <?= e(displayDateTime($doc['created_at'], $currentUser)) ?> · <?= number_format(((int)$doc['file_size']) / 1024, 1) ?> KB</span></div>
                    <small><?= e($doc['scope'] === 'profile' ? 'Stammdaten · ' . $doc['original_filename'] : $doc['original_filename']) ?></small>
                    <?php if($doc['scope'] === 'profile'): ?><form method="post" class="actions" onsubmit="return confirm('Dokument-Zuordnung entfernen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>"><input type="hidden" name="user_document_id" value="<?= (int)$doc['id'] ?>"><button name="action" value="detach_application_document">Entfernen</button></form><?php else: ?><form method="post" class="actions" onsubmit="return confirm('Bewerbungsdokument löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_return" value="documents"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document">Löschen</button></form><?php endif; ?>
                </article><?php endforeach; ?>
                <?php if(!$applicationDocuments): ?><p class="empty">Noch keine Bewerbungsdokumente vorhanden.</p><?php endif; ?>
            </div>
        </section>
        <section class="contact-workspace" id="contacts">
            <section class="panel">
                <div class="section-head"><div><p class="eyebrow">Firma → Job → Kontakt</p><h2><?= $contactEdit ? 'Kontakt bearbeiten' : 'Kontakt erfassen' ?></h2></div><?php if($contactEdit): ?><a href="/?page=applications&edit=<?= (int)$applicationEdit['id'] ?>#contacts">Neu</a><?php endif; ?></div>
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>">
                    <input type="hidden" name="contact_id" value="<?= (int)($contactEdit['id'] ?? 0) ?>">
                    <label>Kontakt gehört zu<select name="contact_company_id"><option value="<?= (int)$applicationEdit['company_id'] ?>" <?= (int)($contactEdit['company_id']??0)===(int)$applicationEdit['company_id']?'selected':'' ?>><?= e($applicationEdit['company_name']) ?> (Arbeitgeber/Kunde)</option><?php if($applicationEdit['intermediary_company_id']): ?><option value="<?= (int)$applicationEdit['intermediary_company_id'] ?>" <?= (int)($contactEdit['company_id']??0)===(int)$applicationEdit['intermediary_company_id']?'selected':'' ?>><?= e($applicationEdit['intermediary_company_name']) ?> (Vermittler)</option><?php endif; ?></select></label>
                    <div class="two"><label>Vorname<input name="first_name" value="<?= e($contactEdit['first_name'] ?? '') ?>" required></label><label>Nachname<input name="last_name" value="<?= e($contactEdit['last_name'] ?? '') ?>" required></label></div>
                    <div class="two"><label>Funktion<input name="position" value="<?= e($contactEdit['position'] ?? '') ?>"></label><label>Abteilung<input name="department" value="<?= e($contactEdit['department'] ?? '') ?>"></label></div>
                    <label>E-Mail<input type="email" name="contact_email" value="<?= e($contactEdit['email'] ?? '') ?>"></label>
                    <div class="two"><label>Telefon<input name="phone" value="<?= e($contactEdit['phone'] ?? '') ?>"></label><label>Mobil<input name="mobile" value="<?= e($contactEdit['mobile'] ?? '') ?>"></label></div>
                    <label>LinkedIn<input type="url" name="linkedin_url" value="<?= e($contactEdit['linkedin_url'] ?? '') ?>"></label>
                    <label>Sprache<select name="preferred_language"><option value="">Nicht gewählt</option><?php foreach(['de'=>'Deutsch','en'=>'English','es'=>'Español','pt'=>'Português'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($contactEdit['preferred_language']??'')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
                    <label>Notizen<textarea name="contact_notes" rows="3"><?= e($contactEdit['notes'] ?? '') ?></textarea></label>
                    <label class="check"><input type="checkbox" name="set_primary" value="1" <?= !$applicationEdit['primary_contact_id'] || (int)$applicationEdit['primary_contact_id']===(int)($contactEdit['id']??0)?'checked':'' ?>> Als Hauptkontakt der Bewerbung verwenden</label>
                    <button class="primary" name="action" value="save_contact">Kontakt speichern</button>
                </form>
            </section>
            <section class="panel"><h2>Zugeordnete Kontakte</h2><div class="contact-list"><?php foreach($contacts as $contact): ?><article class="<?= (int)$applicationEdit['primary_contact_id']===(int)$contact['id']?'is-primary':'' ?> <?= $contactEdit && (int)$contactEdit['id']===(int)$contact['id']?'is-selected':'' ?>"><small><?= e($contact['contact_company_name']) ?> · Firma<?php if((int)$contact['job_id']===(int)$applicationEdit['job_id']): ?> · Job<?php endif; ?><?php if((int)($contact['application_id'] ?? 0)===(int)$applicationEdit['id']): ?> · Bewerbung<?php endif; ?></small><strong><a class="record-link" href="/?page=applications&edit=<?= (int)$applicationEdit['id'] ?>&contact=<?= (int)$contact['id'] ?>#contact-log"><?= e($contact['first_name'].' '.$contact['last_name']) ?></a></strong><span><?= e($contact['position'] ?: $contact['department']) ?></span><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?><a class="button" href="/?page=applications&edit=<?= (int)$applicationEdit['id'] ?>&contact=<?= (int)$contact['id'] ?>#contact-log">Kontakt-Log</a></article><?php endforeach; ?><?php if(!$contacts): ?><p class="empty">Noch keine Kontakte für Arbeitgeber, Job oder Bewerbung.</p><?php endif; ?></div></section>
            <?php if($contactEdit): ?><section class="panel contact-log contact-log-inline" id="contact-log"><div class="section-head"><div><p class="eyebrow">Kontakt-Log</p><h2><?= e($contactEdit['first_name'].' '.$contactEdit['last_name']) ?></h2></div></div><?= contactLogFormHtml($editLog, (int)$applicationEdit['id'], (int)$contactEdit['id'], $contactLogChannels, $contactLogStatuses) ?><?= contactLogTimelineHtml($contactLogs, $contactAttachments, $contactLogChannels, $contactLogStatuses, $currentUser, (int)$applicationEdit['id']) ?></section><?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if($appView === 'table'): ?><section class="panel table-wrap"><table><thead><tr><?= sfHeader('applications','title','Job',$appSf,$appPreserve) ?><?= sfHeader('applications','company','Firma',$appSf,$appPreserve) ?><?= sfHeader('applications','status','Status',$appSf,$appPreserve) ?><?= sfHeader('applications','channel','Kanal',$appSf,$appPreserve) ?><?= sfHeader('applications','next_action','Nächster Schritt',$appSf,$appPreserve) ?><th>Aktionen</th></tr></thead><tbody><?php foreach($apps as $app): ?><tr class="<?= $applicationEdit && (int)$applicationEdit['id']===(int)$app['id']?'is-selected':'' ?>"><td><strong><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form"><?= e($app['title']) ?></a></strong><small><?= e($app['applied_at'] ? displayDateTime($app['applied_at'], $currentUser) : '') ?></small></td><td><a href="/?page=companies&edit=<?= (int)$app['company_id'] ?>"><?= e($app['company_name']) ?></a><?php if($app['intermediary_company_name']): ?><small>über <?= e($app['intermediary_company_name']) ?></small><?php endif; ?></td><td><?= e($applicationStatuses[$app['status']] ?? $app['status']) ?></td><td><?= e($app['channel']) ?></td><td><?= e($app['next_action']) ?><?php if($app['next_action_at']): ?><small><?= e(displayDateTime($app['next_action_at'], $currentUser)) ?></small><?php endif; ?></td><td class="actions"><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form">Bearbeiten</a></td></tr><?php endforeach; ?><?php if(!$apps): ?><tr><td colspan="6" class="empty">Keine Treffer.</td></tr><?php endif; ?></tbody></table></section><?php else: ?><section class="application-list"><?php foreach($apps as $app): ?><article class="application-card <?= $applicationEdit && (int)$applicationEdit['id']===(int)$app['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($applicationStatuses[$app['status']] ?? $app['status']) ?></span><?php if($app['next_action_at']): ?><span class="due"><?= e(displayDateTime($app['next_action_at'], $currentUser)) ?></span><?php endif; ?></div><h3><a href="/?page=jobs&edit=<?= (int)$app['job_id'] ?>#new"><?= e($app['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$app['company_id'] ?>"><?= e($app['company_name']) ?></a><?php if($app['intermediary_company_name']): ?> · über <a href="/?page=companies&edit=<?= (int)$app['intermediary_company_id'] ?>"><?= e($app['intermediary_company_name']) ?></a><?php endif; ?></p><?php if($app['next_action']): ?><p><strong>Nächster Schritt:</strong> <?= e($app['next_action']) ?></p><?php endif; ?><div class="actions"><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form">Bearbeiten</a><form method="post" onsubmit="return confirm('Bewerbung löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$app['id'] ?>"><button name="action" value="delete_application">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$apps): ?><div class="panel empty"><h2>Noch keine Bewerbungen</h2><p>Öffne Jobs und wähle bei einer passenden Stelle „Bewerbung starten“.</p><a class="button primary" href="/?page=jobs">Zu den Jobs</a></div><?php endif; ?></section><?php endif; ?>
    <?php elseif ($page === 'contacts'): ?>
        <?php
        $contactCompanyFilter=(int)($_GET['company_id'] ?? 0);
        $contactSfFields = ['name'=>['label'=>'Kontakt','expr'=>'CONCAT(ct.first_name, " ", ct.last_name)'], 'company'=>['label'=>'Firma','expr'=>'c.name'], 'reachable'=>['label'=>'Erreichbar','expr'=>'CONCAT_WS(" ", ct.email, ct.phone, ct.mobile)'], 'crm'=>['label'=>'CRM-Bezug','expr'=>'CONCAT_WS(" ", j.title, a.status)']];
        $contactSf = sfState('contacts', $contactSfFields, ['sort'=>'name','dir'=>'asc']);
        $contactPreserve = ['page'=>'contacts', 'company_id'=>$contactCompanyFilter ?: '', 'edit_contact'=>$_GET['edit_contact'] ?? ''];
        $contactCompany=$contactCompanyFilter ? dbOne($db,'SELECT id,name FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL','ii',[$contactCompanyFilter,userId()]) : null;
        $contactEditId=(int)($_GET['edit_contact'] ?? 0);
        $contactEdit=$contactEditId>0 ? dbOne($db,'SELECT id, company_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes FROM contacts WHERE id=? AND owner_user_id=? AND deleted_at IS NULL','ii',[$contactEditId,userId()]) : null;
        $contactLogStatuses=contactLogStatusOptions();
        $contactLogChannels=contactLogChannelOptions();
        $contactLogs=$contactEdit ? dbAll($db,'SELECT id, contact_id, application_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE owner_user_id=? AND contact_id=? ORDER BY CASE status WHEN "open" THEN 1 WHEN "planned" THEN 2 WHEN "done" THEN 3 ELSE 4 END, COALESCE(follow_up_at, occurred_at) ASC','ii',[userId(),(int)$contactEdit['id']]) : [];
        $contactAttachments=$contactEdit ? contactLogAttachments($db, userId(), (int)$contactEdit['id']) : [];
        $editLogId=(int)($_GET['edit_log'] ?? 0);
        $editLog=$editLogId > 0 ? dbOne($db,'SELECT id, contact_id, application_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE id=? AND owner_user_id=? AND contact_id=?','iii',[$editLogId,userId(),(int)($contactEdit['id'] ?? 0)]) : null;
        $contactSql='SELECT ct.*, c.name company_name, j.title job_title, a.status application_status, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=ct.id AND l.owner_user_id=ct.owner_user_id) log_count, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=ct.id AND l.owner_user_id=ct.owner_user_id AND l.status IN ("planned","open")) open_log_count FROM contacts ct JOIN companies c ON c.id=ct.company_id LEFT JOIN jobs j ON j.id=ct.job_id LEFT JOIN applications a ON a.id=ct.application_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL'; $contactTypes='i'; $contactVals=[userId()];
        if($contactCompanyFilter>0){ $contactSql.=' AND ct.company_id=?'; $contactTypes.='i'; $contactVals[]=$contactCompanyFilter; }
        $contactSql .= sfApplySql($contactSf, $contactSfFields, $contactTypes, $contactVals);
        $contactOrder = sfOrderSql($contactSf, $contactSfFields, 'name');
        $contactSql .= $contactOrder !== '' ? $contactOrder . ', ct.first_name' : ' ORDER BY ct.last_name, ct.first_name';
        $contactRows=dbAll($db,$contactSql,$contactTypes,$contactVals);
        ?>
        <div class="page-head"><div><p class="eyebrow">CRM</p><h1>Kontakte</h1></div><span><?= count($contactRows) ?> Einträge</span></div>
        <div class="actions export-actions"><?= sfToolbar('contacts', $contactSf, ['page'=>'contacts', 'company_id'=>$contactCompanyFilter ?: ''], $contactSfFields) ?><a class="button" href="/?page=export_pdf&type=contacts">PDF</a></div>
        <?php if($contactCompany): ?><p class="filter-note">Kontakte bei <a href="/?page=companies&edit=<?= (int)$contactCompany['id'] ?>"><?= e($contactCompany['name']) ?></a> · <a href="/?page=contacts">Alle Kontakte anzeigen</a></p><?php endif; ?>
        <div class="split"><?php if($contactEdit): ?><section class="panel"><h2>Kontakt bearbeiten</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="contact_id" value="<?= (int)$contactEdit['id'] ?>"><label>Firma<select name="contact_company_id"><?php foreach($companies as $company): ?><option value="<?= (int)$company['id'] ?>" <?= (int)$contactEdit['company_id']===(int)$company['id']?'selected':'' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select></label><div class="two"><label>Vorname<input name="first_name" value="<?= e($contactEdit['first_name']) ?>" required></label><label>Nachname<input name="last_name" value="<?= e($contactEdit['last_name']) ?>" required></label></div><div class="two"><label>Funktion<input name="position" value="<?= e($contactEdit['position']) ?>"></label><label>Abteilung<input name="department" value="<?= e($contactEdit['department']) ?>"></label></div><label>E-Mail<input type="email" name="contact_email" value="<?= e($contactEdit['email']) ?>"></label><div class="two"><label>Telefon<input name="phone" value="<?= e($contactEdit['phone']) ?>"></label><label>Mobil<input name="mobile" value="<?= e($contactEdit['mobile']) ?>"></label></div><label>LinkedIn<input type="url" name="linkedin_url" value="<?= e($contactEdit['linkedin_url']) ?>"></label><label>Sprache<select name="preferred_language"><option value="">Nicht gewählt</option><?php foreach(['de'=>'Deutsch','en'=>'English','es'=>'Español','pt'=>'Português'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($contactEdit['preferred_language']??'')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><label>Notizen<textarea name="contact_notes" rows="4"><?= e($contactEdit['notes']) ?></textarea></label><div class="actions"><button class="primary" name="action" value="update_contact_global">Kontakt speichern</button><a class="button" href="/?page=contacts">Schließen</a></div></form><hr><h2 id="contact-log">Kontakt-Log</h2><?= contactLogFormHtml($editLog, 0, (int)$contactEdit['id'], $contactLogChannels, $contactLogStatuses) ?><?= contactLogTimelineHtml($contactLogs, $contactAttachments, $contactLogChannels, $contactLogStatuses, $currentUser) ?></section><?php endif; ?><section class="panel table-wrap"><table><thead><tr><?= sfHeader('contacts','name','Kontakt',$contactSf,$contactPreserve) ?><?= sfHeader('contacts','company','Firma',$contactSf,$contactPreserve) ?><?= sfHeader('contacts','reachable','Erreichbar',$contactSf,$contactPreserve) ?><?= sfHeader('contacts','crm','CRM-Bezug',$contactSf,$contactPreserve) ?><th>Aktionen</th></tr></thead><tbody><?php foreach($contactRows as $contact): ?><tr class="<?= $contactEdit && (int)$contactEdit['id']===(int)$contact['id']?'is-selected':'' ?>"><td><strong><?= e($contact['first_name'].' '.$contact['last_name']) ?></strong><small><?= e($contact['position'] ?: $contact['department']) ?></small></td><td><a href="/?page=companies&edit=<?= (int)$contact['company_id'] ?>"><?= e($contact['company_name']) ?></a></td><td><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?><small><?= e($contact['phone'] ?: $contact['mobile']) ?></small></td><td class="link-list"><span><?= (int)$contact['open_log_count'] ?> offen/geplant · <?= (int)$contact['log_count'] ?> total</span><?php if($contact['job_id']): ?><a href="/?page=jobs&edit=<?= (int)$contact['job_id'] ?>#new"><?= e($contact['job_title'] ?: 'Job öffnen') ?></a><?php endif; ?><?php if($contact['application_id']): ?><a href="/?page=applications&edit=<?= (int)$contact['application_id'] ?>&contact=<?= (int)$contact['id'] ?>#contact-log">Bewerbung / Aktivitäten</a><?php endif; ?></td><td class="actions"><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>">Bearbeiten</a><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>#contact-log">Kontakt-Log</a></td></tr><?php endforeach; ?><?php if(!$contactRows): ?><tr><td colspan="5" class="empty">Noch keine Kontakte vorhanden.</td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'documents'): ?>
        <?php
        $documentTypes = dbAll($db, 'SELECT id, code, name_key FROM document_types ORDER BY id');
        $profileDocumentTypes = documentTypesForScope($documentTypes, 'profile');
        $docSfFields = [
            'title'=>['label'=>'Dokument','expr'=>'d.title'],
            'type'=>['label'=>'Typ','expr'=>'dt.code'],
            'version'=>['label'=>'Version','expr'=>'CAST(d.version AS CHAR)'],
            'created_at'=>['label'=>'Datum','expr'=>'d.created_at'],
        ];
        $docSf = sfState('documents', $docSfFields, ['sort'=>'created_at','dir'=>'desc']);
        $docPreserve = ['page'=>'documents'];
        $docSql="SELECT d.*, dt.code type_code, dt.name_key type_name FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope='profile' AND d.deleted_at IS NULL"; $docTypes='i'; $docVals=[userId()];
        $docSql .= sfApplySql($docSf, $docSfFields, $docTypes, $docVals);
        $docOrder = sfOrderSql($docSf, $docSfFields, 'created_at');
        $docSql .= $docOrder !== '' ? $docOrder . ', d.title' : ' ORDER BY d.created_at DESC, d.title';
        $documents = dbAll($db, $docSql, $docTypes, $docVals);
        $userLanguage = (string) ($currentUser['preferred_language'] ?? 'de');
        $editDocumentId = (int) ($_GET['edit_document'] ?? 0);
        $editDocument = $editDocumentId > 0 ? dbOne($db, "SELECT d.*, dt.code type_code FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.id=? AND d.user_id=? AND d.scope='profile' AND d.deleted_at IS NULL", 'ii', [$editDocumentId, userId()]) : null;
        ?>
        <div class="page-head"><div><p class="eyebrow">Stammdaten</p><h1>Dokumente</h1></div><span><?= count($documents) ?> Versionen</span></div>
        <div class="actions export-actions"><?= sfToolbar('documents', $docSf, $docPreserve, $docSfFields) ?><a class="button" href="/?page=export_pdf&type=documents">PDF</a></div>
        <div class="split"><section class="panel" id="document-editor"><h2><?= $editDocument ? 'Dokument bearbeiten' : 'Dokument hochladen' ?></h2><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_return" value="documents"><input type="hidden" name="document_scope" value="profile"><?php if($editDocument): ?><input type="hidden" name="document_id" value="<?= (int)$editDocument['id'] ?>"><?php else: ?><label>Neue Version von<select name="replace_document_id"><option value="0">Neues Dokument</option><?php foreach($documents as $doc): if(!(int)$doc['is_current']) continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label><?php endif; ?><label>Dokumenttyp<select name="document_type_id"><?php foreach($profileDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>" <?= (int)($editDocument['document_type_id'] ?? 0)===(int)$type['id']?'selected':'' ?>><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label><label>Titel<input name="document_title" required placeholder="z. B. Lebenslauf deutsch" value="<?= e($editDocument['title'] ?? '') ?>"></label><label>Sprache<select name="document_language"><option value="">Nicht gewählt</option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= $v ?>" <?= (string)($editDocument['language_code'] ?? $userLanguage)===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><div class="two"><label>Gültig ab<input type="date" name="valid_from" value="<?= e($editDocument['valid_from'] ?? '') ?>"></label><label>Gültig bis<input type="date" name="valid_until" value="<?= e($editDocument['valid_until'] ?? '') ?>"></label></div><label>Beschreibung<textarea name="document_description" rows="3"><?= e($editDocument['description'] ?? '') ?></textarea></label><?php if($editDocument): ?><div class="actions"><button class="primary" name="action" value="update_document">Änderungen speichern</button><a class="button" href="/?page=documents">Neu hochladen</a><a class="button" href="/?page=document_download&id=<?= (int)$editDocument['id'] ?>">Download</a></div><p class="meta-line">Datei ersetzen: „Neu hochladen“ wählen und das bestehende Dokument unter „Neue Version von“ auswählen.</p><?php else: ?><label>Datei<input type="file" name="user_document" required></label><button class="primary" name="action" value="upload_document">Speichern</button><?php endif; ?></form></section>
        <section class="panel table-wrap"><table><thead><tr><?= sfHeader('documents','title','Dokument',$docSf,$docPreserve) ?><?= sfHeader('documents','type','Typ',$docSf,$docPreserve) ?><?= sfHeader('documents','version','Version',$docSf,$docPreserve) ?><?= sfHeader('documents','created_at','Datum',$docSf,$docPreserve) ?><th>Aktionen</th></tr></thead><tbody><?php foreach($documents as $doc): ?><tr class="<?= ((int)$doc['is_current'] ? 'is-selected ' : '') . ($editDocument && (int)$editDocument['id']===(int)$doc['id'] ? 'is-selected' : '') ?>"><td><strong><a class="record-link" href="/?page=documents&edit_document=<?= (int)$doc['id'] ?>#document-editor"><?= e($doc['title']) ?></a></strong><small><?= e($doc['original_filename']) ?></small></td><td><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?><small><?= e($doc['language_code']) ?></small></td><td>v<?= (int)$doc['version'] ?><?= (int)$doc['is_current'] ? ' · aktuell' : '' ?></td><td><?= e(displayDateTime($doc['created_at'], $currentUser)) ?><small><?= number_format(((int)$doc['file_size']) / 1024, 1) ?> KB</small></td><td class="actions"><a href="/?page=documents&edit_document=<?= (int)$doc['id'] ?>#document-editor">Bearbeiten</a><a href="/?page=document_download&id=<?= (int)$doc['id'] ?>">Download</a><form method="post" onsubmit="return confirm('Dokument löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_return" value="documents"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$documents): ?><tr><td colspan="5" class="empty">Noch keine Dokumente vorhanden.</td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'help'): ?>
        <div class="page-head"><div><p class="eyebrow">Support</p><h1>Hilfe</h1></div><span>In Vorbereitung</span></div>
        <section class="panel empty"><h2>Hilfe</h2><p>Dieser Bereich ist vorbereitet und wird später mit Anleitungen, Abläufen und Support-Hinweisen gefüllt.</p></section>
    <?php elseif ($page === 'about'): ?>
        <div class="page-head"><div><p class="eyebrow">Über</p><h1>JeMa Jobs</h1></div><span><?= e($config['app_name'] ?? 'JeMa Jobs') ?></span></div>
        <section class="panel about-panel">
            <p class="version-number">Version <?= e($appVersion) ?></p>
            <h2>Privates Job-CRM</h2>
            <p>JeMa Jobs unterstützt die strukturierte Verwaltung von Firmen, Kontakten, Stellen, Bewerbungen, Pendenten, Dokumenten, Reports, Kalenderterminen und Freigaben. Die Daten bleiben benutzerisoliert; administrative Support-Zugriffe benötigen eine ausdrückliche Freigabe des jeweiligen Benutzers.</p>
            <div class="two">
                <article><h3>Ersteller</h3><p>Markus Lauber<br><a href="mailto:Markus@Lauber.online">Markus@Lauber.online</a></p></article>
                <article><h3>Stand</h3><p>Produktivbetrieb mit Benutzer-SMTP, 2FA, Passwort-Reset, Kontakt-Log, Kalender-Matrix, Reports und ADMIN Support.</p></article>
            </div>
        </section>
    <?php elseif ($page === 'audit'): ?>
        <?php
        $logs=dbAll($db,'SELECT id, action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 100','i',[userId()]);
        $auditSfFields = [
            'created_at'=>['label'=>'Zeit'],
            'action'=>['label'=>'Aktion'],
            'entity_type'=>['label'=>'Bereich'],
        ];
        $auditSf = sfState('audit', $auditSfFields, ['sort'=>'created_at','dir'=>'desc']);
        $auditPreserve = ['page'=>'audit'];
        $logs = sfApplyRows($logs, $auditSf, $auditSfFields);
        ?>
        <div class="page-head"><div><p class="eyebrow">Unveränderbar</p><h1>Änderungsprotokoll</h1></div><span>Letzte <?= count($logs) ?></span></div><section class="panel table-wrap"><div class="actions export-actions"><?= sfToolbar('audit', $auditSf, $auditPreserve, $auditSfFields) ?></div><table><thead><tr><?= sfHeader('audit','created_at','Zeit',$auditSf,$auditPreserve) ?><?= sfHeader('audit','action','Aktion',$auditSf,$auditPreserve) ?><?= sfHeader('audit','entity_type','Bereich',$auditSf,$auditPreserve) ?></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= e(displayDateTime($log['created_at'], $currentUser)) ?></td><td><?= e($log['action']) ?></td><td><?= e($log['entity_type']) ?></td></tr><?php endforeach; ?></tbody></table></section>
    <?php endif; ?>
<?php endif; ?>
</main>
<footer>JeMa Jobs · Produktivbetrieb · Private Daten bleiben benutzerisoliert</footer>
<script src="/assets/qrcode.min.js" defer></script>
<script src="/assets/totp-qr.js" defer></script>
<script>
(() => {
    const placeMenu = (menu) => {
        if (!menu.open) return;
        const button = menu.querySelector('.sf-button');
        const form = menu.querySelector('.sf-form');
        if (!button || !form) return;
        const rect = button.getBoundingClientRect();
        const width = Math.min(290, window.innerWidth - 32);
        const left = Math.max(16, Math.min(rect.right - width, window.innerWidth - width - 16));
        const top = Math.min(rect.bottom + 8, window.innerHeight - 80);
        form.style.setProperty('--sf-menu-left', `${left}px`);
        form.style.setProperty('--sf-menu-top', `${top}px`);
    };
    const closeOtherMenus = (current) => {
        document.querySelectorAll('.sf-menu[open]').forEach((menu) => {
            if (menu !== current) menu.removeAttribute('open');
        });
    };
    document.addEventListener('toggle', (event) => {
        const menu = event.target;
        if (!(menu instanceof HTMLElement) || !menu.classList.contains('sf-menu')) return;
        if (menu.open) {
            closeOtherMenus(menu);
            placeMenu(menu);
        }
    }, true);
    window.addEventListener('resize', () => document.querySelectorAll('.sf-menu[open]').forEach(placeMenu));
    window.addEventListener('scroll', () => document.querySelectorAll('.sf-menu[open]').forEach(placeMenu), true);
})();
</script>
</body></html>
