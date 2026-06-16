<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$options = getopt('', ['limit::', 'browser::', 'timeout::', 'dry-run', 'help']);
if (isset($options['help'])) {
    echo "Usage: php deploy/render-pending-job-pdfs.php [--limit=5] [--browser=/path/to/chrome] [--timeout=60] [--dry-run]\n";
    exit(0);
}

$configuredPath = getenv('JEMA_JOBS_CONFIG') ?: '';
$configCandidates = array_filter([
    $configuredPath,
    __DIR__ . '/../public/config.php',
    __DIR__ . '/../config.php',
]);
$configPath = '';
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $configPath = $candidate;
        break;
    }
}
if ($configPath === '') {
    fwrite(STDERR, "Application configuration is missing. Set JEMA_JOBS_CONFIG or create public/config.php.\n");
    exit(1);
}
$config = require $configPath;
$publicRoot = realpath(dirname($configPath));
if (!$publicRoot) {
    fwrite(STDERR, "Could not resolve public directory.\n");
    exit(1);
}

$limit = max(1, min(50, (int) ($options['limit'] ?? ($config['job_pdf_render_limit'] ?? 5))));
$timeout = max(10, min(300, (int) ($options['timeout'] ?? ($config['job_pdf_render_timeout'] ?? 60))));
$dryRun = isset($options['dry-run']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_password'],
    $config['db_name'],
    (int) $config['db_port']
);
$db->set_charset('utf8mb4');

function dbOne(mysqli $db, string $sql, string $types = '', array $values = []): ?array
{
    $rows = dbAll($db, $sql, $types, $values, 1);
    return $rows[0] ?? null;
}

function dbAll(mysqli $db, string $sql, string $types = '', array $values = [], ?int $limit = null): array
{
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
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

function acquireRendererLock(mysqli $db): bool
{
    $lockName = 'jema_jobs_original_pdf_renderer';
    $stmt = $db->prepare('SELECT GET_LOCK(?, 0) acquired');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $rows = dbAllFromStatement($stmt, 1);
    if ((int) ($rows[0]['acquired'] ?? 0) !== 1) {
        return false;
    }
    register_shutdown_function(static function () use ($db, $lockName): void {
        try {
            $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->bind_param('s', $lockName);
            $stmt->execute();
        } catch (Throwable) {
            // Nothing useful can be reported during shutdown.
        }
    });
    return true;
}

function dbAllFromStatement(mysqli_stmt $stmt, ?int $limit = null): array
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

function publicHttpUrl(string $url): bool
{
    $parts = parse_url($url);
    if (!$parts || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return false;
    }
    $host = (string) ($parts['host'] ?? '');
    if ($host === '') {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
}

function findBrowser(?string $configured): string
{
    $candidates = array_filter([
        $configured,
        getenv('JEMA_JOBS_BROWSER') ?: null,
        '/usr/bin/chromium-browser',
        '/usr/bin/chromium',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/snap/bin/chromium',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    ]);
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    throw new RuntimeException('No Chromium-compatible browser found. Configure job_pdf_browser_path or pass --browser.');
}

function ensureDocumentStorage(string $publicRoot, int $userId): string
{
    $root = $publicRoot . '/storage/documents';
    if (!is_dir($root) && !mkdir($root, 0775, true)) {
        throw new RuntimeException('Document storage root could not be created.');
    }
    $deny = dirname($root) . '/.htaccess';
    if (!is_file($deny)) {
        file_put_contents($deny, "Require all denied\n");
    }
    $dir = $root . '/' . $userId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('User document directory could not be created.');
    }
    return $dir;
}

function renderUrlToPdf(string $browser, string $url, string $target, int $timeout): void
{
    $profileDir = sys_get_temp_dir() . '/jema-jobs-chrome-' . bin2hex(random_bytes(8));
    if (!mkdir($profileDir, 0700, true)) {
        throw new RuntimeException('Temporary browser profile could not be created.');
    }
    $command = [
        $browser,
        '--headless',
        '--disable-gpu',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--hide-scrollbars',
        '--run-all-compositor-stages-before-draw',
        '--virtual-time-budget=10000',
        '--user-data-dir=' . $profileDir,
        '--print-to-pdf=' . $target,
        $url,
    ];
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($escaped, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        removeDirectory($profileDir);
        throw new RuntimeException('Browser process could not be started.');
    }
    $start = time();
    $stdout = '';
    $stderr = '';
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }
    do {
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        if (time() - $start > $timeout) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
            removeDirectory($profileDir);
            throw new RuntimeException('Browser render timed out.');
        }
        usleep(100000);
    } while (true);
    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }
    $exitCode = proc_close($process);
    removeDirectory($profileDir);
    if ($exitCode !== 0 || !is_file($target) || filesize($target) < 1000) {
        $message = trim($stderr ?: $stdout) ?: 'Browser did not produce a valid PDF.';
        throw new RuntimeException(mb_strimwidth($message, 0, 1000, '...'));
    }
}

function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function audit(mysqli $db, int $userId, string $action, string $entityType, int $entityId, array $newValues): void
{
    $old = null;
    $json = json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('ississ', $userId, $action, $entityType, $entityId, $old, $json);
    $stmt->execute();
}

