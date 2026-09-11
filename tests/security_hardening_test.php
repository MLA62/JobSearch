<?php
declare(strict_types=1);

$source = (string)file_get_contents(__DIR__ . '/../public/index.php');
$htaccess = (string)file_get_contents(__DIR__ . '/../public/.htaccess');
$schema = (string)file_get_contents(__DIR__ . '/../sql/jobsearch/16_security_hardening.sql');

function securityCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS {$message}\n";
}

securityCheck(!str_contains($source, "\$_SESSION['password_reset_link'] = \$resetLink"), 'Reset links are never exposed to the requesting browser');
securityCheck(str_contains($source, "if (\$user && outboundEmailEnabled(\$config))"), 'Reset tokens require the operator mail channel');
securityCheck(str_contains($source, "token_type='password_reset' AND consumed_at IS NULL"), 'Open reset tokens are revoked by the security migration');
securityCheck(str_contains($source, "recordAuthAttempt(\$db, 'login'") && str_contains($source, "recordAuthAttempt(\$db, 'totp'") && str_contains($source, "recordAuthAttempt(\$db, 'password_reset'"), 'Login, TOTP and reset requests are rate limited');
securityCheck(str_contains($source, "function twoFactorChallengeState(int \$authenticatedUserId, int \$pendingUserId): string"), 'TOTP submissions use an explicit challenge-state model');
$twoFactorFunctionFound = preg_match('/function twoFactorChallengeState\(int \$authenticatedUserId, int \$pendingUserId\): string\s*\{.*?^\}/ms', $source, $twoFactorFunction) === 1;
securityCheck($twoFactorFunctionFound, 'The pure TOTP challenge-state function can be isolated for regression tests');
eval($twoFactorFunction[0]);
securityCheck(twoFactorChallengeState(17, 0) === 'authenticated', 'An existing authenticated session has precedence over a stale TOTP form');
securityCheck(twoFactorChallengeState(0, 17) === 'pending', 'A password-authenticated user with TOTP has a pending challenge');
securityCheck(twoFactorChallengeState(0, 0) === 'missing', 'A stale TOTP form without a challenge is rejected as missing');
securityCheck(str_contains($source, "if (\$challengeState === 'authenticated')") && str_contains($source, "redirect('/?page=dashboard')"), 'An authenticated session never re-enters TOTP verification');
securityCheck(str_contains($source, "if (\$challengeState === 'missing')") && str_contains($source, "redirect('/?page=login')"), 'A missing TOTP challenge returns to login without a false rejection');
securityCheck(!str_contains($source, "if (\$pendingUserId < 1 || !authRateAllowed"), 'Rate-limit failures cannot disguise a missing TOTP challenge');
securityCheck(!str_contains($source, "<?php if (empty(\$_SESSION['pending_2fa_user_id'])): ?><p class=\"alert warning\">"), 'The TOTP form has no unreachable stale-session warning');
securityCheck(str_contains($source, 'session_version=session_version+1') && str_contains($source, "\$_SESSION['session_version']"), 'Security changes revoke existing sessions');
securityCheck(str_contains($source, '$encryptedSecret = encryptSecret($config, $secret)') && str_contains($source, 'totpSecretValue($config'), 'TOTP secrets are encrypted at rest and decrypted only for verification');
securityCheck(!str_contains($source, 'jema-jobs-local-prototype') && str_contains($source, 'Ein eigener APP_KEY'), 'Secret encryption fails closed without a dedicated key');
securityCheck(str_contains($source, "publicMailEndpoint(\$host, \$port, [465, 587])") && str_contains($source, "publicMailEndpoint(\$imapHost ?: \$host, \$imapPort, [143, 993])"), 'Mail connections use public endpoints and fixed ports');
securityCheck(!str_contains($source, "['tls', 'ssl', 'none']") && !str_contains($source, "'none'=>tr('profile.smtp_encryption_none')"), 'Unencrypted SMTP and IMAP are unavailable');
securityCheck(str_contains($source, "Content-Security-Policy: default-src 'self'") && str_contains($htaccess, 'Strict-Transport-Security'), 'Browser security headers are configured');
securityCheck(!str_contains($source, 'cdn.jsdelivr.net/gh/MLA62/JobSearch'), 'Runtime assets are served locally');
securityCheck(str_contains($source, '$detectedMime') && str_contains($source, 'is_uploaded_file'), 'Uploads require a real upload and matching MIME type');
securityCheck(str_contains($source, "header('Content-Security-Policy: sandbox')") && str_contains($source, "downloadDisposition((string)\$document['original_filename'])"), 'User documents are downloaded with sandbox and safe disposition');
securityCheck(!str_contains($source, 'function interpolateSql('), 'The SQL interpolation fallback is removed');
securityCheck(str_contains($schema, 'CREATE TABLE IF NOT EXISTS auth_rate_limits') && str_contains($schema, 'session_version'), 'Security schema migration is reproducible');
securityCheck(str_contains($source, "\$codeVersion = '2.4.8'"), 'Application version is 2.4.8');

echo "Security hardening contract passed.\n";
