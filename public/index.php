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

function outboundEmailEnabled(): bool
{
    return false;
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
            $stmt = $db->prepare(
                "INSERT INTO users (email, password_hash, status, preferred_language, first_name, last_name, email_verified_at) "
                . "VALUES (?, ?, 'active', 'de', ?, ?, NOW())"
            );
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('ssss', $email, $hash, $first, $last);
            $stmt->execute();
            audit($db, (int) $stmt->insert_id, 'create', 'user', (int) $stmt->insert_id, null, ['email' => $email]);
            flash('Registrierung gespeichert. Du kannst dich jetzt direkt anmelden.');
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
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $db->query('UPDATE users SET last_login_at = NOW() WHERE id = ' . (int) $user['id']);
        audit($db, (int) $user['id'], 'login', 'user', (int) $user['id'], null, null);
        redirect('/');
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
        $target = dbOne($db, 'SELECT id, email, status FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        if (!$target) {
            flash('Benutzer nicht gefunden.', 'danger');
            redirect('/?page=admin_users');
        }
        $status = in_array($_POST['status'] ?? '', ['invited','active','locked','disabled'], true) ? (string) $_POST['status'] : (string) $target['status'];
        $stmt = $db->prepare('UPDATE users SET status=?, email_verified_at=CASE WHEN ?="active" THEN COALESCE(email_verified_at, NOW()) ELSE email_verified_at END WHERE id=?');
        $stmt->bind_param('ssi', $status, $status, $targetUserId);
        $stmt->execute();

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
        audit($db, userId(), 'update', 'user', $targetUserId, $target, ['status' => $status, 'is_admin' => $isAdminTarget]);
        flash('Benutzer aktualisiert.');
        redirect('/?page=admin_users');
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
if ($currentUser && in_array($page, ['login', 'register'], true)) {
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
            <a href="/?page=profile">Profil</a>
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
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label>E-Mail<input type="email" name="email" required></label>
            <label>Passwort<input type="password" name="password" required></label>
            <button class="primary" name="action" value="login">Anmelden</button>
        </form>
        <p>Noch kein Konto? <a href="/?page=register">Registrieren</a></p>
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
        <section class="panel"><h2>Nächste Schritte</h2><p>Erfasse zuerst eine Firma und danach passende Stellen. Der Prototyp berechnet bereits einen transparenten Basis-Match und erkennt mögliche Dubletten.</p></section>
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
                    (SELECT COUNT(*) FROM user_documents d WHERE d.user_id=u.id AND d.deleted_at IS NULL) document_count
               FROM users u
              WHERE u.deleted_at IS NULL
           ORDER BY FIELD(u.status, 'active', 'invited', 'locked', 'disabled'), u.created_at DESC"
        );
        ?>
        <div class="page-head"><div><p class="eyebrow">Administration</p><h1>Benutzer</h1></div><span><?= count($users) ?> Konten</span></div>
        <section class="panel table-wrap"><table><thead><tr><th>Benutzer</th><th>Status</th><th>Nutzung</th><th>Zugriff</th><th>Aktionen</th></tr></thead><tbody>
            <?php foreach($users as $user): $roleCodes = array_filter(explode(',', (string) ($user['role_codes'] ?? ''))); $isConfigAdmin = in_array(strtolower((string) $user['email']), $adminEmails, true); $isUserAdmin = $isConfigAdmin || in_array('admin', $roleCodes, true); $isSelf = (int) $user['id'] === userId(); ?>
                <tr class="<?= $isSelf ? 'is-selected' : '' ?>">
                    <td><strong><?= e(trim((string)$user['first_name'].' '.(string)$user['last_name'])) ?></strong><small><?= e($user['email']) ?></small><small>Registriert: <?= e(displayDateTime($user['created_at'], $currentUser)) ?></small></td>
                    <td><span class="badge"><?= e($user['status']) ?></span><?php if($user['email_verified_at']): ?><small>verifiziert: <?= e(displayDateTime($user['email_verified_at'], $currentUser)) ?></small><?php else: ?><small>nicht verifiziert</small><?php endif; ?><?php if($user['last_login_at']): ?><small>letzter Login: <?= e(displayDateTime($user['last_login_at'], $currentUser)) ?></small><?php endif; ?></td>
                    <td><small><?= (int)$user['job_count'] ?> Jobs</small><small><?= (int)$user['application_count'] ?> Bewerbungen</small><small><?= (int)$user['document_count'] ?> Dokumente</small></td>
                    <td><small><?= $isUserAdmin ? 'Admin' : 'Benutzer' ?></small><?php if($isConfigAdmin): ?><small>Config-Admin</small><?php endif; ?></td>
                    <td>
                        <?php if($isSelf): ?>
                            <span class="meta-line">Eigenes Konto geschützt</span>
                        <?php else: ?>
                            <form method="post" class="actions"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <select name="status"><?php foreach(['active'=>'Aktiv','invited'=>'Eingeladen/Test offen','locked'=>'Gesperrt','disabled'=>'Deaktiviert'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $user['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                <label class="check"><input type="checkbox" name="is_admin" value="1" <?= $isUserAdmin?'checked':'' ?> <?= $isConfigAdmin?'disabled':'' ?>> Admin</label>
                                <?php if($isConfigAdmin): ?><input type="hidden" name="is_admin" value="1"><?php endif; ?>
                                <button class="primary" name="action" value="admin_update_user">Speichern</button>
                            </form>
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
        ?>
        <div class="page-head"><div><p class="eyebrow">Konto</p><h1>Eigenes Profil</h1></div><span><?= e($currentUser['email']) ?></span></div>
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
        $companyRows = dbAll($db, 'SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id AND j.owner_user_id=c.owner_user_id AND j.deleted_at IS NULL) job_count, (SELECT COUNT(*) FROM contacts ct WHERE ct.company_id=c.id AND ct.owner_user_id=c.owner_user_id AND ct.deleted_at IS NULL) contact_count, (SELECT COUNT(*) FROM applications a JOIN jobs j2 ON j2.id=a.job_id WHERE a.user_id=c.owner_user_id AND a.deleted_at IS NULL AND (j2.company_id=c.id OR a.intermediary_company_id=c.id)) application_count, (SELECT GROUP_CONCAT(DISTINCT CONCAT(client.id, "::", client.name) ORDER BY client.name SEPARATOR "||") FROM company_relationships cr JOIN companies client ON client.id=cr.client_company_id WHERE cr.owner_user_id=c.owner_user_id AND cr.intermediary_company_id=c.id AND cr.deleted_at IS NULL AND client.deleted_at IS NULL) mediated_clients, (SELECT GROUP_CONCAT(DISTINCT CONCAT(intermediary.id, "::", intermediary.name) ORDER BY intermediary.name SEPARATOR "||") FROM company_relationships cr JOIN companies intermediary ON intermediary.id=cr.intermediary_company_id WHERE cr.owner_user_id=c.owner_user_id AND cr.client_company_id=c.id AND cr.deleted_at IS NULL AND intermediary.deleted_at IS NULL) mediated_by FROM companies c WHERE c.owner_user_id=? AND c.deleted_at IS NULL ORDER BY c.name', 'i', [userId()]);
        ?>
        <div class="page-head"><div><p class="eyebrow">CRM</p><h1>Firmen</h1></div><span><?= count($companies) ?> Einträge</span></div>
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
        $q = trim((string)($_GET['q'] ?? '')); $status = (string)($_GET['status'] ?? ''); $blue = !empty($_GET['blue']); $companyFilter = (int)($_GET['company_id'] ?? 0);
        $sql = 'SELECT j.id, j.company_id, j.title, j.location_text, j.status, j.workplace_type, j.engagement_type, j.contract_term, j.fixed_term_start, j.fixed_term_end, j.source_url, j.original_pdf_status, j.original_pdf_requested_at, j.original_pdf_rendered_at, j.original_pdf_error, j.salary_min, SUBSTRING(j.description,1,65535) description, j.updated_at, c.name company_name, (SELECT d.id FROM user_documents d WHERE d.user_id=j.owner_user_id AND d.job_id=j.id AND d.title="Originale Stellenausschreibung" AND d.deleted_at IS NULL ORDER BY d.created_at DESC LIMIT 1) original_document_id FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL'; $types='i'; $vals=[userId()];
        if ($companyFilter > 0) { $sql .= ' AND j.company_id=?'; $types.='i'; $vals[]=$companyFilter; }
        if ($q !== '') { $sql .= ' AND (j.title LIKE ? OR c.name LIKE ? OR j.location_text LIKE ?)'; $like="%$q%"; $types.='sss'; array_push($vals,$like,$like,$like); }
        if ($status !== '') { $sql .= ' AND j.status=?'; $types.='s'; $vals[]=$status; }
        if ($blue) { $sql .= " AND (j.employment_type IN ('temporary','part_time') OR j.title REGEXP 'Lager|Reinigung|Produktion|Bau|Service|Zustell|Verkauf')"; }
        $sql .= ' ORDER BY j.updated_at DESC'; $jobs=dbAll($db,$sql,$types,$vals);
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
        <form class="filters" method="get"><input type="hidden" name="page" value="jobs"><input type="hidden" name="company_id" value="<?= $companyFilter ?: '' ?>"><input name="q" value="<?= e($q) ?>" placeholder="Titel, Firma oder Ort"><select name="status"><option value="">Alle Status</option><?php foreach(['open'=>'Offen','interesting'=>'Interessant','applied'=>'Beworben','interview'=>'Interview','offer'=>'Angebot','rejected'=>'Absage','closed'=>'Geschlossen'] as $v=>$l): ?><option value="<?= $v ?>" <?= $status===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select><label class="check"><input type="checkbox" name="blue" value="1" <?= $blue?'checked':'' ?>> Blue-Collar/Ungelernt</label><button>Filtern</button></form>
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
        <section class="cards"><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); ?><article class="job-card <?= $edit && (int)$edit['id']===(int)$job['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($job['status']) ?></span><span class="score"><?= $score ?>%</span></div><h3><a class="record-link" href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new"><?= e($job['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$job['company_id'] ?>"><?= e($job['company_name']) ?></a> · <?= e($job['location_text']) ?></p><p class="meta-line"><?= $job['engagement_type']==='temporary'?'Temporärstelle':'Dauerstelle' ?> · <?= ['open_ended'=>'unbefristet','fixed_term'=>'befristet','unknown'=>'Dauer offen'][$job['contract_term']] ?? 'Dauer offen' ?></p><p class="meta-line"><?= e(originalPdfStatusLabel((string)($job['original_pdf_status'] ?? 'none'))) ?><?php if(!empty($job['original_pdf_error'])): ?> · <?= e(mb_strimwidth((string)$job['original_pdf_error'],0,90,'…')) ?><?php endif; ?></p><p><?= e(mb_strimwidth((string)$job['description'],0,180,'…')) ?></p><details><summary>Warum <?= $score ?>%?</summary><ul><?php foreach($reasons as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul></details><div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button class="primary-link" name="action" value="start_application">Bewerbung starten</button></form><a href="/?page=applications&job_id=<?= (int)$job['id'] ?>">Bewerbungen</a><?php if(!empty($job['original_document_id'])): ?><a href="/?page=document_download&id=<?= (int)$job['original_document_id'] ?>">Original-PDF</a><?php elseif(!empty($job['source_url'])): ?><span class="meta-line">Original-PDF ausstehend</span><?php endif; ?><form method="post" onsubmit="return confirm('Job löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$job['id'] ?>"><button name="action" value="delete_job">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$jobs): ?><div class="empty">Noch keine passenden Jobs vorhanden.</div><?php endif; ?></section></div>
    <?php elseif ($page === 'applications'): ?>
        <?php
        $appCompanyFilter=(int)($_GET['company_id'] ?? 0); $appJobFilter=(int)($_GET['job_id'] ?? 0); $todoOnly=!empty($_GET['todo']);
        $appSql='SELECT a.id, a.job_id, a.intermediary_company_id, a.status, a.applied_at, a.channel, a.next_action, a.next_action_at, a.updated_at, j.title, j.company_id, c.name company_name, i.name intermediary_company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.user_id=? AND a.deleted_at IS NULL'; $appTypes='i'; $appVals=[userId()];
        if($appCompanyFilter>0){ $appSql.=' AND (j.company_id=? OR a.intermediary_company_id=?)'; $appTypes.='ii'; array_push($appVals,$appCompanyFilter,$appCompanyFilter); }
        if($appJobFilter>0){ $appSql.=' AND a.job_id=?'; $appTypes.='i'; $appVals[]=$appJobFilter; }
        if($todoOnly){ $appSql.=' AND a.next_action_at IS NOT NULL'; }
        $appSql.=' ORDER BY a.updated_at DESC';
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
                <?php if(!outboundEmailEnabled()): ?><p class="prototype-note">Prototyp: Es wird keine E-Mail verschickt. Betreff und Begleittext sind nur Entwürfe zum Kopieren.</p><?php endif; ?>
                <label>E-Mail-Betreff<input name="email_subject" value="<?= e($applicationEdit['email_subject'] ?? '') ?>"></label>
                <label>E-Mail-Begleittext<textarea name="email_body" rows="4"><?= e($applicationEdit['email_body'] ?? '') ?></textarea></label>
                <label>Motivationsschreiben<textarea name="cover_letter_text" rows="<?= $coverLetterPrompt ? 16 : 7 ?>"><?= e($applicationEdit['cover_letter_text'] ?: $coverLetterPrompt) ?></textarea><?php if($coverLetterPrompt): ?><small>Das Feld enthält einen ChatGPT-Prompt, weil noch kein Motivationsschreiben gespeichert ist. Kopieren, in ChatGPT verwenden, Ergebnis hier einfügen und speichern.</small><?php endif; ?></label>
                <label>Interne Notizen<textarea name="notes" rows="4"><?= e($applicationEdit['notes'] ?? '') ?></textarea></label>
                <button class="primary" name="action" value="save_application">Bewerbung speichern</button>
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
        <section class="application-list"><?php foreach($apps as $app): ?><article class="application-card <?= $applicationEdit && (int)$applicationEdit['id']===(int)$app['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($applicationStatuses[$app['status']] ?? $app['status']) ?></span><?php if($app['next_action_at']): ?><span class="due"><?= e(displayDateTime($app['next_action_at'], $currentUser)) ?></span><?php endif; ?></div><h3><a href="/?page=jobs&edit=<?= (int)$app['job_id'] ?>#new"><?= e($app['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$app['company_id'] ?>"><?= e($app['company_name']) ?></a><?php if($app['intermediary_company_name']): ?> · über <a href="/?page=companies&edit=<?= (int)$app['intermediary_company_id'] ?>"><?= e($app['intermediary_company_name']) ?></a><?php endif; ?></p><?php if($app['next_action']): ?><p><strong>Nächster Schritt:</strong> <?= e($app['next_action']) ?></p><?php endif; ?><div class="actions"><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form">Bearbeiten</a><form method="post" onsubmit="return confirm('Bewerbung löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$app['id'] ?>"><button name="action" value="delete_application">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$apps): ?><div class="panel empty"><h2>Noch keine Bewerbungen</h2><p>Öffne Jobs und wähle bei einer passenden Stelle „Bewerbung starten“.</p><a class="button primary" href="/?page=jobs">Zu den Jobs</a></div><?php endif; ?></section>
    <?php elseif ($page === 'contacts'): ?>
        <?php
        $contactCompanyFilter=(int)($_GET['company_id'] ?? 0);
        $contactCompany=$contactCompanyFilter ? dbOne($db,'SELECT id,name FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL','ii',[$contactCompanyFilter,userId()]) : null;
        $contactSql='SELECT ct.*, c.name company_name, j.title job_title, a.status application_status, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=ct.id AND l.owner_user_id=ct.owner_user_id) log_count, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=ct.id AND l.owner_user_id=ct.owner_user_id AND l.status IN ("planned","open")) open_log_count FROM contacts ct JOIN companies c ON c.id=ct.company_id LEFT JOIN jobs j ON j.id=ct.job_id LEFT JOIN applications a ON a.id=ct.application_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL'; $contactTypes='i'; $contactVals=[userId()];
        if($contactCompanyFilter>0){ $contactSql.=' AND ct.company_id=?'; $contactTypes.='i'; $contactVals[]=$contactCompanyFilter; }
        $contactSql.=' ORDER BY c.name, ct.last_name, ct.first_name';
        $contactRows=dbAll($db,$contactSql,$contactTypes,$contactVals);
        ?>
        <div class="page-head"><div><p class="eyebrow">CRM</p><h1>Kontakte</h1></div><span><?= count($contactRows) ?> Einträge</span></div>
        <?php if($contactCompany): ?><p class="filter-note">Kontakte bei <a href="/?page=companies&edit=<?= (int)$contactCompany['id'] ?>"><?= e($contactCompany['name']) ?></a> · <a href="/?page=contacts">Alle Kontakte anzeigen</a></p><?php endif; ?>
        <section class="panel table-wrap"><table><thead><tr><th>Kontakt</th><th>Firma</th><th>Erreichbar</th><th>CRM-Bezug</th></tr></thead><tbody><?php foreach($contactRows as $contact): ?><tr><td><strong><?= e($contact['first_name'].' '.$contact['last_name']) ?></strong><small><?= e($contact['position'] ?: $contact['department']) ?></small></td><td><a href="/?page=companies&edit=<?= (int)$contact['company_id'] ?>"><?= e($contact['company_name']) ?></a></td><td><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?><small><?= e($contact['phone'] ?: $contact['mobile']) ?></small></td><td class="link-list"><span><?= (int)$contact['open_log_count'] ?> offen/geplant · <?= (int)$contact['log_count'] ?> total</span><?php if($contact['job_id']): ?><a href="/?page=jobs&edit=<?= (int)$contact['job_id'] ?>#new"><?= e($contact['job_title'] ?: 'Job öffnen') ?></a><?php endif; ?><?php if($contact['application_id']): ?><a href="/?page=applications&edit=<?= (int)$contact['application_id'] ?>&contact=<?= (int)$contact['id'] ?>#contact-log">Bewerbung / Aktivitäten</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$contactRows): ?><tr><td colspan="4" class="empty">Noch keine Kontakte vorhanden.</td></tr><?php endif; ?></tbody></table></section>
    <?php elseif ($page === 'documents'): ?>
        <?php
        $documentTypes = dbAll($db, 'SELECT id, code, name_key FROM document_types ORDER BY id');
        $profileDocumentTypes = documentTypesForScope($documentTypes, 'profile');
        $documents = dbAll($db, "SELECT d.*, dt.code type_code, dt.name_key type_name FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope='profile' AND d.deleted_at IS NULL ORDER BY d.is_current DESC, d.title, d.version DESC", 'i', [userId()]);
        $userLanguage = (string) ($currentUser['preferred_language'] ?? 'de');
        ?>
        <div class="page-head"><div><p class="eyebrow">Stammdaten</p><h1>Dokumente</h1></div><span><?= count($documents) ?> Versionen</span></div>
        <div class="split"><section class="panel"><h2>Dokument hochladen</h2><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label>Neue Version von<select name="replace_document_id"><option value="0">Neues Dokument</option><?php foreach($documents as $doc): if(!(int)$doc['is_current']) continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label><label>Dokumenttyp<select name="document_type_id"><?php foreach($profileDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label><label>Titel<input name="document_title" required placeholder="z. B. Lebenslauf deutsch"></label><label>Sprache<select name="document_language"><option value="">Nicht gewählt</option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= $v ?>" <?= $v===$userLanguage?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><div class="two"><label>Gültig ab<input type="date" name="valid_from"></label><label>Gültig bis<input type="date" name="valid_until"></label></div><label>Beschreibung<textarea name="document_description" rows="3"></textarea></label><label>Datei<input type="file" name="user_document" required></label><button class="primary" name="action" value="upload_document">Speichern</button></form></section>
        <section class="panel table-wrap"><table><thead><tr><th>Dokument</th><th>Typ</th><th>Version</th><th>Datum</th><th>Aktionen</th></tr></thead><tbody><?php foreach($documents as $doc): ?><tr class="<?= (int)$doc['is_current'] ? 'is-selected' : '' ?>"><td><strong><a class="record-link" href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?></a></strong><small><?= e($doc['original_filename']) ?></small></td><td><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?><small><?= e($doc['language_code']) ?></small></td><td>v<?= (int)$doc['version'] ?><?= (int)$doc['is_current'] ? ' · aktuell' : '' ?></td><td><?= e(displayDateTime($doc['created_at'], $currentUser)) ?><small><?= number_format(((int)$doc['file_size']) / 1024, 1) ?> KB</small></td><td class="actions"><a href="/?page=document_download&id=<?= (int)$doc['id'] ?>">Download</a><form method="post" onsubmit="return confirm('Dokument löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$documents): ?><tr><td colspan="5" class="empty">Noch keine Dokumente vorhanden.</td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'audit'): ?>
        <?php $logs=dbAll($db,'SELECT id, action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 100','i',[userId()]); ?>
        <div class="page-head"><div><p class="eyebrow">Unveränderbar</p><h1>Änderungsprotokoll</h1></div><span>Letzte 100</span></div><section class="panel table-wrap"><table><thead><tr><th>Zeit</th><th>Aktion</th><th>Bereich</th><th>ID</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= e(displayDateTime($log['created_at'], $currentUser)) ?></td><td><?= e($log['action']) ?></td><td><?= e($log['entity_type']) ?></td><td><?= (int)$log['entity_id'] ?></td></tr><?php endforeach; ?></tbody></table></section>
    <?php endif; ?>
<?php endif; ?>
</main>
<footer>JeMa Jobs Prototyp · Private Daten bleiben benutzerisoliert</footer>
</body></html>
