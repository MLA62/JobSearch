<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

const REQUIRED_SQL_FILES = ['01_schema.sql', '02_views.sql'];

function fail(string $message, int $status = 500): never
{
    http_response_code($status);
    echo "ERROR: {$message}\n";
    exit(1);
}

function drainResults(mysqli $database): void
{
    do {
        $result = $database->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } while ($database->more_results() && $database->next_result());
}

function executeSqlFile(mysqli $database, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        fail('SQL file is missing or empty: ' . basename($path));
    }

    if (!$database->multi_query($sql)) {
        fail('SQL failed in ' . basename($path) . ': ' . $database->error);
    }

    while (true) {
        $result = $database->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }

        if (!$database->more_results()) {
            break;
        }

        if (!$database->next_result()) {
            fail('SQL failed in ' . basename($path) . ': ' . $database->error);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required.', 405);
}

$configPath = __DIR__ . '/install-config.php';
$lockPath = __DIR__ . '/.installed';

if (is_file($lockPath)) {
    fail('Installer is already locked.', 410);
}

if (!is_file($configPath)) {
    fail('Runtime configuration is missing.');
}

$config = require $configPath;
if (!is_array($config)) {
    fail('Runtime configuration is invalid.');
}

$providedToken = (string) ($_POST['token'] ?? '');
$expectedToken = (string) ($config['install_token'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    fail('Invalid installation token.', 403);
}

foreach (REQUIRED_SQL_FILES as $filename) {
    if (!is_file(__DIR__ . '/' . $filename)) {
        fail('Required SQL file is missing: ' . $filename);
    }
}

mysqli_report(MYSQLI_REPORT_OFF);
$database = mysqli_init();
if (!$database) {
    fail('Could not initialize mysqli.');
}

$database->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
$connected = $database->real_connect(
    (string) ($config['db_host'] ?? 'localhost'),
    (string) ($config['db_user'] ?? ''),
    (string) ($config['db_password'] ?? ''),
    (string) ($config['db_name'] ?? ''),
    (int) ($config['db_port'] ?? 3306)
);

if (!$connected) {
    fail('Database connection failed: ' . mysqli_connect_error());
}

if (!$database->set_charset('utf8mb4')) {
    fail('Could not select utf8mb4: ' . $database->error);
}

executeSqlFile($database, __DIR__ . '/01_schema.sql');
executeSqlFile($database, __DIR__ . '/02_views.sql');

$tableResult = $database->query(
    "SELECT COUNT(*) AS total FROM information_schema.tables " .
    "WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
);
$viewResult = $database->query(
    "SELECT COUNT(*) AS total FROM information_schema.views WHERE table_schema = DATABASE()"
);

if (!$tableResult || !$viewResult) {
    fail('Installation completed, but verification failed: ' . $database->error);
}

$tableCount = (int) $tableResult->fetch_assoc()['total'];
$viewCount = (int) $viewResult->fetch_assoc()['total'];
$tableResult->free();
$viewResult->free();
$database->close();

$lockContents = json_encode(
    ['installed_at' => gmdate(DATE_ATOM), 'tables' => $tableCount, 'views' => $viewCount],
    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
);
if (file_put_contents($lockPath, $lockContents . PHP_EOL, LOCK_EX) === false) {
    fail('Database installed, but installer lock could not be written.');
}

@chmod($lockPath, 0600);
@unlink($configPath);

echo "OK\n";
echo "Tables: {$tableCount}\n";
echo "Views: {$viewCount}\n";
echo "Installer locked. Runtime configuration removed.\n";

