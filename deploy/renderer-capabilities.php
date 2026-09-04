<?php
declare(strict_types=1);
// Temporary, read-only deployment diagnostic. No config, DB or user data is read.
// Access requires an ephemeral header token; the endpoint expires automatically.
ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || time() > 1788530569 ||
    !hash_equals('30b2522b697a80798b6f7b7a80949d8c66d3e010a24ac53c5be6940c66efc927', hash('sha256', (string)($_SERVER['HTTP_X_JEMA_DIAGNOSTIC'] ?? '')))) {
    http_response_code(404); exit;
}
$programs = [];
foreach (['/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome','/opt/google/chrome/chrome','/usr/bin/wkhtmltopdf','/usr/local/bin/wkhtmltopdf','/usr/bin/node','/usr/local/bin/node','/usr/bin/python3','/usr/bin/ldd','/usr/bin/tar','/usr/bin/xz'] as $path) {
    $programs[$path] = @is_executable($path);
}
$functions = [];
foreach (['proc_open','exec','shell_exec','curl_init','gzdecode','brotli_uncompress'] as $name) $functions[$name] = function_exists($name);
$libraries = [];
foreach (['libnss3.so','libatk-1.0.so.0','libatk-bridge-2.0.so.0','libcups.so.2','libdrm.so.2','libXcomposite.so.1','libXdamage.so.1','libXfixes.so.3','libXrandr.so.2','libgbm.so.1','libasound.so.2'] as $name) {
    $libraries[$name] = @file_exists('/usr/lib64/'.$name) || @file_exists('/usr/lib/x86_64-linux-gnu/'.$name);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['os'=>PHP_OS_FAMILY,'architecture'=>php_uname('m'),'php'=>PHP_VERSION,'programs'=>$programs,'functions'=>$functions,'libraries'=>$libraries,'extensions'=>array_values(array_intersect(get_loaded_extensions(),['curl','dom','mbstring','gd','imagick','zip','zlib','openssl']))], JSON_THROW_ON_ERROR);
