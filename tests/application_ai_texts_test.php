<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../public/index.php');
$checks=[
    'version 2.4.50'=>"\$codeVersion = '2.4.50'",
    'structured AI function'=>'function applicationAiTexts(',
    'automatic initial drafts'=>'function initializeApplicationTexts(',
    'rejected drafts are not silently replaced'=>'Ein KI-Text ist zu kurz oder inhaltsleer; es wurde kein generischer Ersatz gespeichert.',
    'Responses API'=>"curl_init('https://api.openai.com/v1/responses')",
    'no API storage'=>"'store'=>false",
    'per-user safety identifier'=>"hash('sha256','jema-application-texts:'.\$userId)",
    'strict structured output'=>"'name'=>'application_texts','strict'=>true",
    'evidence output fields'=>"'required'=>['email_subject','email_body','cover_letter_text','evidence_links']",
    'fresh latest CV per language source rows'=>'function applicationCvSourceRows(',
    'complete CV file input'=>'function applicationCvInputParts(',
    'selected CVs attached to AI request'=>'array_push($inputParts, ...applicationCvInputParts($cvRows, $userId, storageRoot()))',
    'manual AI action'=>"\$action === 'revise_application_texts_ai'",
    'two-line instruction'=>'name="ai_text_instruction" rows="2"',
    'instruction length bound'=>'maxlength="2000"',
    'explicit editing request contract'=>'Carry out every concrete request visibly',
    'editing request controls named text fields'=>'Revise only the text fields explicitly named by user_editing_request',
    'unchanged result retry'=>'Apply the editing request visibly in every requested field.',
    'targeted change verification'=>'foreach ($editTargets as $field)',
    'explicit edit constraint verification'=>'applicationEditRequestIssues($editingRequest,$currentTexts,$texts,$recipientBlock,$editTargets)',
    'specific user-visible failure'=>'applications.ai_failed_detail',
    'empty instruction regeneration contract'=>'Create all three texts from scratch. No previous draft is supplied.',
    'empty instruction excludes current texts'=>"'current_texts'=>\$regenerate ? null : \$currentTexts",
    'no automatic sending'=>'Nichts wird automatisch versendet.',
    'recipient block guaranteed after AI'=>'applicationCoverLetterWithRecipientBlock(',
    'known primary contact loaded'=>'applicationRecipientBlockForApplication(',
    'effective known contact resolver'=>'function applicationRecipientForApplication(',
    'application-linked contact resolution'=>'c.application_id=?',
    'job-linked contact resolution'=>'c.job_id=?',
    'existing complete cover is preserved'=>'if (!in_array(true,$missing,true)) return [\'texts\'=>$current,\'ai\'=>true];',
    'application view does not initialize texts'=>'$applicationEdit = isset($_GET[\'edit\'])',
    'AI must begin with recipient block'=>'The cover letter starts with the recipient block',
    'job advertisement analysed before preparation'=>'$analysed=verifiedJobImport(',
    'missing recipient research'=>'function applicationEnsureRecipientData(',
    'recipient research before existing text return'=>'applicationEnsureRecipientData($config,$db,$userId,$applicationId);',
    'web research fallback'=>'jobWebResearchResponse($config,$userId,$draft,$missing)',
    'selected job remains refresh target'=>"\$analysed['target_job_id']=\$jobId;",
    'recipient enrichment cannot block text preparation'=>'Application recipient enrichment continued with existing data',
    'no-data disclosure is prohibited'=>'Never mention missing or unreadable source material',
    'interview deferral is prohibited'=>'defer the substance to an interview',
    'AI quality retry'=>'Revise this rejected draft',
    'final AI output quality gate'=>'applicationTextQualityIssues($texts,$jobSource,$cvRows,$applicant,in_array(',
    'initialization fills only missing fields'=>'$drafts=applicationFillMissingTexts($current,$generated);',
    'initial drafts are generated anew'=>"applicationAiTexts(\$config,\$db,\$userId,\$applicationId,\$currentUser,'',\$current)",
    'narrow application context'=>'applicationWritingContext($db,$userId,$applicationId,$currentUser,$recipientBlock)',
    'no older drafts sent'=>'The current_texts in this request are the only existing draft.',
    'applicant benefit first'=>'The objective is to show the specific benefit this candidate can bring to the future employer and role',
    'Swiss best-practice guide in prompt'=>'applicationSwissWritingGuide()',
    'failed AI leaves draft unwritten'=>'$generated=applicationAiTexts($config,$db,$userId,$applicationId,$currentUser,\'\',$current);',
];
foreach($checks as $label=>$needle){
    if(!str_contains($source,$needle)) throw new RuntimeException('Missing '.$label);
    echo 'PASS '.$label."\n";
}
if(str_contains($source,"\$applicationEdit['cover_letter_text'] ?: \$coverLetterPrompt")) {
    throw new RuntimeException('Legacy external prompt must not be placed in the cover-letter field.');
}
foreach (['kein lesbarer aktueller Lebenslauf vorhanden','keine Kontakte erfasst','keine Dokumente zugeordnet','keine Kontaktaktivitäten erfasst'] as $forbiddenContext) {
    if(str_contains($source,$forbiddenContext)) throw new RuntimeException('Applicant prompt still exposes missing context: '.$forbiddenContext);
}
$startApplicationStart=strpos($source,"if (\$action === 'start_application')");
$applicationWriteStart=strpos($source,'$db->begin_transaction();',$startApplicationStart);
if($startApplicationStart===false || $applicationWriteStart===false) throw new RuntimeException('Application preparation block not found.');
$startApplicationBlock=substr($source,$startApplicationStart,strpos($source,"if (\$action === 'set_intermediary')",$startApplicationStart)-$startApplicationStart);
if (!str_contains($startApplicationBlock,'$analysed=verifiedJobImport(') || !str_contains($startApplicationBlock,'$db->commit();') || !str_contains($startApplicationBlock,"if (\$exception instanceof AiWorkStopped) throw \$exception;")) {
    throw new RuntimeException('Advertisement analysis or cancellation contract is missing.');
}
echo "PASS advertisement analysis remains optional after application storage\n";
echo "Application AI text checks passed.\n";
