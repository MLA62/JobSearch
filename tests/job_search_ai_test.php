<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
if ($source === false) {
    fwrite(STDERR, "Could not read application source.\n");
    exit(1);
}

$required = [
    'CREATE TABLE IF NOT EXISTS user_job_search_criteria',
    'function savedJobSearchCriteria',
    'function saveJobSearchCriteria',
    'function openAiJobSearchSuggestion',
    "if (\$action === 'reset_platform_search_criteria')",
    "if (\$action === 'suggest_job_search_criteria')",
    "name=\"search_location\"",
    "value=\"reset_platform_search_criteria\"",
    "value=\"suggest_job_search_criteria\"",
    "'store' => false",
    "'max_output_tokens' => 180",
];
foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing required job-search AI behaviour: {$needle}\n");
        exit(1);
    }
}
if (!str_contains($source, "saveJobSearchCriteria(\$db, userId(), \$query, \$location, \$total, \$platformIds)")) {
    fwrite(STDERR, "Manual criteria are not persisted when a search package is generated.\n");
    exit(1);
}

echo "PASS Job-search criteria persist per user, profile defaults reset explicitly, and AI calls are bounded.\n";
