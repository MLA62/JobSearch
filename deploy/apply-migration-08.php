<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

function failMigration(string $message, int $status = 500): never
{
    http_response_code($status);
    echo "ERROR: {$message}\n";
    exit(1);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failMigration('POST required.', 405);
}

$expectedToken = 'replace-at-deploy-time';
$providedToken = (string) ($_POST['token'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    failMigration('Invalid token.', 403);
}

$configPath = '';
foreach ([__DIR__ . '/../config.php', __DIR__ . '/../public/config.php'] as $candidate) {
    if (is_file($candidate)) {
        $configPath = $candidate;
        break;
    }
}
if ($configPath === '') {
    failMigration('Application configuration is missing.');
}
$sqlPath = __DIR__ . '/08_sharing_documents_exports_privacy.sql';
if (!is_file($sqlPath)) {
    failMigration('SQL migration is missing.');
}

$config = require $configPath;
mysqli_report(MYSQLI_REPORT_OFF);
$db = mysqli_init();
if (!$db || !$db->real_connect($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name'], (int) $config['db_port'])) {
    failMigration('Database connection failed: ' . mysqli_connect_error());
}
$db->set_charset('utf8mb4');
$sql = (string) file_get_contents($sqlPath);
if (!$db->multi_query($sql)) {
    failMigration('Migration failed: ' . $db->error);
}
do {
    $result = $db->store_result();
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    if ($db->more_results() && !$db->next_result()) {
        failMigration('Migration failed: ' . $db->error);
    }
} while ($db->more_results());

echo "OK\nMigration 08 applied.\n";
