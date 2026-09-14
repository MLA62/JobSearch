<?php
declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sanitizeRichText(?string $value): string { return trim((string)$value); }
function richTextPlain(?string $value): string { return trim(html_entity_decode(strip_tags(str_ireplace(['<br>','<br/>','<br />','</p>'], ["\n","\n","\n","\n"], (string)$value)), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }

$source = file_get_contents(__DIR__ . '/../public/index.php');
$start = strpos($source, 'function applicationRecipientBlockLines(');
$end = strpos($source, 'function applicationRecipientBlockForApplication(', $start);
$secondStart = strpos($source, 'function applicationCoverLetterWithRecipientBlock(', $end);
$secondEnd = strpos($source, 'function applicationPrompt(', $secondStart);
if ($start === false || $end === false || $secondStart === false || $secondEnd === false) throw new RuntimeException('Recipient block helpers not found.');
eval(substr($source, $start, $end - $start) . substr($source, $secondStart, $secondEnd - $secondStart));

$data = ['company_name'=>'Bardusch AG','first_name'=>'Patrick','last_name'=>'Wenger','address_line1'=>'Industriestrasse 22','address_line2'=>'','postal_code'=>'2545','company_city'=>'Selzach'];
$block = implode("\n", applicationRecipientBlockLines($data, $data, false));
$expected = "Bardusch AG\nPatrick Wenger\nIndustriestrasse 22\n2545 Selzach";
if ($block !== $expected) throw new RuntimeException('Known contact block is wrong: ' . $block);
echo "PASS known application contact is in the exact four-line address block\n";

$letter = applicationCoverLetterWithRecipientBlock('<p>Guten Tag Herr Wenger</p>', $block);
$plain = richTextPlain($letter);
if (!str_starts_with($plain, $expected)) throw new RuntimeException('Address block was not prepended to the cover letter.');
echo "PASS address block is prepended to the generated cover letter\n";

$again = applicationCoverLetterWithRecipientBlock($letter, $block);
if (substr_count(richTextPlain($again), 'Bardusch AG') !== 1) throw new RuntimeException('Address block was duplicated.');
echo "PASS existing address block is not duplicated\n";

$misplaced = applicationCoverLetterWithRecipientBlock('<p>Motivation zuerst</p><p>Bardusch AG<br>Patrick Wenger<br>Industriestrasse 22<br>2545 Selzach</p>', $block);
if (!str_starts_with(richTextPlain($misplaced), $expected)) throw new RuntimeException('A misplaced address block was accepted.');
echo "PASS recipient block is enforced at the beginning, not merely somewhere in the letter\n";

$withoutContact = implode("\n", applicationRecipientBlockLines($data, null, false));
if (str_contains($withoutContact, 'Patrick Wenger') || str_contains($withoutContact, '[')) throw new RuntimeException('Unknown contact produced a person or placeholder.');
echo "PASS unknown contact is neither invented nor represented by a placeholder\n";
