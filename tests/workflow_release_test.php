<?php
declare(strict_types=1);
$source = file_get_contents($argv[1] ?? __DIR__ . '/../public/index.php');
foreach (['calendarExportRows','calendarRemoteTimes','googleCalendarEventPayload','googleCalendarOwnsEvent','googleCalendarStableId','googleCalendarSyncHash','googleCalendarLinkIsCurrent','applicationDefaultNextAction','applicationStatusIsTerminal','applicationNextActionEventType','syncApplicationWorkflow'] as $name) {
    if (!preg_match('/^function ' . $name . '\\(.*?(?=^function |\\z)/ms', $source, $match)) { throw new RuntimeException('Missing ' . $name); }
    eval(trim($match[0]));
}
function absoluteUrl(array $config, string $href): string { return 'https://example.test' . $href; }
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
    echo "OK: $message\n";
}
$user = ['timezone'=>'Europe/Zurich'];
$sent = ['source'=>'workflow_event','source_type'=>'application_status','entry_kind'=>'milestone',
    'id'=>10,'application_id'=>1,'raw_title'=>'sent','workflow_status'=>'sent','title'=>'Bewerbung gesendet',
    'company_name'=>'Test AG','job_title'=>'Sales Manager','starts_at'=>'2026-09-03 09:16:00',
    'ends_at'=>null,'meta'=>'Sales Manager - Test AG','notes'=>''];
$ready = array_replace($sent,['id'=>9,'workflow_status'=>'ready','starts_at'=>'2026-09-03 08:51:00']);
$waiting = array_replace($sent,['id'=>11,'source_type'=>'application_next_action','entry_kind'=>'action','raw_title'=>'await_response']);
$completed = array_replace($waiting,['id'=>12,'raw_title'=>'send_application','source_key'=>'completed-12']);
$follow = array_replace($waiting,['id'=>13,'raw_title'=>'follow_up','title'=>'Nachfassen','source_key'=>'current','starts_at'=>'2026-09-10 14:30:00','ends_at'=>'2026-09-10 15:00:00']);
$rows = calendarExportRows([$ready,$sent,$waiting,$completed,$follow]);
check(count($rows)===2, 'Only sent and scheduled follow-up appear');
$payload = googleCalendarEventPayload([], $rows[0], $user);
check($payload['start']['dateTime']==='2026-09-03T09:16:00+02:00', 'Actual submission timestamp');
check($payload['end']['dateTime']==='2026-09-03T09:17:00+02:00', 'One-minute evidence, not all-day');
check($payload['transparency']==='transparent' && !$payload['reminders']['useDefault'], 'Evidence has no alarm or busy block');
check($payload['summary']==='Test AG · Bewerbung gesendet', 'Concise event title');
check(str_contains($payload['description'],'Sales Manager'), 'Full job details in description');
$timed = googleCalendarEventPayload([], $rows[1], $user);
check($timed['start']['dateTime']==='2026-09-10T14:30:00+02:00' && !isset($timed['reminders']), 'Follow-up time and reminders preserved');
$meeting = array_replace($follow,['source'=>'calendar','source_type'=>'','entry_kind'=>'appointment','id'=>14,'title'=>'Interview']);
$secondMeeting = array_replace($meeting,['id'=>15,'starts_at'=>'2026-09-11 14:30:00']);
check(count(calendarExportRows([$meeting,$secondMeeting]))===2, 'Multiple interviews retained independently');
$rejected = array_replace($sent,['id'=>16,'workflow_status'=>'rejected','title'=>'Absage']);
check(count(calendarExportRows([$sent,$rejected]))===2, 'Submission and result are distinct evidence');
check(count(calendarExportRows([$sent,$sent]))===1, 'Exact duplicated evidence not exported twice');
check(count(calendarExportRows([$sent,array_replace($sent,['application_id'=>2])]))===2, 'Different applications not merged');
check(!isset(googleCalendarEventPayload([], $sent+['all_day'=>1], $user)['start']['date']), 'Owned event cannot become all-day');
check(!googleCalendarOwnsEvent(['summary'=>'Privat'],'calendar',12),'Private events protected');
check(googleCalendarOwnsEvent($payload,'workflow_event',10),'Ownership marker retained');
check(!googleCalendarLinkIsCurrent(['google_event_id'=>'','last_hash'=>'same'],'same'),'Failed creation retried');
check(!googleCalendarLinkIsCurrent(['google_event_id'=>'id','last_hash'=>'same','last_error'=>'timeout'],'same'),'Failed update retried');
check(googleCalendarStableId(1,'calendar','workflow_event',10)===googleCalendarStableId(1,'calendar','workflow_event',10),'Stable retry ID');
check(applicationDefaultNextAction('sent')===null && applicationDefaultNextAction('ready')===null, 'No invented next action');
if (extension_loaded('mysqli')) { throw new RuntimeException('Run with php -n'); }
class mysqli {}
$application = ['id'=>1,'user_id'=>1,'primary_contact_id'=>null,'status'=>'sent','next_action'=>null,'next_action_at'=>null,
    'applied_at'=>'2026-09-03 09:16:00','created_at'=>'2026-09-01 09:00:00','updated_at'=>'2026-09-03 15:00:00'];
