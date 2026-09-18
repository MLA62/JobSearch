<?php
declare(strict_types=1);

function normalizeLocale(string $locale): string { return $locale; }
if (!function_exists('mb_substr')) { function mb_substr(string $value,int $start,?int $length=null): string { return $length===null ? substr($value,$start) : substr($value,$start,$length); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $value): int { return strlen($value); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value): string { return strtolower($value); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $haystack,string $needle): int|false { return stripos($haystack,$needle); } }
function richTextPlain(?string $value): string
{
    $html=preg_replace('/<(?:br\s*\/?)|(?:\/p)>/i',"\n",(string)$value) ?? (string)$value;
    return trim(html_entity_decode(strip_tags($html),ENT_QUOTES | ENT_HTML5,'UTF-8'));
}
function readableText(string $html): string { return trim(strip_tags($html)); }
function importFetchHtml(string $url,int $timeoutSeconds=30): array
{
    $GLOBALS['letter_test_fetches']=($GLOBALS['letter_test_fetches'] ?? 0)+1;
    return ['url'=>(string)($GLOBALS['letter_test_return_url'] ?? $url),'html'=>'<main>Bardusch AG betreut Geschäftskunden mit Dienstleistungsangeboten.</main>'];
}

$source=file_get_contents(__DIR__.'/../public/index.php');
$start=strpos($source,'function applicationLetterStructureIssues(');
$end=strpos($source,'function applicationTextDisqualifyingPatterns(',$start);
if ($start===false || $end===false) throw new RuntimeException('Letter quality helpers not found.');
eval(substr($source,$start,$end-$start));

$recipient="Bardusch AG\nPatrick Wenger\nIndustriestrasse 22\n2545 Selzach";
$body='Die Stelle im Aussendienst verbindet Beratung und eigenverantwortliche Kundenbetreuung. ';
$body.='In meiner bisherigen Vertriebsarbeit habe ich Kundenbedürfnisse aufgenommen, passende Lösungen erläutert und die weitere Betreuung strukturiert geplant. ';
$body.='Besonders interessieren mich die im Inserat genannten Aufgaben rund um die Entwicklung langfristiger Beziehungen und die Zusammenarbeit mit internen Fachbereichen. ';
$body.='Meine Erfahrung in der Koordination verschiedener Ansprechpartner hilft mir, Kundenanliegen verständlich aufzubereiten und verbindlich nachzuverfolgen. ';
$body.='Der konkrete Fokus Ihrer Stelle auf persönliche Gespräche, klare Prozesse und verlässliche Umsetzung passt zu meiner Arbeitsweise. ';
$body.='Ich möchte diese Erfahrung in Ihr Team einbringen und gemeinsam mit Ihnen an nachhaltigen Kundenbeziehungen arbeiten. ';
$body.='Dabei lege ich Wert auf sorgfältige Vorbereitung, transparente Kommunikation und eine Betreuung, die auch nach dem ersten Abschluss Bestand hat.';
$endings=[
    'de-CH'=>['Guten Tag Herr Wenger','Ich freue mich auf Ihre Rückmeldung und die Gelegenheit, Sie persönlich kennenzulernen.','Freundliche Grüsse'],
    'fr-CH'=>['Bonjour Monsieur Wenger','Je me réjouis de votre réponse et de la possibilité de vous rencontrer personnellement.','Meilleures salutations'],
    'en-GB'=>['Dear Mr Wenger','I look forward to hearing from you and to the opportunity to meet you personally.','Kind regards'],
    'pt-BR'=>['Prezados Senhores','Aguardo seu retorno e a oportunidade de conversar pessoalmente com a equipe.','Atenciosamente'],
    'es-MX'=>['Estimado señor Wenger','Espero su respuesta y la oportunidad de conocer personalmente a su equipo.','Saludos cordiales'],
];
foreach ($endings as $locale=>[$salutation,$lastSentence,$signoff]) {
    $letter=$recipient."\n\n".$salutation."\n\n".$body."\n\n".$lastSentence."\n\n".$signoff."\nMarkus Lauber";
    $issues=applicationLetterStructureIssues($letter,$locale,'Markus Lauber',$recipient);
    if ($issues) throw new RuntimeException($locale.' complete letter rejected: '.implode(' ',$issues));
    $noEnding=str_replace("\n\n".$lastSentence."\n\n".$signoff."\nMarkus Lauber",'',$letter);
    if (!applicationLetterStructureIssues($noEnding,$locale,'Markus Lauber',$recipient)) throw new RuntimeException($locale.' incomplete closing accepted.');
    $noFinalSentence=str_replace("\n\n".$lastSentence,'',$letter);
    if (!in_array('Ein vollständiger eigenständiger Schlusssatz fehlt.',applicationLetterStructureIssues($noFinalSentence,$locale,'Markus Lauber',$recipient),true)) throw new RuntimeException($locale.' missing closing sentence accepted.');
    $noSignoff=str_replace("\n\n".$signoff,'',$letter);
    if (!in_array('Die Grussformel fehlt unmittelbar vor der Unterschrift.',applicationLetterStructureIssues($noSignoff,$locale,'Markus Lauber',$recipient),true)) throw new RuntimeException($locale.' missing sign-off accepted.');
    $noRecipient=substr($letter,strlen($recipient));
    if (!in_array('Der Empfängerblock fehlt am Anfang.',applicationLetterStructureIssues($noRecipient,$locale,'Markus Lauber',$recipient),true)) throw new RuntimeException($locale.' missing recipient accepted.');
    echo 'PASS '.$locale." complete letter and missing closing/recipient checks\n";
}

