<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__ . '/../public/index.php');
$functions = ['calendarExportRows','calendarRemoteTimes','googleCalendarEventPayload','googleCalendarOwnsEvent','googleCalendarStableId','googleCalendarSyncHash','googleCalendarLinkIsCurrent'];
foreach ($functions as $name) {
    if (!preg_match('/^function ' . $name . '\\(.*?(?=^function |\\z)/ms', $source, $match)) { throw new RuntimeException('Missing ' . $name); }
    eval(trim($match[0]));
}
function absoluteUrl(array $config, string $href): string { return 'https://example.test' . $href; }
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
    echo "OK: $message\n";
}
$user = ['timezone'=>'Europe/Zurich'];
$base = ['source'=>'workflow_event','source_type'=>'application_status','entry_kind'=>'milestone',
    'id'=>10,'application_id'=>1,'company_name'=>'Test AG','job_title'=>'Sales Manager',
    'title'=>'Gesendet','starts_at'=>'2026-09-03 13:58:00','ends_at'=>'2026-09-03 14:28:00','notes'=>'Versand bestaetigt'];
$second = array_replace($base,['id'=>11,'title'=>'Bestaetigt','starts_at'=>'2026-09-03 14:02:00']);
$appointment = array_replace($base,['id'=>12,'source'=>'calendar','source_type'=>'','entry_kind'=>'appointment','title'=>'Gespraech','starts_at'=>'2026-09-04 15:00:00','ends_at'=>'2026-09-04 16:00:00']);
$waiting = array_replace($base,['id'=>13,'source_type'=>'application_next_action','entry_kind'=>'action','raw_title'=>'await_response','applied_at'=>$base['starts_at']]);
$rows = calendarExportRows([$base,$second,$waiting,$appointment]);
check(count($rows)===2,'One daily milestone and one real appointment');
$day = array_values(array_filter($rows, fn($r)=>$r['source']==='application_day'))[0];
check($day['id']===10 && $day['title']==='Bestaetigt','Stable ID and latest milestone title');
check(str_contains($day['notes'],'13:58 Gesendet') && str_contains($day['notes'],'14:02 Bestaetigt'),'Full milestone history retained');
$payload=googleCalendarEventPayload([],$day,$user);
check($payload['start']===['date'=>'2026-09-03'] && $payload['end']===['date'=>'2026-09-04'],'All-day milestone with exclusive end');
check($payload['reminders']['useDefault']===false && $payload['transparency']==='transparent','Milestone has no alarm or busy time');
check(str_starts_with($payload['summary'],'Test AG') && str_contains($payload['summary'],'Sales Manager'),'Company and job visible in title');
$timed=googleCalendarEventPayload([],$appointment,$user);
check($timed['start']['dateTime']==='2026-09-04T15:00:00+02:00','Appointment time preserved');
check(!isset($timed['reminders']),'Existing appointment reminders preserved');
$waiting['starts_at']='2026-09-10 10:00:00';
check(count(calendarExportRows([$waiting]))===1,'Explicit future follow-up retained');
$other=array_replace($base,['id'=>14,'application_id'=>2]);
check(count(calendarExportRows([$base,$other]))===2,'Different applications not merged');
$tomorrow=array_replace($base,['id'=>15,'starts_at'=>'2026-09-04 09:00:00']);
check(count(calendarExportRows([$base,$tomorrow]))===2,'Different days not merged');
check(!googleCalendarOwnsEvent(['summary'=>'Privat'],'calendar',12),'Private events protected');
check(googleCalendarOwnsEvent($payload,'application_day',10),'Ownership marker matches daily milestone');
check(!googleCalendarLinkIsCurrent(['google_event_id'=>'','last_hash'=>'same'],'same'),'Failed creation retried');
check(!googleCalendarLinkIsCurrent(['google_event_id'=>'id','last_hash'=>'same','last_error'=>'timeout'],'same'),'Failed update retried');
check(googleCalendarStableId(1,'calendar','application_day',10)===googleCalendarStableId(1,'calendar','application_day',10),'Retry uses stable ID');
echo "All external calendar projection tests passed.\n";
