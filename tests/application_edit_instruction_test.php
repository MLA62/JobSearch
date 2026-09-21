<?php
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $value): int { return strlen($value); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value,int $start,?int $length=null): string { return $length===null ? substr($value,$start) : substr($value,$start,$length); } }
function richTextPlain(?string $value): string
{
    return trim(html_entity_decode(strip_tags(str_replace(['<br>','</p>'],"\n",(string)$value)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
}

$source=file_get_contents(__DIR__.'/../public/index.php');
$start=strpos($source,'function applicationEditTargets(');
$end=strpos($source,'function applicationAiTexts(',$start);
if ($start===false || $end===false) throw new RuntimeException('Edit-request helpers not found.');
eval(substr($source,$start,$end-$start));

$instruction='Motivationsschreiben30% ausführlicher. Lass die Erfolgszahlen komplett weg.';
$targets=applicationEditTargets($instruction);
if ($targets!==['cover_letter_text']) throw new RuntimeException('The letter-specific request also targets unrelated fields.');
if (applicationEditTargets('Formuliere Begleit-E-Mail und Motivationsschreiben persönlicher.')!==['email_body','cover_letter_text']) throw new RuntimeException('Two explicitly targeted texts not recognised.');
if (applicationEditTargets('Formuliere natürlicher.')!==['email_body','cover_letter_text']) throw new RuntimeException('Unscoped revision not applied to both texts.');

$address="ROCKEN\nDiego Barth\nWerdstrasse 21\n8004 Zürich";
$original=$address."\n\nBewerbung als Key Account Manager ICT (m/w/d)\n\nGuten Tag Herr Barth\n\n".
    str_repeat('Ich betreue Enterprise-Kunden und entwickle Lösungen gemeinsam mit technischen Spezialisten. ',8).
    "Bei Wizlynx gewann ich 25 bis 30 Aufträge und erzielte CHF 400'000 Umsatz.\n\nFreundliche Grüsse\nMarkus Lauber";
$short=$address."\n\nBewerbung als Key Account Manager ICT (m/w/d)\n\nGuten Tag Herr Barth\n\n".
    str_repeat('Ich betreue Enterprise-Kunden und entwickle Lösungen gemeinsam mit technischen Spezialisten. ',9).
    "Bei Wizlynx gewann ich 25 bis 30 Aufträge.\n\nFreundliche Grüsse\nMarkus Lauber";
$current=['cover_letter_text'=>$original];
$issues=applicationEditRequestIssues($instruction,$current,['cover_letter_text'=>$short],$address,$targets);
if (count($issues)!==2) throw new RuntimeException('Short letter with success figures was not rejected for both explicit requirements: '.implode(' ',$issues));
$expanded=$address."\n\nBewerbung als Key Account Manager ICT (m/w/d)\n\nGuten Tag Herr Barth\n\n".
    str_repeat('Ich betreue Enterprise-Kunden und entwickle Lösungen gemeinsam mit technischen Spezialisten. ',12).
    "In der Kundenentwicklung verbinde ich die technische Perspektive mit kaufmännischen Anforderungen und begleite die Abstimmung bis zum Vertragsabschluss.\n\nFreundliche Grüsse\nMarkus Lauber";
$issues=applicationEditRequestIssues($instruction,$current,['cover_letter_text'=>$expanded],$address,$targets);
if ($issues) throw new RuntimeException('Expanded letter without success figures rejected: '.implode(' ',$issues));
if (!str_contains(applicationEditLetterContent($expanded,$address),'Enterprise-Kunden')) throw new RuntimeException('The content extractor lost the body.');
if (str_contains(applicationEditLetterContent($expanded,$address),'Werdstrasse 21')) throw new RuntimeException('The content extractor included the recipient address.');
$englishRequest='Make the cover letter 30% longer. Remove all success figures.';
if (applicationEditTargets($englishRequest)!==['cover_letter_text']) throw new RuntimeException('English letter target not recognised.');
$englishIssues=applicationEditRequestIssues($englishRequest,$current,['cover_letter_text'=>$short],$address,['cover_letter_text']);
if (count($englishIssues)!==2) throw new RuntimeException('English length and omission request not checked.');
$frenchRequest='Rends la lettre de motivation 30 % plus détaillée. Supprime les résultats chiffrés.';
$frenchIssues=applicationEditRequestIssues($frenchRequest,$current,['cover_letter_text'=>$short],$address,['cover_letter_text']);
if (count($frenchIssues)!==2) throw new RuntimeException('French length and omission request not checked.');

foreach ([
    'manual user instruction at end'=>'Current user edit request (apply this now',
    'no accumulated retry drafts'=>"\$payload['input'][0]['content']=\$baseInputParts;",
    'manual length overrides default prompt'=>'Follow the user-requested length instead of the default word target.',
    'specific edit gate'=>'applicationEditRequestIssues($editingRequest,$currentTexts,$texts,$recipientBlock,$editTargets)',
    'failed instruction and current texts retained'=>"\$_SESSION['application_ai_instruction_draft']=['application_id'=>\$id,'text'=>\$submittedInstruction,'current_texts'=>\$currentTexts]",
] as $label=>$needle) if (!str_contains($source,$needle)) throw new RuntimeException('Missing '.$label);
echo "PASS explicit length and omission instructions, recipient exception, scoped editing, retry and draft preservation\n";