$short=$recipient."\nGuten Tag Herr Wenger\nIch interessiere mich für diese Stelle.\nIch freue mich auf Ihre Rückmeldung.\nFreundliche Grüsse\nMarkus Lauber";
if (!in_array('Der Briefhauptteil ist zu kurz für eine individuelle Bewerbung.',applicationLetterStructureIssues($short,'de-CH','Markus Lauber',$recipient),true)) throw new RuntimeException('Generic short letter accepted.');
$long=str_replace($body,str_repeat('Diese belegte Erfahrung ist für die konkrete Tätigkeit und die beschriebene Verantwortung relevant. ',55),$letter);
if (!in_array('Der Briefhauptteil ist für eine Seite zu lang.',applicationLetterStructureIssues($long,'de-CH','Markus Lauber',$recipient),true)) throw new RuntimeException('Overlong letter accepted.');
if (!str_contains($source,'Endkundenprofil aus offizieller Website: ') || !str_contains($source,'applicationEndClientOfficialContext($application)')) throw new RuntimeException('Mediated end-client profile is not passed to the application prompt.');
if (!str_contains($source,'Mandatory letter checks failed: ')) throw new RuntimeException('AI quality retry is not wired.');
if (!str_contains($source,'$finalIssues=applicationLetterStructureIssues(')) throw new RuntimeException('Final saved output is not validated.');
echo "PASS short letter rejected and end-client research/AI retry wired\n";

$company=['intermediary_company_id'=>0,'company_is_intermediary'=>0,'company_name'=>'Bardusch AG','company_website'=>'https://bardusch.example'];
if (applicationEndClientOfficialContext($company)!=='') throw new RuntimeException('Direct employer needlessly researched.');
$company['intermediary_company_id']=12;
$company['company_is_intermediary']=1;
if (applicationEndClientOfficialContext($company)!=='') throw new RuntimeException('An intermediary was mistaken for the end client.');
$company['company_is_intermediary']=0;
$profile=applicationEndClientOfficialContext($company);
if (!str_contains($profile,'Bardusch AG') || !str_contains($profile,'https://bardusch.example')) throw new RuntimeException('Known end-client website was not considered.');
$GLOBALS['letter_test_return_url']='https://unrelated.example/';
if (applicationEndClientOfficialContext($company)!=='') throw new RuntimeException('Cross-domain redirect was accepted as an official end-client website.');
if (($GLOBALS['letter_test_fetches'] ?? 0)!==2) throw new RuntimeException('End-client research fetch count is wrong.');
echo "PASS official end-client context only for identified mediated employer\n";
