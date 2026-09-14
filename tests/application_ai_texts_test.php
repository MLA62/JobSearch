<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../public/index.php');
$checks=[
    'version 2.4.17'=>"\$codeVersion = '2.4.17'",
    'structured AI function'=>'function applicationAiTexts(',
    'automatic initial drafts'=>'function initializeApplicationTexts(',
    'local failure-safe drafts'=>'function applicationFallbackTexts(',
    'Responses API'=>"curl_init('https://api.openai.com/v1/responses')",
    'no API storage'=>"'store'=>false",
    'per-user safety identifier'=>"hash('sha256','jema-application-texts:'.\$userId)",
    'strict structured output'=>"'name'=>'application_texts','strict'=>true",
    'three output fields'=>"'required'=>['email_subject','email_body','cover_letter_text']",
    'current CV content'=>"dt.code='cv'",
    'corrected or extracted CV text'=>"NULLIF(txt.corrected_text,''),NULLIF(txt.extracted_text,''),NULLIF(txt.ocr_text,'')",
    'current CV only'=>"d.is_current=1",
    'manual AI action'=>"\$action === 'revise_application_texts_ai'",
    'two-line instruction'=>'name="ai_text_instruction" rows="2"',
    'instruction length bound'=>'maxlength="2000"',
    'explicit editing request contract'=>'visibly and substantively apply every feasible requested change',
    'editing request applies to both long texts'=>'apply every feasible requested change in BOTH email_body and cover_letter_text',
    'unchanged result retry'=>'Your previous result left one or more required text fields unchanged',
    'email and cover change verification'=>"foreach (['email_body','cover_letter_text'] as \$field)",
    'specific user-visible failure'=>'applications.ai_failed_detail',
    'empty instruction regeneration contract'=>'create all three texts completely anew',
    'empty instruction excludes current texts'=>"'current_texts'=>\$regenerate ? null : \$currentTexts",
    'no automatic sending'=>'Nichts wird automatisch versendet.',
    'recipient block guaranteed after AI'=>'applicationCoverLetterWithRecipientBlock(',
    'known primary contact loaded'=>'applicationRecipientBlockForApplication(',
    'effective known contact resolver'=>'function applicationRecipientForApplication(',
    'application-linked contact resolution'=>'c.application_id=?',
    'job-linked contact resolution'=>'c.job_id=?',
    'existing complete cover is repaired'=>'$securedCover=applicationCoverLetterWithRecipientBlock(',
    'application view always enforces recipient'=>'if ($applicationEdit) {',
    'AI must begin with recipient block'=>'cover_letter_text must start with the exact recipient address block',
    'job advertisement analysed before preparation'=>'$analysed=verifiedJobImport(',
    'missing recipient research'=>'function applicationEnsureRecipientData(',
    'recipient research before existing text return'=>'applicationEnsureRecipientData($config,$db,$userId,$applicationId);',
    'web research fallback'=>'jobWebResearchResponse($config,$userId,$draft,$missing)',
];
foreach($checks as $label=>$needle){
    if(!str_contains($source,$needle)) throw new RuntimeException('Missing '.$label);
    echo 'PASS '.$label."\n";
}
if(str_contains($source,"\$applicationEdit['cover_letter_text'] ?: \$coverLetterPrompt")) {
    throw new RuntimeException('Legacy external prompt must not be placed in the cover-letter field.');
}
echo "Application AI text checks passed.\n";
