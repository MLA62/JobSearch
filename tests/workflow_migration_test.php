<?php
declare(strict_types=1);
// Transaction contract tests with synthetic rows; never connect to production.
if (extension_loaded('mysqli')) { throw new RuntimeException('Run with php -n'); }
$source = file_get_contents($argv[1] ?? __DIR__ . '/../public/index.php');
preg_match('/^function migrateWorkflowCalendarV5\\(.*?(?=^function |\\z)/ms', $source, $match);
eval(trim($match[0]));
class mysqli {
    public array $calls = [];
    public array $backups = [];
    public array $snapshot = [];
    public bool $marker = false;
    public bool $fail = false;
    public bool $lock = true;
    public bool $race = false;
    public int $checks = 0;
    public function query(string $sql): bool { $this->calls[] = $sql; return true; }
    public function begin_transaction(): void { $this->calls[]='begin'; $this->snapshot=$this->backups; }
    public function commit(): void { $this->calls[]='commit'; }
    public function rollback(): void { $this->calls[]='rollback'; $this->backups=$this->snapshot; $this->marker=false; }
}
function dbOne(mysqli $db, string $sql, string $types='', array $values=[]): ?array {
    $db->calls[]=$sql;
    if (str_contains($sql,'GET_LOCK')) { return ['acquired'=>(int)$db->lock]; }
    if (str_contains($sql,'RELEASE_LOCK')) { return ['released'=>1]; }
    if (str_contains($sql,'app_migrations')) {
        $db->checks++;
        return $db->marker || ($db->race && $db->checks>1) ? ['migration_key'=>'workflow_calendar_v5'] : null;
    }
    throw new RuntimeException('Unexpected query');
}
function dbAll(mysqli $db, string $sql): array {
    $db->calls[]=$sql;
    if (str_starts_with($sql,'SELECT id, user_id')) { return [['id'=>1,'user_id'=>2]]; }
    if (str_contains($sql,'FROM applications')) { return [['id'=>1,'user_id'=>2,'next_action'=>'await_response']]; }
    if (str_contains($sql,"source_type IS NOT NULL")) { return [['id'=>3,'owner_user_id'=>2,'source_type'=>'application_status']]; }
    throw new RuntimeException('Private calendar rows must not be selected');
}
function cascadeExec(mysqli $db, string $sql, string $types, array $values): void {
    $db->calls[]=$sql;
    if (str_contains($sql,'workflow_data_backups')) { $db->backups[]=$values; }
    elseif (str_contains($sql,'app_migrations')) { $db->marker=true; }
}
function syncApplicationWorkflow(mysqli $db, int $userId, int $applicationId): void {
    if (count($db->backups)!==2) { throw new RuntimeException('Backup missing before write'); }
    $db->calls[]='sync';
    if ($db->fail) { throw new RuntimeException('Synthetic migration failure'); }
}
function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
    echo "OK: $message\n";
}
$db=new mysqli();
migrateWorkflowCalendarV5($db);
check($db->marker && in_array('commit',$db->calls,true),'Marker and migration commit together');
check(count($db->backups)===2,'Application and owned calendar backed up');
check(json_decode($db->backups[0][4],true)['next_action']==='await_response','Original values recoverable');
check(str_contains(end($db->calls),'RELEASE_LOCK'),'Lock released after success');
$before=count($db->calls); migrateWorkflowCalendarV5($db);
check(count($db->calls)===$before+1 && count($db->backups)===2,'Second execution performs no migration writes');
$db=new mysqli(); $db->fail=true;
try { migrateWorkflowCalendarV5($db); throw new LogicException('Failure not propagated'); }
catch (RuntimeException $e) { check($e->getMessage()==='Synthetic migration failure','Original failure propagated'); }
check(in_array('rollback',$db->calls,true) && !$db->marker && !$db->backups,'Failure rolls back data and marker');
check(str_contains(end($db->calls),'RELEASE_LOCK'),'Lock released after failure');
$db->fail=false; migrateWorkflowCalendarV5($db);
check($db->marker && count($db->backups)===2,'Failed migration can be retried');
$db=new mysqli(); $db->race=true; migrateWorkflowCalendarV5($db);
check(!in_array('begin',$db->calls,true) && str_contains(end($db->calls),'RELEASE_LOCK'),'Concurrent completed migration is not repeated');
$db=new mysqli(); $db->lock=false;
try { migrateWorkflowCalendarV5($db); throw new LogicException('Lock failure ignored'); }
catch (RuntimeException $e) { check(!in_array('begin',$db->calls,true),'No changes without migration lock'); }
echo "All migration contract tests passed.\n";
