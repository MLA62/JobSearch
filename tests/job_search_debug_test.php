<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
foreach (['jobMatchCriteria','jobSearchDebugLabels','jobSearchDebugSource','jobSearchDebugError','jobSearchDebugEvent','jobSearchDebugReport'] as $name) helpLoadFunction($name);
$state=['uid'=>7,'started_at'=>time(),'criteria'=>['query'=>'SECRET_PROFILE_VALUE','location'=>'SECRET_HOME_ADDRESS','total_count'=>15],'sources'=>['https://portal.example/secret-path?token=SECRET_TOKEN'],'jobs'=>[],'id'=>'SECRET_SESSION_ID','debug_events'=>[],'checked'=>1];
$url='https://private-user:SECRET_PASSWORD@portal.example/private-path?token=SECRET_TOKEN#SECRET_FRAGMENT';
$error=new RuntimeException('Das Portal blockiert den automatischen Abruf (HTTP 403). SECRET_KEY');
$classified=jobSearchDebugError($error);
helpAssert($classified['code']==='portal_blocked' && $classified['http_status']===403,'Blocked portal is distinct from mismatch');
jobSearchDebugEvent($state,['stage'=>'original_read','outcome'=>'technical_error','url'=>$url,'duration_ms'=>42,'raw'=>'SECRET_BODY','quote'=>'SECRET_QUOTE']+$classified);
jobSearchDebugEvent($state,['stage'=>'assessment','outcome'=>'profile_rejected','score'=>64,'checks'=>[['criterion'=>'roles','verdict'=>'met','quote'=>'SECRET_TEXT','reason'=>'SECRET_REASON'],['criterion'=>'location','verdict'=>'unknown']]]);
$report=jobSearchDebugReport($state,7);$json=json_encode($report,JSON_THROW_ON_ERROR);
helpAssert(!str_contains($json,'SECRET_') && !str_contains($json,'private-path') && !str_contains($json,'private-user'),'Export omits profile values, URL credentials/path/query/fragment, raw errors, text and session IDs');
helpAssert($report['events'][0]['url']['host']==='portal.example' && strlen($report['events'][0]['url']['url_fingerprint'])===64,'Host and opaque per-URL correlation remain');
helpAssert($report['events'][1]['checks'][1]['verdict']==='unknown' && $report['active_criteria']['roles']['weight']===35,'Useful criterion diagnostics without private values');
helpAssert(jobSearchDebugError(new RuntimeException('Die aktuelle Verfügbarkeit der Originalausschreibung ist nicht ausreichend belegbar.'))['code']==='availability_unknown','Unknown availability distinguished');
helpAssert(jobSearchDebugError(new RuntimeException('Die Ausschreibung ist nicht mehr verfügbar (HTTP 404).'))['code']==='unavailable','404 distinguished');
helpAssert(jobSearchDebugError(new RuntimeException('Abruf fehlgeschlagen (Netzwerkfehler 28).'))['curl_errno']===28,'Timeout code retained');
helpAssert(jobSearchDebugError(new Error('Call to undefined function example_missing()'))['missing_function']==='example_missing','Missing function diagnosable without a stack trace');
helpAssert(jobSearchDebugError(new Error('Class "ExampleMissing" not found'))['missing_class']==='ExampleMissing','Missing class diagnosable without secret-bearing messages');
foreach ([0,8] as $uid) {
    try { jobSearchDebugReport($state,$uid); throw new LogicException('Owner check missing'); }
    catch (RuntimeException $error) { helpAssert($error->getMessage()==='No diagnostic report for this user','Wrong or anonymous owner denied'); }
}
try { jobSearchDebugReport(['uid'=>7],7); throw new LogicException('Invented historic report'); }
catch (RuntimeException $error) { helpAssert(true,'No retrospective report invented'); }
for ($i=0;$i<300;$i++) jobSearchDebugEvent($state,['stage'=>'assessment','outcome'=>'accepted']);
helpAssert(count($state['debug_events'])===250 && $state['debug_dropped']===52,'Session trace bounded with explicit truncation count');
foreach (['de-CH','fr-CH','en-GB','pt-BR','es-MX'] as $locale) helpAssert(count(jobSearchDebugLabels($locale))===5,'Localized download and privacy labels');
$route=substr($helpSource,strpos($helpSource,"if (\$page === 'job_search_debug_download')"));
$route=substr($route,0,strpos($route,"if (\$page === 'document_download')"));
foreach (['requireLogin();','userId()','Cache-Control: private, no-store','X-Content-Type-Options: nosniff','Content-Disposition: attachment','application/json','http_response_code(404)'] as $contract) helpAssert(str_contains($route,$contract),'Download contract: '.$contract);
if (in_array('--json',$argv,true)) { echo json_encode($report,JSON_THROW_ON_ERROR); exit; }
echo "$helpChecks diagnostic checks passed\n";
