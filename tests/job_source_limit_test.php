<?php
declare(strict_types=1);
require __DIR__.'/job_verification_test.php';
$fresh=static fn(array $sources):array=>['criteria'=>$criteria+['total_count'=>20,'display_locale'=>'de-CH'],'sources'=>$sources,'source_index'=>0,'round'=>0,'queue'=>[],'seen'=>[],'originals'=>[],'excluded'=>[],'jobs'=>[],'checked'=>0,'rejected'=>0,'done'=>false,'limited'=>false];
for ($n=2;$n<=60;$n++) {
    $quotas=array_map(static fn($i)=>jobSearchSourceQuota($n,$i),range(0,$n-1));
    helpAssert(array_sum($quotas)===jobSearchExplorationBudget($n) && max($quotas)-min($quotas)<=1,'All source exploration quotas share their budget equally: '.$n);
}
$requests=[];
$GLOBALS['discoveryFixtureCallback']=static function(array $criteria) use (&$requests):array {
    $requests[]=$criteria;
    return array_map(static fn($i)=>['url'=>$criteria['preferred_sources'][0].'/'.$i],range(1,15));
};
$GLOBALS['verificationFixtureError']=new RuntimeException('Die Stellenanzeige konnte nicht gelesen werden (HTTP 401).');
$sources=array_map(static fn($i)=>'https://source'.$i.'.test',range(1,16));
$multi=$fresh($sources);
for ($step=0;$step<100 && !$multi['done'];$step++) advanceVerifiedJobSearch([],7,$multi);
helpAssert($multi['done'] && $multi['checked']===32 && $multi['sources_explored']===16,'All sixteen blocked sources sampled twice without wasting the full budget');
helpAssert(array_column(array_column($requests,'preferred_sources'),0)===$sources,'Every selected source receives a turn despite retrieval failures');
helpAssert(array_column($requests,'total_count')===array_fill(0,16,2),'Sixteen sources receive two exploration candidates before refinement');
helpAssert($multi['jobs']===[] && $multi['technical_errors']===32,'Errors do not manufacture successful results or consume reserved refinement checks');
$single=$fresh([$sources[0]]); advanceVerifiedJobSearch([],7,$single);
helpAssert(end($requests)['total_count']===15 && count($single['queue'])===15,'Single-source batch policy preserved');
$excluded=$fresh($sources);
for ($i=1;$i<=2;$i++) $excluded['excluded'][$sources[0].'/'.$i]=true;
advanceVerifiedJobSearch([],7,$excluded); advanceVerifiedJobSearch([],7,$excluded);
helpAssert($excluded['source_index']===1 && end($requests)['preferred_sources']===[$sources[1]],'Excluded raw results count; no starvation');
$requests=[]; $batch=0;
$GLOBALS['discoveryFixtureCallback']=static function(array $criteria) use (&$requests,&$batch):array {
    $requests[]=$criteria; $batch++;
    return array_map(static fn($i)=>['url'=>'https://first.test/'.$batch.'-'.$i],range(1,3));
};
unset($GLOBALS['verificationFixtureError']);
$GLOBALS['verificationFixtureCallback']=static function(string $url,array $criteria) use ($enriched):array {
    return $enriched+['company'=>'Example SA','location'=>'Bern','original_url'=>str_replace('first.test','original.test',$url)];
};
$partial=$fresh(array_slice($sources,0,5));
for ($step=0;$step<5;$step++) advanceVerifiedJobSearch([],7,$partial);
helpAssert(array_column($requests,'total_count')===[6,3] && $partial['checked']===3,'Short responses request only the remaining equal exploration quota');
unset($GLOBALS['verificationFixtureCallback']);
$legacy=$fresh($sources); $legacy['round']=1; $legacy['source_raw']=10;
$legacy['queue']=array_map(static fn($i)=>'https://first.test/old-'.$i,range(1,9));
advanceVerifiedJobSearch([],7,$legacy);
helpAssert($legacy['source_raw']===2 && count($legacy['queue'])===0 && $legacy['checked']===1,'Old oversized batch shortened to the new exploration quota');
$legacy=$fresh($sources); $legacy['round']=3; $legacy['queue']=['https://first.test/old'];
advanceVerifiedJobSearch([],7,$legacy);
helpAssert($legacy['source_index']===1 && $legacy['limited'],'Older uncounted batch switches safely');

// After equal exploration, only a source that produced usable verified jobs receives the reserve.
$requests=[]; $batches=[];
$GLOBALS['discoveryFixtureCallback']=static function(array $criteria) use (&$requests,&$batches):array {
    $requests[]=$criteria; $source=$criteria['preferred_sources'][0]; $batches[$source]=($batches[$source] ?? 0)+1;
    return array_map(static fn($i)=>['url'=>$source.'/'.$batches[$source].'-'.$i],range(1,$criteria['total_count']));
};
$GLOBALS['verificationFixtureCallback']=static function(string $url,array $criteria) use ($enriched,$sources):array {
    if (!str_starts_with($url,$sources[0].'/')) throw new RuntimeException('Das Portal blockiert den automatischen Abruf (HTTP 403).');
    $draft=$enriched+['company'=>'Example SA','location'=>'Bern'];
    $draft['original_url']=str_replace('source1.test','original.test',$url);
    return $draft;
};
unset($GLOBALS['verificationFixtureError']);
$adaptive=$fresh($sources);
for ($step=0;$step<150 && !$adaptive['done'];$step++) advanceVerifiedJobSearch([],7,$adaptive);
helpAssert($adaptive['done'] && count($adaptive['jobs'])===20 && $adaptive['checked']===50,'Reserved checks return to a proven source until the requested target is reached');
helpAssert($adaptive['sources_explored']===16 && $adaptive['phase']==='refine','Every selected source is explored before adaptive refinement');
helpAssert(($batches[$sources[0]] ?? 0)>1 && count($batches)===16,'Only proven source is queried again after equal exploration');
helpAssert($adaptive['technical_errors']===30,'Blocked sources stop after two matching retrieval failures');
unset($GLOBALS['discoveryFixtureCallback'],$GLOBALS['verificationFixtureCallback'],$GLOBALS['verificationFixtureError']);
echo "Fair-source checks passed\n";
