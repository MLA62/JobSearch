<?php
declare(strict_types=1);

function repairMojibake(string $value): string { return $value; }
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sanitizeRichText(?string $value): string
{
    $value=trim((string)$value);
    if ($value==='') return '';
    if (!preg_match('/<[^>]+>/', $value)) return nl2br(e($value), false);
    return $value;
}
function richTextPlain(?string $value): string
{
    $html=(string)$value;
    $html=preg_replace('/<(?:br\s*\/?|\/p|\/div|\/li|\/tr|hr\s*\/?)>/i', "\n", $html) ?? $html;
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

$source=file_get_contents(__DIR__.'/../public/index.php');
$start=strpos($source,'function applicationTextDisqualifyingPatterns(');
$end=strpos($source,'function applicationWritingContext(', $start);
if($start===false || $end===false) throw new RuntimeException('Application text quality helpers not found.');
eval(substr($source,$start,$end-$start));

$badGerman='<p>Über meine bisherigen beruflichen Erfahrungen liegen in den vorliegenden Unterlagen keine lesbaren Angaben vor. Gerne erläutere ich Ihnen im persönlichen Gespräch, wie ich Ihr Sales-Team konkret unterstützen kann.</p><p>Die ausgeschriebenen Aufgaben sprechen mich an.</p>';
if(!applicationTextHasDisqualifyingLanguage($badGerman)) throw new RuntimeException('German applicant-undermining wording was not detected.');
$cleanGerman=richTextPlain(applicationTextWithoutDisqualifyingLanguage($badGerman));
if(str_contains($cleanGerman,'vorliegenden Unterlagen') || str_contains($cleanGerman,'persönlichen Gespräch')) throw new RuntimeException('German applicant-undermining wording survived cleanup.');
if(!str_contains($cleanGerman,'ausgeschriebenen Aufgaben')) throw new RuntimeException('Supported German content was removed with the blocked sentences.');
echo "PASS exact reported German wording is removed while supported content remains\n";

if(applicationTextHasMinimumSubstance('Bardusch AG Patrick Wenger',18)) throw new RuntimeException('Address-only result was accepted as a complete cover letter.');
if(!applicationTextHasMinimumSubstance($safe='Die ausgeschriebenen Vertriebsaufgaben sprechen mich besonders an. Ich arbeite strukturiert und zielorientiert. Diese Verantwortung übernehme ich engagiert und zuverlässig.',12)) throw new RuntimeException('Substantive text was rejected.');
echo "PASS address-only residue cannot replace a complete cover letter\n";

$badByLocale=[
    'en'=>'The provided documents contain no readable information about my experience. I can explain my contribution in an interview.',
    'fr'=>'Les documents fournis ne contiennent aucune information sur mon expérience. Je pourrais détailler ma motivation lors d’un entretien.',
    'es'=>'No hay información disponible sobre mi experiencia. Puedo explicar mi motivación en una entrevista.',
    'pt'=>'Não há informações disponíveis sobre minha experiência. Posso detalhar minha motivação em uma entrevista.',
];
foreach($badByLocale as $locale=>$value){
    if(!applicationTextHasDisqualifyingLanguage($value)) throw new RuntimeException('Blocked wording was missed for '.$locale.'.');
    if(trim(richTextPlain(applicationTextWithoutDisqualifyingLanguage($value)))!=='') throw new RuntimeException('Blocked-only text was not fully removed for '.$locale.'.');
    echo 'PASS '.$locale." no-data and interview-deferral wording is removed\n";
}

foreach([
    'Aus meinem beruflichen Werdegang lassen sich keine weiteren Kenntnisse belegen.',
    'In einem persönlichen Gespräch zeige ich gerne, wie ich Ihr Team unterstützen kann.',
    'I cannot substantiate further experience from the material.',
    'In an interview I can explain how I would support the team.',
] as $variant){
    if(!applicationTextHasDisqualifyingLanguage($variant)) throw new RuntimeException('Rephrased applicant-undermining wording was missed: '.$variant);
}
echo "PASS rephrased no-data and reversed interview-deferral variants are blocked\n";

$safe='Die ausgeschriebenen Vertriebsaufgaben sprechen mich besonders an. Ich arbeite strukturiert und zielorientiert.';
if(applicationTextHasDisqualifyingLanguage($safe)) throw new RuntimeException('Supported positive wording was incorrectly blocked.');
if(richTextPlain(applicationTextWithoutDisqualifyingLanguage($safe))!==$safe) throw new RuntimeException('Supported positive wording changed.');
echo "PASS positive factual wording remains unchanged\n";

$substantive='<p>Ich unterstütze Unternehmenskunden bei der Einführung passender ICT-Lösungen und verbinde technische Anforderungen mit klaren kommerziellen Entscheidungen. In der Zusammenarbeit mit Fachspezialisten koordiniere ich komplexe Angebote, kläre offene Fragen und begleite Kunden durch den gesamten Entscheidungsprozess.</p><p>Gerne erläutere ich Ihnen meine Erfahrung in einem persönlichen Gespräch.</p><p>Ich freue mich darauf, meine Erfahrung in dieser Aufgabe einzubringen und Sie kennenzulernen.</p>';
$repaired=applicationTextWithoutDisqualifyingLanguage($substantive);
if(applicationTextHasDisqualifyingLanguage($repaired) || !str_contains(richTextPlain($repaired),'komplexe Angebote') || !str_contains(richTextPlain($repaired),'Ich freue mich darauf')) throw new RuntimeException('A removable interview sentence prevented a substantive draft from surviving.');
echo "PASS removable interview sentence does not discard the substantive draft\n";

$aiStart=strpos($source,'function applicationAiTexts(');
$aiEnd=strpos($source,'function applicationEnsureRecipientData(', $aiStart);
$aiSource=substr($source,$aiStart,$aiEnd-$aiStart);
$repairOffset=strpos($aiSource,'$texts[$field]=applicationTextWithoutDisqualifyingLanguage((string)$texts[$field]);');
$qualityOffset=strpos($aiSource,'$qualityIssues=applicationTextQualityIssues(');
if($repairOffset===false || $qualityOffset===false || $repairOffset>$qualityOffset) throw new RuntimeException('AI drafts must be repaired before the quality gates run.');
if(str_contains($aiSource,'Der KI-Entwurf enthält trotz Überarbeitung ungeeignete Aussagen')) throw new RuntimeException('The old three-attempt removable-sentence rejection is still active.');
echo "PASS AI repair precedes quality validation\n";

if (str_contains($source,'function applicationFallbackTexts(')) throw new RuntimeException('Generic local fallback text must not be available.');
echo "PASS no generic fallback can replace a failed AI draft\n";
