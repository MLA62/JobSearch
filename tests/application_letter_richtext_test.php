<?php
declare(strict_types=1);

function repairMojibake(string $value): string { return $value; }
function e(?string $value): string { return htmlspecialchars((string)$value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'); }
function normalizeLocale(string $locale): string { return $locale; }
if (!function_exists('mb_substr')) { function mb_substr(string $value,int $start,?int $length=null): string { return $length===null ? substr($value,$start) : substr($value,$start,$length); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $value): int { return strlen($value); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value): string { return strtolower($value); } }

$source=file_get_contents(__DIR__.'/../public/index.php');
$richStart=strpos($source,'function sanitizeRichText(');
$richEnd=strpos($source,'function ensureIndex(',$richStart);
$letterStart=strpos($source,'function applicationCoverLetterWithRecipientBlock(');
$letterEnd=strpos($source,'function applicationEndClientOfficialContext(',$letterStart);
if ($richStart===false || $richEnd===false || $letterStart===false || $letterEnd===false) throw new RuntimeException('Production rich-text or letter helpers missing.');
eval(substr($source,$richStart,$richEnd-$richStart).substr($source,$letterStart,$letterEnd-$letterStart));

$block="Bardusch AG\nPatrick Wenger\nIndustriestrasse 22\n2545 Selzach";
$body=str_repeat('Die ausgeschriebene Aufgabe verbindet Kundenberatung mit eigenverantwortlicher Planung. Meine bisherige Arbeit umfasst die strukturierte Betreuung von Geschäftskunden und die verlässliche Abstimmung mit internen Teams. ',5);
$letter='<p>Guten Tag Herr Wenger</p><p>'.e($body).'</p><p>Ich freue mich auf Ihre Rückmeldung und die Gelegenheit, Sie persönlich kennenzulernen.</p><p>Freundliche Grüsse<br>Markus Lauber</p>';
$finished=applicationCoverLetterWithRecipientBlock($letter,$block);
$issues=applicationLetterStructureIssues($finished,'de-CH','Markus Lauber',$block);
if ($issues) throw new RuntimeException('Real sanitizer/recipient/validator integration failed: '.implode(' ',$issues));
if (!str_starts_with(richTextPlain($finished),$block)) throw new RuntimeException('Recipient block is not first in sanitized letter.');
echo "PASS production rich-text, recipient block and letter quality integration\n";
