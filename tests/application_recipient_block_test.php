<?php
declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sanitizeRichText(?string $value): string { return trim((string)$value); }
function richTextPlain(?string $value): string { return trim(html_entity_decode(strip_tags(str_ireplace(['<br>','<br/>','<br />','</p>'], ["\n","\n","\n","\n"], (string)$value)), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }

$source = file_get_contents(__DIR__ . '/../public/index.php');
$start = strpos($source, 'function applicationRecipientBlockLines(');
$end = strpos($source, 'function applicationRecipientBlockForApplication(', $start);
$secondStart = strpos($source, 'function applicationCoverLetterWithRecipientBlock(', $end);
$secondEnd = strpos($source, 'function applicationWritingContext(', $secondStart);
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

$oldBlock = applicationCoverLetterWithRecipientBlock('<p>Bardusch AG<br>Industriestrasse 22<br>2545 Selzach</p><p>Guten Tag</p>', $block);
if (substr_count(richTextPlain($oldBlock), 'Bardusch AG') !== 1 || !str_starts_with(richTextPlain($oldBlock), $expected)) throw new RuntimeException('Incomplete recipient block was not replaced.');
echo "PASS an incomplete old address block is replaced instead of duplicated\n";

$misplaced = applicationCoverLetterWithRecipientBlock('<p>Motivation zuerst</p><p>Bardusch AG<br>Patrick Wenger<br>Industriestrasse 22<br>2545 Selzach</p>', $block);
if (!str_starts_with(richTextPlain($misplaced), $expected)) throw new RuntimeException('A misplaced address block was accepted.');
echo "PASS recipient block is enforced at the beginning, not merely somewhere in the letter\n";

$withoutContact = implode("\n", applicationRecipientBlockLines($data, null, false));
if (str_contains($withoutContact, 'Patrick Wenger') || str_contains($withoutContact, '[')) throw new RuntimeException('Unknown contact produced a person or placeholder.');
echo "PASS unknown contact is neither invented nor represented by a placeholder\n";
if (str_contains(applicationRecipientBlock($data,null),'[')) throw new RuntimeException('Application prompt still emits address placeholders.');
echo "PASS application prompts never emit address placeholders\n";

$application = ['id'=>40,'job_id'=>50,'primary_contact_id'=>0,'company_id'=>60,'intermediary_company_id'=>70];
$companyContact = ['id'=>1,'company_id'=>60,'application_id'=>0,'job_id'=>0,'position'=>'Sales','department'=>''];
$hrContact = ['id'=>2,'company_id'=>60,'application_id'=>0,'job_id'=>0,'position'=>'HR Business Partner','department'=>'Human Resources'];
$jobContact = ['id'=>3,'company_id'=>60,'application_id'=>0,'job_id'=>50,'position'=>'Sales','department'=>''];
$applicationContact = ['id'=>4,'company_id'=>60,'application_id'=>40,'job_id'=>0,'position'=>'Sales','department'=>''];
$primaryContact = ['id'=>5,'company_id'=>60,'application_id'=>0,'job_id'=>0,'position'=>'Sales','department'=>''];
if (applicationRecipientCandidatePriority($companyContact,$application)!==6) throw new RuntimeException('Company fallback priority is wrong.');
if (applicationRecipientCandidatePriority($hrContact,$application)!==4) throw new RuntimeException('HR company contact priority is wrong.');
if (applicationRecipientCandidatePriority($jobContact,$application)!==2) throw new RuntimeException('Job-linked contact priority is wrong.');
if (applicationRecipientCandidatePriority($applicationContact,$application)!==1) throw new RuntimeException('Application-linked contact priority is wrong.');
$application['primary_contact_id']=5;
if (applicationRecipientCandidatePriority($primaryContact,$application)!==0) throw new RuntimeException('Primary contact priority is wrong.');
$staleContact = ['id'=>5,'company_id'=>80,'application_id'=>40,'job_id'=>50,'position'=>'HR','department'=>''];
if (applicationRecipientCandidatePriority($staleContact,$application)!==99) throw new RuntimeException('A contact from a previously linked unrelated company remained eligible.');
echo "PASS existing contacts are ranked primary, application, job, HR and company-wide\n";
