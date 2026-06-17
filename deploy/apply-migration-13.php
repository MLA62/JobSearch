<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

function failMigration13(string $message, int $status = 500): never
{
    http_response_code($status);
    echo "ERROR: {$message}\n";
    exit(1);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failMigration13('POST required.', 405);
}

$expectedToken = 'migration-13-online-application-fields-20260617';
$providedToken = (string) ($_POST['token'] ?? '');
if (!hash_equals($expectedToken, $providedToken)) {
    failMigration13('Invalid token.', 403);
}

$configPath = '';
foreach ([__DIR__ . '/../config.php', __DIR__ . '/../public/config.php'] as $candidate) {
    if (is_file($candidate)) {
        $configPath = $candidate;
        break;
    }
}
if ($configPath === '') {
    failMigration13('Application configuration is missing.');
}

$sqlPath = __DIR__ . '/13_online_application_fields.sql';
if (!is_file($sqlPath)) {
    failMigration13('SQL migration is missing.');
}

$config = require $configPath;
mysqli_report(MYSQLI_REPORT_OFF);
$db = mysqli_init();
if (!$db || !$db->real_connect($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name'], (int) $config['db_port'])) {
    failMigration13('Database connection failed: ' . mysqli_connect_error());
}
$db->set_charset('utf8mb4');

$sql = (string) file_get_contents($sqlPath);
if (!$db->query($sql)) {
    failMigration13('Migration failed: ' . $db->error);
}

echo "OK\nMigration 13 applied.\n";
