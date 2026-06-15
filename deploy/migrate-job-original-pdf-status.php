<?php

declare(strict_types=1);

const MIGRATION_TOKEN = 'codex-migrate-20260616-job-original-pdf-status';

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

function columnExists(mysqli $db, string $table, string $column): bool
{
    $schema = $db->real_escape_string((string) $GLOBALS['config']['db_name']);
    $table = $db->real_escape_string($table);
    $column = $db->real_escape_string($column);
    $result = $db->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$schema' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'");
    $row = $result->fetch_assoc();
    return (int) ($row['c'] ?? 0) > 0;
}

function indexExists(mysqli $db, string $table, string $index): bool
{
    $schema = $db->real_escape_string((string) $GLOBALS['config']['db_name']);
    $table = $db->real_escape_string($table);
    $index = $db->real_escape_string($index);
    $result = $db->query("SELECT COUNT(*) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$schema' AND TABLE_NAME='$table' AND INDEX_NAME='$index'");
    $row = $result->fetch_assoc();
    return (int) ($row['c'] ?? 0) > 0;
}

$changes = [];

if (!columnExists($db, 'jobs', 'original_pdf_status')) {
    $db->query("ALTER TABLE jobs ADD COLUMN original_pdf_status ENUM('none','pending','rendered','failed') NOT NULL DEFAULT 'none' AFTER source_url");
    $changes[] = 'added original_pdf_status';
}
if (!columnExists($db, 'jobs', 'original_pdf_requested_at')) {
    $db->query('ALTER TABLE jobs ADD COLUMN original_pdf_requested_at DATETIME NULL AFTER original_pdf_status');
    $changes[] = 'added original_pdf_requested_at';
}
if (!columnExists($db, 'jobs', 'original_pdf_rendered_at')) {
    $db->query('ALTER TABLE jobs ADD COLUMN original_pdf_rendered_at DATETIME NULL AFTER original_pdf_requested_at');
    $changes[] = 'added original_pdf_rendered_at';
}
if (!columnExists($db, 'jobs', 'original_pdf_error')) {
    $db->query('ALTER TABLE jobs ADD COLUMN original_pdf_error TEXT NULL AFTER original_pdf_rendered_at');
    $changes[] = 'added original_pdf_error';
}
if (!indexExists($db, 'jobs', 'idx_jobs_original_pdf_status')) {
    $db->query('ALTER TABLE jobs ADD KEY idx_jobs_original_pdf_status (owner_user_id, original_pdf_status)');
    $changes[] = 'added idx_jobs_original_pdf_status';
}

$db->query(
    "UPDATE jobs j
        SET original_pdf_status = CASE
                WHEN EXISTS (
                    SELECT 1
                      FROM user_documents d
                     WHERE d.user_id = j.owner_user_id
                       AND d.job_id = j.id
                       AND d.title = 'Originale Stellenausschreibung'
                       AND d.deleted_at IS NULL
                ) THEN 'rendered'
                WHEN j.source_url IS NOT NULL AND j.source_url <> '' THEN 'pending'
                ELSE 'none'
            END,
            original_pdf_requested_at = CASE
                WHEN j.source_url IS NOT NULL AND j.source_url <> '' THEN COALESCE(j.original_pdf_requested_at, j.created_at)
                ELSE NULL
            END,
            original_pdf_rendered_at = CASE
                WHEN EXISTS (
                    SELECT 1
                      FROM user_documents d
                     WHERE d.user_id = j.owner_user_id
                       AND d.job_id = j.id
                       AND d.title = 'Originale Stellenausschreibung'
                       AND d.deleted_at IS NULL
                ) THEN COALESCE(j.original_pdf_rendered_at, NOW())
                ELSE NULL
            END,
            original_pdf_error = CASE
                WHEN j.source_url IS NULL OR j.source_url = '' THEN NULL
                WHEN EXISTS (
                    SELECT 1
                      FROM user_documents d
                     WHERE d.user_id = j.owner_user_id
                       AND d.job_id = j.id
                       AND d.title = 'Originale Stellenausschreibung'
                       AND d.deleted_at IS NULL
                ) THEN NULL
                ELSE j.original_pdf_error
            END
      WHERE j.deleted_at IS NULL"
);

$summary = [];
$result = $db->query("SELECT original_pdf_status, COUNT(*) c FROM jobs WHERE deleted_at IS NULL GROUP BY original_pdf_status ORDER BY original_pdf_status");
while ($row = $result->fetch_assoc()) {
    $summary[$row['original_pdf_status']] = (int) $row['c'];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'changes' => $changes,
    'summary' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
