<?php

declare(strict_types=1);

/*
 * Findet sichtbare Textfragmente, die noch direkt in PHP stehen.
 * Das Werkzeug ist bewusst streng: Treffer werden nicht automatisch geaendert,
 * sondern dienen als Arbeitsliste fuer die DB-i18n-Migration.
 */

$root = dirname(__DIR__);
$files = [
    $root . '/public/index.php',
];

$allowedStringPatterns = [
    '/^[a-z0-9_.:-]+$/i',
    '/^[A-Z0-9_]+$/',
    '/^[\/?#&=.%{}+\-,;:|*<>!()[\]\\\\]+$/',
    '/^\/[a-z0-9_\/.-]+$/i',
    '/^https?:\/\//i',
    '/^[A-Z][A-Z0-9_]*\([^)]+\)$/',
    '/\b(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|FROM|WHERE|JOIN|ENGINE|DEFAULT|VARCHAR|BIGINT|DATETIME|CURRENT_TIMESTAMP|FOREIGN KEY|PRIMARY KEY)\b/i',
    '/^`[^`]+`\s+[A-Z]/',
    '/^\w+\.(php|css|js|pdf|csv|ics|zip)$/i',
];

$ignoredFragments = [
    'de-CH',
    'fr-CH',
    'en-GB',
    'pt-BR',
    'es-MX',
    'UTF-8',
    'STARTTLS',
    'SSL/TLS',
    'JeMa Jobs',
    'ChatGPT',
    'LinkedIn',
    'Facebook',
    'WhatsApp',
    'PDF',
    'CSV',
    'ICS',
    'CRM',
    'ADMIN',
    'JM',
    'Application configuration is missing.',
    'Database connection failed.',
    'Not found',
];

$findings = [];
foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, "Kann Datei nicht lesen: {$file}\n");
        exit(2);
    }
    $tokens = token_get_all($source);
    foreach ($tokens as $token) {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $line = (int) $token[2];
        $raw = (string) $token[1];
        $value = stripcslashes(substr($raw, 1, -1));
        $trimmed = trim($value);
        if ($trimmed === '' || in_array($trimmed, $ignoredFragments, true)) {
            continue;
        }
        $isAllowed = false;
        foreach ($allowedStringPatterns as $pattern) {
            if (preg_match($pattern, $trimmed) === 1) {
                $isAllowed = true;
                break;
            }
        }
        if ($isAllowed || preg_match('/[\p{L}]/u', $trimmed) !== 1) {
            continue;
        }
        $findings[] = [
            'file' => str_replace($root . '/', '', $file),
            'line' => $line,
            'text' => strlen($trimmed) > 140 ? substr($trimmed, 0, 137) . '...' : $trimmed,
        ];
    }
}

echo "Hardcoded-UI-Text Audit\n";
echo "=======================\n";
echo 'Treffer: ' . count($findings) . "\n\n";
foreach (array_slice($findings, 0, 250) as $finding) {
    echo $finding['file'] . ':' . $finding['line'] . ' | ' . $finding['text'] . "\n";
}
if (count($findings) > 250) {
    echo "\n... " . (count($findings) - 250) . " weitere Treffer nicht angezeigt.\n";
}

exit(count($findings) > 0 ? 1 : 0);
