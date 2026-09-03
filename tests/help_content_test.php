<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';

$locales = ['de-CH','fr-CH','en-GB','pt-BR','es-MX'];
helpAssert($helpCatalog['locales'] === $locales, 'Five supported locales');
helpAssert(count($helpCatalog['topics']) === 24, 'All 24 topics');
$ids = []; $pages = [];
foreach ($helpCatalog['topics'] as $topic) {
    helpAssert(!isset($ids[$topic['id']]), 'Unique topic ID');
    $ids[$topic['id']] = true;
    foreach ($topic['pages'] as $page) {
        helpAssert(!isset($pages[$page]), 'Unique page context '.$page);
        $pages[$page] = $topic['id'];
    }
    helpAssert(array_keys($topic['text']) === $locales, 'Every topic has all locales');
    foreach ($topic['text'] as $locale => $text) {
        helpAssert(count($text['steps']) === 3 && count($text['tips']) === 1, 'Complete procedure '.$locale);
        foreach (array_merge([$text['title'],$text['summary']],$text['steps'],$text['tips']) as $value) {
            helpAssert(is_string($value) && trim($value) !== '' && !str_contains($value,'help.v2.'), 'No empty/raw topic text');
        }
    }
}
foreach ($helpCatalog['topics'] as $topic) {
    foreach ($topic['links'] as $page) {
        helpAssert(isset($pages[explode('#',$page,2)[0]]), 'Resolved topic link '.$page);
    }
}
foreach (helpTranslationSeeds() as $key=>$translations) {
    helpAssert(array_keys($translations) === $locales, 'Complete UI key '.$key);
    $expected = null;
    foreach ($translations as $locale=>$text) {
        preg_match_all('/\\{[a-z_]+\\}/', $text, $matches);
        sort($matches[0]);
        $expected ??= $matches[0];
        helpAssert($matches[0] === $expected, 'Consistent placeholders '.$key);
    }
}
foreach ($locales as $locale) {
    $_SESSION['locale'] = $locale;
    $appLocale = currentLocale(['preferred_language'=>'de-CH']);
    helpAssert($appLocale === $locale, 'Session language wins');
    $topics = localizedHelpTopics($locale);
    $contexts = localizedContextHelpTopics($locale);
    foreach ($topics as $i=>$topic) {
        $expected = $helpCatalog['topics'][$i]['text'][$locale];
        foreach (['title','summary','steps','tips'] as $field) {
            helpAssert($topic[$field] === $expected[$field], 'Exact translated topic '.$topic['id'].' '.$field.' '.$locale);
        }
        helpAssert(str_contains($topic['keywords'],$expected['steps'][2]), 'Search includes procedure');
        helpAssert(count($topic['links']) === count($helpCatalog['topics'][$i]['links']), 'No missing links');
        foreach ($topic['pages'] as $page) {
            helpAssert($contexts[$page]['steps'] === $topic['steps'], 'Same context steps '.$page);
            helpAssert($contexts[$page]['link'][1] === '/?page=help#help-topic-'.$topic['id'], 'Context anchor '.$page);
        }
    }
}
preg_match_all('/tr\\(\x27((?:help|context)\\.[^\x27]+)\x27/', $helpSource, $matches);
foreach (array_unique($matches[1]) as $key) {
    // Category/audience prefixes are deliberately resolved dynamically.
    if (str_ends_with($key,'.')) { continue; }
    helpAssert(isset(helpTranslationSeeds()[$key]), 'Every visible help key is managed: '.$key);
}
$start = strpos($helpSource, '<?php elseif ($page === \'help\'): ?>');
$end = strpos($helpSource, '<?php elseif ($page === \'about\'): ?>', $start);
$template = substr($helpSource,$start,$end-$start);
helpAssert(!str_contains($template,'page=pendents') && !str_contains($template,'page=reminders'), 'No obsolete task links');
helpAssert(str_contains($helpSource,'if (isset($managedHelpTexts[$textKey]))'), 'Legacy seed cannot overwrite reviewed keys');
foreach (['dashboard','profile','profile_links','documents','jobs','companies','contacts','applications','calendar','reports','job_room_helper','job_platform_search','application_dossier','sharing','privacy','translations','audit','admin_users','admin_job_platforms','workflow_review','help','about','login','register','forgot_password','reset_password','two_factor'] as $page) {
    helpAssert(isset($pages[$page]), 'Help covers mask '.$page);
}
echo $helpChecks." help content checks passed\n";
