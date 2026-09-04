<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
foreach (['plainText','jobDisplayText','jobDisplayLanguage','jobMatchCriteria','jobEvidenceQuote','jobEvidenceScore','jobFactFields','jobFactValue','jobSalaryPeriodFromEvidence','applyJobEvidence','jobVerificationChecks','jobStructuredResponse','verifiedJobImport','jobSearchDebugError'] as $name) helpLoadFunction($name);

// Run the actual import, schema construction, Responses payload and score conversion.
// Only the external HTML and HTTP boundaries are replaced; no key or production data.
if (extension_loaded('curl')) throw new RuntimeException('Run with php -n; cURL must be isolated.');
foreach (['CURLOPT_POST','CURLOPT_POSTFIELDS','CURLOPT_HTTPHEADER','CURLOPT_RETURNTRANSFER','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_PROTOCOLS','CURLPROTO_HTTPS','CURLINFO_RESPONSE_CODE'] as $i=>$constant) define($constant,100+$i);
function curl_init(string $url): object { helpAssert($url==='https://api.openai.com/v1/responses','Responses endpoint'); return new stdClass(); }
function curl_setopt_array(object $handle,array $options): bool { $GLOBALS['wire']=$options[CURLOPT_POSTFIELDS]; $GLOBALS['payload']=json_decode($GLOBALS['wire'],true,512,JSON_THROW_ON_ERROR); return true; }
function curl_getinfo(object $handle,int $option): int { return 200; }
function curl_close(object $handle): void {}
function curl_exec(object $handle): string {
    return json_encode(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($GLOBALS['response'],JSON_THROW_ON_ERROR)]]]]],JSON_THROW_ON_ERROR);
}
function importFromUrl(string $url,array &$diagnostic=[]): array {
    return ['title'=>'Sales Manager','company'=>'Fixture SA','description'=>'Sales Manager in Bern.','company_details'=>[],
        'research_sources'=>[['id'=>'original','text'=>'Sales Manager in Bern.']], 'original_url'=>$url,'availability'=>['reason'=>'future_validThrough']];
}
$criteria=['query'=>'Sales Manager','location'=>'Bern','display_locale'=>'de-CH'];
$check=static fn(string $quote):array=>['verdict'=>'met','source_id'=>'original','quote'=>$quote,'reason'=>'Belegt'];
$GLOBALS['response']=['title'=>'Vertriebsleiter','summary'=>'Vertrieb in Bern','reason'=>'Rolle und Ort passen','facts'=>[],
    'checks'=>['roles'=>$check('Sales Manager'),'location'=>$check('Bern')]];
$good=$GLOBALS['response'];
$draft=verifiedJobImport(['openai_api_key'=>'TEST-ONLY'],7,'https://example.test/job',$criteria);
$schema=$GLOBALS['payload']['text']['format']['schema'];
$contract=$schema['properties']['checks'];
helpAssert($GLOBALS['payload']['text']['format']['strict']===true && $GLOBALS['payload']['store']===false,'Strict, non-stored request');
helpAssert($contract['type']==='object' && $contract['required']===['roles','location'],'Actual request requires each active ID');
helpAssert($contract['additionalProperties']===false && array_keys($contract['properties'])===['roles','location'],'Unknown criterion names impossible in schema');
helpAssert(!isset($contract['properties']['roles']['properties']['criterion']),'Model cannot translate or duplicate criterion names');
helpAssert($draft['assessment']['score']===100 && $draft['assessment']['eligible'],'Real response-to-score path accepts valid evidence');
helpAssert($draft['assessment']['summary']==='Vertrieb in Bern' && $draft['title']==='Sales Manager','Display translation does not replace original title');
foreach ([
    ['Rolle'=>$check('Sales Manager'),'location'=>$check('Bern')],
    ['roles'=>$check('Sales Manager')],
    ['roles'=>$check('Sales Manager'),'location'=>$check('Bern'),'salary'=>$check('Bern')],
    [['criterion'=>'roles']+$check('Sales Manager'),['criterion'=>'roles']+$check('Sales Manager')],
    ['roles'=>['verdict'=>'excellent']+$check('Sales Manager'),'location'=>$check('Bern')],
    ['roles'=>['quote'=>[]]+$check('Sales Manager'),'location'=>$check('Bern')],
    null,
] as $bad) {
    $GLOBALS['response']=$good; $GLOBALS['response']['checks']=$bad;
    try { verifiedJobImport(['openai_api_key'=>'TEST-ONLY'],7,'https://example.test/job',$criteria); throw new LogicException('Invalid response accepted'); }
    catch (RuntimeException $error) { helpAssert(jobSearchDebugError($error)['code']==='match_contract_error','Invalid response has explicit technical classification'); }
}
$GLOBALS['response']=$good;
$GLOBALS['response']['checks']['location']['quote']='Zurich';
helpAssert(!verifiedJobImport(['openai_api_key'=>'TEST-ONLY'],7,'https://example.test/job',$criteria)['assessment']['eligible'],'Schema compliance alone cannot manufacture evidence');
$GLOBALS['response']=$good; $GLOBALS['response']['checks']=new stdClass();
$empty=verifiedJobImport(['openai_api_key'=>'TEST-ONLY'],7,'https://example.test/job',[]);
helpAssert($empty['assessment']['score']===null,'Empty profile remains unscored');
helpAssert(str_contains($GLOBALS['wire'],'"checks":{"type":"object","additionalProperties":false,"properties":{},"required":[]}'),'Empty schema properties encoded as an object on the wire');
helpAssert(jobVerificationChecks([],[])===[],'Empty criteria conversion');
echo "$helpChecks contract checks passed\n";
