<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

function failMigration11(string $message, int $status = 500): never
{
    http_response_code($status);
    echo "ERROR: {$message}\n";
    exit(1);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failMigration11('POST required.', 405);
}

$expectedToken = 'migration-11-user-smtp-settings-20260617';
$providedToken = (string) ($_POST['token'] ?? '');
if (!hash_equals($expectedToken, $providedToken)) {
    failMigration11('Invalid token.', 403);
}

$configPath = '';
foreach ([__DIR__ . '/../config.php', __DIR__ . '/../public/config.php'] as $candidate) {
    if (is_file($candidate)) {
        $configPath = $candidate;
        break;
    }
}
if ($configPath === '') {
    failMigration11('Application configuration is missing.');
}

$sqlPath = __DIR__ . '/11_user_smtp_settings.sql';
if (!is_file($sqlPath)) {
    failMigration11('SQL migration is missing.');
}

$config = require $configPath;
mysqli_report(MYSQLI_REPORT_OFF);
$db = mysqli_init();
if (!$db || !$db->real_connect($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name'], (int) $config['db_port'])) {
    failMigration11('Database connection failed: ' . mysqli_connect_error());
}
$db->set_charset('utf8mb4');

$sql = (string) file_get_contents($sqlPath);
if (!$db->query($sql)) {
    failMigration11('Migration failed: ' . $db->error);
}

echo "OK\nMigration 11 applied.\n";
