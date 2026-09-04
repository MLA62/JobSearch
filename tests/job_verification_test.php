<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
foreach (['jobDisplayText','findJobPosting','readableText','jobAvailability','jobMatchCriteria','jobEvidenceQuote','jobEvidenceScore','jobFactFields','jobFactValue','applyJobEvidence','canonicalJobUrl','sortJobMatches','visibleVerifiedJobs','advanceVerifiedJobSearch'] as $name) helpLoadFunction($name);
$now=strtotime('2026-09-04T12:00:00Z');
$ad=static fn(array $fields,string $body=''):string=>'<html><script type="application/ld+json">'.json_encode(['@type'=>'JobPosting','title'=>'Sales Manager']+$fields).'</script><main>'.$body.'</main></html>';
foreach ([
    [$ad(['validThrough'=>'2099-12-31'],'Inserat abgelaufen'),'expired'],
    [$ad(['validThrough'=>'2020-01-01']),'expired'],
    [$ad(['validThrough'=>'2099-12-31']),'available'],
    [$ad([],"<h1>This page can't be found</h1>"),'unavailable'],
    [$ad(['datePosted'=>'2020-01-01'],'<a href="/apply">Apply</a>'),'unknown'],
    [$ad([]),'unknown'],
    [$ad([],'<a href="/apply">Apply</a>'),'available'],
    [$ad([],'<button disabled>Apply</button>'),'unknown'],
] as [$html,$expected]) helpAssert(jobAvailability($html,$now)['status']===$expected,'Availability: '.$expected);
$criteria=['query'=>'Sales Manager','location'=>'Bern'];
$sources=[['id'=>'original','text'=>'Sales Manager in Bern. Full time 100%.'],['id'=>'company_1','text'=>'Sales Manager in Bern.']];
$checks=[['criterion'=>'roles','verdict'=>'met','source_id'=>'original','quote'=>'Sales Manager'],['criterion'=>'location','verdict'=>'met','source_id'=>'original','quote'=>'Bern']];
$score=jobEvidenceScore($criteria,$checks,$sources);
helpAssert($score['score']===100 && $score['eligible'],'Exact original evidence earns weighted score');
$bad=$checks; $bad[1]['quote']='Zurich';
helpAssert(!jobEvidenceScore($criteria,$bad,$sources)['eligible'],'Invented location evidence is not usable');
$bad=$checks; $bad[1]['source_id']='company_1';
helpAssert(!jobEvidenceScore($criteria,$bad,$sources)['eligible'],'Company marketing cannot establish job match');
$bad=$checks; $bad[1]['verdict']='unmet';
helpAssert(!jobEvidenceScore($criteria,$bad,$sources)['eligible'],'Hard mismatch excluded');
helpAssert(!jobEvidenceScore($criteria,[],$sources)['eligible'],'No evidence is not a match');
helpAssert(jobEvidenceScore([],[],$sources)['score']===null,'No profile does not invent a percentage');
try { jobEvidenceScore($criteria,[$checks[0],$checks[0]],$sources); throw new LogicException('Duplicate accepted'); }
catch (RuntimeException $error) { helpAssert(str_contains($error->getMessage(),'doppelte'),'Duplicate criterion refused'); }
$draft=['title'=>'Sales Manager','research_sources'=>$sources,'company_details'=>[]];
$facts=[['entity'=>'company','field'=>'address_line1','value'=>'Invented Street 1','source_id'=>'original','quote'=>'Sales Manager'],['entity'=>'job','field'=>'workload_max','value'=>'100','source_id'=>'original','quote'=>'Full time 100%.']];
$enriched=applyJobEvidence($draft,['facts'=>$facts,'checks'=>$checks,'summary'=>'Vertrieb in Bern','reason'=>'Rolle und Ort passen'],$criteria);
helpAssert(empty($enriched['company_details']['address_line1']),'Invented address not imported');
helpAssert($enriched['job_details']['workload_max']===100,'Original workload imported');
helpAssert(jobFactValue('job','workload_max','101')===null && jobFactValue('job','fixed_term_end','2026-02-30')===null,'Invalid typed facts rejected');
helpAssert(canonicalJobUrl('https://example.test/job/1?utm_source=x')==='https://example.test/job/1','Tracking variants deduplicated');

// Exercise actual incremental orchestration with deterministic external boundaries.
function openAiJobSearch(array $config,int $uid,array $criteria):array { return array_map(static fn($id)=>['url'=>'https://example.test/'.$id],['expired','unknown','mismatch','deleted','good','duplicate']); }
function verifiedJobImport(array $config,int $uid,string $url,array $criteria):array {
    global $enriched;
    if (str_ends_with($url,'expired') || str_ends_with($url,'unknown')) throw new RuntimeException('Unavailable');
    $draft=$enriched+['company'=>'Example SA','location'=>'Bern','original_url'=>'https://original.test/good'];
    if (str_ends_with($url,'mismatch')) $draft['assessment']['eligible']=false;
    return $draft;
}
$state=['criteria'=>$criteria+['total_count'=>10,'display_locale'=>'de-CH'],'sources'=>['example'],'source_index'=>0,'round'=>0,'queue'=>[],'seen'=>[],'originals'=>[],'excluded'=>['https://example.test/deleted'=>true],'jobs'=>[],'checked'=>0,'rejected'=>0,'done'=>false,'limited'=>false];
for ($step=0;$step<20 && !$state['done'];$step++) advanceVerifiedJobSearch([],7,$state);
helpAssert($state['done'] && count($state['jobs'])===1,'Only usable original appears; expired, unknown, mismatched, deleted and duplicate jobs excluded');
helpAssert($state['checked']===5 && $state['rejected']===4,'Search continues after unusable candidates');
$row=$state['jobs'][0];
helpAssert(count(visibleVerifiedJobs([$row],$criteria,time()))===1,'Fresh matching result visible');
$old=$row; $old['verified_at']=time()-901;
$legacy=$row; unset($legacy['verification_revision']);
helpAssert(visibleVerifiedJobs([$old,$legacy],$criteria,time())===[],'Stale and legacy unverified results not displayed');
helpAssert(visibleVerifiedJobs([$row],['query'=>'Nurse','location'=>'Bern'],time())===[],'Profile changes invalidate old matches');
echo "$helpChecks verification checks passed\n";
