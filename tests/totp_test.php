<?php
declare(strict_types=1);

// Public RFC test key only. Never load the app bootstrap or account secrets.
$source = file_get_contents($argv[1] ?? __DIR__ . '/../public/index.php');
foreach (['base32Encode', 'base32Decode', 'totpCode', 'verifyTotpCode'] as $name) {
    if (!preg_match('/^function ' . $name . '\\(.*?(?=^function |\\z)/ms', $source, $match)) {
        throw new RuntimeException('Missing helper: ' . $name);
    }
    eval(trim($match[0]));
}
function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
    echo "OK: $message\n";
}
$key = '12345678901234567890';
$secret = base32Encode($key);
check(base32Decode($secret) === $key, 'Base32 round trip');
// https://www.rfc-editor.org/rfc/rfc6238#appendix-B, SHA1 modulo 10^6.
foreach ([59=>'287082', 1111111109=>'081804', 1111111111=>'050471',
          1234567890=>'005924', 2000000000=>'279037', 20000000000=>'353130'] as $time=>$expected) {
    check(totpCode($secret, $time) === $expected, 'RFC vector at ' . $time);
}
$now = time();
check(verifyTotpCode($secret, totpCode($secret, $now)), 'Current code accepted');
check(!verifyTotpCode($secret, ''), 'Empty code rejected');
check(!verifyTotpCode($secret, '12345'), 'Short code rejected');
check(!verifyTotpCode($secret, '1234567'), 'Long code rejected');
check(!verifyTotpCode($secret, totpCode($secret, $now - 300)), 'Expired code rejected');
echo "All TOTP tests passed.\n";
