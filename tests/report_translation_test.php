<?php
declare(strict_types=1);

$source = (string)file_get_contents(__DIR__ . '/../public/index.php');
foreach (['tr', 'helpTranslationSeeds'] as $name) {
    if (preg_match('/function ' . $name . '\(.*?^\}/ms', $source, $match) !== 1) {
        throw new RuntimeException($name . ' not found');
    }
    eval($match[0]);
}

function normalizeLocale(?string $locale): string { return (string)$locale; }
function currentLocale(?array $user = null): string { return 'de-CH'; }
function dbUiText(string $key, string $locale): ?string
{
    return $key === 'reports.filter_any_of' && $locale === 'en-GB' ? 'Custom text' : null;
}

$appLocale = 'de-CH';
$currentUser = null;
$expected = [
    'de-CH'=>'Mehrere Werte wählen (ODER); keine Auswahl = alle',
    'fr-CH'=>'Plusieurs valeurs (OU); aucune sélection = toutes',
    'pt-BR'=>'Selecione vários valores (OU); nenhum = todos',
    'es-MX'=>'Selecciona varios valores (O); ninguno = todos',
];
foreach ($expected as $locale=>$text) {
    if (tr('reports.filter_any_of', $locale) !== $text) {
        throw new RuntimeException('Missing generated translation fallback for ' . $locale);
    }
}
if (tr('reports.filter_any_of', 'en-GB') !== 'Custom text') {
    throw new RuntimeException('An approved database translation must take precedence.');
}
if (tr('reports.filter_from', 'de-CH') !== 'Von' || tr('unknown.translation', 'de-CH') !== 'unknown.translation') {
    throw new RuntimeException('Known and unknown translation fallback behavior differs.');
}
echo "PASS generated translations are used when the production catalog lacks a new key\n";
