<?php
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $value): int { return iconv_strlen($value,'UTF-8'); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value,int $start,?int $length=null): string { return iconv_substr($value,$start,$length,'UTF-8'); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value): string { return strtolower($value); } }

$source = file_get_contents(__DIR__.'/../public/index.php');
$wanted = array_fill_keys(['repairMojibake','plainText','extractJobRoomListingRows','importJobRoomComparable','importSelectJobRoomListing','importHttpHeaders'], true);
$tokens = token_get_all($source);
for ($i=0; $i<count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
    $j=$i+1;
    while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
    if (!is_array($tokens[$j]) || !isset($wanted[$tokens[$j][1]])) continue;
    $name=$tokens[$j][1]; $code=''; $depth=0; $started=false;
    for (; $i<count($tokens); $i++) {
        $token=$tokens[$i]; $code.=is_array($token)?$token[1]:$token;
        if ($token === '{' || (is_array($token) && in_array($token[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true))) { $depth++; $started=true; }
        elseif ($token === '}' && --$depth === 0 && $started) break;
    }
    eval($code); unset($wanted[$name]);
}
if ($wanted) throw new RuntimeException('Missing production helpers: '.implode(', ',array_keys($wanted)));
function checkPlainList(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
checkPlainList(in_array('Accept: application/json', importHttpHeaders('https://www.job-room.ch/jobadservice/api/jobAdvertisements/d748fc6a-f08e-4f5f-bc81-84d4d1ac47ac'), true),
    'Job-Room detail API requests JSON rather than HTML');
checkPlainList(in_array('Accept: text/html,application/xhtml+xml', importHttpHeaders('https://www.job-room.ch/job-search/d748fc6a-f08e-4f5f-bc81-84d4d1ac47ac'), true),
    'Ordinary advert pages continue requesting HTML');
$payload="Recruitment & Sales Consultant m/w/d/ 100%\n\n21.09.2026\ndas team ag3012 Bern (BE)100%Nach Vereinbarung\nRecruitment & Sales Consultant: Baupersonal finden, auswählen und gewinnen\n\nGeneral Manager Schweiz w/m/d\n\n21.09.2026\nLogjob AG - For Supply Chain Experts3052 Zollikofen (BE)100%\nGeneral Manager Schweiz: Markteintritt und Aufbau in der Schweiz";
$rows=extractJobRoomListingRows($payload);
checkPlainList(count($rows)===2 && $rows[0]['company']==='das team ag' && $rows[0]['postal_code']==='3012', 'Plain result list parsed into separate dated cards');
checkPlainList(count(extractJobRoomListingRows("Recruitment & Sales Consultant\n21.09.2026\ndas team ag3012 Bern (BE)\nA complete listing summary"))===1,
    'A single complete result card is importable');
$splitPayload=str_replace('das team ag3012 Bern (BE)','das team ag' . "\n" . '3012 Bern (BE)',$payload);
checkPlainList(count(extractJobRoomListingRows($splitPayload))===2,'Separate company and location lines are parsed');
checkPlainList(extractJobRoomListingRows('Recruitment & Sales Consultant\n21.09.2026\ndas team ag3012 Bern (BE)\nOne advert')===[], 'One ad description is not misclassified as a result list');
$candidate=static function(string $id,string $date,string $description): array {
    return ['jobAdvertisement'=>['id'=>$id,'status'=>'PUBLISHED_PUBLIC','createdTime'=>$date.'T12:00:00',
        'jobContent'=>['company'=>['name'=>'das team ag','postalCode'=>'3012'],
        'location'=>['postalCode'=>'3012'],
        'jobDescriptions'=>[['title'=>'<em>Recruitment</em> &amp; <em>Sales</em> Consultant m/w/d/ 100%',
            'description'=>$description]]]]];
};
$old='9237355e-d087-4c46-b174-9a76524abefd';
$current='c2052175-3f09-436b-8cc5-b2216e504e27';
$results=[$candidate($old,'2026-09-18','An old, unrelated snippet'),
    $candidate($current,'2026-09-21','Recruitment & Sales Consultant: Baupersonal finden, auswählen und gewinnen')];
checkPlainList(importSelectJobRoomListing($rows[0],$results)==='https://www.job-room.ch/job-search/'.$current,
    'Matching result selects the dated advert, not a duplicate title');
$unrelated=$results[0]; $unrelated['jobAdvertisement']['jobContent']['location']['postalCode']='4000';
try { importSelectJobRoomListing($rows[0],[$unrelated]); throw new LogicException('Unrelated search hit accepted'); }
catch (RuntimeException) { checkPlainList(true,'Unrelated search hit rejected'); }
checkPlainList(importSelectJobRoomListing($rows[0],[$results[0]])==='https://www.job-room.ch/job-search/'.$old,
    'A single exact title, company and workplace match survives a publication-date discrepancy');
checkPlainList(importSelectJobRoomListing($rows[0],[$results[1],$results[1]])==='https://www.job-room.ch/job-search/'.$current,
    'Duplicate identical search hits remain one advert');
checkPlainList(str_contains($source,'extractJobRoomListingRows($payload)') && str_contains($source,'importResolveJobRoomListing($item)'),
    'Quick import routes copied result cards through the verified URL resolver');
if ($sample = getenv('JEMA_IMPORT_SAMPLE')) {
    $sampleRows = extractJobRoomListingRows((string)file_get_contents($sample));
    echo 'SAMPLE cards parsed: '.count($sampleRows)."\n";
    foreach ($sampleRows as $sampleRow) echo $sampleRow['date'].' | '.$sampleRow['title'].' | '.($sampleRow['company'] ?? 'unvollständig')."\n";
    if (($sampleIndex=getenv('JEMA_MATCH_INDEX')) !== false) {
        $results=json_decode((string)stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);
        try { echo 'MATCH: '.importSelectJobRoomListing($sampleRows[(int)$sampleIndex],$results)."\n"; }
        catch (RuntimeException $exception) { echo 'NO_MATCH: '.$exception->getMessage()."\n"; }
    }
}
