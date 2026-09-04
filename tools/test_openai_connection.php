<?php

declare(strict_types=1);

/**
 * Minimaler, manueller Verbindungstest fuer die serverseitige OpenAI-Konfiguration.
 * Aufruf nur in einer geschuetzten Shell: php -n tools/test_openai_connection.php
 *
 * Der Test erzeugt eine sehr kurze, kostenpflichtige Responses-Anfrage. Er gibt den
 * API-Schluessel, den Antworttext und den vollstaendigen Fehlertext nie aus.
 */

$configPath = dirname(__DIR__) . '/public/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "OPENAI_TEST_NOT_CONFIGURED: public/config.php fehlt.\n");
    exit(2);
}

$config = require $configPath;
if (!is_array($config) || trim((string)($config['openai_api_key'] ?? '')) === '') {
    fwrite(STDERR, "OPENAI_TEST_NOT_CONFIGURED: openai_api_key fehlt.\n");
    exit(2);
}
$payload = json_encode([
    'model' => 'gpt-5.6-luna',
    'store' => false,
    'max_output_tokens' => 16,
    'input' => 'Reply only with PONG.',
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

$apiKey = (string)$config['openai_api_key'];
$raw = false;
$status = 0;
$error = '';

if (extension_loaded('curl')) {
    $handle = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $raw = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
} elseif (PHP_OS_FAMILY === 'Windows' && is_file(getenv('SystemRoot') . '\\System32\\curl.exe')) {
    // Die Konfiguration (und damit der Schluessel) geht ueber STDIN, nicht als Kommandozeilenargument.
    $curlConfig = "url = \"https://api.openai.com/v1/responses\"\n"
        . "request = \"POST\"\n"
        . "header = \"Authorization: Bearer " . addcslashes($apiKey, "\\\"\r\n") . "\"\n"
        . "header = \"Content-Type: application/json\"\n"
        . "data = \"" . addcslashes($payload, "\\\"\r\n") . "\"\n"
        . "connect-timeout = 10\n"
        . "max-time = 30\n"
        . "write-out = \"\\n__OPENAI_HTTP_STATUS__:%{http_code}\"\n";
    $process = proc_open([
        getenv('SystemRoot') . '\\System32\\curl.exe',
        '--silent',
        '--show-error',
        '--config',
        '-',
    ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (is_resource($process)) {
        fwrite($pipes[0], $curlConfig);
        fclose($pipes[0]);
        $rawWithStatus = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[2]);
        proc_close($process);
        if (is_string($rawWithStatus) && preg_match('/^(.*)\\n__OPENAI_HTTP_STATUS__:(\\d{3})$/s', $rawWithStatus, $matches)) {
            $raw = $matches[1];
            $status = (int)$matches[2];
        }
    } else {
        $error = 'curl.exe konnte nicht gestartet werden';
    }
} else {
    fwrite(STDERR, "OPENAI_TEST_UNAVAILABLE: PHP-cURL ist nicht aktiv.\n");
    exit(2);
}

if (!is_string($raw) || $status < 200 || $status >= 300) {
    fwrite(STDERR, 'OPENAI_TEST_FAILED: HTTP ' . $status . ($error !== '' ? ' (' . $error . ')' : '') . "\n");
    exit(1);
}

$response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
if (($response['status'] ?? '') !== 'completed' || !is_string($response['id'] ?? null)) {
    fwrite(STDERR, "OPENAI_TEST_FAILED: Antwort ist nicht abgeschlossen.\n");
    exit(1);
}

echo 'OPENAI_TEST_OK: model=' . (string)($response['model'] ?? 'unknown')
    . ' response_id=' . (string)$response['id'] . PHP_EOL;
