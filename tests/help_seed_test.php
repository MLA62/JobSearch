<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
helpLoadFunction('seedReviewedHelp');

class mysqli
{
    public array $keys = [];
    public array $texts = [];
    public array $markers = [];
    public array $events = [];
    public bool $locked = true;
    public int $failAt = 0;
    private int $writes = 0;
    private array $snapshot = [];
    public function begin_transaction(): void { $this->snapshot=[$this->keys,$this->texts,$this->markers]; $this->events[]='begin'; }
    public function commit(): void { $this->events[]='commit'; }
    public function rollback(): void { [$this->keys,$this->texts,$this->markers]=$this->snapshot; $this->events[]='rollback'; }
    public function query(string $sql): void { helpAssert(str_contains($sql,'RELEASE_LOCK'), 'Only release query'); $this->events[]='release'; }
    public function prepare(string $sql): HelpStatement { return new HelpStatement($this,$sql); }
    public function write(string $sql, array $p): void
    {
        if (++$this->writes === $this->failAt) { throw new RuntimeException('Synthetic failure'); }
        if (str_starts_with($sql,'INSERT INTO ui_text_keys')) { $this->keys[$p[0]] ??= count($this->keys)+1; }
        elseif (str_starts_with($sql,'INSERT INTO ui_text_translations')) { $this->texts[$p[0].':'.$p[1]]=$p[2]; }
        elseif (str_starts_with($sql,'INSERT INTO app_migrations')) { $this->markers[$p[0]]=true; }
        else { throw new RuntimeException('Unexpected write outside help scope: '.$sql); }
    }
}
class HelpStatement
{
    private array $params = [];
    public function __construct(private mysqli $db, private string $sql) {}
    public function bind_param(string $types, &...$params): void { $this->params=&$params; }
    public function execute(): void { $this->db->write($this->sql,$this->params); }
}
function dbOne(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    if (str_contains($sql,'GET_LOCK')) { return ['acquired'=>$db->locked?1:0]; }
    if (str_contains($sql,'FROM app_migrations')) { return isset($db->markers[$params[0]]) ? ['migration_key'=>$params[0]] : null; }
    if (str_contains($sql,'FROM ui_text_keys')) { return ['id'=>$db->keys[$params[0]]]; }
    throw new RuntimeException('Unexpected help lookup');
}
$db=new mysqli();
seedReviewedHelp($db);
helpAssert(count($db->keys)===count(helpTranslationSeeds()), 'Every help key seeded');
helpAssert(count($db->texts)===count(helpTranslationSeeds())*5, 'Every help translation seeded');
helpAssert(count($db->markers)===1, 'Marker committed');
helpAssert($db->events===['begin','commit','release'], 'Transaction and release');
$events=$db->events;
seedReviewedHelp($db);
helpAssert($events===$db->events, 'Repeat is a no-op');
foreach (helpTranslationSeeds() as $key=>$translations) {
    foreach ($translations as $locale=>$value) {
        helpAssert($db->texts[$db->keys[$key].':'.$locale]===$value, 'Correct bound translation');
    }
}
$failed=new mysqli(); $failed->failAt=7;
try { seedReviewedHelp($failed); throw new RuntimeException('Failure not raised'); }
catch (RuntimeException $e) { helpAssert($e->getMessage()==='Synthetic failure','Failure propagated'); }
helpAssert($failed->keys===[] && $failed->texts===[] && $failed->markers===[], 'Rollback has no partial text or marker');
helpAssert($failed->events===['begin','rollback','release'], 'Failed transaction releases lock');
$failed->failAt=0;
seedReviewedHelp($failed);
helpAssert(count($failed->markers)===1, 'Retry succeeds');
$busy=new mysqli(); $busy->locked=false;
try { seedReviewedHelp($busy); throw new RuntimeException('Busy lock not raised'); }
catch (RuntimeException $e) { helpAssert($e->getMessage()==='Help update is busy','Busy lock fails safely'); }
helpAssert($busy->events===[] && $busy->keys===[], 'Busy lock writes nothing');
echo $helpChecks." help seed checks passed (mock database)\n";
