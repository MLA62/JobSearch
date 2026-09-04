<?php
declare(strict_types=1);
require __DIR__.'/job_content_language_test.php';

$originalUrl='https://ohws.prospective.ch/public/v1/jobs/example-id/';
$portal='<script>window.state={"externalUrl":'.json_encode($originalUrl).',"other":undefined};</script>';
verify(importOriginalCandidates($portal,'https://www.jobs.ch/en/vacancies/detail/example/')[0] === $originalUrl,'Escaped JobCloud original URL extracted');
verify(importOriginalCandidates('<script>{"externalUrl":"javascript:alert(1)"}</script>','https://www.jobs.ch/en/vacancies/detail/example/') === [],'Non-HTTPS original rejected');
verify(importOriginalCandidates($portal,'https://unrelated.example/') === [],'JobCloud state adapter limited to JobCloud hosts');
verify(importResolveUrl('https://example.test/a/b/','../original/?x=1') === 'https://example.test/a/original/?x=1','Relative original link resolved');
verify(importResolveUrl('https://example.test/a','javascript:alert(1)') === '','Script links rejected');
verify(importSameJob(['title'=>'Key Account Manager (m/w/d)'],['title'=>'Key Account Manager']) === true,'Same title on original accepted');
verify(importSameJob(['title'=>'Key Account Manager'],['title'=>'Software Engineer']) === false,'Different advertisement not accepted');
$schema=['@type'=>'JobPosting','title'=>'Key Account Manager','hiringOrganization'=>['name'=>'Example Logistics AG'],'description'=>'Short schema extract'];
$original='<html><head><script type="application/ld+json">'.json_encode($schema).'</script></head><body><header><a href="https://employer.example/" title="Example Logistics AG"><img alt="Example Logistics AG"></a></header><main><section><h1>Key Account Manager</h1><p>Complete original responsibilities.</p></section><section id="contact"><div class="contactInfo"><p>Example Logistics AG<br>Anna Muster<br>Leiterin Personal<br>061 555 12 34<br>anna@employer.example</p></div><div class="contactInfo"><p>Herr<br>Peter Beispiel<br>Geschäftsführer<br>peter@employer.example</p></div></section></main></body></html>';
$draft=importJobHtml($original,$originalUrl);
verify($draft['company']==='Example Logistics AG','Actual employer taken from original');
verify(count($draft['contacts'])===2,'Both original contact persons extracted');
verify($draft['contacts'][0]['last_name']==='Muster' && $draft['contacts'][1]['last_name']==='Beispiel','Names are not company names or honorifics');
verify($draft['company_details']['website']==='https://employer.example/','Employer website resolved from named original logo');
verify(str_contains($draft['description'],'Complete original responsibilities.') && str_contains($draft['description'],'peter@employer.example'),'Original main body, not shortened schema description');
$companyHtml='<footer><p>Example Logistics AG<br>Beispielstrasse 97<br>CH-4053 Basel</p><p>Tel.: +41 (0) 61 555 12 34<br>E-Mail: info@employer.example</p></footer>';
$details=importCompanyPageDetails($companyHtml,'Example Logistics AG');
verify($details['address_line1']==='Beispielstrasse 97' && $details['postal_code']==='4053' && $details['city']==='Basel','Named employer postal block parsed');
verify($details['country_code']==='CH' && $details['email']==='info@employer.example','Company country and general email retained');
verify(importCompanyPageDetails($companyHtml,'Other Company AG')===[],'Unrelated company address never imported');

$fixturePages=[];
function importFetchHtml(string $url): array {
    global $fixturePages;
    if (!isset($fixturePages[$url])) throw new RuntimeException('Fixture: original is unavailable');
    return ['url'=>$url,'html'=>$fixturePages[$url]];
}
$portalUrl='https://www.jobs.ch/en/vacancies/detail/example/';
$portalSchema=$schema;
$portalSchema['hiringOrganization']['name']='Parent Company AG';
$portalPage=$portal.'<script type="application/ld+json">'.json_encode($portalSchema).'</script>';
$fixturePages=[$portalUrl=>$portalPage,$originalUrl=>$original,'https://employer.example/'=>$companyHtml];
$resolved=importFromUrl($portalUrl);
verify($resolved['company']==='Example Logistics AG' && count($resolved['contacts'])===2,'Full drill-down uses original employer and both contacts');
verify($resolved['source_url']===$portalUrl && $resolved['original_url']===$originalUrl && count($resolved['source_chain'])===2,'Portal and original provenance retained separately');
verify($resolved['company_details']['address_line1']==='Beispielstrasse 97','Drill-down enriches original employer from named company website');
unset($fixturePages[$originalUrl]);
try { importFromUrl($portalUrl); verify(false,'Missing original must fail'); }
catch (RuntimeException $error) { verify(str_contains($error->getMessage(),'unavailable'),'Missing original never silently imports the portal summary'); }
$fixturePages[$originalUrl]=str_replace('Key Account Manager','Software Engineer',$original);
try { importFromUrl($portalUrl); verify(false,'Different original must fail'); }
catch (RuntimeException $error) { verify(str_contains($error->getMessage(),'passt nicht'),'Mismatched linked original rejected before storage'); }
$fixturePages[$originalUrl]=$original;
unset($fixturePages['https://employer.example/']);
$partial=importFromUrl($portalUrl);
verify(!empty($partial['import_warnings']) && empty($partial['company_details']['address_line1']),'Unavailable company page does not invent an address or discard readable original');
$handler=substr($source,strpos($source,"if (\$action === 'prepare_ai_job_import')"));
$handler=substr($handler,0,strpos($handler,"if (\$action === 'exclude_ai_job')"));
verify(!str_contains($handler,'pdfTableBytes') && !str_contains($handler,'file_put_contents'),'Import never creates a fake original PDF or attachment');
verify(str_contains($handler,'begin_transaction()') && substr_count($handler,'commit()')===2 && str_contains($handler,'rollback()'),'Both AI import storage paths have explicit transaction boundaries (source contract)');

if (in_array('--live-fixture',$argv,true)) {
    // Input is fetched read-only by the caller; no real ad or person is stored in Git.
    $live=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
    $urls=importOriginalCandidates($live['portal'],'https://www.jobs.ch/en/vacancies/detail/db96617d-65ca-44e1-ac20-b047acfcc9be/');
    verify(count($urls)>0 && str_starts_with($urls[0],'https://ohws.prospective.ch/'),'Live example: drill-down found');
    $liveDraft=importJobHtml($live['original'],'https://ohws.prospective.ch/public/v1/jobs/1d08e34c-d6ae-4406-abed-b5e39ed165eb/');
    verify($liveDraft['company']==='ChemOil Logistics AG','Live example: original employer, not portal parent company');
    verify(count($liveDraft['contacts'])===2,'Live example: both contact persons found');
    $liveAddress=importCompanyPageDetails($live['company'],$liveDraft['company']);
    verify(($liveAddress['address_line1']??'')==='Güterstrasse 97' && ($liveAddress['postal_code']??'')==='4053','Live example: address verified from named employer footer');
    verify(str_contains($liveDraft['description'],'Noch Fragen?'),'Live example: complete original section retained');
}