$history = [['id'=>21,'new_status'=>'sent','comment'=>'','changed_at'=>'2026-09-03 15:00:00']];
$events=[]; $writes=[]; $currentAction=null;
function dbOne($db,string $sql,string $types='',array $values=[]): ?array { global $application,$currentAction; return str_contains($sql,'FROM applications')?$application:$currentAction; }
function dbAll($db,string $sql,string $types='',array $values=[]): array { global $history; return $history; }
function cascadeExec($db,string $sql,string $types,array $values): void { global $writes; $writes[]=[$sql,$values]; }
function upsertWorkflowCalendarEvent(...$args): void { global $events; $events[]=$args; }
$db=new mysqli(); syncApplicationWorkflow($db,1,1);
check(count($events)===1 && $events[0][10]===$application['applied_at'], 'Late entry does not change submission time');
$events=[]; $history=[]; syncApplicationWorkflow($db,1,1);
check(count($events)===1 && $events[0][7]==='application_submission','Submission without history still visible');
$events=[]; $application['applied_at']=null; $application['status']='interview'; syncApplicationWorkflow($db,1,1);
check($events===[], 'Status alone never invents an interview date');
$application['next_action']='follow_up'; $application['next_action_at']='2026-09-10 14:30:00';
$currentAction=['id'=>9,'title'=>'send_application','starts_at'=>'2026-09-01 09:00:00']; $events=[]; $writes=[];
syncApplicationWorkflow($db,1,1);
check(count($events)===1 && $events[0][10]===$application['next_action_at'],'Explicit follow-up retained');
check(!array_filter($writes,fn($w)=>str_contains($w[0],"status='completed'")),'Replacing a step does not falsely complete it');
check(str_contains($source,'workflow_calendar_v5') && str_contains($source,'INSERT IGNORE INTO workflow_data_backups'),'Migration and backup present');
check(str_contains($source,"sfHeader('applications','applied_at'"),'Application date has its own filter');
check(str_contains($source,'data-job-room-recorded') && str_contains($source,"this.checked?'recorded':'not_recorded'"),'Job-Room registration explicitly editable');
preg_match('/        if \(\$appliedAt\) \{.*?(?=        \$statusChanged =)/s', $source, $timestampBlock);
check(!empty($timestampBlock[0]), 'Timestamp normalization available');
$normalize = static function (?string $input, ?string $previous) use ($timestampBlock): ?string {
    $appliedAt=$input; $nextActionAt=null; $old=['applied_at'=>$previous]; $status='sent';
    eval($timestampBlock[0]);
    return $appliedAt;
};
check($normalize('2026-09-03T09:16','2026-09-03 09:16:42')==='2026-09-03 09:16:42','Autosave preserves timestamp seconds');
check($normalize(null,'2026-09-03 09:16:42')==='2026-09-03 09:16:42','Delayed form update cannot reset submission time');
check($normalize('2026-09-04T10:30','2026-09-03 09:16:42')==='2026-09-04 10:30:00','Intentional date correction remains possible');
echo "All calendar workflow tests passed.\n";
