<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__.'/../public/index.php');
$functions = ['applicationWorkflowDateSql','applicationWorkflowView','applicationStatusOptions','applicationStatusSequence',
    'applicationNextActionLabel','applicationNextActionChoices','applicationNextActionOptions','workflowDateTime','workflowCalendarRange',
    'applicationSentAt','calendarExportRows','applicationExportData','applicationExportHeaders','optionLabel','applicationChannelOptions','calendarIcsText','calendarIcsEscape','calendarRemoteTimes'];
foreach ($functions as $name) {
    preg_match('/^function '.$name.'\(.*?(?=^function |\z)/ms',$source,$match);
    if (!$match) { throw new RuntimeException('Missing '.$name); }
    eval(trim($match[0]));
}
function tr(string $key): string { return $key; }
function displayDateTime(?string $date, array $user, bool $time = true): string { return $date ? date($time ? 'd.m.Y H:i' : 'd.m.Y',strtotime($date)) : ''; }
function check(bool $value, string $message): void { if (!$value) { throw new RuntimeException($message); } echo "PASS $message\n"; }
$row = ['status'=>'ready','applied_at'=>'2026-09-03 09:16:42','next_action'=>'await_response','next_action_at'=>'2026-09-03 09:16:42',
    'latest_workflow_at'=>'2026-09-10 14:30:00','title'=>'Long job title','company'=>'Example AG','channel'=>'website'];
$view = applicationWorkflowView($row);
check($view['sent_at']===null && $view['next_task']==='next_action.send_application','Ready has no submission claim and shows only send task');
foreach (['sent','interview','accepted','rejected'] as $status) {
    $view = applicationWorkflowView(array_replace($row,['status'=>$status]));
    check($view['next_task']==='' && $view['sent_at']===$row['applied_at'],$status.' has no stale send or waiting task');
}
check(array_keys(applicationStatusOptions(false))===['draft','ready','sent','interview','accepted','rejected'],'Exactly six selectable statuses');
check(isset(applicationStatusOptions()['offer']),'Legacy statuses can still be displayed without guessing a mapping');
check(applicationExportData([$row],[])[0][0]==='10.09.2026','Exports contain only the workflow date, without time');
check(count(applicationExportHeaders())===5 && count(applicationExportData([$row],[])[0])===5,'CSV and PDF have the same five data columns');
check(applicationSentAt(['applied_at'=>'2026-09-03 09:16:42'],'sent','2026-09-03T09:16')==='2026-09-03 09:16:42','Autosave preserves seconds');
check(applicationSentAt(['applied_at'=>null],'interview',null)===null,'No guessed submission date from interview status');
check(applicationSentAt(['applied_at'=>'2026-09-03 09:16:42'],'ready',null)===null,'Reset to preparation removes current submission claim');
check(workflowCalendarRange('2026-09-03T00:00','')[0]==='2026-09-03 00:00:00','Midnight is a timed appointment');
foreach ([['2026-02-30T10:00',''],['2026-09-03T10:00','2026-09-03T09:00'],['2026-09-03T10:00','2026-09-03T10:00'],['nonsense','']] as $values) {
    try { workflowCalendarRange(...$values); throw new LogicException('Invalid date accepted'); }
    catch (InvalidArgumentException) { check(true,'Invalid calendar range rejected'); }
}
$event = ['id'=>1,'application_id'=>1,'source_type'=>'application_status','entry_kind'=>'milestone','workflow_status'=>'sent','starts_at'=>'2026-09-03 09:16:42'];
check(count(calendarExportRows([$event,array_replace($event,['id'=>2,'starts_at'=>'2026-09-03 09:20:00'])]))===1,'Repeated submission status has one event');
check(calendarExportRows([array_replace($event,['application_status'=>'ready'])])===[],'Ready applications do not expose old sent milestones');
foreach (['send_application','await_response','review_documents','prepare_interview'] as $action) {
    check(calendarExportRows([array_replace($event,['entry_kind'=>'action','source_type'=>'application_next_action','raw_title'=>$action])])===[],'No calendar entry for '.$action);
}
$interview = ['source_type'=>'','entry_kind'=>'appointment','id'=>7,'application_id'=>1,'starts_at'=>'2026-09-10 10:00:00'];
check(count(calendarExportRows([$interview,array_replace($interview,['id'=>8])]))===2,'Even simultaneous separate interviews are not merged');
$ics=calendarIcsText([$event+['source'=>'workflow_event','title'=>'Gesendet']],['timezone'=>'Europe/Zurich']);
check(str_contains($ics,'DTSTART:20260903T071642Z') && str_contains($ics,'DTEND:20260903T071742Z'),'ICS preserves actual local time as UTC with positive duration');
check(str_contains($ics,'TRANSP:TRANSPARENT'),'Submission evidence does not reserve busy time');
$ics=calendarIcsText([$interview+['source'=>'calendar','title'=>str_repeat('Bewerbungsgespräch ',30)]],['timezone'=>'Europe/Zurich']);
check(max(array_map('strlen',explode("\r\n",$ics)))<=74,'ICS folds long titles without clipping');
$sql = applicationWorkflowDateSql();
check(str_contains($sql,'application_status_history') && str_contains($sql,'calendar_events') && !str_contains($sql,'updated_at'),'Workflow date uses business events, not autosave timestamps');
check(!str_contains($source,'bulk-select-control') && !str_contains($source,"sfHeader('applications','next_action'"),'No automatic checkbox column or misleading next-calendar header');
check(!str_contains($source,'name="mail_follow_up_at"') && !str_contains($source,'name="follow_up_at"'),'Contact and mail forms cannot create another schedule');
check(!str_contains($source,"tr('mail_activity.pendent_hint')"),'Mail form does not promise removed follow-up or pending modules');
check(str_contains($source,'$_POST') && str_contains($source,'event_request_id'),'Calendar form has replay protection');
$catalog=[];
preg_match_all("/^        '((?:workflow\\.|applications\\.workflow_date|applications\\.plan_|applications\\.next_task)[^']*)' => \\[\\R(.*?)^        \\],/ms",$source,$matches,PREG_SET_ORDER);
foreach ($matches as $match) { $catalog[$match[1]]=eval('return ['.$match[2].'];'); }
check(count($catalog)>=15,'Workflow translation catalog discovered');
foreach (['de-CH','fr-CH','en-GB','pt-BR','es-MX'] as $locale) {
    foreach ($catalog as $key=>$values) { check(!empty($values[$locale]),$locale.' '.$key); }
}
echo "Workflow v6 regression checks passed.\n";
