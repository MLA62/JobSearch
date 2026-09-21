<?php
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $value): int { return strlen($value); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value,int $start,?int $length=null): string { return $length===null ? substr($value,$start) : substr($value,$start,$length); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value): string { return strtolower($value); } }
function repairMojibake(string $value): string { return $value; }
function e(string $value): string { return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function normalizeLocale(string $value): string { return $value; }

$source=file_get_contents(__DIR__.'/../public/index.php');
foreach ([
    ['function sanitizeRichText(', 'function richTextHtml('],
    ['function richTextPlain(', 'function ensureIndex('],
    ['function applicationCoverLetterWithRecipientBlock(', 'function applicationEndClientOfficialContext('],
    ['function applicationEditRecipientBlock(', 'function applicationEditLetterContent('],
] as [$startMarker,$endMarker]) {
    $start=strpos($source,$startMarker);
    $end=strpos($source,$endMarker,$start ?: 0);
    if ($start===false || $end===false) throw new RuntimeException('Letter helper missing.');
    eval(substr($source,$start,$end-$start));
}

$recipient="Muster AG\nAlex Beispiel\nMusterstrasse 21\n8004 Zürich";
$editedRecipient="Muster AG\nRobin Kontakt\nNeue Strasse 8\n4052 Basel";
if (applicationEditRecipientBlock($editedRecipient."\n\nBewerbung als Account Manager\n\nGuten Tag",$recipient)!==$editedRecipient) {
    throw new RuntimeException('The current user-supplied address was replaced by an older database address.');
}
if (applicationEditRecipientBlock("Fremde AG\nRobin Kontakt\nNeue Strasse 8\n4052 Basel",$recipient)!==$recipient) {
    throw new RuntimeException('An unrelated company address was accepted.');
}
$body="Guten Tag Herr Beispiel\n\n".str_repeat('Ich entwickle kundennahe ICT-Lösungen gemeinsam mit technischen Spezialisten. ',22)."\n\n".
    "Ich möchte diese Erfahrung in Ihr Team einbringen und freue mich auf den Austausch über die Aufgaben.\n\nFreundliche Grüsse\nMarkus Lauber";
$subject="Bewerbung als Key Account Manager ICT (m/w/d)";
foreach ([$body, '<p>'.str_replace("\n",'<br>',$body).'</p>', '<p>Falsche Firma<br>Falscher Kontakt</p>'.$body,
    '<p>Falsche Firma<br>Falscher Kontakt</p><p>'.$subject.'</p>'.$body,
    '<p>'.str_replace("\n",'<br>',$recipient).'</p><p>'.$subject.'</p>'.$body,
] as $variant) {
    $letter=applicationCoverLetterWithRecipientBlock($variant,$recipient);
    $issues=applicationLetterStructureIssues($letter,'de-CH','Markus Lauber',$recipient);
    if ($issues) throw new RuntimeException(implode(' ',$issues).' Output: '.richTextPlain($letter));
    if (str_contains($variant,$subject) && !str_contains(richTextPlain($letter),$subject)) throw new RuntimeException('The user-supplied subject was lost.');
    if (str_contains(richTextPlain($letter),'Falscher Kontakt')) throw new RuntimeException('The stale recipient was kept.');
}
echo "PASS recipient block and letter structure across plain and HTML formats\n";

// A missing greeting is a deterministic formatting repair, not a reason to
// discard an otherwise useful AI response after three identical retries.
$withoutGreeting=$recipient."\n\n".$subject."\n\n".
    str_repeat('Meine Vertriebserfahrung hilft, komplexe ICT-Anforderungen mit technischen Teams für Kunden verständlich umzusetzen. ',16).
    "\n\nIch freue mich auf ein Gespräch über Ihren konkreten Bedarf und die nächsten Schritte.\n\nFreundliche Grüsse\nMarkus Lauber";
$repaired=applicationLetterWithSalutation(applicationCoverLetterWithRecipientBlock($withoutGreeting,$recipient),'de-CH',$recipient);
$plain=richTextPlain($repaired);
if (preg_match('/'.preg_quote($subject,'/').'\s+Guten Tag/u',$plain)!==1) throw new RuntimeException('Missing greeting was not inserted after the subject.');
if (substr_count($plain,'Meine Vertriebserfahrung')!==16) throw new RuntimeException('Greeting repair lost letter content.');
if (applicationLetterStructureIssues($repaired,'de-CH','Markus Lauber',$recipient)) throw new RuntimeException('Repaired letter still fails structure validation.');
$withSwissGreeting=str_replace($subject."\n\n",$subject."\n\nGrüezi Herr Beispiel\n\n",$withoutGreeting);
$preserved=applicationLetterWithSalutation(applicationCoverLetterWithRecipientBlock($withSwissGreeting,$recipient),'de-CH',$recipient);
if (substr_count(richTextPlain($preserved),'Grüezi Herr Beispiel')!==1 || str_contains(richTextPlain($preserved),'Guten Tag')) throw new RuntimeException('Existing Swiss greeting was overwritten.');
if (applicationLetterStructureIssues($preserved,'de-CH','Markus Lauber',$recipient)) throw new RuntimeException('Existing Swiss greeting was rejected.');
foreach (['fr-CH'=>'Bonjour','en-GB'=>'Dear Hiring Team','pt-BR'=>'Prezados Senhores','es-MX'=>'Estimado equipo de selección'] as $locale=>$greeting) {
    $localized=applicationLetterWithSalutation($withoutGreeting,$locale,$recipient);
    if (preg_match('/'.preg_quote($subject,'/').'\s+'.preg_quote($greeting,'/').'/u',richTextPlain($localized))!==1) {
        throw new RuntimeException('Missing greeting was not repaired for '.$locale.'.');
    }
}
echo "PASS missing greeting repaired without losing content; Swiss greeting preserved\n";
