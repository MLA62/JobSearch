<?php
declare(strict_types=1);

if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value): string { return strtolower($value); } }
function normalizeLocale(string $locale): string { return $locale; }
function e(?string $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function sanitizeRichText(?string $value): string { return trim((string)$value); }
function richTextPlain(?string $value): string {
    $html=preg_replace('/<(?:br\s*\/?|\/p|\/div)>/i',"\n",(string)$value) ?? (string)$value;
    return trim(html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8'));
}

$source=file_get_contents(__DIR__.'/../public/index.php');
if (!is_string($source)) throw new RuntimeException('Application source unavailable.');
$start=strpos($source,'function applicationEmailWithSignoff(');
$end=strpos($source,'function applicationEditTargets(',$start ?: 0);
if ($start===false || $end===false) throw new RuntimeException('Email signoff helper not found.');
eval(substr($source,$start,$end-$start));

$html='<p>Guten Tag Frau Ulmann</p><p>Ich unterstütze Ihr Team bei der Betreuung komplexer ICT-Kunden. <a href="https://example.test/cv">Lebenslauf</a></p>';
$completed=applicationEmailWithSignoff($html,'de-CH','Markus Lauber');
if (!str_contains($completed,'href="https://example.test/cv"') || !str_ends_with(richTextPlain($completed),"Freundliche Grüsse\nMarkus Lauber")) {
    throw new RuntimeException('Email closing repair lost formatting or the applicant name.');
}
if (applicationEmailWithSignoff($completed,'de-CH','Markus Lauber')!==$completed) {
    throw new RuntimeException('A complete email was changed on repeated repair.');
}
foreach (['fr-CH'=>'Meilleures salutations','en-GB'=>'Kind regards','pt-BR'=>'Atenciosamente','es-MX'=>'Saludos cordiales'] as $locale=>$closing) {
    $localized=applicationEmailWithSignoff('Bonjour, merci pour votre annonce. Je joins ma candidature complète pour votre étude.',$locale,'Markus Lauber');
    if (!str_contains(richTextPlain($localized),$closing) || !str_ends_with(richTextPlain($localized),'Markus Lauber')) {
        throw new RuntimeException('Email signoff repair failed for '.$locale);
    }
}

$aiStart=strpos($source,'function applicationAiTexts(');
$aiEnd=strpos($source,'function applicationEnsureRecipientData(',$aiStart ?: 0);
if ($aiStart===false || $aiEnd===false) throw new RuntimeException('Application AI function not found.');
$ai=substr($source,$aiStart,$aiEnd-$aiStart);
if (!str_contains($ai,'applicationRecipientBlockForApplication($db,$userId,$applicationId)')
    || !str_contains($ai,'applicationCvSourceRows($db, $userId)')
    || !str_contains($source,'SUBSTRING(c.notes,1,3000) company_notes')) {
    throw new RuntimeException('Current platform recipient, CV and company data are not read for each AI run.');
}
foreach ([
    "if (\$attempt===1) {",
    'catch (Throwable $reviewException)',
    'Application AI editorial review retained',
    'Application AI recipient review retained',
    '$bestValidTexts=$texts;',
    'if ($bestValidTexts===null) throw $attemptException;',
    '$texts=$bestValidTexts;',
    'applicationEditRequestIssues($editingRequest,$currentTexts,$texts,$recipientBlock,$editTargets)',
    'applicationLetterStructureIssues($texts[\'cover_letter_text\'],$locale,$applicant,$recipientBlock)',
] as $required) {
    if (!str_contains($ai,$required)) throw new RuntimeException('Abort-gate contract missing: '.$required);
}
foreach ([
    'throw new RuntimeException(\'Der Entwurf bestand die unabhängige Empfängerprüfung nicht:',
    'throw new RuntimeException(\'Die KI-Texte sind auch nach Korrektur nicht ausreichend individuell:',
] as $forbidden) {
    if (str_contains($ai,$forbidden)) throw new RuntimeException('A model opinion still unconditionally vetoes an otherwise valid draft.');
}
$minimumOffset=strpos($ai,'$tooShort=[];');
$fallbackOffset=strpos($ai,'$bestValidTexts=$texts;');
if ($minimumOffset===false || $fallbackOffset===false || $minimumOffset>$fallbackOffset) {
    throw new RuntimeException('The editorial fallback was captured before hard minimum-substance validation.');
}
echo "PASS optional model reviews advise once but cannot veto valid drafts; explicit edits and structure remain checked\n";
