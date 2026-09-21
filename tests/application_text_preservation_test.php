<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../public/index.php');
$start=strpos($source,'function applicationMissingTextFields(');
$end=strpos($source,'function initializeApplicationTexts(',$start);
if ($start===false || $end===false) throw new RuntimeException('Text-preservation helpers not found.');
eval(substr($source,$start,$end-$start));

$current=[
    'email_subject'=>'  Meine Betreffzeile  ',
    'email_body'=>'<p>Meine <strong>Begleit-E-Mail</strong></p>',
    'cover_letter_text'=>'<p>Mein eigenes Motivationsschreiben</p>',
];
$generated=[
    'email_subject'=>'KI-Betreff',
    'email_body'=>'KI-E-Mail',
    'cover_letter_text'=>'KI-Brief',
];
if (in_array(true,applicationMissingTextFields($current),true)) throw new RuntimeException('Filled fields misclassified as empty.');
if (applicationFillMissingTexts($current,$generated)!==$current) throw new RuntimeException('Filled text changed during fill-only merge.');

$partial=$current;
$partial['cover_letter_text']='';
$merged=applicationFillMissingTexts($partial,$generated);
if ($merged['cover_letter_text']!=='KI-Brief' || $merged['email_subject']!==$current['email_subject'] || $merged['email_body']!==$current['email_body']) {
    throw new RuntimeException('Fill-only merge changed an existing field.');
}
$blank=['email_subject'=>' ','email_body'=>'','cover_letter_text'=>''];
if (applicationFillMissingTexts($blank,$generated)!==$generated) throw new RuntimeException('Empty fields were not generated.');

$viewStart=strpos($source,'$applicationEdit = isset($_GET[\'edit\'])');
$viewEnd=strpos($source,'$history = $applicationEdit ?',$viewStart);
if ($viewStart===false || $viewEnd===false) throw new RuntimeException('Application GET view not found.');
$view=substr($source,$viewStart,$viewEnd-$viewStart);
if (str_contains($view,'initializeApplicationTexts(') || str_contains($view,'UPDATE applications')) {
    throw new RuntimeException('Opening the application can still rewrite its text fields.');
}
$initStart=strpos($source,'function initializeApplicationTexts(');
$initEnd=strpos($source,'function matchJob(',$initStart);
$initialization=substr($source,$initStart,$initEnd-$initStart);
if (!str_contains($initialization,"if (!in_array(true,\$missing,true)) return ['texts'=>\$current,'ai'=>true];")) {
    throw new RuntimeException('Filled application does not return before enrichment or writing.');
}
if (str_contains($initialization,'applicationTextWithoutDisqualifyingLanguage($original)')) {
    throw new RuntimeException('Existing text is still silently cleaned during initialization.');
}

$actionStart=strpos($source,"if (\$action === 'revise_application_texts_ai')");
$actionEnd=strpos($source,"if (in_array(\$action, ['save_application', 'autosave_application']",$actionStart);
$action=substr($source,$actionStart,$actionEnd-$actionStart);
$guard=strpos($action,"if (\$submittedInstruction==='' && !in_array(true,\$missingTexts,true))");
$aiCall=strpos($action,'$texts=applicationAiTexts(');
$write=strpos($action,'UPDATE applications SET email_subject=?');
if ($guard===false || $aiCall===false || $write===false || !($guard<$aiCall && $aiCall<$write)) {
    throw new RuntimeException('Empty-instruction AI action can still overwrite fully populated texts.');
}
if (!str_contains($action,"if (\$submittedInstruction==='') \$texts=applicationFillMissingTexts(\$currentTexts,\$texts);")) {
    throw new RuntimeException('Empty-instruction AI action does not preserve filled fields.');
}
echo "PASS opening preserves texts; empty-instruction AI fills only missing fields; explicit edits remain available\n";
