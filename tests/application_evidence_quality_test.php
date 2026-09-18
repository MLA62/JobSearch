<?php
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $value): int { return strlen($value); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value): string { return strtolower($value); } }
function richTextPlain(?string $value): string { return trim(html_entity_decode(strip_tags(str_replace(['<br>','</p>'],"\n",(string)$value)),ENT_QUOTES|ENT_HTML5,'UTF-8')); }

$source=file_get_contents(__DIR__.'/../public/index.php');
$start=strpos($source,'function applicationTextQualityIssues(');
$end=strpos($source,'function applicationAiTexts(',$start);
if ($start===false || $end===false) throw new RuntimeException('Evidence quality validator missing.');
eval(substr($source,$start,$end-$start));

$job="Beratung und Ausbau bestehender Geschäftskunden im Aussendienst.\nEigenständige Planung der Kundenbesuche und systematische Nachbetreuung.";
$cvRows=[['id'=>42,'corrected_text'=>'Betreute Geschäftskunden im Aussendienst und entwickelte neue Kundenbeziehungen. Organisierte regelmässig Kundenbesuche und dokumentierte die Folgeaufgaben.']];
$texts=[
    'email_body'=>"Guten Tag Herr Muster\n\nFür Ihre Aussendienstrolle bringe ich Erfahrung in der Betreuung von Geschäftskunden und in der strukturierten Nachbereitung von Kundenbesuchen mit. Meine Bewerbung und meinen Lebenslauf finden Sie im Anhang.\n\nFreundliche Grüsse\nMarkus Lauber",
    'cover_letter_text'=>"Die Betreuung von Geschäftskunden im Aussendienst gehört zu meiner bisherigen Arbeit. Kundenbesuche plane ich eigenständig und dokumentiere Folgeaufgaben für eine verlässliche Nachbetreuung.",
    'evidence_links'=>[
        ['cv_id'=>42,'cv_quote'=>'Betreute Geschäftskunden im Aussendienst','job_quote'=>'Beratung und Ausbau bestehender Geschäftskunden','letter_excerpt'=>'Die Betreuung von Geschäftskunden im Aussendienst gehört zu meiner bisherigen Arbeit.'],
        ['cv_id'=>42,'cv_quote'=>'Organisierte regelmässig Kundenbesuche und dokumentierte die Folgeaufgaben.','job_quote'=>'Eigenständige Planung der Kundenbesuche','letter_excerpt'=>'Kundenbesuche plane ich eigenständig und dokumentiere Folgeaufgaben für eine verlässliche Nachbetreuung.'],
    ],
];
if ($issues=applicationTextQualityIssues($texts,$job,$cvRows,'Markus Lauber')) throw new RuntimeException('Supported draft rejected: '.implode('; ',$issues));
$broken=$texts; $broken['evidence_links'][0]['job_quote']='Unbelegtes Wachstum in Asien und Amerika';
if (!applicationTextQualityIssues($broken,$job,$cvRows,'Markus Lauber')) throw new RuntimeException('Invented job requirement accepted.');
$broken=$texts; $broken['evidence_links'][0]['cv_quote']='Erzielte hunderte Millionen Franken Umsatz';
if (!applicationTextQualityIssues($broken,$job,$cvRows,'Markus Lauber')) throw new RuntimeException('Invented corrected CV detail accepted.');
$broken=$texts; $broken['evidence_links'][0]['cv_id']=999;
if (!applicationTextQualityIssues($broken,$job,$cvRows,'Markus Lauber')) throw new RuntimeException('Superseded or unrelated CV accepted.');
$broken=$texts; $broken['evidence_links']=[];
if (!applicationTextQualityIssues($broken,$job,$cvRows,'Markus Lauber')) throw new RuntimeException('Generic evidence-free letter accepted.');
$broken=$texts; $broken['email_body']='Bitte finden Sie meine Unterlagen im Anhang.';
if (!applicationTextQualityIssues($broken,$job,$cvRows,'Markus Lauber')) throw new RuntimeException('Empty email accepted.');
$broken=$texts; $broken['cover_letter_text']='Die Position als Sales Manager hat mein Interesse geweckt. '.$texts['cover_letter_text'];
if (!applicationTextQualityIssues($broken,$job,$cvRows,'Markus Lauber')) throw new RuntimeException('Stock cover letter opening accepted.');
echo "PASS source-backed links, current CV id, and substantive email are required\n";
