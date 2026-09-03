<?php
declare(strict_types=1);

$source = file_get_contents($argv[1] ?? __DIR__ . '/../public/index.php');
$catalog = [];
preg_match_all("/^        '((?:profile|jobs)[^']+)' => \\[\\R(.*?)^        \\],/ms", $source, $entries, PREG_SET_ORDER);
foreach ($entries as $entry) {
    $catalog[$entry[1]] = eval('return [' . $entry[2] . '];');
}
foreach (['multilingualUiEnabled', 'normalizeLocale', 'browserLocale', 'currentLocale', 'languageUrl', 'localeHtmlLang', 'tr'] as $name) {
    if (!preg_match('/^function ' . $name . '\\(.*?(?=^function |\\z)/ms', $source, $match)) {
        throw new RuntimeException('Missing helper: ' . $name);
    }
    eval(trim($match[0]));
}
function dbUiText(string $key, string $locale): ?string {
    global $catalog;
    return $catalog[$key][$locale] ?? null;
}
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "OK: $message\n";
}
$start = strpos($source, '$remotePreferenceLabels =');
$end = strpos($source, '</form></section>', $start);
preg_match_all("/tr\\('([^']+)'/", substr($source, $start, $end - $start), $matches);
$keys = array_unique($matches[1]);
$locales = ['de-CH', 'fr-CH', 'en-GB', 'pt-BR', 'es-MX'];
$currentUser = ['preferred_language' => 'de-CH'];
foreach ($locales as $locale) {
    $_SESSION = ['locale' => $locale];
    $appLocale = currentLocale($currentUser);
    foreach ($keys as $key) {
        check(isset($catalog[$key][$locale]) && tr($key) !== $key && tr($key) !== '', "$locale: $key");
    }
    check(localeHtmlLang($appLocale) === $locale, "$locale: document language");
    $_GET = ['page' => 'profile', 'lang' => 'de-CH'];
    parse_str(parse_url(languageUrl($locale), PHP_URL_QUERY), $params);
    check($params['page'] === 'profile' && $params['lang'] === $locale, "$locale: language link preserves page");
}
$_SESSION = [];
check(currentLocale(['preferred_language'=>'pt-BR']) === 'pt-BR', 'Saved profile language without session override');
$_SESSION = ['locale'=>'en-GB'];
check(currentLocale(['preferred_language'=>'de-CH']) === 'en-GB', 'Explicit language selection takes priority');
$appLocale = 'de-CH';
check(tr('profile.remote.onsite') === 'Nur vor Ort', 'German onsite-only option');
$appLocale = 'en-GB';
check(tr('profile.remote.onsite') === 'Onsite only', 'English onsite-only option');
$appLocale = 'de-CH';
check(tr('profile.remote_preference') === 'Arbeitsmodell', 'Switch back to German');
echo "Profile translation and shared language-switch checks passed.\n";
