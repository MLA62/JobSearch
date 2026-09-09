<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
$source = file_get_contents(dirname(__DIR__).'/public/index.php');
helpLoadFunction('pendingJobImport');
helpLoadFunction('jobImportDialogHtml');
helpLoadFunction('sortJobMatches');
$sorted=sortJobMatches([['id'=>'low','match_percent'=>23],['id'=>'high','match_percent'=>'98'],['id'=>'tie','match_percent'=>98],['id'=>'middle','match_percent'=>72],['id'=>'unknown']]);
helpAssert(array_column($sorted,'id')===['high','tie','middle','low','unknown'],'Numeric match descending, stable ties, unknown last');
helpAssert(sortJobMatches([])===[],'Empty matches remain empty');
$entry=['uid'=>7,'expires'=>200,'draft'=>['title'=>'Fixture']];
foreach ([[8,100],[7,201]] as [$uid,$now]) {
    $pending=['fixture'=>$entry];
    try { pendingJobImport($pending,'fixture',$uid,$now); throw new RuntimeException('Expected rejection'); }
    catch (RuntimeException $error) { helpAssert($error->getMessage()==='Import preparation expired.','Wrong owner and expiry rejected'); }
    helpAssert(isset($pending['fixture']),'Rejected claim does not consume another user preparation');
}
$pending=['fixture'=>$entry];
helpAssert(pendingJobImport($pending,'fixture',7,100)['title']==='Fixture','Valid preparation returns original draft');
helpAssert(!$pending,'Preparation consumed only once');
try { pendingJobImport($pending,'fixture',7,100); throw new RuntimeException('Expected rejection'); }
catch (RuntimeException $error) { helpAssert($error->getMessage()==='Import preparation expired.','Repeated commit token rejected'); }
foreach (['de-CH','fr-CH','en-GB','pt-BR','es-MX'] as $locale) {
    $html=jobImportDialogHtml($locale);
    helpAssert(str_contains($html,'<dialog') && str_contains($html,'aria-live="polite"'),'Accessible modal for '.$locale);
    helpAssert(str_contains($html,'AbortController') && str_contains($html,"'prepare_job_import'") && str_contains($html,"'commit_job_import'"),'Prepare and commit stages for '.$locale);
    helpAssert(str_contains($html,"'prepare_quick_import'") && str_contains($html,"'process_quick_import'") && str_contains($html,'data-import-history'),'Quick-import progress and history for '.$locale);
    helpAssert(!str_contains($html,'__LABELS__'),'Translated labels injected for '.$locale);
}
helpAssert(str_contains($source, "['prepare_quick_import','process_quick_import','cancel_quick_import']"),'Quick-import JSON lifecycle is routed');
helpAssert(str_contains($source, "\$_SESSION['pending_quick_imports']") && str_contains($source, "'next'=>0"),'Quick-import progress is durable between item requests');
helpAssert(str_contains($source, "verifiedJobImport(\$config, \$uid, \$sourceUrl") && str_contains($source, "'processed'=>(int)\$run['next']"),'Each quick-import item is verified and reports progress');
echo "$helpChecks import dialog checks passed\n";