if (!acquireRendererLock($db)) {
    echo json_encode([
        'checked' => 0,
        'rendered' => 0,
        'failed' => 0,
        'skipped' => 0,
        'dry_run' => $dryRun,
        'locked' => true,
        'results' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$browser = $dryRun ? '' : findBrowser((string) ($options['browser'] ?? ($config['job_pdf_browser_path'] ?? '')));
$documentType = dbOne($db, "SELECT id FROM document_types WHERE code='other' LIMIT 1");
if (!$documentType) {
    throw new RuntimeException('Document type "other" is missing.');
}
$documentTypeId = (int) $documentType['id'];

$jobs = dbAll(
    $db,
    'SELECT j.id, j.owner_user_id, j.title, j.source_url
       FROM jobs j
      WHERE j.deleted_at IS NULL
        AND j.original_pdf_status = "pending"
        AND j.source_url IS NOT NULL
        AND j.source_url <> ""
        AND NOT EXISTS (
            SELECT 1 FROM user_documents d
             WHERE d.user_id = j.owner_user_id
               AND d.job_id = j.id
               AND d.title = "Originale Stellenausschreibung"
               AND d.deleted_at IS NULL
        )
      ORDER BY COALESCE(j.original_pdf_requested_at, j.created_at), j.id
      LIMIT ?',
    'i',
    [$limit]
);

$summary = ['checked' => count($jobs), 'rendered' => 0, 'failed' => 0, 'skipped' => 0, 'dry_run' => $dryRun, 'results' => []];

foreach ($jobs as $job) {
    $jobId = (int) $job['id'];
    $userId = (int) $job['owner_user_id'];
    $url = trim((string) $job['source_url']);
    if (!publicHttpUrl($url)) {
        $summary['skipped']++;
        $summary['results'][] = ['job_id' => $jobId, 'status' => 'skipped', 'error' => 'Source URL is not public HTTP(S).'];
        if (!$dryRun) {
            $error = 'Source URL is not public HTTP(S).';
            $stmt = $db->prepare("UPDATE jobs SET original_pdf_status='failed', original_pdf_error=?, original_pdf_rendered_at=NULL WHERE id=? AND owner_user_id=?");
            $stmt->bind_param('sii', $error, $jobId, $userId);
            $stmt->execute();
        }
        continue;
    }

    if ($dryRun) {
        $summary['results'][] = ['job_id' => $jobId, 'status' => 'would_render', 'url' => $url];
        continue;
    }

    $target = '';
    try {
        $dir = ensureDocumentStorage($publicRoot, $userId);
        $storedName = bin2hex(random_bytes(18)) . '.pdf';
        $target = $dir . '/' . $storedName;
        renderUrlToPdf($browser, $url, $target, $timeout);

        $relativePath = 'storage/documents/' . $userId . '/' . $storedName;
        $fileName = 'job-' . $jobId . '-original-rendered-' . date('Ymd-His') . '.pdf';
        $scope = 'application';
        $applicationId = null;
        $languageCode = null;
        $title = 'Originale Stellenausschreibung';
        $description = 'Browser-gerenderte Sicherung der ursprünglichen Stellenausschreibung.';
        $mime = 'application/pdf';
        $size = filesize($target) ?: 0;
        $sha = hash_file('sha256', $target);
        $version = 1;

        $stmt = $db->prepare('INSERT INTO user_documents (user_id, document_type_id, language_code, scope, application_id, job_id, title, description, original_filename, storage_path, mime_type, file_size, sha256, valid_from, valid_until, version, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, 1)');
        $stmt->bind_param('iissiisssssisi', $userId, $documentTypeId, $languageCode, $scope, $applicationId, $jobId, $title, $description, $fileName, $relativePath, $mime, $size, $sha, $version);
        $stmt->execute();
        $documentId = (int) $stmt->insert_id;

        $stmt = $db->prepare("UPDATE jobs SET original_pdf_status='rendered', original_pdf_rendered_at=NOW(), original_pdf_error=NULL WHERE id=? AND owner_user_id=?");
        $stmt->bind_param('ii', $jobId, $userId);
        $stmt->execute();
        audit($db, $userId, 'create', 'user_document', $documentId, [
            'title' => $title,
            'job_id' => $jobId,
            'source_url' => $url,
            'renderer' => 'browser',
        ]);

        $summary['rendered']++;
        $summary['results'][] = ['job_id' => $jobId, 'status' => 'rendered', 'document_id' => $documentId, 'file_size' => $size];
    } catch (Throwable $exception) {
        if ($target !== '' && is_file($target)) {
            unlink($target);
        }
        $error = mb_strimwidth($exception->getMessage(), 0, 2000, '...');
        $stmt = $db->prepare("UPDATE jobs SET original_pdf_status='failed', original_pdf_error=?, original_pdf_rendered_at=NULL WHERE id=? AND owner_user_id=?");
        $stmt->bind_param('sii', $error, $jobId, $userId);
        $stmt->execute();
        $summary['failed']++;
        $summary['results'][] = ['job_id' => $jobId, 'status' => 'failed', 'error' => $error];
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
