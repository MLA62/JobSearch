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
                "INSERT INTO users (email, password_hash, status, preferred_language, first_name, last_name) "
                . "VALUES (?, ?, 'invited', 'de', ?, ?)"
            );
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('ssss', $email, $hash, $first, $last);
            $stmt->execute();
            audit($db, (int) $stmt->insert_id, 'create', 'user', (int) $stmt->insert_id, null, ['email' => $email]);
            flash('Registrierung gespeichert. In der Prototypphase wird keine E-Mail verschickt; Freigabe und Bestätigung bleiben intern.');
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
        if ($user['status'] !== 'active') {
            flash('Dieses Konto ist noch nicht freigeschaltet.', 'warning');
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

    if ($action === 'preview_import') {
        $payload = trim((string) ($_POST['import_payload'] ?? ''));
        if ($payload === '') {
            flash('Bitte eine Stellen-URL oder den Ausschreibungstext einfügen.', 'danger');
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
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last = trim((string) ($_POST['last_name'] ?? ''));
        $language = in_array($_POST['preferred_language'] ?? '', ['de','en','es','pt'], true) ? (string) $_POST['preferred_language'] : 'de';
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
        $old = dbOne($db, 'SELECT first_name,last_name,preferred_language,timezone,phone,mobile,city,region,country_code FROM users WHERE id=?', 'i', [userId()]);
        $stmt = $db->prepare('UPDATE users SET first_name=?, last_name=?, preferred_language=?, timezone=?, phone=?, mobile=?, city=?, region=?, country_code=? WHERE id=?');
        $uid = userId();
        $stmt->bind_param('sssssssssi', $first, $last, $language, $timezone, $phone, $mobile, $city, $region, $country, $uid);
        $stmt->execute();
        $_SESSION['user_name'] = $first . ' ' . $last;
        audit($db, $uid, 'update', 'profile', $uid, $old, ['first_name'=>$first,'last_name'=>$last,'preferred_language'=>$language,'timezone'=>$timezone]);
        flash('Profil gespeichert.');
        redirect('/?page=profile');
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
        $region = trim((string) ($_POST['company_region'] ?? ''));
        $countryCode = strtoupper(substr(trim((string) ($_POST['company_country_code'] ?? '')), 0, 2));
        if ($name === '') {
            flash('Firmenname ist erforderlich.', 'danger');
            redirect('/?page=companies');
        }
        if ($id > 0) {
            $old = dbOne($db, 'SELECT * FROM companies WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
            if (!$old) {
                http_response_code(404); exit('Not found');
            }
            $stmt = $db->prepare('UPDATE companies SET name = ?, city = ?, website = ?, phone = ?, address_line1 = ?, address_line2 = ?, postal_code = ?, region = ?, country_code = ? WHERE id = ? AND owner_user_id = ?');
            $uid = userId();
            $stmt->bind_param('sssssssssii', $name, $city, $website, $phone, $addressLine1, $addressLine2, $postalCode, $region, $countryCode, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'company', $id, $old, ['name' => $name, 'city' => $city, 'website' => $website, 'phone' => $phone, 'address_line1' => $addressLine1]);
        } else {
            $stmt = $db->prepare('INSERT INTO companies (owner_user_id, name, city, website, phone, address_line1, address_line2, postal_code, region, country_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('isssssssss', $uid, $name, $city, $website, $phone, $addressLine1, $addressLine2, $postalCode, $region, $countryCode);
            $stmt->execute();
            $id = (int) $stmt->insert_id;
            audit($db, userId(), 'create', 'company', $id, null, ['name' => $name, 'city' => $city, 'website' => $website, 'phone' => $phone, 'address_line1' => $addressLine1]);
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
            $old = dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, source_url, SUBSTRING(description,1,65535) description FROM jobs WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
            if (!$old) { http_response_code(404); exit('Not found'); }
            $stmt = $db->prepare('UPDATE jobs SET company_id=?, title=?, location_text=?, description=?, status=?, workplace_type=?, engagement_type=?, contract_term=?, fixed_term_start=?, fixed_term_end=?, source_url=? WHERE id=? AND owner_user_id=?');
            $uid = userId();
            $stmt->bind_param('issssssssssii', $companyId, $title, $location, $description, $status, $workplace, $engagementType, $contractTerm, $fixedTermStart, $fixedTermEnd, $sourceUrl, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'job', $id, $old, ['title' => $title, 'status' => $status]);
        } else {
            $stmt = $db->prepare('INSERT INTO jobs (owner_user_id, company_id, title, location_text, description, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, source_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('iissssssssss', $uid, $companyId, $title, $location, $description, $status, $workplace, $engagementType, $contractTerm, $fixedTermStart, $fixedTermEnd, $sourceUrl);
            $stmt->execute();
            $id = (int) $stmt->insert_id;
            audit($db, userId(), 'create', 'job', $id, null, ['title' => $title, 'status' => $status]);
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
            $intermediary = dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$intermediaryCompanyId, userId()]);
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
        $nextAction = trim((string) ($_POST['next_action'] ?? '')) ?: null;
        $nextActionAt = trim((string) ($_POST['next_action_at'] ?? '')) ?: null;
        $emailSubject = trim((string) ($_POST['email_subject'] ?? '')) ?: null;
        $emailBody = trim((string) ($_POST['email_body'] ?? '')) ?: null;
        $coverLetter = trim((string) ($_POST['cover_letter_text'] ?? '')) ?: null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $primaryContactId = (int) ($_POST['primary_contact_id'] ?? 0);
        $intermediaryCompanyId = (int) ($_POST['intermediary_company_id'] ?? 0);
        if ($intermediaryCompanyId > 0) {
            $jobCompany = dbOne($db, 'SELECT company_id FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$old['job_id'], userId()]);
            $intermediary = dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$intermediaryCompanyId, userId()]);
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
            audit($db, $uid, 'delete', 'application', $id, $old, null);
        }
        flash('Bewerbung gelöscht.');
        redirect('/?page=applications');
    }
}

$currentUser = userId() ? dbOne($db, 'SELECT * FROM users WHERE id = ?', 'i', [userId()]) : null;
if ($currentUser && in_array($currentUser['timezone'], timezone_identifiers_list(), true)) {
    date_default_timezone_set($currentUser['timezone']);
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
    <?php elseif ($page === 'profile'): ?>
        <div class="page-head"><div><p class="eyebrow">Konto</p><h1>Eigenes Profil</h1></div><span><?= e($currentUser['email']) ?></span></div>
        <section class="panel"><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <div class="two"><label>Vorname<input name="first_name" value="<?= e($currentUser['first_name']) ?>" required></label><label>Nachname<input name="last_name" value="<?= e($currentUser['last_name']) ?>" required></label></div>
            <label>E-Mail<input value="<?= e($currentUser['email']) ?>" disabled><small>Prototyp-Regel: Es werden keine E-Mails verschickt. Änderungen der Login-E-Mail bleiben bis zur Versandfreigabe deaktiviert.</small></label>
            <div class="two"><label>Sprache<select name="preferred_language"><?php foreach(['de'=>'Deutsch','en'=>'English','es'=>'Español','pt'=>'Português'] as $v=>$l): ?><option value="<?= $v ?>" <?= $currentUser['preferred_language']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><label>Zeitzone<select name="timezone"><?php foreach(timezoneChoices() as $continent=>$zones): ?><optgroup label="<?= e($continent) ?>"><?php foreach($zones as $zone=>$label): ?><option value="<?= e($zone) ?>" <?= $currentUser['timezone']===$zone?'selected':'' ?>><?= e($label) ?> (<?= e($zone) ?>)</option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label></div>
            <div class="two"><label>Telefon<input name="phone" value="<?= e($currentUser['phone']) ?>"></label><label>Mobil<input name="mobile" value="<?= e($currentUser['mobile']) ?>"></label></div>
            <div class="three"><label>Ort<input name="city" value="<?= e($currentUser['city']) ?>"></label><label>Region<select name="region_key" id="profile-region"><option value="">Nicht gewählt</option><?php foreach(regionChoices() as $countryCode=>$regions): ?><optgroup label="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>"><?php foreach($regions as $region): $selectedRegion = $currentUser['region']===$region && $currentUser['country_code']===$countryCode; ?><option value="<?= e($countryCode . '|' . $region) ?>" data-country="<?= e($countryCode) ?>" data-country-name="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>" <?= $selectedRegion?'selected':'' ?>><?= e($region) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><label>Land<output id="profile-country-display" class="readonly-value"><?= e(countryChoices()[$currentUser['country_code']] ?? 'Ergibt sich aus Region') ?></output></label></div>
            <button class="primary" name="action" value="save_profile">Profil speichern</button>
        </form></section>
        <script>
        (() => {
            const region = document.getElementById('profile-region');
            const country = document.getElementById('profile-country-display');
            if (!region || !country) return;
            const syncCountry = () => {
                const selected = region.options[region.selectedIndex];
                country.textContent = selected ? (selected.dataset.countryName || 'Ergibt sich aus Region') : 'Ergibt sich aus Region';
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
        <div class="split"><section class="panel"><h2><?= $edit ? 'Firma bearbeiten' : 'Neue Firma' ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><label>Name<input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label><label>Haupttelefon<input name="company_phone" value="<?= e($edit['phone'] ?? '') ?>"></label><label>Adresse<textarea name="address" rows="3" placeholder="Strasse und Nummer&#10;Adresszusatz"><?= e(trim((string)($edit['address_line1'] ?? '') . "\n" . (string)($edit['address_line2'] ?? ''))) ?></textarea></label><div class="two"><label>PLZ<input name="postal_code" value="<?= e($edit['postal_code'] ?? '') ?>"></label><label>Ort<input name="city" value="<?= e($edit['city'] ?? '') ?>"></label></div><div class="two"><label>Region<input name="company_region" value="<?= e($edit['region'] ?? '') ?>"></label><label>Land<input name="company_country_code" maxlength="2" value="<?= e($edit['country_code'] ?? '') ?>"></label></div><label>Website<input type="url" name="website" value="<?= e($edit['website'] ?? '') ?>"></label><button class="primary" name="action" value="save_company">Speichern</button></form></section>
        <section class="panel table-wrap"><table><thead><tr><th>Firma</th><th>Adresse / Telefon</th><th>Rolle / Vermittlung</th><th>Verknüpfungen</th><th>Aktionen</th></tr></thead><tbody><?php foreach($companyRows as $company): ?><tr class="<?= $edit && (int)$edit['id']===(int)$company['id']?'is-selected':'' ?>"><td><strong><a class="record-link" href="/?page=companies&edit=<?= (int)$company['id'] ?>"><?= e($company['name']) ?></a></strong><small><?= e($company['website']) ?></small></td><td><?php if($company['address_line1']): ?><small><?= nl2br(e(trim((string)$company['address_line1'] . "\n" . (string)$company['address_line2']))) ?></small><?php endif; ?><?php if($company['city']): ?><small><?= e($company['city']) ?></small><?php endif; ?><?php if($company['phone']): ?><small><?= e($company['phone']) ?></small><?php endif; ?></td><td class="relationship-cell"><?php if($company['mediated_clients']): ?><span class="badge role-badge">Vermittler</span><small>Vermittelt: <?php foreach(explode('||', $company['mediated_clients']) as $entry): [$id,$name]=array_pad(explode('::',$entry,2),2,''); ?><a href="/?page=companies&edit=<?= (int)$id ?>"><?= e($name) ?></a><?php endforeach; ?></small><?php endif; ?><?php if($company['mediated_by']): ?><span class="badge">Vermittelt</span><small>durch: <?php foreach(explode('||', $company['mediated_by']) as $entry): [$id,$name]=array_pad(explode('::',$entry,2),2,''); ?><a href="/?page=companies&edit=<?= (int)$id ?>"><?= e($name) ?></a><?php endforeach; ?></small><?php endif; ?><?php if(!$company['mediated_clients'] && !$company['mediated_by']): ?><small>Direkte Firma / keine Vermittlung erfasst</small><?php endif; ?></td><td class="link-list"><a href="/?page=jobs&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['job_count'] ?> Jobs</a><a href="/?page=applications&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['application_count'] ?> Bewerbungen</a><a href="/?page=contacts&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['contact_count'] ?> Kontakte</a></td><td class="actions"><form method="post" onsubmit="return confirm('Firma löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$company['id'] ?>"><button name="action" value="delete_company">Löschen</button></form></td></tr><?php endforeach; ?></tbody></table></section></div>
    <?php elseif ($page === 'jobs'): ?>
        <?php
        $q = trim((string)($_GET['q'] ?? '')); $status = (string)($_GET['status'] ?? ''); $blue = !empty($_GET['blue']); $companyFilter = (int)($_GET['company_id'] ?? 0);
        $sql = 'SELECT j.id, j.company_id, j.title, j.location_text, j.status, j.workplace_type, j.engagement_type, j.contract_term, j.fixed_term_start, j.fixed_term_end, j.source_url, j.salary_min, SUBSTRING(j.description,1,65535) description, j.updated_at, c.name company_name FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL'; $types='i'; $vals=[userId()];
        if ($companyFilter > 0) { $sql .= ' AND j.company_id=?'; $types.='i'; $vals[]=$companyFilter; }
        if ($q !== '') { $sql .= ' AND (j.title LIKE ? OR c.name LIKE ? OR j.location_text LIKE ?)'; $like="%$q%"; $types.='sss'; array_push($vals,$like,$like,$like); }
        if ($status !== '') { $sql .= ' AND j.status=?'; $types.='s'; $vals[]=$status; }
        if ($blue) { $sql .= " AND (j.employment_type IN ('temporary','part_time') OR j.title REGEXP 'Lager|Reinigung|Produktion|Bau|Service|Zustell|Verkauf')"; }
        $sql .= ' ORDER BY j.updated_at DESC'; $jobs=dbAll($db,$sql,$types,$vals);
        $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, source_url, SUBSTRING(description,1,65535) description FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
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
        <section class="panel import-panel"><h2>Schnellimport</h2><p>Stellen-URL oder kopierten E-Mail-/Ausschreibungstext einfügen. Vor dem Speichern bleibt alles bearbeitbar.</p><form method="post" class="import-form"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><textarea name="import_payload" rows="3" placeholder="https://… oder Titel, Firma, Ort und Ausschreibungstext" required></textarea><button class="primary" name="action" value="preview_import">Vorschlag erstellen</button></form></section>
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
        <section class="cards"><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); ?><article class="job-card <?= $edit && (int)$edit['id']===(int)$job['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($job['status']) ?></span><span class="score"><?= $score ?>%</span></div><h3><a class="record-link" href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new"><?= e($job['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$job['company_id'] ?>"><?= e($job['company_name']) ?></a> · <?= e($job['location_text']) ?></p><p class="meta-line"><?= $job['engagement_type']==='temporary'?'Temporärstelle':'Dauerstelle' ?> · <?= ['open_ended'=>'unbefristet','fixed_term'=>'befristet','unknown'=>'Dauer offen'][$job['contract_term']] ?? 'Dauer offen' ?></p><p><?= e(mb_strimwidth((string)$job['description'],0,180,'…')) ?></p><details><summary>Warum <?= $score ?>%?</summary><ul><?php foreach($reasons as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul></details><div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button class="primary-link" name="action" value="start_application">Bewerbung starten</button></form><a href="/?page=applications&job_id=<?= (int)$job['id'] ?>">Bewerbungen</a><form method="post" onsubmit="return confirm('Job löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$job['id'] ?>"><button name="action" value="delete_job">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$jobs): ?><div class="empty">Noch keine passenden Jobs vorhanden.</div><?php endif; ?></section></div>
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
        $applicationStatuses=['draft'=>'Entwurf','ready'=>'Bereit','sent'=>'Gesendet','confirmed'=>'Bestätigt','interview'=>'Interview','assessment'=>'Assessment','offer'=>'Angebot','accepted'=>'Angenommen','rejected'=>'Absage','withdrawn'=>'Zurückgezogen','closed'=>'Abgeschlossen'];
        $contactLogStatuses=['planned'=>'Geplant','open'=>'Offen','done'=>'Erledigt','cancelled'=>'Abgebrochen'];
        $channels=['email'=>'E-Mail','portal'=>'Jobportal','website'=>'Karriereseite','mail'=>'Post','referral'=>'Empfehlung','other'=>'Andere'];
        ?>
        <div class="page-head"><div><p class="eyebrow">Pipeline</p><h1>Bewerbungen</h1></div><span><?= count($apps) ?> Einträge</span></div>
        <?php if($appCompanyFilter || $appJobFilter || $todoOnly): ?><p class="filter-note">Gefilterte Ansicht · <a href="/?page=applications">Alle Bewerbungen anzeigen</a></p><?php endif; ?>
        <?php if ($applicationEdit): ?><section class="panel company-path" id="companies"><div><p class="eyebrow">Firmenbeziehung</p><h2><?= e($applicationEdit['company_name']) ?><?php if($applicationEdit['intermediary_company_name']): ?> <span>über <?= e($applicationEdit['intermediary_company_name']) ?></span><?php endif; ?></h2><p>Die Stelle gehört zur Kunden-/Arbeitgeberfirma. Optional kann eine Vermittler- oder Temporärfirma dazwischengeschaltet sein.</p></div><form method="post" class="company-path-form"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>"><label>Vermittlerfirma<select name="intermediary_company_id"><option value="0">Direktbewerbung ohne Vermittler</option><?php foreach($companies as $company): if((int)$company['id']===(int)$applicationEdit['company_id']) continue; ?><option value="<?= (int)$company['id'] ?>" <?= (int)$applicationEdit['intermediary_company_id']===(int)$company['id']?'selected':'' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select></label><button class="primary" name="action" value="set_intermediary">Zuordnung speichern</button></form></section><?php endif; ?>
        <?php if ($applicationEdit): ?><section class="panel application-editor" id="application-form"><div class="section-head"><div><p class="eyebrow"><?= e($applicationEdit['company_name']) ?></p><h2><?= e($applicationEdit['title']) ?></h2></div><a href="/?page=applications">Schließen</a></div><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$applicationEdit['id'] ?>"><div class="three"><label>Status<select name="status"><?php foreach($applicationStatuses as $v=>$l): ?><option value="<?= $v ?>" <?= $applicationEdit['status']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><label>Kanal<select name="channel"><option value="">Nicht gewählt</option><?php foreach($channels as $v=>$l): ?><option value="<?= $v ?>" <?= $applicationEdit['channel']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><label>Gesendet am<input type="datetime-local" name="applied_at" value="<?= e($applicationEdit['applied_at'] ? date('Y-m-d\TH:i', strtotime($applicationEdit['applied_at'])) : '') ?>"></label></div><label>Hauptkontakt<select name="primary_contact_id"><option value="0">Noch kein Kontakt gewählt</option><?php foreach($contacts as $contact): ?><option value="<?= (int)$contact['id'] ?>" <?= (int)$applicationEdit['primary_contact_id']===(int)$contact['id']?'selected':'' ?>><?= e($contact['first_name'].' '.$contact['last_name'].($contact['position'] ? ' · '.$contact['position'] : '')) ?></option><?php endforeach; ?></select></label><div class="two"><label>Nächster Schritt<input name="next_action" value="<?= e($applicationEdit['next_action'] ?? '') ?>" placeholder="z. B. telefonisch nachfragen"></label><label>Fällig am<input type="datetime-local" name="next_action_at" value="<?= e($applicationEdit['next_action_at'] ? date('Y-m-d\TH:i', strtotime($applicationEdit['next_action_at'])) : '') ?>"></label></div><label>Kommentar zur Statusänderung<input name="status_comment" placeholder="Optional, wird im Verlauf gespeichert"></label><?php if(!outboundEmailEnabled()): ?><p class="prototype-note">Prototyp: Es wird keine E-Mail verschickt. Betreff und Begleittext sind nur Entwürfe zum Kopieren.</p><?php endif; ?><label>E-Mail-Betreff<input name="email_subject" value="<?= e($applicationEdit['email_subject'] ?? '') ?>"></label><label>E-Mail-Begleittext<textarea name="email_body" rows="4"><?= e($applicationEdit['email_body'] ?? '') ?></textarea></label><label>Motivationsschreiben<textarea name="cover_letter_text" rows="7"><?= e($applicationEdit['cover_letter_text'] ?? '') ?></textarea></label><label>Interne Notizen<textarea name="notes" rows="4"><?= e($applicationEdit['notes'] ?? '') ?></textarea></label><button class="primary" name="action" value="save_application">Bewerbung speichern</button></form><?php if($history): ?><div class="history"><h3>Statusverlauf</h3><?php foreach($history as $entry): ?><article><strong><?= e($applicationStatuses[$entry['new_status']] ?? $entry['new_status']) ?></strong><span><?= e($entry['changed_at']) ?></span><?php if($entry['comment']): ?><p><?= e($entry['comment']) ?></p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?></section>
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
        <?php if($contactEdit): ?><section class="panel contact-log" id="contact-log"><div class="section-head"><div><p class="eyebrow">Kontakt-Log</p><h2><?= e($contactEdit['first_name'].' '.$contactEdit['last_name']) ?></h2></div></div><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>"><input type="hidden" name="contact_id" value="<?= (int)$contactEdit['id'] ?>"><div class="three"><label>Kanal<select name="log_channel"><?php foreach(['email'=>'E-Mail','phone'=>'Telefon','meeting'=>'Treffen','video'=>'Video','message'=>'Nachricht','letter'=>'Brief','note'=>'Notiz','other'=>'Andere'] as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></label><label>Richtung<select name="direction"><option value="outgoing">Ausgehend</option><option value="incoming">Eingehend</option><option value="internal">Intern</option></select></label><label>Status<select name="log_status"><option value="planned">Geplant</option><option value="open" selected>Offen</option><option value="done">Erledigt</option><option value="cancelled">Abgebrochen</option></select></label></div><div class="two"><label>Aktionszeitpunkt<input type="datetime-local" name="occurred_at" value="<?= date('Y-m-d\TH:i') ?>" required></label><label>Wiedervorlage / nächster Termin<input type="datetime-local" name="follow_up_at"></label></div><label>Betreff<input name="subject"></label><label>Inhalt<textarea name="log_body" rows="4"></textarea></label><label>Ergebnis / nächster Schritt<input name="outcome"></label><button class="primary" name="action" value="save_contact_log">Aktivität speichern</button></form><div class="log-timeline"><?php foreach($contactLogs as $entry): ?><article class="log-status-<?= e($entry['status']) ?>"><div><strong><?= e($entry['subject'] ?: ucfirst($entry['channel'])) ?></strong><span><?= e(($contactLogStatuses[$entry['status']] ?? $entry['status']).' · '.$entry['direction'].' · '.$entry['occurred_at']) ?></span></div><?php if($entry['body']): ?><p><?= nl2br(e($entry['body'])) ?></p><?php endif; ?><?php if($entry['outcome']): ?><small>Ergebnis / nächster Schritt: <?= e($entry['outcome']) ?></small><?php endif; ?><?php if($entry['follow_up_at']): ?><small>Wiedervorlage: <?= e($entry['follow_up_at']) ?></small><?php endif; ?></article><?php endforeach; ?><?php if(!$contactLogs): ?><p class="empty">Noch keine Kontaktaktivitäten.</p><?php endif; ?></div></section><?php endif; ?>
        <?php endif; ?>
        <section class="application-list"><?php foreach($apps as $app): ?><article class="application-card <?= $applicationEdit && (int)$applicationEdit['id']===(int)$app['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($applicationStatuses[$app['status']] ?? $app['status']) ?></span><?php if($app['next_action_at']): ?><span class="due"><?= e(date('d.m.Y H:i', strtotime($app['next_action_at']))) ?></span><?php endif; ?></div><h3><a href="/?page=jobs&edit=<?= (int)$app['job_id'] ?>#new"><?= e($app['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$app['company_id'] ?>"><?= e($app['company_name']) ?></a><?php if($app['intermediary_company_name']): ?> · über <a href="/?page=companies&edit=<?= (int)$app['intermediary_company_id'] ?>"><?= e($app['intermediary_company_name']) ?></a><?php endif; ?></p><?php if($app['next_action']): ?><p><strong>Nächster Schritt:</strong> <?= e($app['next_action']) ?></p><?php endif; ?><div class="actions"><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form">Bearbeiten</a><form method="post" onsubmit="return confirm('Bewerbung löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$app['id'] ?>"><button name="action" value="delete_application">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$apps): ?><div class="panel empty"><h2>Noch keine Bewerbungen</h2><p>Öffne Jobs und wähle bei einer passenden Stelle „Bewerbung starten“.</p><a class="button primary" href="/?page=jobs">Zu den Jobs</a></div><?php endif; ?></section>
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
    <?php elseif ($page === 'audit'): ?>
        <?php $logs=dbAll($db,'SELECT id, action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 100','i',[userId()]); ?>
        <div class="page-head"><div><p class="eyebrow">Unveränderbar</p><h1>Änderungsprotokoll</h1></div><span>Letzte 100</span></div><section class="panel table-wrap"><table><thead><tr><th>Zeit</th><th>Aktion</th><th>Bereich</th><th>ID</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= e($log['created_at']) ?></td><td><?= e($log['action']) ?></td><td><?= e($log['entity_type']) ?></td><td><?= (int)$log['entity_id'] ?></td></tr><?php endforeach; ?></tbody></table></section>
    <?php endif; ?>
<?php endif; ?>
</main>
<footer>JeMa Jobs Prototyp · Private Daten bleiben benutzerisoliert</footer>
</body></html>
