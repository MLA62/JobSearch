<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../public/index.php');
if (!is_string($source)) throw new RuntimeException('Application source unavailable.');
$contextStart=strpos($source,'function applicationWritingContext(');
$contextEnd=strpos($source,'function applicationTextQualityIssues(',$contextStart);
$aiStart=strpos($source,'function applicationAiTexts(');
$aiEnd=strpos($source,'function applicationEnsureRecipientData(',$aiStart);
if ($contextStart===false || $contextEnd===false || $aiStart===false || $aiEnd===false) throw new RuntimeException('Writing context or AI request not found.');
$context=substr($source,$contextStart,$contextEnd-$contextStart);
$ai=substr($source,$aiStart,$aiEnd-$aiStart);
foreach (['application_documents','user_documents','document_texts','contact_logs','email_body','cover_letter_text','user_preferences','applicationPrompt('] as $forbidden) {
    if (str_contains($context,$forbidden)) throw new RuntimeException('Earlier or unrelated source in writing context: '.$forbidden);
}
foreach (['job_description','job_requirements','company_name','recipientBlock'] as $required) {
    if (!str_contains($context,$required)) throw new RuntimeException('Missing required writing context: '.$required);
}
if (!str_contains($ai,"'current_texts'=>\$regenerate ? null : \$currentTexts")) throw new RuntimeException('Manual request must use only posted current texts.');
if (!str_contains($ai,'applicationCvSourceRows($db, $userId)')) throw new RuntimeException('CV selection must run for every AI request.');
if (!str_contains($ai,'applicationCvInputParts($cvRows, $userId, storageRoot())')) throw new RuntimeException('Selected CV files must be passed to AI.');
if (str_contains($ai,'applicationPrompt(')) throw new RuntimeException('Legacy broad prompt is still used.');
if (!str_contains($source,"'current_texts'=>\$currentTexts];")) throw new RuntimeException('Posted current texts are not restored after an AI error.');
if (!str_contains($ai,"applicationEditRequestIssues('Erfolgszahlen weglassen'")) throw new RuntimeException('Default no-success-figures validation is missing.');
echo "PASS only current form texts and freshly selected latest CVs enter the writing request\n";
