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
    if (!outboundEmailEnabled($config)) {
        logOutboundEmail($db, $ownerUserId, $to, $subject, $body, 'draft');
        return false;
    }
    try {
        sendSmtpMail($config, $to, $subject, $body);
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
    $lines = [$title, str_repeat('-', min(90, strlen($title) + 8)), implode(' | ', $headers)];
    foreach ($rows as $row) {
        $lines[] = implode(' | ', array_map(static fn($value): string => mb_strimwidth((string) $value, 0, 42, '...'), $row));
    }
    $objects = [];
    $pages = [];
    $chunks = array_chunk($lines, 44);
    $fontObjectNo = 3;
    foreach ($chunks as $chunk) {
        $content = "BT /F1 9 Tf 36 806 Td 12 TL\n";
        foreach ($chunk as $line) {
            $content .= '(' . pdfEscape($line) . ") Tj T*\n";
        }
        $content .= "ET";
        $contentNo = count($objects) + 4;
        $pageNo = $contentNo + 1;
        $objects[$contentNo] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        $objects[$pageNo] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObjectNo} 0 R >> >> /Contents {$contentNo} 0 R >>";
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

function sortDirection(): string
{
    return strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
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
        'Lohnvorstellung: ' . trim((string)($preference['salary_min'] ?? '') . ' - ' . (string)($preference['salary_max'] ?? '') . ' ' . (string)($preference['salary_currency'] ?? '') . ' / ' . (string)($preference['salary_period'] ?? '')),
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
                    sendConfiguredMail($db, $config, $newUserId, $email, $subject, $body);
                    $_SESSION['email_verify_notice'] = 'Registrierung gespeichert. Bitte bestätige deine E-Mail-Adresse über den Link in deinem Postfach.';
                } catch (Throwable) {
                    $_SESSION['email_verify_notice'] = 'Registrierung gespeichert, aber die Bestätigungs-E-Mail konnte nicht versendet werden. Bitte SMTP-Konfiguration prüfen.';
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
        $db->query('UPDATE users SET last_login_at = NOW() WHERE id = ' . (int) $user['id']);
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
        $db->query('UPDATE users SET last_login_at = NOW() WHERE id = ' . $pendingUserId);
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
            if (outboundEmailEnabled($config)) {
                try {
                    sendSmtpMail($config, $email, $subject, $body);
                    $_SESSION['password_reset_notice'] = 'Reset-Link wurde per E-Mail versendet.';
                    logOutboundEmail($db, (int) $user['id'], $email, $subject, $body, 'sent');
                } catch (Throwable $exception) {
                    $_SESSION['password_reset_notice'] = 'E-Mail konnte nicht versendet werden. Bitte SMTP-Konfiguration prüfen.';
                    logOutboundEmail($db, (int) $user['id'], $email, $subject, $body, 'failed', $exception->getMessage());
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
        session_destroy();
        redirect('/?page=login');
    }

    requireLogin();

    if ($action === 'admin_update_user') {
        if (!isAdmin($db, userId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === userId()) {
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
                $uid = userId();
                $stmt->bind_param('iii', $targetUserId, $roleId, $uid);
                $stmt->execute();
            } else {
                $stmt = $db->prepare('DELETE FROM user_roles WHERE user_id=? AND role_id=?');
                $stmt->bind_param('ii', $targetUserId, $roleId);
                $stmt->execute();
            }
        }
        audit($db, userId(), 'update', 'user', $targetUserId, $target, ['email' => $email, 'first_name' => $firstName, 'last_name' => $lastName, 'status' => $status, 'is_admin' => $isAdminTarget]);
        flash('Benutzer aktualisiert.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_reset_user_password') {
        if (!isAdmin($db, userId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === userId()) {
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
        audit($db, userId(), 'update', 'user', $targetUserId, ['admin_password_reset' => true], ['target_email' => $target['email']]);
        flash('Passwort wurde zurückgesetzt.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_delete_user') {
        if (!isAdmin($db, userId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === userId()) {
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
        audit($db, userId(), 'delete', 'user', $targetUserId, $target, ['soft_delete' => true]);
        flash('Benutzer wurde gelöscht.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_reset_user_2fa') {
        if (!isAdmin($db, userId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === userId()) {
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
        audit($db, userId(), 'delete', 'user', $targetUserId, ['two_factor_reset' => true], ['target_email' => $target['email']]);
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
            sendConfiguredMail($db, $config, $uid, $recipient, $subject, $body);
            flash('Freigabe erstellt und per E-Mail vorbereitet.');
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
        $baseEntity = in_array($_POST['base_entity'] ?? '', ['companies','jobs','contacts','contact_logs','applications','documents','calendar'], true) ? (string) $_POST['base_entity'] : 'jobs';
        $displayType = in_array($_POST['display_type'] ?? '', ['table','list','cards','preview','calendar_day','calendar_week','calendar_month'], true) ? (string) $_POST['display_type'] : 'table';
        if ($name === '') {
            flash('Report-Name ist erforderlich.', 'danger');
            redirect('/?page=reports');
        }
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO saved_reports (owner_user_id, name, base_entity, display_type, is_shared) VALUES (?, ?, ?, ?, 0)');
        $stmt->bind_param('isss', $uid, $name, $baseEntity, $displayType);
        $stmt->execute();
        audit($db, $uid, 'create', 'saved_report', (int) $stmt->insert_id, null, ['base_entity' => $baseEntity, 'display_type' => $displayType]);
        flash('Report gespeichert.');
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
        $salaryMax = $_POST['salary_max'] !== '' ? (float) $_POST['salary_max'] : null;
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
        $redirectTarget = $scope === 'application' && $applicationId ? '/?page=applications&edit=' . $applicationId . '#documents' : '/?page=profile#documents';
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
        $target = $old && ($old['scope'] ?? '') === 'application' && !empty($old['application_id'])
            ? '/?page=applications&edit=' . (int) $old['application_id'] . '#documents'
            : '/?page=profile#documents';
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
            $old = dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, source_url, original_pdf_status, original_pdf_requested_at, original_pdf_rendered_at, original_pdf_error, SUBSTRING(description,1,65535) description FROM jobs WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
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
            $stmt = $db->prepare('UPDATE jobs SET company_id=?, title=?, location_text=?, description=?, status=?, workplace_type=?, engagement_type=?, contract_term=?, fixed_term_start=?, fixed_term_end=?, source_url=?, original_pdf_status=?, original_pdf_requested_at=?, original_pdf_rendered_at=?, original_pdf_error=? WHERE id=? AND owner_user_id=?');
            $uid = userId();
            $stmt->bind_param('issssssssssssssii', $companyId, $title, $location, $description, $status, $workplace, $engagementType, $contractTerm, $fixedTermStart, $fixedTermEnd, $sourceUrl, $pdfStatus, $pdfRequestedAt, $pdfRenderedAt, $pdfError, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'job', $id, $old, ['title' => $title, 'status' => $status, 'original_pdf_status' => $pdfStatus]);
        } else {
            $pdfStatus = $sourceUrl !== '' ? 'pending' : 'none';
            $pdfRequestedAt = $sourceUrl !== '' ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare('INSERT INTO jobs (owner_user_id, company_id, title, location_text, description, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, source_url, original_pdf_status, original_pdf_requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('iissssssssssss', $uid, $companyId, $title, $location, $description, $status, $workplace, $engagementType, $contractTerm, $fixedTermStart, $fixedTermEnd, $sourceUrl, $pdfStatus, $pdfRequestedAt);
            $stmt->execute();
            $id = (int) $stmt->insert_id;
            audit($db, userId(), 'create', 'job', $id, null, ['title' => $title, 'status' => $status, 'original_pdf_status' => $pdfStatus]);
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
        if (!$job) { http_response_code(404); exit('Not found'); }
        $existing = dbOne($db, 'SELECT id FROM applications WHERE user_id=? AND job_id=? AND deleted_at IS NULL', 'ii', [userId(), $jobId]);
        if ($existing) {
            redirect('/?page=applications&edit=' . (int) $existing['id'] . '#application-form');
        }
        $stmt = $db->prepare("INSERT INTO applications (user_id, job_id, status, next_action) VALUES (?, ?, 'draft', 'Unterlagen vorbereiten')");
        $uid = userId();
        $stmt->bind_param('ii', $uid, $jobId);
        $stmt->execute();
        $applicationId = (int) $stmt->insert_id;
        $history = $db->prepare("INSERT INTO application_status_history (application_id, changed_by, old_status, new_status, comment) VALUES (?, ?, NULL, 'draft', 'Bewerbung angelegt')");
        $history->bind_param('ii', $applicationId, $uid);
        $history->execute();
        audit($db, $uid, 'create', 'application', $applicationId, null, ['job_id' => $jobId, 'status' => 'draft']);
        flash('Bewerbung angelegt. Ergänze jetzt Unterlagen und nächsten Schritt.');
        redirect('/?page=applications&edit=' . $applicationId . '#application-form');
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

    if ($action === 'save_contact_log') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $relation = dbOne($db, 'SELECT a.id application_id, a.job_id, c.company_id FROM applications a JOIN jobs j ON j.id=a.job_id JOIN contacts c ON c.id=? AND c.owner_user_id=a.user_id AND c.deleted_at IS NULL AND (c.company_id=j.company_id OR c.company_id=a.intermediary_company_id OR c.application_id=a.id OR c.job_id=a.job_id) WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'iii', [$contactId, $applicationId, userId()]);
        if (!$relation) { http_response_code(404); exit('Not found'); }
        $allowedChannels = ['email','phone','meeting','video','message','letter','note','other'];
        $allowedDirections = ['incoming','outgoing','internal'];
        $allowedLogStatuses = ['planned','open','done','cancelled'];
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
        $stmt = $db->prepare('INSERT INTO contact_logs (owner_user_id, contact_id, company_id, application_id, job_id, channel, direction, status, subject, body, occurred_at, follow_up_at, outcome) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $uid=userId(); $companyId=(int)$relation['company_id']; $jobId=(int)$relation['job_id']; $logApplicationId=(int)$relation['application_id'];
        $stmt->bind_param('iiiiissssssss', $uid, $contactId, $companyId, $logApplicationId, $jobId, $channel, $direction, $logStatus, $subject, $body, $occurredAt, $followUpAt, $outcome);
        $stmt->execute();
        $logId=(int)$stmt->insert_id;
        audit($db, $uid, 'create', 'contact_log', $logId, null, ['contact_id'=>$contactId,'application_id'=>$logApplicationId,'channel'=>$channel,'direction'=>$direction,'status'=>$logStatus,'subject'=>$subject]);
        flash('Kontaktaktivität gespeichert.');
        redirect('/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contact-log');
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
        $stmt = $db->prepare('UPDATE applications SET intermediary_company_id=NULLIF(?,0), primary_contact_id=NULLIF(?,0), status=?, channel=?, applied_at=?, next_action=?, next_action_at=?, email_subject=?, email_body=?, cover_letter_text=?, notes=? WHERE id=? AND user_id=?');
        $uid = userId();
        $stmt->bind_param('iisssssssssii', $intermediaryCompanyId, $primaryContactId, $status, $channel, $appliedAt, $nextAction, $nextActionAt, $emailSubject, $emailBody, $coverLetter, $notes, $id, $uid);
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
        audit($db, $uid, 'update', 'application', $id, ['status' => $old['status']], ['status' => $status, 'next_action' => $nextAction, 'next_action_at' => $nextActionAt]);
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
            flash('E-Mail konnte nicht versendet werden. Bitte SMTP-Konfiguration prüfen.', 'danger');
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
            flash('SMTP ist nicht aktiv. E-Mail wurde als Entwurf protokolliert.', 'warning');
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
$currentUserIsAdmin = $currentUser ? isAdmin($db, userId(), $config) : false;
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
        csvResponse('bewerbungen.csv', ['ID','Job','Firma','Status','Kanal','Gesendet','Nächster Schritt','Fällig'], array_map(static fn(array $r): array => [(int)$r['id'], $r['title'], $r['company'], $r['status'], $r['channel'], $r['applied_at'], $r['next_action'], $r['next_action_at']], $rows));
    }
    if ($type === 'audit') {
        $rows = dbAll($db, 'SELECT action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 1000', 'i', [userId()]);
        csvResponse('audit.csv', ['Aktion','Typ','ID','Zeit'], array_map(static fn(array $r): array => [$r['action'], $r['entity_type'], $r['entity_id'], $r['created_at']], $rows));
    }
    $rows = dbAll($db, 'SELECT j.id, j.title, c.name company, j.location_text, j.status, j.workplace_type, j.updated_at FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.updated_at DESC', 'i', [userId()]);
    csvResponse('jobs.csv', ['ID','Titel','Firma','Ort','Status','Arbeitsmodell','Aktualisiert'], array_map(static fn(array $r): array => [(int)$r['id'], $r['title'], $r['company'], $r['location_text'], $r['status'], $r['workplace_type'], $r['updated_at']], $rows));
}
if ($page === 'export_pdf') {
    requireLogin();
    $type = (string) ($_GET['type'] ?? 'jobs');
    if ($type === 'applications') {
        $rows = dbAll($db, 'SELECT a.id, j.title, c.name company, a.status, a.channel, a.applied_at, a.next_action FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC', 'i', [userId()]);
        pdfResponse('bewerbungen.pdf', 'Bewerbungen', ['ID','Job','Firma','Status','Kanal','Gesendet','Nächster Schritt'], array_map(static fn(array $r): array => [(int)$r['id'], $r['title'], $r['company'], $r['status'], $r['channel'], $r['applied_at'], $r['next_action']], $rows));
    }
    if ($type === 'companies') {
        $rows = dbAll($db, 'SELECT id, name, city, phone, website, updated_at FROM companies WHERE owner_user_id=? AND deleted_at IS NULL ORDER BY name', 'i', [userId()]);
        pdfResponse('firmen.pdf', 'Firmen', ['ID','Name','Ort','Telefon','Website','Aktualisiert'], array_map(static fn(array $r): array => [(int)$r['id'], $r['name'], $r['city'], $r['phone'], $r['website'], $r['updated_at']], $rows));
    }
    if ($type === 'contacts') {
        $rows = dbAll($db, 'SELECT ct.id, ct.first_name, ct.last_name, c.name company_name, ct.email, ct.phone FROM contacts ct JOIN companies c ON c.id=ct.company_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL ORDER BY c.name, ct.last_name', 'i', [userId()]);
        pdfResponse('kontakte.pdf', 'Kontakte', ['ID','Vorname','Nachname','Firma','E-Mail','Telefon'], array_map(static fn(array $r): array => [(int)$r['id'], $r['first_name'], $r['last_name'], $r['company_name'], $r['email'], $r['phone']], $rows));
    }
    if ($type === 'documents') {
        $rows = dbAll($db, 'SELECT d.id, d.title, dt.code type_code, d.version, d.original_filename, d.file_size, d.created_at FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.deleted_at IS NULL ORDER BY d.created_at DESC', 'i', [userId()]);
        pdfResponse('dokumente.pdf', 'Dokumente', ['ID','Titel','Typ','Version','Datei','Größe','Datum'], array_map(static fn(array $r): array => [(int)$r['id'], $r['title'], $r['type_code'], 'v'.$r['version'], $r['original_filename'], bytesLabel((int)$r['file_size']), $r['created_at']], $rows));
    }
    $rows = dbAll($db, 'SELECT j.id, j.title, c.name company, j.location_text, j.status, j.workplace_type, j.updated_at FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.updated_at DESC', 'i', [userId()]);
    pdfResponse('jobs.pdf', 'Jobs', ['ID','Titel','Firma','Ort','Status','Arbeitsmodell','Aktualisiert'], array_map(static fn(array $r): array => [(int)$r['id'], $r['title'], $r['company'], $r['location_text'], $r['status'], $r['workplace_type'], $r['updated_at']], $rows));
}
if ($currentUser && in_array($page, ['login', 'register', 'forgot_password', 'reset_password', 'two_factor'], true)) {
    redirect('/?page=dashboard');
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$companies = userId() ? dbAll($db, 'SELECT * FROM companies WHERE owner_user_id = ? AND deleted_at IS NULL ORDER BY name', 'i', [userId()]) : [];

?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['app_name']) ?></title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/app.css">
<link rel="stylesheet" href="/assets/applications.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/"><img src="/assets/favicon.svg" alt="" width="32" height="32"> <span>JeMa <strong>Jobs</strong></span></a>
    <?php if ($currentUser): ?>
        <button class="menu-button" type="button" onclick="document.body.classList.toggle('nav-open')">Menü</button>
        <nav>
            <a href="/?page=dashboard">Übersicht</a>
            <a href="/?page=jobs">Jobs</a>
            <a href="/?page=companies">Firmen</a>
            <a href="/?page=contacts">Kontakte</a>
            <a href="/?page=applications">Bewerbungen</a>
            <a href="/?page=applications&todo=1">Offene Schritte</a>
            <a href="/?page=calendar">Kalender</a>
            <a href="/?page=reports">Reports</a>
            <a href="/?page=sharing">Freigaben</a>
            <a href="/?page=translations">Übersetzungen</a>
            <a href="/?page=profile">Profil</a>
            <a href="/?page=privacy">Datenschutz</a>
            <?php if ($currentUserIsAdmin): ?><a href="/?page=admin_users">Benutzer</a><?php endif; ?>
            <a href="/?page=audit">Log</a>
        </nav>
        <form method="post" class="logout"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="logout">Abmelden</button></form>
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
            ['label' => 'Offene Schritte', 'value' => dbOne($db, 'SELECT COUNT(*) c FROM applications WHERE user_id=? AND next_action_at IS NOT NULL AND deleted_at IS NULL', 'i', [userId()])['c'], 'href' => '/?page=applications&todo=1'],
        ]; ?>
        <div class="hero"><div><p class="eyebrow">Guten Tag, <?= e($currentUser['first_name']) ?></p><h1>Deine Jobs. Dein Prozess.</h1><p>Privat, strukturiert und auf allen Geräten nutzbar.</p></div><a class="button primary" href="/?page=jobs#new">Job erfassen</a></div>
        <div class="stats"><?php foreach ($stats as $stat): ?><a class="stat-link" href="<?= e($stat['href']) ?>"><article><strong><?= e((string) $stat['value']) ?></strong><span><?= e($stat['label']) ?></span></article></a><?php endforeach; ?></div>
        <section class="panel"><h2>Nächste Schritte</h2><p>Erfasse zuerst eine Firma und danach passende Stellen. Bei „Job erfassen“ ist auch ein Schnellimport von einer oder mehreren Stellen gleichzeitig möglich; die Firma wird bei Bedarf automatisch erzeugt. Der Prototyp berechnet bereits einen transparenten Basis-Match und erkennt mögliche Dubletten.</p></section>
    <?php elseif ($page === 'sharing'): ?>
        <?php
        $shares = dbAll($db, 'SELECT * FROM guest_shares WHERE owner_user_id=? ORDER BY created_at DESC', 'i', [userId()]);
        $shareTargets = [
            'area' => 'Ganzer Bereich',
            'job' => 'Ein Job',
            'application' => 'Eine Bewerbung',
            'document' => 'Ein Dokument',
        ];
        ?>
        <div class="page-head"><div><p class="eyebrow">Zusammenarbeit</p><h1>Freigaben</h1></div><span><?= count($shares) ?> Links</span></div>
        <?php if(!empty($_SESSION['last_share_link'])): ?><div class="alert warning"><strong>Freigabelink:</strong> <a href="<?= e($_SESSION['last_share_link']) ?>"><?= e($_SESSION['last_share_link']) ?></a><input value="<?= e($_SESSION['last_share_link']) ?>" readonly onclick="this.select()"></div><?php unset($_SESSION['last_share_link']); endif; ?>
        <div class="split"><section class="panel"><h2>Neue Freigabe</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label>Titel<input name="title" placeholder="z. B. Bewerbung Review"></label>
            <label>Empfänger-E-Mail<input type="email" name="recipient_email" required></label>
            <div class="two"><label>Ziel<select name="target_type"><?php foreach($shareTargets as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>Ziel-ID<input type="number" min="1" name="target_id" placeholder="leer bei ganzem Bereich"></label></div>
            <div class="two"><label>Recht<select name="permission"><option value="view">Nur ansehen</option><option value="comment">Kommentieren</option><option value="edit">Bearbeiten vorbereitet</option></select></label><label>Download<select name="download_policy"><option value="none">Kein Download</option><option value="original">Original</option><option value="pdf">PDF vorbereitet</option><option value="both">Original und PDF</option></select></label></div>
            <label>Ablauf<input type="datetime-local" name="expires_at"></label>
            <label class="check"><input type="checkbox" name="watermark_enabled" value="1" checked> Wasserzeichen / persönliche Nachverfolgung</label>
            <button class="primary" name="action" value="create_share">Freigabe erstellen</button>
        </form></section>
        <section class="panel table-wrap"><h2>Aktive und frühere Links</h2><table><thead><tr><th>Titel</th><th>Ziel</th><th>Empfänger</th><th>Status</th><th>Aktionen</th></tr></thead><tbody><?php foreach($shares as $share): ?><tr><td><strong><?= e($share['title']) ?></strong><small><?= e($share['permission']) ?> · Download <?= e($share['download_policy']) ?></small></td><td><?= e($share['target_type']) ?> #<?= e((string)$share['target_id']) ?></td><td><?= e($share['recipient_email']) ?></td><td><?php if($share['revoked_at']): ?>widerrufen<?php elseif($share['expires_at'] && strtotime((string)$share['expires_at']) < time()): ?>abgelaufen<?php else: ?>aktiv<?php endif; ?><small><?= e($share['last_accessed_at'] ? 'letzter Zugriff '.displayDateTime($share['last_accessed_at'], $currentUser) : 'noch kein Zugriff') ?></small></td><td><?php if(!$share['revoked_at']): ?><form method="post" onsubmit="return confirm('Freigabe widerrufen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="share_id" value="<?= (int)$share['id'] ?>"><button name="action" value="revoke_share">Widerrufen</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$shares): ?><tr><td colspan="5" class="empty">Noch keine Freigaben.</td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'reports'): ?>
        <?php $reports = dbAll($db, 'SELECT * FROM saved_reports WHERE owner_user_id=? ORDER BY updated_at DESC', 'i', [userId()]); ?>
        <div class="page-head"><div><p class="eyebrow">Auswertung</p><h1>Reports & Exporte</h1></div><span><?= count($reports) ?> Reports</span></div>
        <div class="split"><section class="panel"><h2>Report speichern</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label>Name<input name="report_name" required></label><label>Basis<select name="base_entity"><?php foreach(['jobs'=>'Jobs','applications'=>'Bewerbungen','companies'=>'Firmen','contacts'=>'Kontakte','documents'=>'Dokumente','calendar'=>'Kalender'] as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></label><label>Ansicht<select name="display_type"><option value="table">Tabelle</option><option value="list">Liste</option><option value="cards">Karten</option><option value="preview">Vorschau</option><option value="calendar_week">Kalenderwoche</option><option value="calendar_month">Kalendermonat</option></select></label><button class="primary" name="action" value="save_report">Speichern</button></form><div class="actions export-actions"><a class="button" href="/?page=export_csv&type=jobs">Jobs CSV</a><a class="button" href="/?page=export_pdf&type=jobs">Jobs PDF</a><a class="button" href="/?page=export_csv&type=applications">Bewerbungen CSV</a><a class="button" href="/?page=export_pdf&type=applications">Bewerbungen PDF</a><a class="button" href="/?page=export_csv&type=audit">Audit CSV</a></div></section><section class="panel table-wrap"><h2>Gespeicherte Reports</h2><table><thead><tr><th>Name</th><th>Basis</th><th>Ansicht</th><th>Aktualisiert</th></tr></thead><tbody><?php foreach($reports as $report): ?><tr><td><strong><?= e($report['name']) ?></strong><small><?= e($report['description']) ?></small></td><td><?= e($report['base_entity']) ?></td><td><?= e($report['display_type']) ?></td><td><?= e(displayDateTime($report['updated_at'], $currentUser)) ?></td></tr><?php endforeach; ?><?php if(!$reports): ?><tr><td colspan="4" class="empty">Noch keine Reports gespeichert.</td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'calendar'): ?>
        <?php
        $events = dbAll($db, 'SELECT ce.*, a.status application_status, j.title job_title FROM calendar_events ce LEFT JOIN applications a ON a.id=ce.application_id LEFT JOIN jobs j ON j.id=a.job_id WHERE ce.owner_user_id=? ORDER BY ce.starts_at ASC LIMIT 100', 'i', [userId()]);
        $followUps = dbAll($db, 'SELECT a.id, a.next_action, a.next_action_at, j.title job_title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL AND a.next_action_at IS NOT NULL ORDER BY a.next_action_at ASC LIMIT 50', 'i', [userId()]);
        $appsForCalendar = dbAll($db, 'SELECT a.id, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC LIMIT 100', 'i', [userId()]);
        ?>
        <div class="page-head"><div><p class="eyebrow">Zeitplan</p><h1>Kalender & Erinnerungen</h1></div><span><?= count($events) + count($followUps) ?> Einträge</span></div>
        <div class="split"><section class="panel"><h2>Eintrag erstellen</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label>Titel<input name="event_title" required></label><label>Typ<select name="event_type"><option value="reminder">Erinnerung</option><option value="follow_up">Nachfassen</option><option value="interview">Interview</option><option value="deadline">Frist</option><option value="meeting">Termin</option><option value="task">Aufgabe</option></select></label><label>Start<input type="datetime-local" name="starts_at" required></label><label>Bewerbung<select name="application_id"><option value="0">Keine Verknüpfung</option><?php foreach($appsForCalendar as $app): ?><option value="<?= (int)$app['id'] ?>"><?= e($app['title'].' · '.$app['company_name']) ?></option><?php endforeach; ?></select></label><label>Notizen<textarea name="event_notes" rows="3"></textarea></label><button class="primary" name="action" value="save_calendar_event">Speichern</button></form></section><section class="panel"><h2>Agenda</h2><div class="log-timeline"><?php foreach($events as $event): ?><article><div><strong><?= e($event['title']) ?></strong><span><?= e(displayDateTime($event['starts_at'], $currentUser)) ?> · <?= e($event['event_type']) ?></span></div><?php if($event['job_title']): ?><small><?= e($event['job_title']) ?></small><?php endif; ?><?php if($event['notes']): ?><p><?= nl2br(e($event['notes'])) ?></p><?php endif; ?></article><?php endforeach; ?><?php foreach($followUps as $todo): ?><article><div><strong><?= e($todo['next_action']) ?></strong><span><?= e(displayDateTime($todo['next_action_at'], $currentUser)) ?></span></div><small><?= e($todo['job_title'].' · '.$todo['company_name']) ?></small></article><?php endforeach; ?><?php if(!$events && !$followUps): ?><p class="empty">Keine Termine oder Wiedervorlagen.</p><?php endif; ?></div></section></div>
    <?php elseif ($page === 'translations'): ?>
        <?php $translations = dbAll($db, 'SELECT id, entity_type, entity_id, target_language, title, SUBSTRING(body,1,65535) body, version, is_current, updated_at FROM record_translations WHERE owner_user_id=? ORDER BY updated_at DESC LIMIT 100', 'i', [userId()]); ?>
        <div class="page-head"><div><p class="eyebrow">Sprache</p><h1>Übersetzungen</h1></div><span><?= count($translations) ?> Versionen</span></div>
        <div class="split"><section class="panel"><h2>Übersetzung speichern</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><div class="two"><label>Typ<select name="entity_type"><option value="job">Job</option><option value="company">Firma</option><option value="application">Bewerbung</option><option value="contact">Kontakt</option><option value="document">Dokument</option></select></label><label>ID<input type="number" min="1" name="entity_id" required></label></div><label>Zielsprache<select name="target_language"><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label><label>Titel<input name="translation_title"></label><label>Übersetzung<textarea name="translation_body" rows="8" required></textarea></label><button class="primary" name="action" value="save_translation">Speichern</button></form></section><section class="panel"><h2>Gespeicherte Übersetzungen</h2><div class="log-timeline"><?php foreach($translations as $translation): ?><article><div><strong><?= e($translation['title'] ?: $translation['entity_type'].' #'.$translation['entity_id']) ?></strong><span><?= e($translation['target_language']) ?> · v<?= (int)$translation['version'] ?><?= $translation['is_current'] ? ' · aktuell' : '' ?></span></div><p><?= nl2br(e(mb_strimwidth((string)$translation['body'],0,500,'...'))) ?></p></article><?php endforeach; ?><?php if(!$translations): ?><p class="empty">Noch keine Übersetzungen gespeichert.</p><?php endif; ?></div></section></div>
    <?php elseif ($page === 'privacy'): ?>
        <?php
        $usage = storageUsageBytes($db, userId());
        $quota = dbOne($db, 'SELECT quota_bytes FROM storage_quotas WHERE user_id=?', 'i', [userId()]);
        $quotaBytes = (int) ($quota['quota_bytes'] ?? 5368709120);
        $cleanupRequests = dbAll($db, 'SELECT id, cutoff_date, status, SUBSTRING(preview_json,1,65535) preview_json, created_at FROM cleanup_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 20', 'i', [userId()]);
        ?>
        <div class="page-head"><div><p class="eyebrow">Datenschutz</p><h1>Speicher, Exporte, Cleanup</h1></div><span><?= e(bytesLabel($usage)) ?> / <?= e(bytesLabel($quotaBytes)) ?></span></div>
        <div class="split"><section class="panel"><h2>Speicherquote</h2><p><?= e(number_format($quotaBytes > 0 ? ($usage / $quotaBytes) * 100 : 0, 1)) ?>% genutzt.</p><progress max="<?= (int)$quotaBytes ?>" value="<?= (int)$usage ?>" style="width:100%"></progress><div class="actions"><a class="button" href="/?page=export_csv&type=audit">Audit exportieren</a><a class="button" href="/?page=export_csv&type=applications">Bewerbungen exportieren</a></div></section><section class="panel"><h2>Cleanup anfragen</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label>Daten älter als<input type="date" name="cutoff_date" value="<?= e((new DateTimeImmutable('-6 months'))->format('Y-m-d')) ?>" required></label><button class="primary" name="action" value="request_cleanup">Vorschau erstellen und anfragen</button></form></section></div>
        <section class="panel table-wrap"><h2>Cleanup-Anfragen</h2><table><thead><tr><th>Stichtag</th><th>Status</th><th>Vorschau</th><th>Erstellt</th></tr></thead><tbody><?php foreach($cleanupRequests as $request): $preview=json_decode((string)$request['preview_json'], true) ?: []; ?><tr><td><?= e($request['cutoff_date']) ?></td><td><?= e($request['status']) ?></td><td><small>Jobs: <?= (int)($preview['jobs'] ?? 0) ?> · Bewerbungen: <?= (int)($preview['applications'] ?? 0) ?> · alte Dokumentversionen: <?= (int)($preview['old_document_versions'] ?? 0) ?> · <?= e(bytesLabel((int)($preview['document_bytes'] ?? 0))) ?></small></td><td><?= e(displayDateTime($request['created_at'], $currentUser)) ?></td></tr><?php endforeach; ?><?php if(!$cleanupRequests): ?><tr><td colspan="4" class="empty">Noch keine Cleanup-Anfragen.</td></tr><?php endif; ?></tbody></table></section>
    <?php elseif ($page === 'admin_users'): ?>
        <?php
        if (!$currentUserIsAdmin) {
            http_response_code(403);
            exit('Forbidden');
        }
        $adminEmails = array_map('strtolower', (array) ($config['admin_emails'] ?? ['admin@jema.business']));
        $users = dbAll(
            $db,
            "SELECT u.id, u.email, u.status, u.first_name, u.last_name, u.created_at, u.last_login_at, u.email_verified_at,
                    (SELECT GROUP_CONCAT(r.code ORDER BY r.code)
                       FROM user_roles ur
                       JOIN roles r ON r.id=ur.role_id
                      WHERE ur.user_id=u.id) role_codes,
                    (SELECT COUNT(*) FROM jobs j WHERE j.owner_user_id=u.id AND j.deleted_at IS NULL) job_count,
                    (SELECT COUNT(*) FROM applications a WHERE a.user_id=u.id AND a.deleted_at IS NULL) application_count,
                    (SELECT COUNT(*) FROM user_documents d WHERE d.user_id=u.id AND d.deleted_at IS NULL) document_count,
                    (SELECT COUNT(*) FROM two_factor_methods tf WHERE tf.user_id=u.id AND tf.verified_at IS NOT NULL) two_factor_count
               FROM users u
              WHERE u.deleted_at IS NULL
           ORDER BY FIELD(u.status, 'active', 'invited', 'locked', 'disabled'), u.created_at DESC"
        );
        $managedUserId = (int) ($_GET['manage_user'] ?? 0);
        $managedUser = null;
        foreach ($users as $candidate) {
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
            <?php else: $managedRoleCodes = array_filter(explode(',', (string) ($managedUser['role_codes'] ?? ''))); $managedIsConfigAdmin = in_array(strtolower((string) $managedUser['email']), $adminEmails, true); $managedIsAdmin = $managedIsConfigAdmin || in_array('admin', $managedRoleCodes, true); $managedIsSelf = (int) $managedUser['id'] === userId(); ?>
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
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="panel table-wrap"><table><thead><tr><th>Benutzer</th><th>Status</th><th>Nutzung</th><th>Zugriff</th><th>Aktionen</th></tr></thead><tbody>
            <?php foreach($users as $user): $roleCodes = array_filter(explode(',', (string) ($user['role_codes'] ?? ''))); $isConfigAdmin = in_array(strtolower((string) $user['email']), $adminEmails, true); $isUserAdmin = $isConfigAdmin || in_array('admin', $roleCodes, true); $isSelf = (int) $user['id'] === userId(); ?>
                <tr class="<?= $isSelf ? 'is-selected' : '' ?>">
                    <td><strong><?= e(trim((string)$user['first_name'].' '.(string)$user['last_name'])) ?></strong><small><?= e($user['email']) ?></small><small>Registriert: <?= e(displayDateTime($user['created_at'], $currentUser)) ?></small><small><a href="/?page=admin_users&manage_user=<?= (int)$user['id'] ?>">Verwalten</a></small></td>
                    <td><span class="badge"><?= e($user['status']) ?></span><?php if($user['email_verified_at']): ?><small>verifiziert: <?= e(displayDateTime($user['email_verified_at'], $currentUser)) ?></small><?php else: ?><small>nicht verifiziert</small><?php endif; ?><?php if((int)$user['two_factor_count'] > 0): ?><small>2FA aktiv</small><?php else: ?><small>2FA nicht aktiv</small><?php endif; ?><?php if($user['last_login_at']): ?><small>letzter Login: <?= e(displayDateTime($user['last_login_at'], $currentUser)) ?></small><?php endif; ?></td>
                    <td><small><?= (int)$user['job_count'] ?> Jobs</small><small><?= (int)$user['application_count'] ?> Bewerbungen</small><small><?= (int)$user['document_count'] ?> Dokumente</small></td>
                    <td><small><?= $isUserAdmin ? 'Admin' : 'Benutzer' ?></small><?php if($isConfigAdmin): ?><small>Config-Admin</small><?php endif; ?></td>
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
        <section class="panel"><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <div class="two"><label>Vorname<input name="first_name" value="<?= e($currentUser['first_name']) ?>" required></label><label>Nachname<input name="last_name" value="<?= e($currentUser['last_name']) ?>" required></label></div>
            <label>E-Mail<input value="<?= e($currentUser['email']) ?>" disabled><small>Prototyp-Regel: Es werden keine E-Mails verschickt. Änderungen der Login-E-Mail bleiben bis zur Versandfreigabe deaktiviert.</small></label>
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
            <div class="salary-row"><label>Lohn min. <span class="salary-currency-display"><?= e($profileCurrency) ?></span><input type="number" min="0" step="0.01" name="salary_min" value="<?= e((string)($preference['salary_min'] ?? '')) ?>"></label><label>Lohn max. <span class="salary-currency-display"><?= e($profileCurrency) ?></span><input type="number" min="0" step="0.01" name="salary_max" value="<?= e((string)($preference['salary_max'] ?? '')) ?>"></label><label>Format<select name="salary_period"><?php foreach(['hour'=>'pro Stunde','month'=>'pro Monat','year'=>'pro Jahr'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($preference['salary_period'] ?? 'year')===$v?'selected':'' ?>><?= $l ?> · <?= e($profileCurrency) ?></option><?php endforeach; ?></select></label></div>
            <label>Verfügbar ab<input type="date" name="available_from" value="<?= e($preference['available_from'] ?? '') ?>"></label>
            <label>PK / Extras / Benefits<textarea name="desired_benefits" rows="2" placeholder="z. B. gute PK, ÖV-Beitrag, Schichtzulagen, Weiterbildung"><?= e($preference['desired_benefits'] ?? '') ?></textarea></label>
            <label>Ausschlüsse<textarea name="excluded_industries" rows="2" placeholder="Branchen, Tätigkeiten oder Bedingungen, die nicht passen"><?= e($preference['excluded_industries'] ?? '') ?></textarea></label>
            <div class="two"><label class="check"><input type="checkbox" name="willing_to_relocate" value="1" <?= !empty($preference['willing_to_relocate'])?'checked':'' ?>> Umzug möglich</label><label>Reiseanteil max. %<input type="number" min="0" max="100" name="travel_percentage" value="<?= e((string)($preference['travel_percentage'] ?? '')) ?>"></label></div>
            <label>Notizen zu Job-Referenzen<textarea name="preference_notes" rows="3"><?= e($preference['notes'] ?? '') ?></textarea></label>
            <button class="primary" name="action" value="save_profile">Profil speichern</button>
        </form></section>
        <section class="panel" id="documents"><div class="section-head"><div><p class="eyebrow">Stammdaten</p><h2>Dokumenten-Management</h2></div><span><?= count($profileDocuments) ?> Versionen</span></div><div class="split inner-split"><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_scope" value="profile"><label>Neue Version von<select name="replace_document_id"><option value="0">Neues Stammdaten-Dokument</option><?php foreach($profileDocuments as $doc): if(!(int)$doc['is_current']) continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label><label>Dokumenttyp<select name="document_type_id"><?php foreach($profileDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label><label>Titel<input name="document_title" required placeholder="z. B. Lebenslauf Deutsch"></label><label>Sprache<select name="document_language"><option value="">Nicht gewählt</option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= $v ?>" <?= $v===$userLanguage?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><div class="two"><label>Gültig ab<input type="date" name="valid_from"></label><label>Gültig bis<input type="date" name="valid_until"></label></div><label>Beschreibung<textarea name="document_description" rows="3"></textarea></label><label>Datei<input type="file" name="user_document" required></label><button class="primary" name="action" value="upload_document">Stammdaten-Dokument speichern</button></form><div class="table-wrap"><table><thead><tr><th>Dokument</th><th>Typ</th><th>Version</th><th>Aktionen</th></tr></thead><tbody><?php foreach($profileDocuments as $doc): ?><tr class="<?= (int)$doc['is_current'] ? 'is-selected' : '' ?>"><td><strong><a class="record-link" href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?></a></strong><small><?= e($doc['original_filename']) ?></small></td><td><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?><small><?= e($doc['language_code']) ?></small></td><td>v<?= (int)$doc['version'] ?><?= (int)$doc['is_current'] ? ' · aktuell' : '' ?></td><td class="actions"><a href="/?page=document_download&id=<?= (int)$doc['id'] ?>">Download</a><form method="post" onsubmit="return confirm('Dokument löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$profileDocuments): ?><tr><td colspan="4" class="empty">Noch keine Stammdaten-Dokumente vorhanden.</td></tr><?php endif; ?></tbody></table></div></div></section>
        <script>
        (() => {
            const region = document.getElementById('profile-region');
            const country = document.getElementById('profile-country-display');
            const currencies = document.querySelectorAll('.salary-currency-display');
            if (!region || !country) return;
            const syncCountry = () => {
                const selected = region.options[region.selectedIndex];
                country.textContent = selected ? (selected.dataset.countryName || '') : '';
                currencies.forEach((item) => { item.textContent = selected ? (selected.dataset.currency || 'EUR') : 'CHF'; });
            };
            region.addEventListener('change', syncCountry);
            syncCountry();
        })();
        </script>
    <?php elseif ($page === 'companies'): ?>
        <?php
        $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT * FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
        $companyQ=trim((string)($_GET['q'] ?? '')); $companySort=(string)($_GET['sort'] ?? 'name'); $companyDir=sortDirection();
        $companySql='SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id AND j.owner_user_id=c.owner_user_id AND j.deleted_at IS NULL) job_count, (SELECT COUNT(*) FROM contacts ct WHERE ct.company_id=c.id AND ct.owner_user_id=c.owner_user_id AND ct.deleted_at IS NULL) contact_count, (SELECT COUNT(*) FROM applications a JOIN jobs j2 ON j2.id=a.job_id WHERE a.user_id=c.owner_user_id AND a.deleted_at IS NULL AND (j2.company_id=c.id OR a.intermediary_company_id=c.id)) application_count, (SELECT GROUP_CONCAT(DISTINCT CONCAT(client.id, "::", client.name) ORDER BY client.name SEPARATOR "||") FROM company_relationships cr JOIN companies client ON client.id=cr.client_company_id WHERE cr.owner_user_id=c.owner_user_id AND cr.intermediary_company_id=c.id AND cr.deleted_at IS NULL AND client.deleted_at IS NULL) mediated_clients, (SELECT GROUP_CONCAT(DISTINCT CONCAT(intermediary.id, "::", intermediary.name) ORDER BY intermediary.name SEPARATOR "||") FROM company_relationships cr JOIN companies intermediary ON intermediary.id=cr.intermediary_company_id WHERE cr.owner_user_id=c.owner_user_id AND cr.client_company_id=c.id AND cr.deleted_at IS NULL AND intermediary.deleted_at IS NULL) mediated_by FROM companies c WHERE c.owner_user_id=? AND c.deleted_at IS NULL'; $companyTypes='i'; $companyVals=[userId()];
        if($companyQ !== ''){ $companySql.=' AND (c.name LIKE ? OR c.city LIKE ? OR c.website LIKE ?)'; $like="%$companyQ%"; $companyTypes.='sss'; array_push($companyVals,$like,$like,$like); }
        $companySortMap=['name'=>'c.name','city'=>'c.city','updated_at'=>'c.updated_at']; $companySql.=' ORDER BY '.($companySortMap[$companySort] ?? 'c.name').' '.strtoupper($companyDir);
        $companyRows = dbAll($db, $companySql, $companyTypes, $companyVals);
        ?>
        <div class="page-head"><div><p class="eyebrow">CRM</p><h1>Firmen</h1></div><span><?= count($companies) ?> Einträge</span></div>
        <form class="filters" method="get"><input type="hidden" name="page" value="companies"><input name="q" value="<?= e($companyQ) ?>" placeholder="Firma, Ort oder Website"><select name="sort"><option value="name" <?= $companySort==='name'?'selected':'' ?>>Sort: Name</option><option value="city" <?= $companySort==='city'?'selected':'' ?>>Sort: Ort</option><option value="updated_at" <?= $companySort==='updated_at'?'selected':'' ?>>Sort: Aktualisiert</option></select><select name="dir"><option value="asc" <?= $companyDir==='asc'?'selected':'' ?>>Aufsteigend</option><option value="desc" <?= $companyDir==='desc'?'selected':'' ?>>Absteigend</option></select><button>Filtern</button><a class="button" href="/?page=export_pdf&type=companies">PDF</a></form>
        <div class="split"><section class="panel"><h2><?= $edit ? 'Firma bearbeiten' : 'Neue Firma' ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><label>Name<input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label><label class="check"><input type="checkbox" name="is_intermediary" value="1" <?= !empty($edit['is_intermediary'])?'checked':'' ?>> Möglicher Vermittler / Personalvermittler</label><label>Haupttelefon<input name="company_phone" value="<?= e($edit['phone'] ?? '') ?>"></label><label>Adresse<textarea name="address" rows="3" placeholder="Strasse und Nummer&#10;Adresszusatz"><?= e(trim((string)($edit['address_line1'] ?? '') . "\n" . (string)($edit['address_line2'] ?? ''))) ?></textarea></label><div class="two"><label>PLZ<input name="postal_code" value="<?= e($edit['postal_code'] ?? '') ?>"></label><label>Ort<input name="city" value="<?= e($edit['city'] ?? '') ?>"></label></div><div class="two"><label>Region<select name="company_region_key" id="company-region"><option value="">Nicht gewählt</option><?php foreach(regionChoices() as $countryCode=>$regions): ?><optgroup label="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>"><?php foreach($regions as $region): $selectedRegion = ($edit['region'] ?? '')===$region && ($edit['country_code'] ?? '')===$countryCode; ?><option value="<?= e($countryCode . '|' . $region) ?>" data-country="<?= e($countryCode) ?>" data-country-name="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>" <?= $selectedRegion?'selected':'' ?>><?= e($region) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><label>Land<output id="company-country-display" class="readonly-value"><?= e(countryChoices()[$edit['country_code'] ?? ''] ?? 'Ergibt sich aus Region') ?></output></label></div><label>Website<input type="url" name="website" value="<?= e($edit['website'] ?? '') ?>"></label><button class="primary" name="action" value="save_company">Speichern</button></form></section>
        <section class="panel table-wrap"><table><thead><tr><th>Firma</th><th>Adresse / Telefon</th><th>Rolle / Vermittlung</th><th>Verknüpfungen</th><th>Aktionen</th></tr></thead><tbody><?php foreach($companyRows as $company): ?><tr class="<?= $edit && (int)$edit['id']===(int)$company['id']?'is-selected':'' ?>"><td><strong><a class="record-link" href="/?page=companies&edit=<?= (int)$company['id'] ?>"><?= e($company['name']) ?></a></strong><small><?= e($company['website']) ?></small></td><td><?php if($company['address_line1']): ?><small><?= nl2br(e(trim((string)$company['address_line1'] . "\n" . (string)$company['address_line2']))) ?></small><?php endif; ?><?php if($company['city']): ?><small><?= e($company['city']) ?></small><?php endif; ?><?php if($company['phone']): ?><small><?= e($company['phone']) ?></small><?php endif; ?></td><td class="relationship-cell"><?php if(!empty($company['is_intermediary']) || $company['mediated_clients']): ?><span class="badge role-badge">Vermittler</span><?php endif; ?><?php if($company['mediated_clients']): ?><small>Vermittelt: <?php foreach(explode('||', $company['mediated_clients']) as $entry): [$id,$name]=array_pad(explode('::',$entry,2),2,''); ?><a href="/?page=companies&edit=<?= (int)$id ?>"><?= e($name) ?></a><?php endforeach; ?></small><?php endif; ?><?php if($company['mediated_by']): ?><span class="badge">Vermittelt</span><small>durch: <?php foreach(explode('||', $company['mediated_by']) as $entry): [$id,$name]=array_pad(explode('::',$entry,2),2,''); ?><a href="/?page=companies&edit=<?= (int)$id ?>"><?= e($name) ?></a><?php endforeach; ?></small><?php endif; ?><?php if(empty($company['is_intermediary']) && !$company['mediated_clients'] && !$company['mediated_by']): ?><small>Direkte Firma / keine Vermittlung erfasst</small><?php endif; ?></td><td class="link-list"><a href="/?page=jobs&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['job_count'] ?> Jobs</a><a href="/?page=applications&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['application_count'] ?> Bewerbungen</a><a href="/?page=contacts&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['contact_count'] ?> Kontakte</a></td><td class="actions"><form method="post" onsubmit="return confirm('Firma löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$company['id'] ?>"><button name="action" value="delete_company">Löschen</button></form></td></tr><?php endforeach; ?></tbody></table></section></div>
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
        $q = trim((string)($_GET['q'] ?? '')); $status = (string)($_GET['status'] ?? ''); $blue = !empty($_GET['blue']); $companyFilter = (int)($_GET['company_id'] ?? 0); $jobView = ($_GET['view'] ?? 'cards') === 'table' ? 'table' : 'cards'; $jobSort = (string)($_GET['sort'] ?? 'updated_at'); $jobDir = sortDirection();
        $sql = 'SELECT j.id, j.company_id, j.title, j.location_text, j.status, j.workplace_type, j.engagement_type, j.contract_term, j.fixed_term_start, j.fixed_term_end, j.source_url, j.original_pdf_status, j.original_pdf_requested_at, j.original_pdf_rendered_at, j.original_pdf_error, j.salary_min, SUBSTRING(j.description,1,65535) description, j.updated_at, c.name company_name, (SELECT d.id FROM user_documents d WHERE d.user_id=j.owner_user_id AND d.job_id=j.id AND d.title="Originale Stellenausschreibung" AND d.deleted_at IS NULL ORDER BY d.created_at DESC LIMIT 1) original_document_id FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL'; $types='i'; $vals=[userId()];
        if ($companyFilter > 0) { $sql .= ' AND j.company_id=?'; $types.='i'; $vals[]=$companyFilter; }
        if ($q !== '') { $sql .= ' AND (j.title LIKE ? OR c.name LIKE ? OR j.location_text LIKE ?)'; $like="%$q%"; $types.='sss'; array_push($vals,$like,$like,$like); }
        if ($status !== '') { $sql .= ' AND j.status=?'; $types.='s'; $vals[]=$status; }
        if ($blue) { $sql .= " AND (j.employment_type IN ('temporary','part_time') OR j.title REGEXP 'Lager|Reinigung|Produktion|Bau|Service|Zustell|Verkauf')"; }
        $jobSortMap = ['title'=>'j.title','company'=>'c.name','location'=>'j.location_text','status'=>'j.status','score'=>'j.updated_at','updated_at'=>'j.updated_at'];
        $sql .= ' ORDER BY ' . ($jobSortMap[$jobSort] ?? 'j.updated_at') . ' ' . strtoupper($jobDir);
        $jobs=dbAll($db,$sql,$types,$vals);
        $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, source_url, original_pdf_status, SUBSTRING(description,1,65535) description FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
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
        ?>
        <div class="page-head"><div><p class="eyebrow">Stellen-Pipeline</p><h1>Jobs</h1></div><span><?= count($jobs) ?> Treffer</span></div>
        <section class="panel import-panel"><h2>Schnellimport</h2><p>Eine Stellen-URL, kopierten E-Mail-/Ausschreibungstext oder mehrere Joblinks einfügen. Bei mehreren Links: ein Link pro Zeile. Original-PDFs werden nur mit echter Browser-Renderung abgelegt.</p><form method="post" class="import-form"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><textarea name="import_payload" rows="4" placeholder="https://…&#10;https://…&#10;oder Titel, Firma, Ort und Ausschreibungstext" required></textarea><button class="primary" name="action" value="preview_import">Vorschlag erstellen</button></form></section>
        <form class="filters" method="get"><input type="hidden" name="page" value="jobs"><input type="hidden" name="company_id" value="<?= $companyFilter ?: '' ?>"><input name="q" value="<?= e($q) ?>" placeholder="Titel, Firma oder Ort"><select name="status"><option value="">Alle Status</option><?php foreach(['open'=>'Offen','interesting'=>'Interessant','applied'=>'Beworben','interview'=>'Interview','offer'=>'Angebot','rejected'=>'Absage','closed'=>'Geschlossen'] as $v=>$l): ?><option value="<?= $v ?>" <?= $status===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select><select name="sort"><option value="updated_at" <?= $jobSort==='updated_at'?'selected':'' ?>>Sort: Aktualisiert</option><option value="title" <?= $jobSort==='title'?'selected':'' ?>>Sort: Titel</option><option value="company" <?= $jobSort==='company'?'selected':'' ?>>Sort: Firma</option><option value="location" <?= $jobSort==='location'?'selected':'' ?>>Sort: Ort</option><option value="status" <?= $jobSort==='status'?'selected':'' ?>>Sort: Status</option></select><select name="dir"><option value="desc" <?= $jobDir==='desc'?'selected':'' ?>>Absteigend</option><option value="asc" <?= $jobDir==='asc'?'selected':'' ?>>Aufsteigend</option></select><select name="view"><option value="cards" <?= $jobView==='cards'?'selected':'' ?>>Karten</option><option value="table" <?= $jobView==='table'?'selected':'' ?>>Tabelle</option></select><label class="check"><input type="checkbox" name="blue" value="1" <?= $blue?'checked':'' ?>> Blue-Collar/Ungelernt</label><button>Filtern</button><a class="button" href="/?page=export_pdf&type=jobs">PDF</a></form>
        <div class="split"><section class="panel" id="new"><h2><?= $edit ? 'Job bearbeiten' : ($draft ? 'Import prüfen' : 'Job erfassen') ?></h2><form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <label>Firma<select name="company_id"><option value="0">Neue Firma aus Import</option><?php foreach($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($form['company_id']??$matchedCompanyId)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
            <label>Neue Firma<input name="new_company_name" value="<?= e($matchedCompanyId ? '' : $draftCompany) ?>" placeholder="Nur ausfüllen, wenn die Firma noch fehlt"></label>
            <label>Jobtitel<input name="title" value="<?= e($form['title'] ?? '') ?>" required></label>
            <div class="two"><label>Ort<input name="location_text" value="<?= e($form['location_text'] ?? $form['location'] ?? '') ?>"></label><label>Arbeitsmodell<select name="workplace_type"><?php foreach(['unknown'=>'Unbekannt','onsite'=>'Vor Ort','hybrid'=>'Hybrid','remote'=>'Remote'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($form['workplace_type']??'unknown')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label></div>
            <div class="two"><label>Stellenart<select name="engagement_type"><option value="permanent" <?= ($form['engagement_type']??'permanent')==='permanent'?'selected':'' ?>>Dauerstelle</option><option value="temporary" <?= ($form['engagement_type']??'permanent')==='temporary'?'selected':'' ?>>Temporärstelle</option></select></label><label>Vertragsdauer<select name="contract_term"><option value="unknown" <?= ($form['contract_term']??'unknown')==='unknown'?'selected':'' ?>>Noch unbekannt</option><option value="open_ended" <?= ($form['contract_term']??'unknown')==='open_ended'?'selected':'' ?>>Unbefristet</option><option value="fixed_term" <?= ($form['contract_term']??'unknown')==='fixed_term'?'selected':'' ?>>Befristet</option></select></label></div>
            <div class="two"><label>Befristet von<input type="date" name="fixed_term_start" value="<?= e($form['fixed_term_start'] ?? '') ?>"></label><label>Befristet bis<input type="date" name="fixed_term_end" value="<?= e($form['fixed_term_end'] ?? '') ?>"></label></div>
            <label>Status<select name="status"><?php foreach(['open'=>'Offen','interesting'=>'Interessant','applied'=>'Beworben','interview'=>'Interview','offer'=>'Angebot','rejected'=>'Absage','closed'=>'Geschlossen'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($form['status']??'open')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
            <label>Quell-URL<input type="url" name="source_url" value="<?= e($form['source_url'] ?? '') ?>"></label><label>Beschreibung<textarea name="description" rows="6"><?= e($form['description'] ?? '') ?></textarea></label>
            <?php if(!empty($_GET['duplicate'])): ?><label class="check"><input type="checkbox" name="confirm_duplicate" value="1" required> Als separate Stelle speichern</label><?php endif; ?><button class="primary" name="action" value="save_job">Speichern</button>
        </form></section>
        <?php if($jobView === 'table'): ?><section class="panel table-wrap"><table><thead><tr><th>Titel</th><th>Firma</th><th>Ort</th><th>Status</th><th>Match</th><th>Aktionen</th></tr></thead><tbody><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); ?><tr><td><strong><a href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new"><?= e($job['title']) ?></a></strong><small><?= e(mb_strimwidth((string)$job['description'],0,120,'...')) ?></small></td><td><a href="/?page=companies&edit=<?= (int)$job['company_id'] ?>"><?= e($job['company_name']) ?></a></td><td><?= e($job['location_text']) ?></td><td><?= e($job['status']) ?><small><?= e($job['engagement_type']) ?> · <?= e($job['contract_term']) ?></small></td><td><?= $score ?>%</td><td class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button name="action" value="start_application">Bewerbung starten</button></form><a href="/?page=applications&job_id=<?= (int)$job['id'] ?>">Bewerbungen</a></td></tr><?php endforeach; ?><?php if(!$jobs): ?><tr><td colspan="6" class="empty">Keine Treffer.</td></tr><?php endif; ?></tbody></table></section><?php else: ?><section class="cards"><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); ?><article class="job-card <?= $edit && (int)$edit['id']===(int)$job['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($job['status']) ?></span><span class="score"><?= $score ?>%</span></div><h3><a class="record-link" href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new"><?= e($job['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$job['company_id'] ?>"><?= e($job['company_name']) ?></a> · <?= e($job['location_text']) ?></p><p class="meta-line"><?= $job['engagement_type']==='temporary'?'Temporärstelle':'Dauerstelle' ?> · <?= ['open_ended'=>'unbefristet','fixed_term'=>'befristet','unknown'=>'Dauer offen'][$job['contract_term']] ?? 'Dauer offen' ?></p><p class="meta-line"><?= e(originalPdfStatusLabel((string)($job['original_pdf_status'] ?? 'none'))) ?><?php if(!empty($job['original_pdf_error'])): ?> · <?= e(mb_strimwidth((string)$job['original_pdf_error'],0,90,'...')) ?><?php endif; ?></p><p><?= e(mb_strimwidth((string)$job['description'],0,180,'...')) ?></p><details><summary>Warum <?= $score ?>%?</summary><ul><?php foreach($reasons as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul></details><div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button class="primary-link" name="action" value="start_application">Bewerbung starten</button></form><a href="/?page=applications&job_id=<?= (int)$job['id'] ?>">Bewerbungen</a><?php if(!empty($job['original_document_id'])): ?><a href="/?page=document_download&id=<?= (int)$job['original_document_id'] ?>">Original-PDF</a><?php elseif(!empty($job['source_url'])): ?><span class="meta-line">Original-PDF ausstehend</span><?php endif; ?><form method="post" onsubmit="return confirm('Job löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$job['id'] ?>"><button name="action" value="delete_job">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$jobs): ?><div class="empty">Noch keine passenden Jobs vorhanden.</div><?php endif; ?></section><?php endif; ?></div>
    <?php elseif ($page === 'applications'): ?>
        <?php
        $appCompanyFilter=(int)($_GET['company_id'] ?? 0); $appJobFilter=(int)($_GET['job_id'] ?? 0); $todoOnly=!empty($_GET['todo']); $appQ=trim((string)($_GET['q'] ?? '')); $appStatus=(string)($_GET['status'] ?? ''); $appView=($_GET['view'] ?? 'cards') === 'table' ? 'table' : 'cards'; $appSort=(string)($_GET['sort'] ?? 'updated_at'); $appDir=sortDirection();
        $appSql='SELECT a.id, a.job_id, a.intermediary_company_id, a.status, a.applied_at, a.channel, a.next_action, a.next_action_at, a.updated_at, j.title, j.company_id, c.name company_name, i.name intermediary_company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.user_id=? AND a.deleted_at IS NULL'; $appTypes='i'; $appVals=[userId()];
        if($appCompanyFilter>0){ $appSql.=' AND (j.company_id=? OR a.intermediary_company_id=?)'; $appTypes.='ii'; array_push($appVals,$appCompanyFilter,$appCompanyFilter); }
        if($appJobFilter>0){ $appSql.=' AND a.job_id=?'; $appTypes.='i'; $appVals[]=$appJobFilter; }
        if($todoOnly){ $appSql.=' AND a.next_action_at IS NOT NULL'; }
        if($appQ !== ''){ $appSql.=' AND (j.title LIKE ? OR c.name LIKE ? OR i.name LIKE ?)'; $like="%$appQ%"; $appTypes.='sss'; array_push($appVals,$like,$like,$like); }
        if($appStatus !== ''){ $appSql.=' AND a.status=?'; $appTypes.='s'; $appVals[]=$appStatus; }
        $appSortMap=['updated_at'=>'a.updated_at','title'=>'j.title','company'=>'c.name','status'=>'a.status','next_action_at'=>'a.next_action_at','applied_at'=>'a.applied_at'];
        $appSql.=' ORDER BY ' . ($appSortMap[$appSort] ?? 'a.updated_at') . ' ' . strtoupper($appDir);
        $apps=dbAll($db,$appSql,$appTypes,$appVals);
        $applicationEdit = isset($_GET['edit']) ? dbOne($db, 'SELECT a.id, a.job_id, a.intermediary_company_id, a.primary_contact_id, a.status, a.applied_at, a.channel, a.next_action, a.next_action_at, a.email_subject, SUBSTRING(a.email_body,1,65535) email_body, SUBSTRING(a.cover_letter_text,1,65535) cover_letter_text, SUBSTRING(a.notes,1,65535) notes, j.company_id, j.title, c.name company_name, i.name intermediary_company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
        $history = $applicationEdit ? dbAll($db, 'SELECT old_status, new_status, comment, changed_at FROM application_status_history WHERE application_id=? ORDER BY changed_at DESC', 'i', [(int)$applicationEdit['id']]) : [];
        $contacts = $applicationEdit ? dbAll($db, 'SELECT c.id, c.company_id, c.application_id, c.job_id, c.first_name, c.last_name, c.position, c.department, c.email, c.phone, c.mobile, c.linkedin_url, c.preferred_language, c.notes, co.name contact_company_name FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND (c.company_id=? OR c.company_id=? OR c.application_id=? OR c.job_id=?) AND c.deleted_at IS NULL ORDER BY co.name, c.last_name, c.first_name', 'iiiii', [userId(), (int)$applicationEdit['company_id'], (int)($applicationEdit['intermediary_company_id'] ?? 0), (int)$applicationEdit['id'], (int)$applicationEdit['job_id']]) : [];
        $selectedContactId = (int) ($_GET['contact'] ?? ($applicationEdit['primary_contact_id'] ?? 0));
        $contactEdit = $selectedContactId > 0 && $applicationEdit ? dbOne($db, 'SELECT id, company_id, application_id, job_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes FROM contacts WHERE id=? AND owner_user_id=? AND (company_id=? OR company_id=? OR application_id=? OR job_id=?) AND deleted_at IS NULL', 'iiiiii', [$selectedContactId, userId(), (int)$applicationEdit['company_id'], (int)($applicationEdit['intermediary_company_id'] ?? 0), (int)$applicationEdit['id'], (int)$applicationEdit['job_id']]) : null;
        $contactLogs = $contactEdit ? dbAll($db, 'SELECT id, application_id, job_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE owner_user_id=? AND contact_id=? ORDER BY CASE status WHEN "open" THEN 1 WHEN "planned" THEN 2 WHEN "done" THEN 3 ELSE 4 END, COALESCE(follow_up_at, occurred_at) ASC', 'ii', [userId(), (int)$contactEdit['id']]) : [];
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
        $contactLogStatuses=['planned'=>'Geplant','open'=>'Offen','done'=>'Erledigt','cancelled'=>'Abgebrochen'];
        $channels=['email'=>'E-Mail','portal'=>'Jobportal','website'=>'Karriereseite','mail'=>'Post','referral'=>'Empfehlung','other'=>'Andere'];
        ?>
        <div class="page-head"><div><p class="eyebrow">Pipeline</p><h1>Bewerbungen</h1></div><span><?= count($apps) ?> Einträge</span></div>
        <form class="filters" method="get"><input type="hidden" name="page" value="applications"><input type="hidden" name="company_id" value="<?= $appCompanyFilter ?: '' ?>"><input type="hidden" name="job_id" value="<?= $appJobFilter ?: '' ?>"><input name="q" value="<?= e($appQ) ?>" placeholder="Job, Firma oder Vermittler"><select name="status"><option value="">Alle Status</option><?php foreach(['draft'=>'Entwurf','ready'=>'Bereit','sent'=>'Gesendet','confirmed'=>'Bestätigt','interview'=>'Interview','assessment'=>'Assessment','offer'=>'Angebot','accepted'=>'Angenommen','rejected'=>'Absage','withdrawn'=>'Zurückgezogen','closed'=>'Abgeschlossen'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appStatus===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select><select name="sort"><option value="updated_at" <?= $appSort==='updated_at'?'selected':'' ?>>Sort: Aktualisiert</option><option value="title" <?= $appSort==='title'?'selected':'' ?>>Sort: Job</option><option value="company" <?= $appSort==='company'?'selected':'' ?>>Sort: Firma</option><option value="status" <?= $appSort==='status'?'selected':'' ?>>Sort: Status</option><option value="next_action_at" <?= $appSort==='next_action_at'?'selected':'' ?>>Sort: Fällig</option><option value="applied_at" <?= $appSort==='applied_at'?'selected':'' ?>>Sort: Gesendet</option></select><select name="dir"><option value="desc" <?= $appDir==='desc'?'selected':'' ?>>Absteigend</option><option value="asc" <?= $appDir==='asc'?'selected':'' ?>>Aufsteigend</option></select><select name="view"><option value="cards" <?= $appView==='cards'?'selected':'' ?>>Karten</option><option value="table" <?= $appView==='table'?'selected':'' ?>>Tabelle</option></select><label class="check"><input type="checkbox" name="todo" value="1" <?= $todoOnly?'checked':'' ?>> Offene Schritte</label><button>Filtern</button><a class="button" href="/?page=export_pdf&type=applications">PDF</a></form>
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
                <label>Hauptkontakt<select name="primary_contact_id"><option value="0">Noch kein Kontakt gewählt</option><?php foreach($contacts as $contact): ?><option value="<?= (int)$contact['id'] ?>" <?= (int)$applicationEdit['primary_contact_id']===(int)$contact['id']?'selected':'' ?>><?= e($contact['first_name'].' '.$contact['last_name'].($contact['position'] ? ' · '.$contact['position'] : '')) ?></option><?php endforeach; ?></select></label>
                <div class="two">
                    <label>Nächster Schritt<select name="next_action"><option value="">Kein nächster Schritt</option><?php foreach($nextActionChoices as $choice): ?><option value="<?= e($choice) ?>" <?= ($applicationEdit['next_action'] ?? '')===$choice?'selected':'' ?>><?= e($choice) ?></option><?php endforeach; ?></select><?php if(($applicationEdit['next_action'] ?? '') && !in_array((string)$applicationEdit['next_action'], $nextActionChoices, true)): ?><small>Bisheriger Freitext: <?= e($applicationEdit['next_action']) ?></small><?php endif; ?></label>
                    <label>Fällig am<input type="datetime-local" name="next_action_at" value="<?= e($applicationEdit['next_action_at'] ? date('Y-m-d\TH:i', strtotime($applicationEdit['next_action_at'])) : '') ?>"></label>
                </div>
                <label>Kommentar zur Statusänderung<input name="status_comment" placeholder="Optional, wird im Verlauf gespeichert"></label>
                <?php if(!outboundEmailEnabled($config)): ?><p class="prototype-note">Prototyp: Es wird keine E-Mail verschickt. Betreff und Begleittext sind nur Entwürfe zum Kopieren.</p><?php endif; ?>
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
                    <?php if($doc['scope'] === 'profile'): ?><form method="post" class="actions" onsubmit="return confirm('Dokument-Zuordnung entfernen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>"><input type="hidden" name="user_document_id" value="<?= (int)$doc['id'] ?>"><button name="action" value="detach_application_document">Entfernen</button></form><?php else: ?><form method="post" class="actions" onsubmit="return confirm('Bewerbungsdokument löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document">Löschen</button></form><?php endif; ?>
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
            <section class="panel"><h2>Zugeordnete Kontakte</h2><div class="contact-list"><?php foreach($contacts as $contact): ?><article class="<?= (int)$applicationEdit['primary_contact_id']===(int)$contact['id']?'is-primary':'' ?> <?= $contactEdit && (int)$contactEdit['id']===(int)$contact['id']?'is-selected':'' ?>"><small><?= e($contact['contact_company_name']) ?> · Firma<?php if((int)$contact['job_id']===(int)$applicationEdit['job_id']): ?> · Job<?php endif; ?><?php if((int)($contact['application_id'] ?? 0)===(int)$applicationEdit['id']): ?> · Bewerbung<?php endif; ?></small><strong><a class="record-link" href="/?page=applications&edit=<?= (int)$applicationEdit['id'] ?>&contact=<?= (int)$contact['id'] ?>#contacts"><?= e($contact['first_name'].' '.$contact['last_name']) ?></a></strong><span><?= e($contact['position'] ?: $contact['department']) ?></span><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?></article><?php endforeach; ?><?php if(!$contacts): ?><p class="empty">Noch keine Kontakte für Arbeitgeber, Job oder Bewerbung.</p><?php endif; ?></div></section>
        </section>
        <?php if($contactEdit): ?><section class="panel contact-log" id="contact-log"><div class="section-head"><div><p class="eyebrow">Kontakt-Log</p><h2><?= e($contactEdit['first_name'].' '.$contactEdit['last_name']) ?></h2></div></div><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>"><input type="hidden" name="contact_id" value="<?= (int)$contactEdit['id'] ?>"><div class="three"><label>Kanal<select name="log_channel"><?php foreach(['email'=>'E-Mail','phone'=>'Telefon','meeting'=>'Treffen','video'=>'Video','message'=>'Nachricht','letter'=>'Brief','note'=>'Notiz','other'=>'Andere'] as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></label><label>Richtung<select name="direction"><option value="outgoing">Ausgehend</option><option value="incoming">Eingehend</option><option value="internal">Intern</option></select></label><label>Status<select name="log_status"><option value="planned">Geplant</option><option value="open" selected>Offen</option><option value="done">Erledigt</option><option value="cancelled">Abgebrochen</option></select></label></div><div class="two"><label>Aktionszeitpunkt<input type="datetime-local" name="occurred_at" value="<?= date('Y-m-d\TH:i') ?>" required></label><label>Wiedervorlage / nächster Termin<input type="datetime-local" name="follow_up_at"></label></div><label>Betreff<input name="subject"></label><label>Inhalt<textarea name="log_body" rows="4"></textarea></label><label>Ergebnis / nächster Schritt<input name="outcome"></label><button class="primary" name="action" value="save_contact_log">Aktivität speichern</button></form><div class="log-timeline"><?php foreach($contactLogs as $entry): ?><article class="log-status-<?= e($entry['status']) ?>"><div><strong><?= e($entry['subject'] ?: ucfirst($entry['channel'])) ?></strong><span><?= e(($contactLogStatuses[$entry['status']] ?? $entry['status']).' · '.$entry['direction'].' · '.displayDateTime($entry['occurred_at'], $currentUser)) ?></span></div><?php if($entry['body']): ?><p><?= nl2br(e($entry['body'])) ?></p><?php endif; ?><?php if($entry['outcome']): ?><small>Ergebnis / nächster Schritt: <?= e($entry['outcome']) ?></small><?php endif; ?><?php if($entry['follow_up_at']): ?><small>Wiedervorlage: <?= e(displayDateTime($entry['follow_up_at'], $currentUser)) ?></small><?php endif; ?></article><?php endforeach; ?><?php if(!$contactLogs): ?><p class="empty">Noch keine Kontaktaktivitäten.</p><?php endif; ?></div></section><?php endif; ?>
        <?php endif; ?>
        <?php if($appView === 'table'): ?><section class="panel table-wrap"><table><thead><tr><th>Job</th><th>Firma</th><th>Status</th><th>Kanal</th><th>Nächster Schritt</th><th>Aktionen</th></tr></thead><tbody><?php foreach($apps as $app): ?><tr class="<?= $applicationEdit && (int)$applicationEdit['id']===(int)$app['id']?'is-selected':'' ?>"><td><strong><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form"><?= e($app['title']) ?></a></strong><small><?= e($app['applied_at'] ? displayDateTime($app['applied_at'], $currentUser) : '') ?></small></td><td><a href="/?page=companies&edit=<?= (int)$app['company_id'] ?>"><?= e($app['company_name']) ?></a><?php if($app['intermediary_company_name']): ?><small>über <?= e($app['intermediary_company_name']) ?></small><?php endif; ?></td><td><?= e($applicationStatuses[$app['status']] ?? $app['status']) ?></td><td><?= e($app['channel']) ?></td><td><?= e($app['next_action']) ?><?php if($app['next_action_at']): ?><small><?= e(displayDateTime($app['next_action_at'], $currentUser)) ?></small><?php endif; ?></td><td class="actions"><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form">Bearbeiten</a></td></tr><?php endforeach; ?><?php if(!$apps): ?><tr><td colspan="6" class="empty">Keine Treffer.</td></tr><?php endif; ?></tbody></table></section><?php else: ?><section class="application-list"><?php foreach($apps as $app): ?><article class="application-card <?= $applicationEdit && (int)$applicationEdit['id']===(int)$app['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($applicationStatuses[$app['status']] ?? $app['status']) ?></span><?php if($app['next_action_at']): ?><span class="due"><?= e(displayDateTime($app['next_action_at'], $currentUser)) ?></span><?php endif; ?></div><h3><a href="/?page=jobs&edit=<?= (int)$app['job_id'] ?>#new"><?= e($app['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$app['company_id'] ?>"><?= e($app['company_name']) ?></a><?php if($app['intermediary_company_name']): ?> · über <a href="/?page=companies&edit=<?= (int)$app['intermediary_company_id'] ?>"><?= e($app['intermediary_company_name']) ?></a><?php endif; ?></p><?php if($app['next_action']): ?><p><strong>Nächster Schritt:</strong> <?= e($app['next_action']) ?></p><?php endif; ?><div class="actions"><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form">Bearbeiten</a><form method="post" onsubmit="return confirm('Bewerbung löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$app['id'] ?>"><button name="action" value="delete_application">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$apps): ?><div class="panel empty"><h2>Noch keine Bewerbungen</h2><p>Öffne Jobs und wähle bei einer passenden Stelle „Bewerbung starten“.</p><a class="button primary" href="/?page=jobs">Zu den Jobs</a></div><?php endif; ?></section><?php endif; ?>
    <?php elseif ($page === 'contacts'): ?>
        <?php
        $contactCompanyFilter=(int)($_GET['company_id'] ?? 0); $contactQ=trim((string)($_GET['q'] ?? '')); $contactSort=(string)($_GET['sort'] ?? 'name'); $contactDir=sortDirection();
        $contactCompany=$contactCompanyFilter ? dbOne($db,'SELECT id,name FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL','ii',[$contactCompanyFilter,userId()]) : null;
        $contactSql='SELECT ct.*, c.name company_name, j.title job_title, a.status application_status, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=ct.id AND l.owner_user_id=ct.owner_user_id) log_count, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=ct.id AND l.owner_user_id=ct.owner_user_id AND l.status IN ("planned","open")) open_log_count FROM contacts ct JOIN companies c ON c.id=ct.company_id LEFT JOIN jobs j ON j.id=ct.job_id LEFT JOIN applications a ON a.id=ct.application_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL'; $contactTypes='i'; $contactVals=[userId()];
        if($contactCompanyFilter>0){ $contactSql.=' AND ct.company_id=?'; $contactTypes.='i'; $contactVals[]=$contactCompanyFilter; }
        if($contactQ !== ''){ $contactSql.=' AND (ct.first_name LIKE ? OR ct.last_name LIKE ? OR ct.email LIKE ? OR c.name LIKE ?)'; $like="%$contactQ%"; $contactTypes.='ssss'; array_push($contactVals,$like,$like,$like,$like); }
        $contactSortMap=['name'=>'ct.last_name','company'=>'c.name','email'=>'ct.email','updated_at'=>'ct.updated_at']; $contactSql.=' ORDER BY '.($contactSortMap[$contactSort] ?? 'ct.last_name').' '.strtoupper($contactDir).', ct.first_name';
        $contactRows=dbAll($db,$contactSql,$contactTypes,$contactVals);
        ?>
        <div class="page-head"><div><p class="eyebrow">CRM</p><h1>Kontakte</h1></div><span><?= count($contactRows) ?> Einträge</span></div>
        <form class="filters" method="get"><input type="hidden" name="page" value="contacts"><input type="hidden" name="company_id" value="<?= $contactCompanyFilter ?: '' ?>"><input name="q" value="<?= e($contactQ) ?>" placeholder="Name, Firma oder E-Mail"><select name="sort"><option value="name" <?= $contactSort==='name'?'selected':'' ?>>Sort: Name</option><option value="company" <?= $contactSort==='company'?'selected':'' ?>>Sort: Firma</option><option value="email" <?= $contactSort==='email'?'selected':'' ?>>Sort: E-Mail</option><option value="updated_at" <?= $contactSort==='updated_at'?'selected':'' ?>>Sort: Aktualisiert</option></select><select name="dir"><option value="asc" <?= $contactDir==='asc'?'selected':'' ?>>Aufsteigend</option><option value="desc" <?= $contactDir==='desc'?'selected':'' ?>>Absteigend</option></select><button>Filtern</button><a class="button" href="/?page=export_pdf&type=contacts">PDF</a></form>
        <?php if($contactCompany): ?><p class="filter-note">Kontakte bei <a href="/?page=companies&edit=<?= (int)$contactCompany['id'] ?>"><?= e($contactCompany['name']) ?></a> · <a href="/?page=contacts">Alle Kontakte anzeigen</a></p><?php endif; ?>
        <section class="panel table-wrap"><table><thead><tr><th>Kontakt</th><th>Firma</th><th>Erreichbar</th><th>CRM-Bezug</th></tr></thead><tbody><?php foreach($contactRows as $contact): ?><tr><td><strong><?= e($contact['first_name'].' '.$contact['last_name']) ?></strong><small><?= e($contact['position'] ?: $contact['department']) ?></small></td><td><a href="/?page=companies&edit=<?= (int)$contact['company_id'] ?>"><?= e($contact['company_name']) ?></a></td><td><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?><small><?= e($contact['phone'] ?: $contact['mobile']) ?></small></td><td class="link-list"><span><?= (int)$contact['open_log_count'] ?> offen/geplant · <?= (int)$contact['log_count'] ?> total</span><?php if($contact['job_id']): ?><a href="/?page=jobs&edit=<?= (int)$contact['job_id'] ?>#new"><?= e($contact['job_title'] ?: 'Job öffnen') ?></a><?php endif; ?><?php if($contact['application_id']): ?><a href="/?page=applications&edit=<?= (int)$contact['application_id'] ?>&contact=<?= (int)$contact['id'] ?>#contact-log">Bewerbung / Aktivitäten</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$contactRows): ?><tr><td colspan="4" class="empty">Noch keine Kontakte vorhanden.</td></tr><?php endif; ?></tbody></table></section>
    <?php elseif ($page === 'documents'): ?>
        <?php
        $documentTypes = dbAll($db, 'SELECT id, code, name_key FROM document_types ORDER BY id');
        $profileDocumentTypes = documentTypesForScope($documentTypes, 'profile');
        $docQ=trim((string)($_GET['q'] ?? '')); $docSort=(string)($_GET['sort'] ?? 'created_at'); $docDir=sortDirection();
        $docSql="SELECT d.*, dt.code type_code, dt.name_key type_name FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope='profile' AND d.deleted_at IS NULL"; $docTypes='i'; $docVals=[userId()];
        if($docQ !== ''){ $docSql.=' AND (d.title LIKE ? OR d.original_filename LIKE ? OR dt.code LIKE ?)'; $like="%$docQ%"; $docTypes.='sss'; array_push($docVals,$like,$like,$like); }
        $docSortMap=['created_at'=>'d.created_at','title'=>'d.title','type'=>'dt.code','version'=>'d.version']; $docSql.=' ORDER BY '.($docSortMap[$docSort] ?? 'd.created_at').' '.strtoupper($docDir).', d.title';
        $documents = dbAll($db, $docSql, $docTypes, $docVals);
        $userLanguage = (string) ($currentUser['preferred_language'] ?? 'de');
        ?>
        <div class="page-head"><div><p class="eyebrow">Stammdaten</p><h1>Dokumente</h1></div><span><?= count($documents) ?> Versionen</span></div>
        <form class="filters" method="get"><input type="hidden" name="page" value="documents"><input name="q" value="<?= e($docQ) ?>" placeholder="Titel, Datei oder Typ"><select name="sort"><option value="created_at" <?= $docSort==='created_at'?'selected':'' ?>>Sort: Datum</option><option value="title" <?= $docSort==='title'?'selected':'' ?>>Sort: Titel</option><option value="type" <?= $docSort==='type'?'selected':'' ?>>Sort: Typ</option><option value="version" <?= $docSort==='version'?'selected':'' ?>>Sort: Version</option></select><select name="dir"><option value="desc" <?= $docDir==='desc'?'selected':'' ?>>Absteigend</option><option value="asc" <?= $docDir==='asc'?'selected':'' ?>>Aufsteigend</option></select><button>Filtern</button><a class="button" href="/?page=export_pdf&type=documents">PDF</a></form>
        <div class="split"><section class="panel"><h2>Dokument hochladen</h2><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label>Neue Version von<select name="replace_document_id"><option value="0">Neues Dokument</option><?php foreach($documents as $doc): if(!(int)$doc['is_current']) continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label><label>Dokumenttyp<select name="document_type_id"><?php foreach($profileDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label><label>Titel<input name="document_title" required placeholder="z. B. Lebenslauf deutsch"></label><label>Sprache<select name="document_language"><option value="">Nicht gewählt</option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= $v ?>" <?= $v===$userLanguage?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><div class="two"><label>Gültig ab<input type="date" name="valid_from"></label><label>Gültig bis<input type="date" name="valid_until"></label></div><label>Beschreibung<textarea name="document_description" rows="3"></textarea></label><label>Datei<input type="file" name="user_document" required></label><button class="primary" name="action" value="upload_document">Speichern</button></form></section>
        <section class="panel table-wrap"><table><thead><tr><th>Dokument</th><th>Typ</th><th>Version</th><th>Datum</th><th>Aktionen</th></tr></thead><tbody><?php foreach($documents as $doc): ?><tr class="<?= (int)$doc['is_current'] ? 'is-selected' : '' ?>"><td><strong><a class="record-link" href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?></a></strong><small><?= e($doc['original_filename']) ?></small></td><td><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?><small><?= e($doc['language_code']) ?></small></td><td>v<?= (int)$doc['version'] ?><?= (int)$doc['is_current'] ? ' · aktuell' : '' ?></td><td><?= e(displayDateTime($doc['created_at'], $currentUser)) ?><small><?= number_format(((int)$doc['file_size']) / 1024, 1) ?> KB</small></td><td class="actions"><a href="/?page=document_download&id=<?= (int)$doc['id'] ?>">Download</a><form method="post" onsubmit="return confirm('Dokument löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$documents): ?><tr><td colspan="5" class="empty">Noch keine Dokumente vorhanden.</td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'audit'): ?>
        <?php $logs=dbAll($db,'SELECT id, action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 100','i',[userId()]); ?>
        <div class="page-head"><div><p class="eyebrow">Unveränderbar</p><h1>Änderungsprotokoll</h1></div><span>Letzte 100</span></div><section class="panel table-wrap"><table><thead><tr><th>Zeit</th><th>Aktion</th><th>Bereich</th><th>ID</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= e(displayDateTime($log['created_at'], $currentUser)) ?></td><td><?= e($log['action']) ?></td><td><?= e($log['entity_type']) ?></td><td><?= (int)$log['entity_id'] ?></td></tr><?php endforeach; ?></tbody></table></section>
    <?php endif; ?>
<?php endif; ?>
</main>
<footer>JeMa Jobs Prototyp · Private Daten bleiben benutzerisoliert</footer>
<script src="/assets/qrcode.min.js" defer></script>
<script src="/assets/totp-qr.js" defer></script>
</body></html>
