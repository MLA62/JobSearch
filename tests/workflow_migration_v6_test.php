<?php
declare(strict_types=1);
if (extension_loaded('mysqli')) { throw new RuntimeException('Run with php -n'); }
$source=file_get_contents(__DIR__.'/../public/index.php');
foreach (['workflowMigrationPlan','workflowMigrationHash','migrateWorkflowCalendarV6','applicationNextActionChoices','applicationStatusSequence'] as $name) {
    preg_match('/^function '.$name.'\(.*?(?=^function |\z)/ms',$source,$match); eval(trim($match[0]));
}
function tr(string $key): string { return $key; }
function check(bool $value,string $message): void { if (!$value) { throw new RuntimeException($message); } echo "PASS $message\n"; }
class mysqli {
    public array $calls=[];
    public array $backups=[];
    public array $snapshot=[];
    public bool $marker=false;
    public bool $lock=true;
    public bool $fail=false;
    public array $applications;
    public array $events;
    public function __construct() {
        $app=['id'=>1,'user_id'=>1,'status'=>'sent','next_action'=>'send_application','next_action_at'=>'2026-09-03 09:16:00','applied_at'=>'2026-09-03 09:16:00'];
        $this->applications=[$app,array_replace($app,['id'=>2,'user_id'=>2,'status'=>'offer','next_action'=>'custom step']),array_replace($app,['id'=>3,'next_action'=>'follow_up','next_action_at'=>'2026-09-10 14:30:00'])];
        $event=['id'=>1,'owner_user_id'=>1,'application_id'=>1,'source_type'=>'application_status','source_key'=>'ready','status'=>'completed','title'=>'ready','starts_at'=>'2026-09-03 09:00:00'];
        $this->events=[$event,array_replace($event,['id'=>2,'source_key'=>'sent','title'=>'sent']),array_replace($event,['id'=>3,'source_key'=>'sent','title'=>'sent']),
            array_replace($event,['id'=>4,'source_type'=>'application_next_action','source_key'=>'current','title'=>'follow_up','status'=>'planned','starts_at'=>'2026-09-10 10:00:00']),
            array_replace($event,['id'=>5,'source_type'=>'contact_log','source_key'=>'follow_up','status'=>'planned','starts_at'=>'2026-09-10 10:00:00']),
            array_replace($event,['id'=>6,'source_type'=>'application_next_action','title'=>'custom step'])];
    }
    public function begin_transaction(): void { $this->calls[]='begin'; $this->snapshot=[$this->backups,$this->marker]; }
    public function commit(): void { $this->calls[]='commit'; }
    public function rollback(): void { $this->calls[]='rollback'; [$this->backups,$this->marker]=$this->snapshot; }
}
function dbOne(mysqli $db,string $sql,string $types='',array $values=[]): ?array {
    $db->calls[]=$sql;
    if (str_contains($sql,'GET_LOCK')) { return ['acquired'=>(int)$db->lock]; }
    if (str_contains($sql,'RELEASE_LOCK')) { return ['released'=>1]; }
    if (str_contains($sql,'app_migrations')) { return $db->marker ? ['migration_key'=>'workflow_calendar_v6'] : null; }
    throw new RuntimeException('Unexpected query '.$sql);
}
function dbAll(mysqli $db,string $sql): array {
    $db->calls[]=$sql;
    if (str_contains($sql,'FROM applications')) { return $db->applications; }
    if (str_contains($sql,'FROM calendar_events')) {
        check(str_contains($sql,'WHERE source_type IN'),'Private and manually entered appointments excluded from migration');
        return $db->events;
    }
    throw new RuntimeException('Unexpected query');
}
function cascadeExec(mysqli $db,string $sql,string $types,array $values): void {
    check(substr_count($sql,'?')===strlen($types) && strlen($types)===count($values),'SQL parameters match');
    $db->calls[]=$sql;
    if (str_contains($sql,'workflow_data_backups')) { $db->backups[]=$values; return; }
    check(count($db->backups)===6,'All six affected records are backed up before the first write');
    if ($db->fail) { throw new RuntimeException('Injected failure'); }
    if (str_contains($sql,'app_migrations')) { $db->marker=true; }
}
function upsertWorkflowCalendarEvent(...$args): void {
    $db=$args[0]; check(count($db->backups)===6,'Recovered follow-up has its original application backup');
    check($args[4]==='follow_up' && $args[10]==='2026-09-10 14:30:00','Explicit follow-up date preserved exactly');
    $db->calls[]='recover';
}
$db=new mysqli(); $plan=workflowMigrationPlan($db);
$find=static fn(string $table,int $id): array => array_values(array_filter($plan,fn($item)=>$item['table']===$table && $item['id']===$id))[0];
check($find('calendar_events',1)['operation']==='cancel','Preparation calendar noise is retired');
check($find('calendar_events',2)['operation']==='preserve' && $find('calendar_events',3)['operation']==='cancel','One canonical submission; duplicate history preserved separately');
check($find('calendar_events',4)['operation']==='detach' && $find('calendar_events',5)['operation']==='detach','Ambiguous simultaneous follow-ups are retained, not guessed duplicates');
check($find('calendar_events',6)['operation']==='preserve' && $find('applications',2)['operation']==='preserve','Unknown actions and legacy statuses unchanged');
check($find('applications',3)['operation']==='recover_follow_up','Only explicitly named follow-up is recovered');
check(!in_array('begin',$db->calls,true) && !$db->backups,'Preview is read-only');
migrateWorkflowCalendarV6($db,workflowMigrationHash($plan));
check($db->marker && in_array('commit',$db->calls,true),'Migration and marker committed');
check(json_decode($db->backups[0][4],true)['source_key']==='ready','Original row remains recoverable');
$count=count($db->calls); migrateWorkflowCalendarV6($db,workflowMigrationHash($plan));
check(count($db->calls)===$count+1,'Completed migration is idempotent');
$db=new mysqli(); $hash=workflowMigrationHash(workflowMigrationPlan($db)); $db->applications[0]['status']='rejected';
try { migrateWorkflowCalendarV6($db,$hash); throw new LogicException('Stale preview accepted'); }
catch (RuntimeException $e) { check($e->getMessage()==='workflow.changed','Changed preview refused'); }
check(!$db->backups && !$db->marker && in_array('rollback',$db->calls,true),'Stale preview writes nothing');
$db=new mysqli(); $db->fail=true;
try { migrateWorkflowCalendarV6($db,workflowMigrationHash(workflowMigrationPlan($db))); throw new LogicException('Failure ignored'); }
catch (RuntimeException $e) { check($e->getMessage()==='Injected failure','Injected failure propagated'); }
check(!$db->backups && !$db->marker && in_array('rollback',$db->calls,true),'Failure rolls back backups and changes');
check(str_contains(end($db->calls),'RELEASE_LOCK'),'Migration lock released after failure');
echo "Migration v6 transaction contract passed.\n";
