<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../public/index.php');
$checks=[
    'version 2.3.2'=>"\$codeVersion = '2.3.2'",
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
];
foreach($checks as $label=>$needle){
    if(!str_contains($source,$needle)) throw new RuntimeException('Missing '.$label);
    echo 'PASS '.$label."\n";
}
if(str_contains($source,"\$applicationEdit['cover_letter_text'] ?: \$coverLetterPrompt")) {
    throw new RuntimeException('Legacy external prompt must not be placed in the cover-letter field.');
}
echo "Application AI text checks passed.\n";
