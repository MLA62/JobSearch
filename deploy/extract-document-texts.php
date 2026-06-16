<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$options = getopt('', ['limit::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php deploy/extract-document-texts.php [--limit=20]\n";
    exit(0);
}

$configCandidates = array_filter([
    getenv('JEMA_JOBS_CONFIG') ?: '',
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
    fwrite(STDERR, "Application configuration is missing.\n");
    exit(1);
}
$config = require $configPath;
$publicRoot = realpath(dirname($configPath));
if (!$publicRoot) {
    fwrite(STDERR, "Could not resolve public directory.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name'], (int) $config['db_port']);
$db->set_charset('utf8mb4');
$limit = max(1, min(100, (int) ($options['limit'] ?? 20)));

$stmt = $db->prepare(
    "SELECT dt.user_document_id, d.storage_path, d.mime_type
       FROM document_texts dt
       JOIN user_documents d ON d.id=dt.user_document_id
      WHERE dt.extraction_status='pending'
        AND d.deleted_at IS NULL
      ORDER BY d.created_at ASC
      LIMIT ?"
);
$stmt->bind_param('i', $limit);
$stmt->execute();
$stmt->bind_result($documentId, $storagePath, $mimeType);
$rows = [];
while ($stmt->fetch()) {
    $rows[] = [
        'user_document_id' => $documentId,
        'storage_path' => $storagePath,
        'mime_type' => $mimeType,
    ];
}
$stmt->close();

function commandExists(string $command): bool
{
    $where = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'command -v';
    $output = [];
    $code = 1;
    @exec($where . ' ' . escapeshellarg($command), $output, $code);
    return $code === 0;
}

foreach ($rows as $row) {
    $id = (int) $row['user_document_id'];
    $path = realpath($publicRoot . '/' . $row['storage_path']);
    $text = '';
    $status = 'ready';
    $error = null;
    try {
        if (!$path || !is_file($path)) {
            throw new RuntimeException('File missing.');
        }
        if ($row['mime_type'] === 'text/plain') {
            $text = (string) file_get_contents($path);
        } elseif ($row['mime_type'] === 'application/pdf' && commandExists('pdftotext')) {
            $tmp = tempnam(sys_get_temp_dir(), 'jema-pdf-text-');
            $cmd = 'pdftotext -layout ' . escapeshellarg($path) . ' ' . escapeshellarg($tmp);
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);
            if ($code !== 0) {
                throw new RuntimeException('pdftotext failed.');
            }
            $text = (string) file_get_contents($tmp);
            @unlink($tmp);
        } else {
            $status = 'skipped';
        }
    } catch (Throwable $exception) {
        $status = 'failed';
        $error = $exception->getMessage();
    }
    $updated = $db->prepare('UPDATE document_texts SET extraction_status=?, extracted_text=?, extracted_at=IF(?="ready", NOW(), extracted_at), error_message=? WHERE user_document_id=?');
    $updated->bind_param('ssssi', $status, $text, $status, $error, $id);
    $updated->execute();
    echo $id . ': ' . $status . PHP_EOL;
}
