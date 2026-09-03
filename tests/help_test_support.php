<?php
declare(strict_types=1);

$helpSource = file_get_contents(dirname(__DIR__).'/public/index.php');
$helpCatalog = json_decode(file_get_contents(dirname(__DIR__).'/docs/jobsearch/help/source.json'), true, 512, JSON_THROW_ON_ERROR);

function helpLoadFunction(string $name): void
{
    global $helpSource;
    if (!preg_match('/^function '.preg_quote($name, '/').'\\b.*?^}/ms', $helpSource, $match)) {
        throw new RuntimeException('Missing function '.$name);
    }
    eval($match[0]);
}

foreach (['multilingualUiEnabled','normalizeLocale','browserLocale','currentLocale','tr','dbUiText','repairMojibake','e','helpTranslationSeeds','helpTopicDefinitions','localizedHelpTopics','localizedContextHelpTopics'] as $name) {
    helpLoadFunction($name);
}

// Synthetic DB rows exercise the real DB lookup, locale selection and rendering.
function dbAll($db, string $sql, string $types = '', array $params = []): array
{
    $rows = [];
    $seeds = helpTranslationSeeds();
    $seeds['nav.help'] = $seeds['context.help'];
    $seeds['support.title'] = $seeds['help.hero_eyebrow'];
    foreach ($seeds as $key => $values) {
        $rows[] = ['text_key'=>$key, 'text_value'=>$values[$params[0] ?? 'de-CH']];
    }
    return $rows;
}

function helpAssert(bool $condition, string $label): void
{
    global $helpChecks;
    if (!$condition) { throw new RuntimeException($label); }
    $helpChecks = ($helpChecks ?? 0) + 1;
}
