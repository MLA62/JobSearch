<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
function tr(string $key): string
{
    return [
        'applications.job_room_not_recorded'=>'Noch nicht im Job-Room erfasst',
        'applications.job_room_recorded'=>'Im Job-Room erfasst',
        'applications.job_room_interview'=>'Vorstellungsgespräch',
        'job_room_helper.result.open'=>'Noch offen',
        'job_room_helper.result.hired'=>'Anstellung',
        'job_room_helper.result.rejected'=>'Absage',
    ][$key] ?? $key;
}
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
    ['filter_values'=>['applied_at'=>'2026-09-01 08:00:00','match_score'=>'25','job_room_result'=>['not_recorded'],'title'=>$rows[0][3],'id'=>'1'], 'filter_labels'=>['job_room_result'=>['not_recorded'=>'Noch nicht im Job-Room erfasst']]],
    ['filter_values'=>['applied_at'=>'2026-09-15 08:00:00','match_score'=>'60','job_room_result'=>['recorded','result:open'],'title'=>$rows[1][3],'id'=>'2'], 'filter_labels'=>['job_room_result'=>['recorded'=>'Im Job-Room erfasst','result:open'=>'Noch offen']]],
    ['filter_values'=>['applied_at'=>'2026-09-30 08:00:00','match_score'=>'95','job_room_result'=>['recorded','result:rejected'],'title'=>$rows[2][3],'id'=>'3'], 'filter_labels'=>['job_room_result'=>['recorded'=>'Im Job-Room erfasst','result:rejected'=>'Absage']]],
    ['filter_values'=>['applied_at'=>'','match_score'=>'','job_room_result'=>'','title'=>$rows[3][3],'id'=>'4']],
];

$definitions = reportViewFilterDefinitions($columns, $headers, $rows, $meta);
$choice = $definitions[2]['options'];
if (array_keys($choice) !== ['result:rejected','result:hired','recorded','not_recorded','result:open','interview','__empty__']) {
    throw new RuntimeException('Semantic choice options are incomplete or unstable: ' . json_encode($choice, JSON_UNESCAPED_UNICODE));
}
echo "PASS Job-Room options are independent values, not displayed combinations\n";

$interviewRows = [$rows[1], ['15.09.2026','60','Im Job-Room erfasst – Noch offen · Vorstellungsgespräch','Beta Gespräch','5']];
$interviewMeta = [
    $meta[1],
    ['filter_values'=>['applied_at'=>'2026-09-15 08:00:00','match_score'=>'60','job_room_result'=>['recorded','result:open','interview'],'title'=>$interviewRows[1][3],'id'=>'5'], 'filter_labels'=>['job_room_result'=>['recorded'=>'Im Job-Room erfasst','result:open'=>'Noch offen','interview'=>'Vorstellungsgespräch']]],
];
$interviewChoices = reportViewFilterDefinitions($columns, $headers, $interviewRows, $interviewMeta)[2]['options'];
if (array_keys($interviewChoices) !== ['result:rejected','result:hired','recorded','not_recorded','result:open','interview']) throw new RuntimeException('All atomic choices must remain available even when absent from report rows.');
if ($interviewChoices['not_recorded'] !== 'Noch nicht im Job-Room erfasst') throw new RuntimeException('The unrecorded choice needs its localized label.');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['value'=>'not_recorded']], [], 'unrecorded choice is selectable with zero matching rows');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['value'=>$interviewRows[1][2]]], ['5'], 'interview and open filter');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['value'=>$interviewRows[0][2]]], ['2'], 'open without interview filter');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['value'=>'result:open']], ['2','5'], 'open includes interview-open without selecting a combination');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['value'=>'interview']], ['5'], 'interview is independently filterable');
expectIds($columns, $interviewRows, $interviewMeta, ['job_room_result'=>['values'=>['result:open','interview']], 'title'=>['value'=>'Gespräch']], ['5'], 'atomic choices combine with other fields using AND');
$normalizedChoice = reportViewFilterState($columns, ['job_room_result'=>['values'=>['result:open','result:open','interview']]]);
if ($normalizedChoice['job_room_result']['values'] !== ['result:open','interview']) throw new RuntimeException('Multiple choice state is not deduplicated.');
if (reportViewFilterState($columns, ['job_room_result'=>['value'=>$interviewRows[0][2]]])['job_room_result']['values'] !== [$interviewRows[0][2]]) throw new RuntimeException('Existing single-choice report links must remain valid.');
echo "PASS multiple choice state and legacy single-choice URL\n";

expectIds($columns,$rows,$meta,['applied_at'=>['from'=>'2026-09-15']],['2','3'],'date from');
expectIds($columns,$rows,$meta,['applied_at'=>['to'=>'2026-09-15']],['1','2'],'date to');
expectIds($columns,$rows,$meta,['applied_at'=>['from'=>'2026-09-10','to'=>'2026-09-20']],['2'],'date range');
expectIds($columns,$rows,$meta,['match_score'=>['min'=>'60']],['2','3'],'number minimum');
expectIds($columns,$rows,$meta,['match_score'=>['max'=>'60']],['1','2'],'number maximum');
expectIds($columns,$rows,$meta,['match_score'=>['min'=>'30','max'=>'90']],['2'],'number range');
expectIds($columns,$rows,$meta,['title'=>['value'=>'beratung']],['2'],'case-insensitive text contains');
foreach (['not_recorded'=>['1'],'recorded'=>['2','3'],'result:open'=>['2'],'result:rejected'=>['3'],'__empty__'=>['4']] as $option=>$ids) {
    expectIds($columns,$rows,$meta,['job_room_result'=>['value'=>$option]],$ids,'choice ' . $option);
}
expectIds($columns,$rows,$meta,['job_room_result'=>['values'=>['result:open','result:rejected']]],['2','3'],'multiple independent choice values');
expectIds($columns,$rows,$meta,['job_room_result'=>['values'=>['result:open','__empty__']]],['2','4'],'empty choice combines with a nonempty value');
expectIds($columns,$rows,$meta,['applied_at'=>['from'=>'2026-09-01','to'=>'2026-09-30'],'match_score'=>['min'=>'50'],'title'=>['value'=>'a'],'job_room_result'=>['value'=>'result:open']],['2'],'combined filters');
expectIds($columns,$rows,$meta,['applied_at'=>['from'=>'invalid'],'match_score'=>['min'=>'invalid'],'title'=>['value'=>'']],['1','2','3','4'],'invalid and empty filters ignored');

foreach (['status','channel','next_action','job_room_result','job_room_registration','country_code','preferred_language','language_code','scope','type','entry_kind','source_type'] as $field) {
    if (reportViewFilterType($field) !== 'choice') throw new RuntimeException($field . ' is not a choice filter.');
}
echo "PASS all report choice fields use the tested semantic choice engine\n";
