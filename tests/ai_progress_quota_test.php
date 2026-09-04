<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$php = file_get_contents($root . '/public/index.php');
$config = file_get_contents($root . '/public/config.example.php');
$help = json_decode(file_get_contents($root . '/docs/jobsearch/help/source.json'), true, 512, JSON_THROW_ON_ERROR);

$checks = [
    'version 2.1.1' => "\$codeVersion = '2.1.1'",
    'usage table' => 'CREATE TABLE IF NOT EXISTS ai_usage_events',
    'response deduplication' => 'UNIQUE KEY uq_ai_usage_response (response_id)',
    'input tokens' => "\$usage['input_tokens']",
    'cached input tokens' => "\$usage['input_tokens_details']['cached_tokens']",
    'output tokens' => "\$usage['output_tokens']",
    'web-search calls' => "=== 'web_search_call'",
    'usage recorder' => 'function recordOpenAiUsage(',
    'quota calculation' => 'function openAiQuotaStatus(',
    'AI modal' => 'id="ai-work-dialog"',
    'modal title' => "tr('ai.work_title')",
    'abort button' => 'data-ai-work-abort',
    'abort controller' => 'new AbortController()',
    'AI actions' => "new Set(['start_application', 'revise_application_texts_ai', 'suggest_job_search_criteria'])",
    'footer disclosure' => "tr('footer.ai_notice'",
    'manufacturer' => "'manufacturer'=>'OpenAI'",
];
foreach ($checks as $label => $needle) {
    if (!str_contains($php, $needle)) throw new RuntimeException('Missing ' . $label);
}

foreach ([
    "'openai_budget_usd' => 10.00",
    "'openai_input_usd_per_million' => 0.20",
    "'openai_cached_input_usd_per_million' => 0.02",
    "'openai_output_usd_per_million' => 1.20",
    "'openai_web_search_usd_per_call' => 0.01",
] as $needle) {
    if (!str_contains($config, $needle)) throw new RuntimeException('Missing config: ' . $needle);
}

foreach (['ai.work_title', 'ai.work_hint', 'ai.abort', 'footer.ai_notice'] as $key) {
    if (!isset($help['ui'][$key])) throw new RuntimeException('Missing UI key ' . $key);
    foreach ($help['locales'] as $locale) {
        if (trim((string)($help['ui'][$key][$locale] ?? '')) === '') throw new RuntimeException('Missing ' . $key . ' ' . $locale);
    }
}
if (!str_contains($help['ui']['footer.ai_notice']['de-CH'], 'App-Kontingent')) throw new RuntimeException('Footer must identify local app allowance');

echo "AI progress and quota contract passed\n";
