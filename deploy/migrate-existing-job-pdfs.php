<?php

declare(strict_types=1);

const MIGRATION_TOKEN = 'codex-migrate-20260616-existing-job-pdfs';

if (($_GET['token'] ?? '') !== MIGRATION_TOKEN) {
    http_response_code(403);
    exit('Forbidden');
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Application configuration is missing.');
}
$config = require $configPath;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_password'],
    $config['db_name'],
    (int) $config['db_port']
);
$db->set_charset('utf8mb4');

function publicHttpUrl(string $url): bool
{
    $parts = parse_url($url);
    if (!$parts || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return false;
    }
    $host = (string) ($parts['host'] ?? '');
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
        return false;
    }
    return true;
}

function storageRoot(): string
{
    return __DIR__ . '/storage/documents';
}

function ensureDocumentStorage(int $userId): void
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
}

function pdfEscape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

function createSimplePdf(string $title, array $lines): string
{
    $content = "BT\n/F1 12 Tf\n50 790 Td\n14 TL\n";
    foreach (array_merge([$title, ''], $lines) as $line) {
        $wrapped = preg_split('/\R/u', wordwrap(trim($line), 90, "\n", true)) ?: [];
        foreach ($wrapped as $part) {
            $content .= '(' . pdfEscape(mb_strimwidth($part, 0, 180, '...')) . ") Tj\nT*\n";
        }
    }
    $content .= "ET\n";
    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
        "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
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

function statementRows(mysqli_stmt $stmt): array
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
    }
    return $rows;
}

function audit(mysqli $db, int $userId, string $action, string $entityType, int $entityId, ?array $new): void
{
    $json = $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $old = null;
    $stmt = $db->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('ississ', $userId, $action, $entityType, $entityId, $old, $json);
    $stmt->execute();
}

$typeRow = dbAll($db, "SELECT id FROM document_types WHERE code='other' LIMIT 1");
if (!$typeRow) {
    http_response_code(500);
    exit('document type other missing');
}
$documentTypeId = (int) $typeRow[0]['id'];

$jobs = dbAll(
    $db,
    'SELECT j.id, j.owner_user_id, j.title, j.source_url, SUBSTRING(j.description,1,65535) description
       FROM jobs j
      WHERE j.deleted_at IS NULL
        AND j.source_url IS NOT NULL
        AND j.source_url <> ""
        AND NOT EXISTS (
            SELECT 1 FROM user_documents d
             WHERE d.user_id = j.owner_user_id
               AND d.job_id = j.id
               AND d.title = "Originale Stellenausschreibung"
               AND d.deleted_at IS NULL
        )
      ORDER BY j.id'
);

$created = 0;
$skipped = 0;
$failed = [];

foreach ($jobs as $job) {
    $jobId = (int) $job['id'];
    $userId = (int) $job['owner_user_id'];
    $sourceUrl = trim((string) $job['source_url']);
    if (!publicHttpUrl($sourceUrl)) {
        $skipped++;
        continue;
    }
    try {
        ensureDocumentStorage($userId);
        $relativePath = 'storage/documents/' . $userId . '/' . bin2hex(random_bytes(18)) . '.pdf';
        $target = __DIR__ . '/' . $relativePath;
        $fileName = 'original-job-' . $jobId . '-' . date('Ymd-His') . '.pdf';
        $pdf = createSimplePdf('Originale Stellenausschreibung', [
            'Job: ' . (string) $job['title'],
            'Quelle: ' . $sourceUrl,
            'Nachträglich migriert am: ' . date('c'),
            '',
            mb_strimwidth((string) $job['description'], 0, 5000, '...'),
        ]);
        if (file_put_contents($target, $pdf) === false) {
            throw new RuntimeException('PDF could not be written');
        }
        $scope = 'application';
        $applicationId = null;
        $languageCode = null;
        $documentTitle = 'Originale Stellenausschreibung';
        $documentDescription = 'Nachträglich aus vorhandenem Weblink der Ausschreibung gesichert.';
        $mime = 'application/pdf';
        $size = filesize($target) ?: strlen($pdf);
        $sha = hash_file('sha256', $target);
        $version = 1;
        $stmt = $db->prepare('INSERT INTO user_documents (user_id, document_type_id, language_code, scope, application_id, job_id, title, description, original_filename, storage_path, mime_type, file_size, sha256, valid_from, valid_until, version, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, 1)');
        $stmt->bind_param('iissiisssssisi', $userId, $documentTypeId, $languageCode, $scope, $applicationId, $jobId, $documentTitle, $documentDescription, $fileName, $relativePath, $mime, $size, $sha, $version);
        $stmt->execute();
        $documentId = (int) $stmt->insert_id;
        audit($db, $userId, 'create', 'user_document', $documentId, [
            'title' => $documentTitle,
            'job_id' => $jobId,
            'source_url' => $sourceUrl,
            'migration' => 'existing_job_pdfs',
        ]);
        $created++;
    } catch (Throwable $exception) {
        $failed[] = ['job_id' => $jobId, 'error' => $exception->getMessage()];
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'checked' => count($jobs),
    'created' => $created,
    'skipped' => $skipped,
    'failed' => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
