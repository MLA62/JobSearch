<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
foreach (['jobDisplayText','importUpsertCompany','importUpsertContact','importDraftContacts','importStoreDraft'] as $name) helpLoadFunction($name);

class mysqli {
    public array $companies = [];
    public array $contacts = [];
    public array $jobs = [];
    public bool $failContacts = false;
    private array $snapshot = [];
    public function begin_transaction(): void { $this->snapshot=[$this->companies,$this->contacts,$this->jobs]; }
    public function commit(): void { $this->snapshot=[]; }
    public function rollback(): void { [$this->companies,$this->contacts,$this->jobs]=$this->snapshot; $this->snapshot=[]; }
    public function prepare(string $sql): ImportStatement { return new ImportStatement($this,$sql); }
}
class ImportStatement {
    public int $insert_id = 0;
    private array $params = [];
    public function __construct(private mysqli $db, private string $sql) {}
    public function bind_param(string $types, &...$params): void {
        helpAssert(strlen($types) === count($params),'Binding type count matches variables');
        helpAssert(substr_count($this->sql,'?') === count($params),'Binding count matches SQL placeholders');
        $this->params = $params;
    }
    public function execute(): void {
        $p=$this->params;
        if (str_starts_with($this->sql,'INSERT INTO companies')) {
            $this->insert_id=count($this->db->companies)+1;
            $this->db->companies[$this->insert_id]=['id'=>$this->insert_id,'owner_user_id'=>$p[0],'name'=>$p[1],'city'=>$p[2],'website'=>$p[3]];
        } elseif (str_starts_with($this->sql,'UPDATE companies')) {
            preg_match('/SET `([a-z_0-9]+)`/', $this->sql,$match); $field=$match[1];
            helpAssert(str_contains($this->sql,'owner_user_id=?') && str_contains($this->sql,'IS NULL OR TRIM'),'Company write is owner-scoped and non-destructive');
            $row=&$this->db->companies[$p[1]];
            if ($row['owner_user_id'] === $p[2] && trim((string)($row[$field]??'')) === '') $row[$field]=$p[0];
        } elseif (str_starts_with($this->sql,'INSERT INTO contacts')) {
            if ($this->db->failContacts) throw new RuntimeException('Simulated contact write failure');
            $this->insert_id=count($this->db->contacts)+1;
            $this->db->contacts[$this->insert_id]=array_combine(['owner_user_id','company_id','job_id','first_name','last_name','position','email','phone'],$p)+['id'=>$this->insert_id];
        } elseif (str_starts_with($this->sql,'UPDATE contacts')) {
            preg_match('/SET `([a-z_0-9]+)`/',$this->sql,$match); $field=$match[1];
            helpAssert(str_contains($this->sql,'owner_user_id=? AND company_id=? AND job_id=?') && str_contains($this->sql,'IS NULL OR TRIM'),'Contact enrichment is scoped and preserves nonempty fields');
            $row=&$this->db->contacts[$p[1]];
            if ($row['owner_user_id']===$p[2] && $row['company_id']===$p[3] && $row['job_id']===$p[4] && trim((string)($row[$field]??''))==='') $row[$field]=$p[0];
        } elseif (str_starts_with($this->sql,'INSERT INTO jobs')) {
            $this->insert_id=count($this->db->jobs)+1;
            $this->db->jobs[$this->insert_id]=array_combine(['owner_user_id','company_id','title','location_text','status','workplace_type','engagement_type','contract_term','source_url','description'],$p)+['id'=>$this->insert_id,'notes'=>''];
        } elseif (str_starts_with($this->sql,'UPDATE jobs')) {
            helpAssert(str_contains($this->sql,'owner_user_id=?'),'Job write is owner-scoped');
            $row=&$this->db->jobs[$p[4]];
            if ($row['owner_user_id']===$p[5]) foreach (['company_id','title','location_text','description'] as $i=>$field) $row[$field]=$p[$i];
        } else { throw new RuntimeException('Unexpected SQL'); }
    }
}
function dbOne(mysqli $db,string $sql,string $types='',array $params=[]): ?array {
    helpAssert(strlen($types) === count($params) && substr_count($sql,'?') === count($params),'Lookup binding count matches');
    if (str_contains($sql,'FROM companies')) {
        foreach ($db->companies as $row) if ($row['owner_user_id']===$params[0] && $row['name']===$params[1]) return $row;
    } elseif (str_contains($sql,'FROM contacts')) {
        foreach ($db->contacts as $row) {
            if ($row['owner_user_id']===$params[0] && $row['company_id']===$params[1] && $row['job_id']===$params[2]
                && (($params[3]!=='' && $row['email']===$params[4]) || ($params[5]!=='' && $row['first_name']===$params[6] && $row['last_name']===$params[7]))) return $row;
        }
    } elseif (str_contains($sql,'FROM jobs')) {
        foreach ($db->jobs as $row) if ($row['owner_user_id']===$params[0] && $row['source_url']===$params[1]) return $row;
    } else { throw new RuntimeException('Unexpected lookup'); }
    return null;
}
function audit(...$args): void {}
$db=new mysqli();
$id=importUpsertCompany($db,7,'Example SA',['city'=>'Bienne','website'=>'https://example.test','address_line1'=>'Rue Exemple 12','postal_code'=>'2502','country_code'=>'CH']);
helpAssert($db->companies[$id]['address_line1']==='Rue Exemple 12','New employer address saved');
helpAssert(importUpsertCompany($db,7,'Example SA',['city'=>'Paris','website'=>'','phone'=>'+41 32 555 01 02'])===$id,'Repeat reuses same own company');
helpAssert($db->companies[$id]['city']==='Bienne' && $db->companies[$id]['website']==='https://example.test','Nonempty existing values preserved');
helpAssert($db->companies[$id]['phone']==='+41 32 555 01 02','Missing phone filled');
helpAssert(importUpsertCompany($db,8,'Example SA',[])!==$id,'Different owner never shares company');
$contact=['first_name'=>'Anne','last_name'=>'Dupont','email'=>'anne@example.test','position'=>'RH'];
importUpsertContact($db,7,$id,11,$contact);
importUpsertContact($db,7,$id,11,$contact);
helpAssert(count($db->contacts)===1,'Repeat import does not duplicate contact');
helpAssert($db->contacts[1]['company_id']===$id && $db->contacts[1]['job_id']===11,'Contact linked to employer and job');
importUpsertContact($db,7,$id,11,[]);
helpAssert(count($db->contacts)===1,'No invented empty person');
importDraftContacts($db,7,$id,11,['contacts'=>[$contact,['first_name'=>'Marc','last_name'=>'Exemple','email'=>'marc@example.test','phone'=>'+41 32 555 01 03']]]);
helpAssert(count($db->contacts)===2,'Multiple original contacts saved without duplicating the first');
importDraftContacts($db,7,$id,11,['contacts'=>[$contact,['first_name'=>'Marc','last_name'=>'Exemple','email'=>'marc@example.test']]]);
helpAssert(count($db->contacts)===2,'Multiple-contact repeat import remains idempotent');
importUpsertContact($db,7,$id,11,$contact+['phone'=>'+41 32 555 99 00']);
helpAssert($db->contacts[1]['phone']==='+41 32 555 99 00','Existing contact receives missing phone');
importUpsertContact($db,7,$id,11,['first_name'=>'Anne','last_name'=>'Dupont','email'=>'changed@example.test','phone'=>'999','position'=>'Changed']);
helpAssert($db->contacts[1]['email']==='anne@example.test' && $db->contacts[1]['position']==='RH' && $db->contacts[1]['phone']==='+41 32 555 99 00','Existing contact details are not overwritten');

