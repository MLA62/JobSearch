<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$php = file_get_contents($root . '/public/index.php');
$config = file_get_contents($root . '/public/config.example.php');
$help = json_decode(file_get_contents($root . '/docs/jobsearch/help/source.json'), true, 512, JSON_THROW_ON_ERROR);

$checks = [
    'version 2.3.4' => "\$codeVersion = '2.3.4'",
    'AI modal' => 'id="ai-work-dialog"',
    'modal title' => "tr('ai.work_title')",
    'abort button' => 'data-ai-work-abort',
    'abort controller' => 'new AbortController()',
    'AI actions' => "new Set(['start_application', 'revise_application_texts_ai', 'suggest_job_search_criteria', 'admin_ai_request'])",
    'mobile-safe direct click handler' => "document.addEventListener('click', async event =>",
    'action button delegation' => "event.target.closest?.('button[name=\"action\"]')",
    'paint modal before request' => 'requestAnimationFrame(() => requestAnimationFrame(resolve))',
    'post-AI cache buster' => "target.searchParams.set('_ai_done'",
    'explicit async marker' => "data.set('_ai_fetch', '1')",
    'JSON redirect contract' => "function redirectAiFetch(string \$path): never",
    'start application destination' => "redirectAiFetch('/?page=applications&edit='",
    'native application submission' => "HTMLFormElement.prototype.submit.call(form)",
    'native action field' => "actionInput.dataset.aiNativeAction = '1'",
    'native AI text revision' => "action === 'start_application' || action === 'revise_application_texts_ai'",
    'admin AI action' => "if (\$action === 'admin_ai_request')",
    'admin AI scope' => 'JeMa Jobs administrator operations executor',
    'admin AI web search' => "'tools' => [['type' => 'web_search']]",
    'admin AI structured schema' => "name' => 'jema_admin_operations'",
    'admin AI execution' => 'function adminAiApplyOperations',
    'admin AI all-table execution' => 'function adminAiTableDefinitions',
    'admin AI table upsert' => "'table_upsert'",
    'admin AI status lookup' => 'function adminAiRecordLookup',
    'admin AI status question guard' => 'function adminAiStatusQuestion',
    'admin AI direct execution' => 'Ein direkter Admin-Auftrag ist verbindlich',
    'admin AI markdown renderer' => 'window.renderAdminAiMarkdown',
    'admin AI memory' => "\$_SESSION['admin_ai_context']",
    'admin AI clear memory' => "admin_ai_clear_memory",
    'admin AI JSON fetch' => "'executed'=>\$execution['executed']",
    'rich editor values synchronized first' => "source.dispatchEvent(new Event('jema:richtext-sync'))",
    'autosave paused for AI submission' => "form.dispatchEvent(new Event('jema:manual-submit'))",
    'native abort' => 'window.stop()',
    'footer disclosure' => "tr('footer.ai_notice'",
    'manufacturer' => "'manufacturer'=>'OpenAI'",
];
foreach ($checks as $label => $needle) {
    if (!str_contains($php, $needle)) throw new RuntimeException('Missing ' . $label);
}

foreach (['ai.work_title', 'ai.work_hint', 'ai.abort', 'footer.ai_notice'] as $key) {
    if (!isset($help['ui'][$key])) throw new RuntimeException('Missing UI key ' . $key);
    foreach ($help['locales'] as $locale) {
        if (trim((string)($help['ui'][$key][$locale] ?? '')) === '') throw new RuntimeException('Missing ' . $key . ' ' . $locale);
    }
}
if (str_contains($help['ui']['footer.ai_notice']['de-CH'], '{percent}') || str_contains($php, 'openAiQuotaStatus')) throw new RuntimeException('Unreliable quota display must be absent');

echo "AI progress and quota contract passed\n";
