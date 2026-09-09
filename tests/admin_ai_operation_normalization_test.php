<?php
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/public/index.php');
$start = strpos($source, 'function adminAiTableDefinitions(): array');
$end = strpos($source, 'function adminAiResolveReference(', $start);
if ($start === false || $end === false) throw new RuntimeException('Admin-AI normalization functions not found');
eval(substr($source, $start, $end - $start));

function normalizationAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$cleeven = adminAiNormalizeOperation([
    'type'=>'table_upsert','execute'=>true,'table'=>'companies','match_field'=>'name','match_value'=>'Cleeven HE IT',
    'fields'=>[
        ['name'=>'name','value'=>'Cleeven HE IT'],
        ['name'=>'uid','value'=>'CHE-123.456.789'],
        ['name'=>'registration_number','value'=>'CH-270.3.000.000-0'],
        ['name'=>'address','value'=>'Aeschenplatz 1'],
        ['name'=>'zip','value'=>'4052'],
        ['name'=>'city','value'=>'Basel'],
        ['name'=>'website','value'=>'https://example.test'],
    ],
    'company'=>[],
]);
normalizationAssert($cleeven['type'] === 'company_upsert', 'Company table_upsert was not routed to the company writer');
normalizationAssert($cleeven['company']['name'] === 'Cleeven HE IT', 'Company name was lost');
normalizationAssert($cleeven['company']['uid'] === 'CHE-123.456.789', 'UID was not retained as company identity metadata');
normalizationAssert($cleeven['company']['registration_number'] === 'CH-270.3.000.000-0', 'Registration number was lost');
normalizationAssert($cleeven['company']['address_line1'] === 'Aeschenplatz 1' && $cleeven['company']['postal_code'] === '4052', 'Company address aliases were not normalized');

$job = adminAiNormalizeOperation([
    'type'=>'table_upsert','execute'=>true,'table'=>'job','match_field'=>'url','match_value'=>'https://example.test/job',
    'fields'=>[['name'=>'company_name','value'=>'Cleeven HE IT'],['name'=>'job_title','value'=>'Consultant'],['name'=>'location','value'=>'Basel'],['name'=>'url','value'=>'https://example.test/job']],
]);
$jobFields = array_column($job['fields'], 'value', 'name');
normalizationAssert($job['table'] === 'jobs', 'Singular table alias was not normalized');
normalizationAssert($job['match_field'] === 'source_url', 'Match-field alias was not normalized');
normalizationAssert(($jobFields['company_id'] ?? '') === 'Cleeven HE IT' && ($jobFields['title'] ?? '') === 'Consultant' && ($jobFields['location_text'] ?? '') === 'Basel', 'Job aliases were not normalized');

$jobLookup = adminAiNormalizeOperation([
    'type'=>'record_lookup','execute'=>true,'table'=>'job','match_field'=>'','match_value'=>'',
    'fields'=>[['name'=>'job_title','value'=>'Generatives Jobprofil Cleeven']],
]);
normalizationAssert($jobLookup['table'] === 'jobs', 'Lookup table alias was not normalized');
normalizationAssert($jobLookup['match_field'] === 'title' && $jobLookup['match_value'] === 'Generatives Jobprofil Cleeven', 'Lookup match data was not inferred from fields');

$companyLookup = adminAiNormalizeOperation([
    'type'=>'record_lookup','execute'=>true,'table'=>'company','match_field'=>'','match_value'=>'',
    'fields'=>[['name'=>'uid','value'=>'CHE-123.456.789']],
]);
normalizationAssert($companyLookup['match_field'] === 'uid' && $companyLookup['match_value'] === 'CHE-123.456.789', 'Company identity lookup was not inferred');

$definitions = adminAiTableDefinitions();
normalizationAssert(count($definitions) >= 20, 'The test does not cover the complete writable table set');
foreach ($definitions as $table => $definition) {
    $fields = array_map(static fn(string $name): array => ['name'=>$name,'value'=>'test-value'], $definition['fields']);
    $normalized = adminAiNormalizeOperation(['type'=>'table_upsert','execute'=>true,'table'=>$table,'match_field'=>'','match_value'=>'','fields'=>$fields,'company'=>[]]);
    normalizationAssert(($normalized['table'] ?? '') === $table, 'Table changed unexpectedly: '.$table);
    if ($table === 'companies') {
        normalizationAssert(($normalized['type'] ?? '') === 'company_upsert', 'Companies did not use the dedicated writer');
        foreach ($definition['fields'] as $field) normalizationAssert(array_key_exists($field, $normalized['company']), 'Company field lost: '.$field);
    } else {
        $normalizedNames = array_column($normalized['fields'], 'name');
        foreach ($definition['fields'] as $field) normalizationAssert(in_array($field, $normalizedNames, true), 'Allow-listed field lost for '.$table.': '.$field);
    }
}

$withUnknown = adminAiNormalizeOperation(['type'=>'table_upsert','execute'=>true,'table'=>'jobs','match_field'=>'title','match_value'=>'Test','fields'=>[['name'=>'title','value'=>'Test'],['name'=>'invented_ai_field','value'=>'research value']]]);
$unknownFields = array_column($withUnknown['fields'], 'value', 'name');
normalizationAssert(str_contains((string)($unknownFields['notes'] ?? ''), 'invented_ai_field: research value'), 'Unknown researched metadata was not preserved in notes');
normalizationAssert(($withUnknown['_unmapped_fields'][0] ?? '') === 'invented_ai_field', 'Unknown field was not reported');
normalizationAssert(!empty($withUnknown['_preserved_unmapped']), 'Preserved metadata was not marked as retained');

foreach (['company_id','intermediary_company_id','client_company_id','source_id','job_id','contact_id','primary_contact_id','application_id','document_type_id'] as $reference) {
    normalizationAssert(in_array($reference, adminAiReferenceFields(), true), 'Missing reference resolver coverage: '.$reference);
}

normalizationAssert(str_contains($source, 'platform_context.writable_schema'), 'The model is not instructed to use the real writable schema');
normalizationAssert(str_contains($source, "'writable_schema' => \$writableSchema"), 'The real writable schema is not sent to the model');

echo "Admin AI operation normalization contract passed\n";
