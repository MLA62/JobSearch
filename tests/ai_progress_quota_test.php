<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$php = file_get_contents($root . '/public/index.php');
$config = file_get_contents($root . '/public/config.example.php');
$help = json_decode(file_get_contents($root . '/docs/jobsearch/help/source.json'), true, 512, JSON_THROW_ON_ERROR);

$checks = [
    'version 2.4.2' => "\$codeVersion = '2.4.2'",
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
    'admin AI scope' => 'persistent JeMa Jobs administrator operations agent',
    'admin AI web search' => "'tools' => [['type' => 'web_search']]",
    'admin AI structured schema' => "name' => 'jema_admin_operations'",
    'admin AI execution' => 'function adminAiApplyOperations',
    'admin AI all-table execution' => 'function adminAiTableDefinitions',
    'admin AI operation normalization' => 'function adminAiNormalizeOperation',
    'admin AI writable schema context' => "'writable_schema' => \$writableSchema",
    'admin AI table upsert' => "'table_upsert'",
    'admin AI status lookup' => 'function adminAiRecordLookup',
    'admin AI status question guard' => 'function adminAiStatusQuestion',
    'admin AI autonomous execution' => 'function adminAiRunTask',
    'admin AI execution feedback' => "'execution_feedback' => mb_substr(\$executionFeedback",
    'admin AI completion verification' => 'Perform a follow-up verification against the refreshed platform_context now.',
    'admin AI markdown renderer' => 'window.renderAdminAiMarkdown',
    'admin AI memory' => "\$_SESSION['admin_ai_context']",
    'admin AI durable memory table' => 'CREATE TABLE IF NOT EXISTS admin_ai_memory',
    'admin AI durable memory load' => 'function adminAiLoadState',
    'admin AI durable memory save' => 'function adminAiSaveState',
    'admin AI complete memory is sent' => "'previous_context' => \$memory",
    'admin AI instruction saved before request' => "\$state['instruction'] = \$instruction;",
    'admin AI failure retained in context' => "'summary'=>'Fehlgeschlagen: '",
    'admin AI JSON errors parsed before status handling' => "let result = null;",
    'admin AI errors do not reload page' => "if (action === 'admin_ai_request' && error?.name !== 'AbortError')",
    'admin AI browser draft retained' => "jema-admin-ai-draft",
    'admin AI clear memory' => "admin_ai_clear_memory",
    'admin AI clear works with empty input' => 'value="admin_ai_clear_memory" formnovalidate',
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
if (str_contains($php, 'class="admin-ai-intro"')) throw new RuntimeException('Obsolete admin AI intro is still rendered');

foreach (['ai.work_title', 'ai.work_hint', 'ai.abort', 'footer.ai_notice'] as $key) {
    if (!isset($help['ui'][$key])) throw new RuntimeException('Missing UI key ' . $key);
    foreach ($help['locales'] as $locale) {
        if (trim((string)($help['ui'][$key][$locale] ?? '')) === '') throw new RuntimeException('Missing ' . $key . ' ' . $locale);
    }
}
if (str_contains($help['ui']['footer.ai_notice']['de-CH'], '{percent}') || str_contains($php, 'openAiQuotaStatus')) throw new RuntimeException('Unreliable quota display must be absent');

echo "AI progress and quota contract passed\n";
