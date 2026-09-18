<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
$start = strpos($source, 'function reportViewFilterType(');
$end = strpos($source, 'function reportViewFiltersHtml(', $start);
if ($start === false || $end === false) throw new RuntimeException('Report filter engine not found.');
eval(substr($source, $start, $end - $start));

function expectIds(array $columns, array $rows, array $meta, array $filter, array $expected, string $label): void
{
    [$filteredRows] = reportViewApplyFilters($columns, $rows, $meta, reportViewFilterState($columns, $filter));
    $actual = array_column($filteredRows, 4);
    if ($actual !== $expected) throw new RuntimeException($label . ': ' . json_encode($actual));
    echo 'PASS ' . $label . "\n";
}

$columns = ['applied_at','match_score','job_room_result','title','id'];
$headers = ['Datum','Match','Job-Room','Titel','ID'];
$rows = [
    ['01.09.2026','25','Noch nicht im Job-Room erfasst','Alpha Verkauf','1'],
    ['15.09.2026','60','Im Job-Room erfasst – Noch offen','Beta Beratung','2'],
    ['30.09.2026','95','Im Job-Room erfasst – Absage','Gamma Leitung','3'],
    ['','','','Ohne Werte','4'],
];
$meta = [
    ['filter_values'=>['applied_at'=>'2026-09-01 08:00:00','match_score'=>'25','job_room_result'=>$rows[0][2],'title'=>$rows[0][3],'id'=>'1']],
    ['filter_values'=>['applied_at'=>'2026-09-15 08:00:00','match_score'=>'60','job_room_result'=>$rows[1][2],'title'=>$rows[1][3],'id'=>'2']],
    ['filter_values'=>['applied_at'=>'2026-09-30 08:00:00','match_score'=>'95','job_room_result'=>$rows[2][2],'title'=>$rows[2][3],'id'=>'3']],
    ['filter_values'=>['applied_at'=>'','match_score'=>'','job_room_result'=>'','title'=>$rows[3][3],'id'=>'4']],
];

$definitions = reportViewFilterDefinitions($columns, $headers, $rows, $meta);
$choice = $definitions[2]['options'];
if (array_keys($choice) !== ['Im Job-Room erfasst – Absage','Im Job-Room erfasst – Noch offen','Noch nicht im Job-Room erfasst','__empty__']) {
    throw new RuntimeException('Semantic choice options are incomplete or unstable: ' . json_encode($choice, JSON_UNESCAPED_UNICODE));
}
echo "PASS every semantic choice option is independently addressable\n";

$interviewRows = [$rows[1], ['15.09.2026','60','Im Job-Room erfasst – Noch offen · Vorstellungsgespräch','Beta Gespräch','5']];
$interviewMeta = [
    $meta[1],
    ['filter_values'=>['applied_at'=>'2026-09-15 08:00:00','match_score'=>'60','job_room_result'=>$interviewRows[1][2],'title'=>$interviewRows[1][3],'id'=>'5']],
];
$interviewChoices = reportViewFilterDefinitions($columns, $headers, $interviewRows, $interviewMeta)[2]['options'];
if (count($interviewChoices) !== 2) throw new RuntimeException('Open and interview-open Job-Room states must remain separate filter options.');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['value'=>$interviewRows[1][2]]], ['5'], 'interview and open filter');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['value'=>$interviewRows[0][2]]], ['2'], 'open without interview filter');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['values'=>[$interviewRows[0][2],$interviewRows[1][2]]]], ['2','5'], 'multiple open variants use OR within one field');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['values'=>[$interviewRows[0][2],$interviewRows[1][2]]], 'title'=>['value'=>'Gespräch']], ['5'], 'multiple choices combine with other fields using AND');
$normalizedChoice = reportViewFilterState($columns, ['job_room_result'=>['values'=>[$interviewRows[0][2],$interviewRows[0][2],$interviewRows[1][2]]]]);
if ($normalizedChoice['job_room_result']['values'] !== [$interviewRows[0][2],$interviewRows[1][2]]) throw new RuntimeException('Multiple choice state is not deduplicated.');
if (reportViewFilterState($columns, ['job_room_result'=>['value'=>$interviewRows[0][2]]])['job_room_result']['values'] !== [$interviewRows[0][2]]) throw new RuntimeException('Existing single-choice report links must remain valid.');
echo "PASS multiple choice state and legacy single-choice URL\n";

expectIds($columns,$rows,$meta,['applied_at'=>['from'=>'2026-09-15']],['2','3'],'date from');
expectIds($columns,$rows,$meta,['applied_at'=>['to'=>'2026-09-15']],['1','2'],'date to');
expectIds($columns,$rows,$meta,['applied_at'=>['from'=>'2026-09-10','to'=>'2026-09-20']],['2'],'date range');
expectIds($columns,$rows,$meta,['match_score'=>['min'=>'60']],['2','3'],'number minimum');
expectIds($columns,$rows,$meta,['match_score'=>['max'=>'60']],['1','2'],'number maximum');
expectIds($columns,$rows,$meta,['match_score'=>['min'=>'30','max'=>'90']],['2'],'number range');
expectIds($columns,$rows,$meta,['title'=>['value'=>'beratung']],['2'],'case-insensitive text contains');
foreach ([$rows[0][2]=>['1'],$rows[1][2]=>['2'],$rows[2][2]=>['3'],'__empty__'=>['4']] as $option=>$ids) {
    expectIds($columns,$rows,$meta,['job_room_result'=>['value'=>$option]],$ids,'choice ' . $option);
}
expectIds($columns,$rows,$meta,['job_room_result'=>['values'=>[$rows[1][2],$rows[2][2]]]],['2','3'],'multiple independent choice values');
expectIds($columns,$rows,$meta,['job_room_result'=>['values'=>[$rows[1][2],'__empty__']]],['2','4'],'empty choice combines with a nonempty value');
expectIds($columns,$rows,$meta,['applied_at'=>['from'=>'2026-09-01','to'=>'2026-09-30'],'match_score'=>['min'=>'50'],'title'=>['value'=>'a'],'job_room_result'=>['value'=>$rows[1][2]]],['2'],'combined filters');
expectIds($columns,$rows,$meta,['applied_at'=>['from'=>'invalid'],'match_score'=>['min'=>'invalid'],'title'=>['value'=>'']],['1','2','3','4'],'invalid and empty filters ignored');

foreach (['status','channel','next_action','job_room_result','job_room_registration','country_code','preferred_language','language_code','scope','type','entry_kind','source_type'] as $field) {
    if (reportViewFilterType($field) !== 'choice') throw new RuntimeException($field . ' is not a choice filter.');
}
echo "PASS all report choice fields use the tested semantic choice engine\n";