$db=new mysqli();
$draft=['title'=>'Account Manager','company'=>'Original Employer SA','source_url'=>'https://example.test/jobs/one','location'=>'Bienne','description'=>'Full original job text','company_details'=>['address_line1'=>'Rue Exemple 12','postal_code'=>'2502','city'=>'Bienne'],'contacts'=>[$contact,['first_name'=>'Marc','last_name'=>'Exemple','email'=>'marc@example.test']]];
$saved=importStoreDraft($db,7,$draft);
$jobId=$saved['job_id']; $firmId=$saved['company_id'];
helpAssert($saved['created'] && count($db->jobs)===1 && count($db->contacts)===2,'Real shared writer saves one job and both contacts');
helpAssert($db->jobs[$jobId]['company_id']===$firmId && $db->companies[$firmId]['address_line1']==='Rue Exemple 12' && $db->companies[$firmId]['postal_code']==='2502','Job links to company with imported address');
$db->jobs[$jobId]['notes']='Private note'; $db->jobs[$jobId]['status']='interesting';
$repeat=importStoreDraft($db,7,$draft);
helpAssert(!$repeat['created'] && count($db->jobs)===1 && count($db->contacts)===2,'Shared writer reimport is idempotent');
helpAssert($db->jobs[$jobId]['notes']==='Private note' && $db->jobs[$jobId]['status']==='interesting','Reimport preserves private notes and status');
$replacement=$draft; $replacement['company']='Correct Employer SA';
$fixed=importStoreDraft($db,7,$replacement);
helpAssert($fixed['job_id']===$jobId && $fixed['company_id']!==$firmId && $db->jobs[$jobId]['company_id']===$fixed['company_id'],'Deliberate repeat corrects employer association on both entry paths');
$other=importStoreDraft($db,8,$draft);
helpAssert($other['job_id']!==$jobId && $other['company_id']!==$firmId,'Other user has separate job and company');
$before=[$db->companies,$db->contacts,$db->jobs];
$db->failContacts=true; $failedDraft=$draft; $failedDraft['source_url']='https://example.test/jobs/two'; $failedDraft['company']='Rollback Employer SA';
try { importStoreDraft($db,7,$failedDraft); throw new RuntimeException('Expected failure'); }
catch (RuntimeException $error) { helpAssert($error->getMessage()==='Simulated contact write failure','Contact storage failure propagated'); }
helpAssert([$db->companies,$db->contacts,$db->jobs]===$before,'Failure rolls back company, job and contacts together in mock DB');
echo "$helpChecks import storage checks passed (mock database)\n";
