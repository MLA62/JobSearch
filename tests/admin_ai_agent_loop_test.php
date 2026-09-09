<?php
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/public/index.php');

function agentLoopAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$intentStart = strpos($source, 'function adminAiStatusQuestion(');
$intentEnd = strpos($source, 'function adminAiExecutionErrors(', $intentStart);
agentLoopAssert($intentStart !== false && $intentEnd !== false, 'Admin AI intent functions not found');
eval(substr($source, $intentStart, $intentEnd - $intentStart));

$exactInstruction = 'Erstelle ein generatives Jobprofil in Jobs für Cleever, dass auf mein Profil passt (prüfe meinen CV).';
agentLoopAssert(adminAiWriteIntent($exactInstruction), 'The reported create-job instruction is not recognized as a write command');
agentLoopAssert(!adminAiWriteIntent('Wurde Cleeven bereits erfasst?'), 'A status question must remain read-only');

$requiredContracts = [
    'agent loop' => 'function adminAiRunTask(',
    'four bounded rounds' => '$maxRounds = 4;',
    'execution feedback' => "'execution_feedback' => mb_substr(\$executionFeedback",
    'completion flag schema' => "'task_complete' => ['type' => 'boolean']",
    'next step schema' => "'next_step' => ['type' => 'string'",
    'refreshed context each round' => 'adminAiPlatformContext($db, $userId, $instruction)',
    'current profile context' => "'current_profile' => \$currentProfile",
    'current CV context' => "'current_cv' => \$currentCv",
    'current inventory context' => "'current_user_inventory' => \$inventory",
    'self-repair instruction' => 'repair invalid fields or missing dependencies yourself',
    'write verification round' => 'Perform a follow-up verification against the refreshed platform_context now.',
    'status lookup verification' => 'but no record_lookup has executed yet',
    'handler uses agent loop' => 'adminAiRunTask($config, $db, userId(), $instruction, $memory)',
    'specific terminal obstacle' => 'Letztes konkretes Hindernis:',
];
foreach ($requiredContracts as $label => $needle) {
    agentLoopAssert(str_contains($source, $needle), 'Missing ' . $label);
}

$requestStart = strpos($source, 'function adminAiRequest(');
$requestEnd = strpos($source, 'function adminAiSources(', $requestStart);
$requestSource = substr($source, $requestStart, $requestEnd - $requestStart);
agentLoopAssert(str_contains($requestSource, "'task_complete' => !empty(\$data['task_complete'])"), 'Completion flag is not parsed from the AI response');
agentLoopAssert(str_contains($requestSource, "'execution_feedback' => mb_substr(\$executionFeedback"), 'Execution feedback is not sent to the AI');

echo "Admin AI autonomous follow-up contract passed\n";
