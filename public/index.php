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
            flash('Registrierung gespeichert. E-Mail-Bestätigung und Admin-Freigabe folgen im nächsten Ausbau.');
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

    if ($action === 'save_company') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) $_POST['name']);
        $city = trim((string) $_POST['city']);
        $website = trim((string) $_POST['website']);
        if ($name === '') {
            flash('Firmenname ist erforderlich.', 'danger');
            redirect('/?page=companies');
        }
        if ($id > 0) {
            $old = dbOne($db, 'SELECT * FROM companies WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
            if (!$old) {
                http_response_code(404); exit('Not found');
            }
            $stmt = $db->prepare('UPDATE companies SET name = ?, city = ?, website = ? WHERE id = ? AND owner_user_id = ?');
            $uid = userId();
            $stmt->bind_param('sssii', $name, $city, $website, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'company', $id, $old, ['name' => $name, 'city' => $city, 'website' => $website]);
        } else {
            $stmt = $db->prepare('INSERT INTO companies (owner_user_id, name, city, website) VALUES (?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('isss', $uid, $name, $city, $website);
            $stmt->execute();
            $id = (int) $stmt->insert_id;
            audit($db, userId(), 'create', 'company', $id, null, ['name' => $name, 'city' => $city, 'website' => $website]);
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
            $old = dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, source_url, SUBSTRING(description,1,65535) description FROM jobs WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
            if (!$old) { http_response_code(404); exit('Not found'); }
            $stmt = $db->prepare('UPDATE jobs SET company_id=?, title=?, location_text=?, description=?, status=?, workplace_type=?, source_url=? WHERE id=? AND owner_user_id=?');
            $uid = userId();
            $stmt->bind_param('issssssii', $companyId, $title, $location, $description, $status, $workplace, $sourceUrl, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'job', $id, $old, ['title' => $title, 'status' => $status]);
        } else {
            $stmt = $db->prepare('INSERT INTO jobs (owner_user_id, company_id, title, location_text, description, status, workplace_type, source_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('iissssss', $uid, $companyId, $title, $location, $description, $status, $workplace, $sourceUrl);
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
}

$currentUser = userId() ? dbOne($db, 'SELECT * FROM users WHERE id = ?', 'i', [userId()]) : null;
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
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">JeMa <strong>Jobs</strong></a>
    <?php if ($currentUser): ?>
        <button class="menu-button" type="button" onclick="document.body.classList.toggle('nav-open')">Menü</button>
        <nav>
            <a href="/?page=dashboard">Übersicht</a>
            <a href="/?page=jobs">Jobs</a>
            <a href="/?page=companies">Firmen</a>
            <a href="/?page=applications">Bewerbungen</a>
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
            'Jobs' => dbOne($db, 'SELECT COUNT(*) c FROM jobs WHERE owner_user_id=? AND deleted_at IS NULL', 'i', [userId()])['c'],
            'Firmen' => count($companies),
            'Bewerbungen' => dbOne($db, 'SELECT COUNT(*) c FROM applications WHERE user_id=? AND deleted_at IS NULL', 'i', [userId()])['c'],
            'Offene Schritte' => dbOne($db, 'SELECT COUNT(*) c FROM applications WHERE user_id=? AND next_action_at IS NOT NULL AND deleted_at IS NULL', 'i', [userId()])['c'],
        ]; ?>
        <div class="hero"><div><p class="eyebrow">Guten Tag, <?= e($currentUser['first_name']) ?></p><h1>Deine Jobs. Dein Prozess.</h1><p>Privat, strukturiert und auf allen Geräten nutzbar.</p></div><a class="button primary" href="/?page=jobs#new">Job erfassen</a></div>
        <div class="stats"><?php foreach ($stats as $label => $value): ?><article><strong><?= e((string) $value) ?></strong><span><?= e($label) ?></span></article><?php endforeach; ?></div>
        <section class="panel"><h2>Nächste Schritte</h2><p>Erfasse zuerst eine Firma und danach passende Stellen. Der Prototyp berechnet bereits einen transparenten Basis-Match und erkennt mögliche Dubletten.</p></section>
    <?php elseif ($page === 'companies'): ?>
        <?php $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT * FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null; ?>
        <div class="page-head"><div><p class="eyebrow">CRM</p><h1>Firmen</h1></div><span><?= count($companies) ?> Einträge</span></div>
        <div class="split"><section class="panel"><h2><?= $edit ? 'Firma bearbeiten' : 'Neue Firma' ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><label>Name<input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label><label>Ort<input name="city" value="<?= e($edit['city'] ?? '') ?>"></label><label>Website<input type="url" name="website" value="<?= e($edit['website'] ?? '') ?>"></label><button class="primary" name="action" value="save_company">Speichern</button></form></section>
        <section class="panel table-wrap"><table><thead><tr><th>Firma</th><th>Ort</th><th>Aktionen</th></tr></thead><tbody><?php foreach($companies as $company): ?><tr><td><strong><?= e($company['name']) ?></strong><small><?= e($company['website']) ?></small></td><td><?= e($company['city']) ?></td><td class="actions"><a href="/?page=companies&edit=<?= (int)$company['id'] ?>">Bearbeiten</a><form method="post" onsubmit="return confirm('Firma löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$company['id'] ?>"><button name="action" value="delete_company">Löschen</button></form></td></tr><?php endforeach; ?></tbody></table></section></div>
    <?php elseif ($page === 'jobs'): ?>
        <?php
        $q = trim((string)($_GET['q'] ?? '')); $status = (string)($_GET['status'] ?? ''); $blue = !empty($_GET['blue']);
        $sql = 'SELECT j.id, j.company_id, j.title, j.location_text, j.status, j.workplace_type, j.source_url, j.salary_min, SUBSTRING(j.description,1,65535) description, j.updated_at, c.name company_name FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL'; $types='i'; $vals=[userId()];
        if ($q !== '') { $sql .= ' AND (j.title LIKE ? OR c.name LIKE ? OR j.location_text LIKE ?)'; $like="%$q%"; $types.='sss'; array_push($vals,$like,$like,$like); }
        if ($status !== '') { $sql .= ' AND j.status=?'; $types.='s'; $vals[]=$status; }
        if ($blue) { $sql .= " AND (j.employment_type IN ('temporary','part_time') OR j.title REGEXP 'Lager|Reinigung|Produktion|Bau|Service|Zustell|Verkauf')"; }
        $sql .= ' ORDER BY j.updated_at DESC'; $jobs=dbAll($db,$sql,$types,$vals);
        $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, source_url, SUBSTRING(description,1,65535) description FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
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
        <form class="filters" method="get"><input type="hidden" name="page" value="jobs"><input name="q" value="<?= e($q) ?>" placeholder="Titel, Firma oder Ort"><select name="status"><option value="">Alle Status</option><?php foreach(['open'=>'Offen','interesting'=>'Interessant','applied'=>'Beworben','interview'=>'Interview','offer'=>'Angebot','rejected'=>'Absage','closed'=>'Geschlossen'] as $v=>$l): ?><option value="<?= $v ?>" <?= $status===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select><label class="check"><input type="checkbox" name="blue" value="1" <?= $blue?'checked':'' ?>> Blue-Collar/Ungelernt</label><button>Filtern</button></form>
        <div class="split"><section class="panel" id="new"><h2><?= $edit ? 'Job bearbeiten' : ($draft ? 'Import prüfen' : 'Job erfassen') ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><label>Firma<select name="company_id"><option value="0">Neue Firma aus Import</option><?php foreach($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($form['company_id']??$matchedCompanyId)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label><label>Neue Firma<input name="new_company_name" value="<?= e($matchedCompanyId ? '' : $draftCompany) ?>" placeholder="Nur ausfüllen, wenn die Firma noch fehlt"></label><label>Jobtitel<input name="title" value="<?= e($form['title'] ?? '') ?>" required></label><div class="two"><label>Ort<input name="location_text" value="<?= e($form['location_text'] ?? $form['location'] ?? '') ?>"></label><label>Arbeitsmodell<select name="workplace_type"><?php foreach(['unknown'=>'Unbekannt','onsite'=>'Vor Ort','hybrid'=>'Hybrid','remote'=>'Remote'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($form['workplace_type']??'unknown')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label></div><label>Status<select name="status"><?php foreach(['open'=>'Offen','interesting'=>'Interessant','applied'=>'Beworben','interview'=>'Interview','offer'=>'Angebot','rejected'=>'Absage','closed'=>'Geschlossen'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($form['status']??'open')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label><label>Quell-URL<input type="url" name="source_url" value="<?= e($form['source_url'] ?? '') ?>"></label><label>Beschreibung<textarea name="description" rows="6"><?= e($form['description'] ?? '') ?></textarea></label><?php if(!empty($_GET['duplicate'])): ?><label class="check"><input type="checkbox" name="confirm_duplicate" value="1" required> Als separate Stelle speichern</label><?php endif; ?><button class="primary" name="action" value="save_job">Speichern</button></form></section>
        <section class="cards"><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); ?><article class="job-card"><div class="job-top"><span class="badge"><?= e($job['status']) ?></span><span class="score"><?= $score ?>%</span></div><h3><?= e($job['title']) ?></h3><p class="company"><?= e($job['company_name']) ?> · <?= e($job['location_text']) ?></p><p><?= e(mb_strimwidth((string)$job['description'],0,180,'…')) ?></p><details><summary>Warum <?= $score ?>%?</summary><ul><?php foreach($reasons as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul></details><div class="actions"><a href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new">Bearbeiten</a><form method="post" onsubmit="return confirm('Job löschen?')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$job['id'] ?>"><button name="action" value="delete_job">Löschen</button></form></div></article><?php endforeach; ?><?php if(!$jobs): ?><div class="empty">Noch keine passenden Jobs vorhanden.</div><?php endif; ?></section></div>
    <?php elseif ($page === 'applications'): ?>
        <?php $apps=dbAll($db,'SELECT a.id, a.status, a.applied_at, a.next_action, a.next_action_at, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC','i',[userId()]); ?>
        <div class="page-head"><div><p class="eyebrow">Pipeline</p><h1>Bewerbungen</h1></div><span><?= count($apps) ?> Einträge</span></div>
        <section class="panel">
            <p>Im Prototyp werden Bewerbungen angezeigt, sobald sie über die Datenbank oder den nächsten Ausbau angelegt wurden.</p>
            <div class="kanban">
                <?php foreach (['draft'=>'Entwurf','sent'=>'Gesendet','interview'=>'Interview','offer'=>'Angebot','rejected'=>'Absage'] as $s => $label): ?>
                    <div>
                        <h3><?= $label ?></h3>
                        <?php foreach ($apps as $app): ?>
                            <?php if ($app['status'] === $s): ?>
                                <article><strong><?= e($app['title']) ?></strong><small><?= e($app['company_name']) ?></small></article>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif ($page === 'audit'): ?>
        <?php $logs=dbAll($db,'SELECT id, action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 100','i',[userId()]); ?>
        <div class="page-head"><div><p class="eyebrow">Unveränderbar</p><h1>Änderungsprotokoll</h1></div><span>Letzte 100</span></div><section class="panel table-wrap"><table><thead><tr><th>Zeit</th><th>Aktion</th><th>Bereich</th><th>ID</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= e($log['created_at']) ?></td><td><?= e($log['action']) ?></td><td><?= e($log['entity_type']) ?></td><td><?= (int)$log['entity_id'] ?></td></tr><?php endforeach; ?></tbody></table></section>
    <?php endif; ?>
<?php endif; ?>
</main>
<footer>JeMa Jobs Prototyp · Private Daten bleiben benutzerisoliert</footer>
</body></html>
