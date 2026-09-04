<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
foreach (['jobDisplayText','findJobPosting','readableText','jobAvailability','jobMatchCriteria','jobEvidenceQuote','jobEvidenceScore','jobFactFields','jobFactValue','applyJobEvidence','canonicalJobUrl','sortJobMatches','visibleVerifiedJobs','jobSearchDebugSource','jobSearchDebugError','jobSearchDebugEvent','advanceVerifiedJobSearch'] as $name) helpLoadFunction($name);
$now=strtotime('2026-09-04T12:00:00Z');
if (in_array('--availability-probe',$argv,true)) {
    echo json_encode(jobAvailability(stream_get_contents(STDIN),time()),JSON_THROW_ON_ERROR);
    exit;
}
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
    [$ad([],'<div hidden><button>Apply</button></div>'),'unknown'],
    [$ad([],'<div aria-hidden="true"><a href="/apply">Apply</a></div>'),'unknown'],
    [$ad([],'<fieldset disabled><button>Apply</button></fieldset>'),'unknown'],
    [$ad(['validThrough'=>'2099-12-31'],'Es werden keine Bewerbungen mehr angenommen.'),'expired'],
    [$ad([],'Ne prend rien. Cette entreprise n’accepte plus de candidatures.'),'expired'],
    [$ad([],'Não aceita mais candidaturas'),'expired'],
    [$ad([],'Ya no se aceptan solicitudes'),'expired'],
    [$ad([]).'<button data-modal="job-details-topcard-apply-modal">Bewerben</button>','available'],
    [$ad([]).'<button hidden data-modal="job-details-topcard-apply-modal">Bewerben</button>','unknown'],
    ['<button data-modal="job-details-topcard-apply-modal">Bewerben</button>','unknown'],
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
function openAiJobSearch(array $config,int $uid,array $criteria):array { if (isset($GLOBALS['discoveryFixtureCallback'])) return ($GLOBALS['discoveryFixtureCallback'])($criteria); if (isset($GLOBALS['discoveryFixtureError'])) throw $GLOBALS['discoveryFixtureError']; return array_map(static fn($id)=>['url'=>'https://example.test/'.$id],['expired','unknown','mismatch','deleted','good','duplicate']); }
function verifiedJobImport(array $config,int $uid,string $url,array $criteria):array {
    global $enriched;
    if (isset($GLOBALS['verificationFixtureError'])) throw $GLOBALS['verificationFixtureError'];
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
helpAssert(count(array_filter($state['debug_events'],static fn($event)=>($event['outcome']??'')==='profile_rejected'))===1,'Profile rejection logged with its own outcome');
helpAssert(count(array_filter($state['debug_events'],static fn($event)=>($event['outcome']??'')==='accepted'))===1,'Only accepted candidate logged as accepted');
$GLOBALS['verificationFixtureError']=new RuntimeException('Das Portal blockiert den automatischen Abruf (HTTP 403).');
$blocked=$state;$blocked['done']=false;$blocked['queue']=['https://example.test/blocked'];
advanceVerifiedJobSearch([],7,$blocked);
helpAssert(end($blocked['debug_events'])['code']==='portal_blocked' && end($blocked['debug_events'])['http_status']===403,'Actual search preserves blocked portal reason');
unset($GLOBALS['verificationFixtureError']);
$GLOBALS['discoveryFixtureError']=new RuntimeException('Die KI-Stellensuche ist fehlgeschlagen (HTTP 429).');
$failure=$state;$failure['done']=false;$failure['queue']=[];$failure['source_index']=0;$failure['advance_source']=false;
try { advanceVerifiedJobSearch([],7,$failure); throw new LogicException('Service error swallowed'); }
catch (RuntimeException $error) { helpAssert(end($failure['debug_events'])['stage']==='discovery' && end($failure['debug_events'])['code']==='ai_service_error','Service failure leaves diagnostic event before propagation, not a portal block'); }
unset($GLOBALS['discoveryFixtureError']);
$GLOBALS['verificationFixtureError']=new RuntimeException('Match-Antwortformat ungültig: Kriterienzuordnung.');
$contractFailure=$state; $contractFailure['done']=false; $contractFailure['queue']=['https://example.test/contract'];
try { advanceVerifiedJobSearch([],7,$contractFailure); throw new LogicException('Broken contract silently skipped'); }
catch (RuntimeException $error) {
    helpAssert(end($contractFailure['debug_events'])['code']==='match_contract_error','Broken match contract recorded and surfaced');
    helpAssert($contractFailure['rejected']===$state['rejected'],'Technical contract failure is not a profile rejection');
}
unset($GLOBALS['verificationFixtureError']);
echo "$helpChecks verification checks passed\n";
