<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
foreach (['jobDisplayText','jobFactFields','jobFactValue','applyJobWebResearch'] as $name) helpLoadFunction($name);

$draft=[
    'company'=>'Brack.Alltron AG',
    'company_details'=>['legal_name'=>'Brack.Alltron AG','address_line1'=>'','postal_code'=>'','city'=>'','website'=>'https://brackalltron.ch'],
    'contacts'=>[['first_name'=>'Eveline','last_name'=>'Icobas','email'=>'eveline.icobas@brackalltron.ch']],
];
$response=['facts'=>[
    ['entity'=>'company','person'=>'','field'=>'address_line1','value'=>'Hintermättlistrasse 3','source_url'=>'https://brackalltron.ch/legal/impressum','evidence'=>'Hintermättlistrasse 3'],
    ['entity'=>'company','person'=>'','field'=>'postal_code','value'=>'5506','source_url'=>'https://brackalltron.ch/legal/impressum','evidence'=>'5506 Mägenwil'],
    ['entity'=>'company','person'=>'','field'=>'city','value'=>'Mägenwil','source_url'=>'https://brackalltron.ch/legal/impressum','evidence'=>'5506 Mägenwil'],
    ['entity'=>'company','person'=>'','field'=>'website','value'=>'https://wrong.example','source_url'=>'https://wrong.example','evidence'=>'wrong'],
    ['entity'=>'contact','person'=>'Eveline Icobas','field'=>'first_name','value'=>'Eveline','source_url'=>'https://jobs.brackalltron.ch/offene-stellen/test','evidence'=>'Eveline Icobas'],
    ['entity'=>'contact','person'=>'Eveline Icobas','field'=>'last_name','value'=>'Icobas','source_url'=>'https://jobs.brackalltron.ch/offene-stellen/test','evidence'=>'Eveline Icobas'],
    ['entity'=>'contact','person'=>'Eveline Icobas','field'=>'email','value'=>'eveline.icobas@brackalltron.ch','source_url'=>'https://jobs.brackalltron.ch/offene-stellen/test','evidence'=>'eveline.icobas@brackalltron.ch'],
    ['entity'=>'company','person'=>'','field'=>'address_line2','value'=>'ignored','source_url'=>'http://insecure.example','evidence'=>'ignored'],
], 'sources'=>[]];
$result=applyJobWebResearch($draft,$response);
helpAssert($result['company_details']['address_line1']==='Hintermättlistrasse 3','Web research fills missing street');
helpAssert($result['company_details']['postal_code']==='5506' && $result['company_details']['city']==='Mägenwil','Web research fills postal code and city');
helpAssert($result['company_details']['website']==='https://brackalltron.ch','Existing CRM value is not overwritten');
helpAssert(count($result['contacts'])===2 && $result['contacts'][1]['email']==='eveline.icobas@brackalltron.ch','Supported recruiting contact is retained');
helpAssert(!isset($result['company_details']['address_line2']),'Non-HTTPS evidence is rejected');
helpAssert(count($result['web_research_sources'])===2,'Accepted evidence sources are recorded once');

$source=file_get_contents(__DIR__.'/../public/index.php');
foreach ([
    "'tools'=>[['type'=>'web_search']]"=>'Responses web search tool',
    'function applicationEnsureRecipientData('=>'application-time recipient research',
    'applicationEnsureRecipientData($config,$db,$userId,$applicationId);'=>'research before text initialization',
    "'missing_fields'=>array_values(\$missing)"=>'explicit missing-field request',
    'official career page, legal notice and official registry'=>'primary-source preference',
    'importFetchHtml($url)'=>'independent fetch of every cited source',
    'mb_stripos($pageText[$url],$evidence)!==false'=>'exact evidence verification before acceptance',
    'Never invent achievements, qualifications, employer facts, contacts or addresses.'=>'recipient placeholder prohibition',
] as $needle=>$label) helpAssert(str_contains($source,$needle),$label);
echo "$helpChecks job web research checks passed\n";
