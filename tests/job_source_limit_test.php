<?php
declare(strict_types=1);
require __DIR__.'/job_verification_test.php';
$fresh=static fn(array $sources):array=>['criteria'=>$criteria+['total_count'=>20,'display_locale'=>'de-CH'],'sources'=>$sources,'source_index'=>0,'round'=>0,'queue'=>[],'seen'=>[],'originals'=>[],'excluded'=>[],'jobs'=>[],'checked'=>0,'rejected'=>0,'done'=>false,'limited'=>false];
for ($n=2;$n<=60;$n++) {
    $quotas=array_map(static fn($i)=>jobSearchSourceQuota($n,$i),range(0,$n-1));
    helpAssert(array_sum($quotas)===60 && max($quotas)-min($quotas)<=1,'All source quotas share budget equally: '.$n);
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
helpAssert($multi['done'] && $multi['checked']===60 && $multi['source_index']===16,'All sixteen sources processed within sixty checks');
helpAssert(array_column(array_column($requests,'preferred_sources'),0)===$sources,'Every selected source receives a turn despite retrieval failures');
helpAssert(array_column($requests,'total_count')===array_merge(array_fill(0,12,4),array_fill(0,4,3)),'Sixteen sources receive four or three raw candidates, never ten');
helpAssert($multi['jobs']===[] && $multi['technical_errors']===60,'Errors do not manufacture successful results');
$single=$fresh([$sources[0]]); advanceVerifiedJobSearch([],7,$single);
helpAssert(end($requests)['total_count']===15 && count($single['queue'])===15,'Single-source batch policy preserved');
$excluded=$fresh($sources);
for ($i=1;$i<=4;$i++) $excluded['excluded'][$sources[0].'/'.$i]=true;
advanceVerifiedJobSearch([],7,$excluded); advanceVerifiedJobSearch([],7,$excluded);
helpAssert($excluded['source_index']===1 && end($requests)['preferred_sources']===[$sources[1]],'Excluded raw results count; no starvation');
$requests=[]; $batch=0;
$GLOBALS['discoveryFixtureCallback']=static function(array $criteria) use (&$requests,&$batch):array {
    $requests[]=$criteria; $batch++;
    return array_map(static fn($i)=>['url'=>'https://first.test/'.$batch.'-'.$i],range(1,3));
};
$partial=$fresh($sources);
for ($step=0;$step<6;$step++) advanceVerifiedJobSearch([],7,$partial);
helpAssert(array_column($requests,'total_count')===[4,1] && $partial['checked']===4,'Short responses request only remaining quota');
$legacy=$fresh($sources); $legacy['round']=1; $legacy['source_raw']=10;
$legacy['queue']=array_map(static fn($i)=>'https://first.test/old-'.$i,range(1,9));
advanceVerifiedJobSearch([],7,$legacy);
helpAssert($legacy['source_raw']===4 && count($legacy['queue'])===2 && $legacy['checked']===1,'Old ten-item batch shortened using already consumed raw count');
$legacy=$fresh($sources); $legacy['round']=3; $legacy['queue']=['https://first.test/old'];
advanceVerifiedJobSearch([],7,$legacy);
helpAssert($legacy['source_index']===1 && $legacy['limited'],'Older uncounted batch switches safely');
unset($GLOBALS['discoveryFixtureCallback'],$GLOBALS['verificationFixtureError']);
echo "Fair-source checks passed\n";
