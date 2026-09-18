<?php
declare(strict_types=1);

$source = (string) file_get_contents(__DIR__ . '/../public/index.php');

function reportCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

function tr(string $key, ?string $locale = null, array $replace = []): string
{
    $key = match ($key) {
        'applications.job_room_recorded_result' => 'Im Job-Room erfasst – {result}',
        'applications.job_room_interview' => 'Vorstellungsgespräch',
        'job_room_helper.result.open' => 'Noch offen',
        'job_room_helper.result.hired' => 'Anstellung',
        'job_room_helper.result.rejected' => 'Absage',
        default => $key,
    };
    foreach ($replace as $name => $value) {
        $key = str_replace([':' . $name, '{' . $name . '}'], (string)$value, $key);
    }
    return $key;
}

reportCheck(preg_match('/function limitReportColumns\(.*?^\}/ms', $source, $match) === 1, 'Report column limiter is isolated for regression testing');
eval($match[0]);
$allowed = array_combine(array_map(static fn(int $number): string => 'field_' . $number, range(1, 20)), range(1, 20));
$selection = array_merge(['field_1', 'unknown', 'field_1'], array_map(static fn(int $number): string => 'field_' . $number, range(2, 20)));
$limited = limitReportColumns($allowed, $selection, 12);
reportCheck(count($limited) === 12, 'At most twelve report fields are accepted');
reportCheck($limited[0] === 'field_1' && $limited[11] === 'field_12', 'Report fields retain their selected order');
reportCheck(count(array_unique($limited)) === count($limited) && !in_array('unknown', $limited, true), 'Unknown and duplicate report fields are rejected');

reportCheck(preg_match('/function reportEditorFieldOptions\(.*?^\}/ms', $source, $editorFieldsMatch) === 1, 'Report editor field ordering is isolated for regression testing');
eval($editorFieldsMatch[0]);
$editorFields = reportEditorFieldOptions(['one'=>'One','two'=>'Two','three'=>'Three'], ['three','one']);
reportCheck(array_keys($editorFields) === ['three','one','two'], 'The editor preserves saved column order before unselected fields');

reportCheck(preg_match('/function reportNormalizeRelations\(.*?^\}/ms', $source, $relationMatch) === 1, 'Report relation normalizer is isolated for regression testing');
eval($relationMatch[0]);
$mediated = reportNormalizeRelations('applications', ['company_id'=>8,'company'=>'Agency AG','company_is_intermediary'=>1,'intermediary_company_id'=>null,'intermediary_company'=>'']);
reportCheck($mediated['intermediary_company_id'] === 8 && $mediated['intermediary_company'] === 'Agency AG', 'An intermediary stored as the job company is shown as intermediary');
$direct = reportNormalizeRelations('applications', ['company_id'=>8,'company'=>'Employer AG','company_is_intermediary'=>0,'intermediary_company_id'=>null,'intermediary_company'=>'']);
reportCheck(empty($direct['intermediary_company']), 'A normal employer is not invented as intermediary');
$contact = reportNormalizeRelations('applications', ['primary_contact_id'=>3,'primary_contact'=>'','primary_contact_email'=>'person@example.test']);
reportCheck($contact['primary_contact'] === 'person@example.test', 'An assigned nameless contact remains visible by email');
$document = reportNormalizeRelations('documents', ['job_id'=>null,'application_job_id'=>19]);
reportCheck($document['job_id'] === 19, 'A document inherits the job ID from its linked application');
$calendar = reportNormalizeRelations('calendar', ['company'=>'','contact_company'=>'Contact Company AG']);
reportCheck($calendar['company'] === 'Contact Company AG', 'A calendar entry inherits the company from its linked contact');
$calendarContact = reportNormalizeRelations('calendar', ['contact_id'=>4,'contact'=>'','contact_email'=>'calendar@example.test']);
reportCheck($calendarContact['contact'] === 'calendar@example.test', 'A calendar contact without a name remains visible by email');
$namedContact = reportNormalizeRelations('contacts', ['name'=>'','email'=>'contact@example.test']);
reportCheck($namedContact['name'] === 'contact@example.test', 'A nameless contact remains visible by email');

reportCheck(str_contains($source, 'function reportColumnLimit(): int') && str_contains($source, 'return 12;'), 'The report field maximum is explicit');
reportCheck(str_contains($source, 'reportSelectedColumns($base, $_POST[\'report_columns\'] ?? [])'), 'The server validates saved report columns');
reportCheck(str_contains($source, 'reportSelectedColumns($base, $requestedColumns)'), 'The server validates exported report columns');
reportCheck(str_contains($source, 'data-report-column-picker') && str_contains($source, 'data-report-column-count'), 'The editor displays the full field picker and live selection count');
reportCheck(str_contains($source, 'data-report-drag-handle') && str_contains($source, "handle.addEventListener('dragstart'") && str_contains($source, "wrapper.addEventListener('dragover'"), 'Report columns can be reordered by drag and drop');
reportCheck(str_contains($source, "redirect('/?page=reports&edit_report=' . \$reportId . '&view_report=' . \$reportId . '#report-view')") && str_contains($source, "redirect('/?page=reports&edit_report=' . \$id . '&view_report=' . \$id . '#report-view')"), 'Saving immediately reloads the updated report instead of leaving the previous view visible');
reportCheck(str_contains($source, "base.addEventListener('change'") && str_contains($source, "picker.addEventListener('change',enforceLimit)"), 'Changing the data source refreshes fields and enforces the maximum immediately');
reportCheck(preg_match('/function reportOpenUrl\(array \$report\): string\s*\{.*?^\}/ms', $source, $openUrlMatch) === 1, 'Saved-report navigation can be isolated for regression testing');
eval($openUrlMatch[0]);
reportCheck(reportOpenUrl(['id'=>73, 'base_entity'=>'jobs', 'display_type'=>'table']) === '/?page=reports&view_report=73#report-view', 'Show opens the selected saved report instead of an unrelated module table');
reportCheck(str_contains($source, '[$viewReportHeaders, $viewReportData, $viewReportMeta] = reportDataset('), 'The visible report uses its saved settings, report dataset, and display metadata');
reportCheck(str_contains($source, 'reportRowsHtml($viewReportHeaders, $viewReportData, $viewReportDisplayType, $viewReportMeta)'), 'The saved display type controls the visible report renderer');
reportCheck(str_contains($source, 'data-report-display') && str_contains($source, 'const displays=') && str_contains($source, "display.value='table'"), 'Changing the data source refreshes the valid display types');
reportCheck(str_contains($source, 'reportDisplayType($baseEntity, $_POST[\'display_type\'] ?? null)'), 'Save and update validate the display type against the selected data source');

reportCheck(preg_match('/function reportDisplayOptions\(.*?^\}/ms', $source, $displayOptionsMatch) === 1, 'Report display options are isolated for regression testing');
eval($displayOptionsMatch[0]);
reportCheck(preg_match('/function reportDisplayType\(.*?^\}/ms', $source, $displayTypeMatch) === 1, 'Report display validation is isolated for regression testing');
eval($displayTypeMatch[0]);
reportCheck(preg_match('/function reportViewDisplayType\(.*?^\}/ms', $source, $viewDisplayTypeMatch) === 1, 'Report view override is isolated for regression testing');
eval($viewDisplayTypeMatch[0]);
reportCheck(preg_match('/function reportViewUrl\(.*?^\}/ms', $source, $viewUrlMatch) === 1, 'Report view links are isolated for regression testing');
eval($viewUrlMatch[0]);
reportCheck(preg_match('/function reportRecordUrl\(.*?^\}/ms', $source, $recordUrlMatch) === 1, 'Report record links are isolated for regression testing');
eval($recordUrlMatch[0]);
reportCheck(preg_match('/function reportFieldRecordUrl\(.*?^\}/ms', $source, $fieldRecordUrlMatch) === 1, 'Report field links are isolated for regression testing');
eval($fieldRecordUrlMatch[0]);
reportCheck(reportDisplayType('jobs', 'cards') === 'cards', 'Card view remains available for normal report data');
reportCheck(reportDisplayType('jobs', 'calendar_month') === 'table', 'Calendar-only view is rejected for non-calendar data');
reportCheck(reportDisplayType('calendar', 'calendar_month') === 'calendar_month', 'Calendar month view remains available for calendar data');
reportCheck(reportViewDisplayType('applications', 'list', 'table') === 'table' && reportViewDisplayType('applications', 'list', 'cards') === 'cards', 'Every opened report can switch directly between table and cards');
reportCheck(reportViewDisplayType('applications', 'list', 'invalid') === 'list', 'An invalid view override falls back to the saved display type');
reportCheck(reportViewUrl(73, 'cards') === '/?page=reports&view_report=73&report_as=cards#report-view', 'The cards switch keeps the selected report');
reportCheck(str_contains(reportViewUrl(73, 'cards', ['status'=>['value'=>'sent']]), 'report_filter%5Bstatus%5D%5Bvalue%5D=sent'), 'The table and cards switches preserve active report filters');
reportCheck(str_contains(reportViewUrl(73, 'cards', ['status'=>['values'=>['sent','interview']]]), 'report_filter%5Bstatus%5D%5Bvalues%5D%5B0%5D=sent'), 'The table and cards switches preserve multiple choice values');
reportCheck(str_contains($source, 'data-report-view-option="table"') && str_contains($source, 'data-report-view-option="cards"'), 'The opened report displays both view switches');
reportCheck(str_contains($source, 'class="panel table-wrap report-saved-panel"') && str_contains((string)file_get_contents(__DIR__ . '/../public/assets/app.css'), '.reports-layout > .report-saved-panel { order: -1; }'), 'Saved reports are displayed above the report editor');

foreach (['reportViewFilterType','reportViewFilterState','reportViewFilterDefinitions','reportViewApplyFilters'] as $functionName) {
    reportCheck(preg_match('/function ' . $functionName . '\(.*?^\}/ms', $source, $filterFunctionMatch) === 1, "{$functionName} is isolated for regression testing");
    eval($filterFunctionMatch[0]);
}
reportCheck(reportViewFilterType('applied_at') === 'date' && reportViewFilterType('match_score') === 'number' && reportViewFilterType('status') === 'choice' && reportViewFilterType('title') === 'text', 'Report filters adapt to date, number, choice, and text fields');
$filterColumns = ['applied_at','match_score','status','title'];
$filterState = reportViewFilterState($filterColumns, [
    'applied_at'=>['from'=>'2026-09-02','to'=>'2026-09-30'],
    'match_score'=>['min'=>'70','max'=>'90'],
    'status'=>['value'=>'sent'],
    'title'=>['value'=>'sales'],
    'unknown'=>['value'=>'ignored'],
]);
reportCheck(array_keys($filterState) === $filterColumns && !isset($filterState['unknown']), 'Only filters for fields displayed by the report are accepted');
$filterRows = [
    ['03.09.2026','85','Gesendet','Senior Sales Manager'],
    ['01.09.2026','92','Gesendet','Sales Director'],
    ['04.09.2026','80','Entwurf','Sales Engineer'],
];
$filterMeta = [
    ['record_url'=>'/?page=applications&edit=1','filter_values'=>['applied_at'=>'2026-09-03 10:00:00','match_score'=>'85','status'=>'sent','title'=>'Senior Sales Manager']],
    ['record_url'=>'/?page=applications&edit=2','filter_values'=>['applied_at'=>'2026-09-01 10:00:00','match_score'=>'92','status'=>'sent','title'=>'Sales Director']],
    ['record_url'=>'/?page=applications&edit=3','filter_values'=>['applied_at'=>'2026-09-04 10:00:00','match_score'=>'80','status'=>'draft','title'=>'Sales Engineer']],
];
[$filteredRows, $filteredMeta] = reportViewApplyFilters($filterColumns, $filterRows, $filterMeta, $filterState);
reportCheck(count($filteredRows) === 1 && $filteredRows[0][3] === 'Senior Sales Manager', 'All active field-specific filters are combined correctly');
reportCheck(($filteredMeta[0]['record_url'] ?? '') === '/?page=applications&edit=1', 'Filtering keeps source-record links aligned with their result');
$filterDefinitions = reportViewFilterDefinitions($filterColumns, ['Datum','Match','Status','Job'], $filterRows, $filterMeta);
reportCheck(array_column($filterDefinitions, 'type') === ['date','number','choice','text'], 'The opened report renders the suitable control for each selected field');
reportCheck(($filterDefinitions[2]['options']['sent'] ?? '') === 'Gesendet' && ($filterDefinitions[2]['options']['draft'] ?? '') === 'Entwurf', 'Choice filters use the values actually available in the report');
reportCheck(str_contains($source, 'reportViewFiltersHtml((int)$viewReport[\'id\']') && str_contains($source, 'reportViewApplyFilters($viewReportColumns'), 'Opened reports apply and display their field-specific filters');
$recordUrls = [
    'jobs'=>reportRecordUrl('jobs', ['id'=>1]),
    'applications'=>reportRecordUrl('applications', ['id'=>2]),
    'companies'=>reportRecordUrl('companies', ['id'=>3]),
    'contacts'=>reportRecordUrl('contacts', ['id'=>4]),
    'documents'=>reportRecordUrl('documents', ['id'=>5]),
    'calendar'=>reportRecordUrl('calendar', ['id'=>6]),
];
reportCheck($recordUrls === [
    'jobs'=>'/?page=jobs&edit=1#new',
    'applications'=>'/?page=applications&edit=2#application-form',
    'companies'=>'/?page=companies&edit=3',
    'contacts'=>'/?page=contacts&edit_contact=4#contact-editor',
    'documents'=>'/?page=documents&edit_document=5#document-editor',
    'calendar'=>'/?page=calendar&edit_event=6#calendar-entry-form',
], 'Every report data source links to its own record editor');
reportCheck(reportRecordUrl('unknown', ['id'=>9]) === '' && reportRecordUrl('jobs', ['id'=>0]) === '', 'Unknown sources and invalid IDs never produce report links');
reportCheck(reportFieldRecordUrl('applications','company',['id'=>9,'company_id'=>31]) === '/?page=companies&edit=31', 'Application company values link to the individual company');
reportCheck(reportFieldRecordUrl('applications','title',['id'=>9,'job_id'=>41]) === '/?page=jobs&edit=41#new', 'Application job values link to the individual job');
reportCheck(reportFieldRecordUrl('applications','intermediary_company',['id'=>9,'intermediary_company_id'=>51]) === '/?page=companies&edit=51', 'Intermediary values link to the individual intermediary company');
reportCheck(reportFieldRecordUrl('applications','primary_contact',['id'=>9,'primary_contact_id'=>61]) === '/?page=contacts&edit_contact=61#contact-editor', 'Application contact values link to the individual contact');
reportCheck(reportFieldRecordUrl('applications','status',['id'=>9]) === '/?page=applications&edit=9#application-form', 'Application-owned values link to the application');
reportCheck(reportFieldRecordUrl('contacts','company',['id'=>7,'company_id'=>31]) === '/?page=companies&edit=31', 'Contact company values link to the individual company');
reportCheck(reportFieldRecordUrl('contacts','job',['id'=>7,'job_id'=>41]) === '/?page=jobs&edit=41#new', 'Contact job values link to the individual job');
reportCheck(reportFieldRecordUrl('documents','application',['id'=>8,'application_id'=>9]) === '/?page=applications&edit=9#application-form', 'Document application values link to the individual application');
reportCheck(reportFieldRecordUrl('calendar','contact',['id'=>6,'contact_id'=>61]) === '/?page=contacts&edit_contact=61#contact-editor', 'Calendar contact values link to the individual contact');
reportCheck(str_contains($source, "\$rowDisplayMeta['cell_urls'] = array_map(") && str_contains($source, 'reportFieldValueHtml($value'), 'Every rendered field receives its corresponding record link');
reportCheck(!str_contains($source, 'reportRecordLinkHtml((array)($displayMeta[$index] ?? []))'), 'Reports no longer spend a separate column or button on record links');
foreach (['table','list','cards','preview','calendar_day','calendar_week','calendar_month'] as $displayType) {
    reportCheck(str_contains($source, "'{$displayType}'"), "Report renderer supports {$displayType}");
}

reportCheck(preg_match('/function jobRoomApplicationResult\(.*?^\}/ms', $source, $jobRoomResultMatch) === 1, 'Job-Room result formatter is isolated for regression testing');
eval($jobRoomResultMatch[0]);
reportCheck(jobRoomApplicationResult('open', 'sent', 'not_recorded') === 'applications.job_room_not_recorded', 'An application not recorded in Job-Room is not reported as open');
reportCheck(jobRoomApplicationResult('open', 'sent', 'unknown') === 'applications.job_room_not_recorded', 'An unconfirmed Job-Room registration is not reported as open');
reportCheck(jobRoomApplicationResult('open', 'sent', 'recorded') === 'Noch offen', 'Open is shown only after confirmed Job-Room registration');
reportCheck(preg_match('/function jobRoomApplicationStatus\(.*?^\}/ms', $source, $jobRoomStatusMatch) === 1, 'Combined Job-Room status formatter is isolated for regression testing');
eval($jobRoomStatusMatch[0]);
reportCheck(jobRoomApplicationStatus('open', 'sent', 'recorded', null) === 'applications.job_room_not_recorded', 'A Job-Room status without an application date is not reported as recorded');
reportCheck(jobRoomApplicationStatus('open', 'sent', 'recorded', '2026-09-11 10:30:00') === 'Im Job-Room erfasst – Noch offen', 'A confirmed registration replaces the result placeholder with the localized result');
reportCheck(jobRoomApplicationStatus('open', 'interview', 'recorded', '2026-09-11 10:30:00', true) === 'Im Job-Room erfasst – Noch offen · Vorstellungsgespräch', 'An open Job-Room result and an interview are both visible in the report');
reportCheck(jobRoomApplicationStatus('hired', 'interview', 'recorded', '2026-09-11 10:30:00', true) === 'Im Job-Room erfasst – Anstellung · Vorstellungsgespräch', 'An interview does not replace a different Job-Room result');
reportCheck(jobRoomApplicationStatus('open', 'interview', 'not_recorded', '2026-09-11 10:30:00', true) === 'applications.job_room_not_recorded', 'An interview alone does not claim a Job-Room registration');
reportCheck(jobRoomApplicationStatus('open', 'interview', 'recorded', null, true) === 'applications.job_room_not_recorded', 'An interview without an application date does not claim a Job-Room registration');
reportCheck(!str_contains(jobRoomApplicationStatus('open', 'sent', 'recorded', '2026-09-11 10:30:00'), ':result'), 'No raw result placeholder reaches the report');
reportCheck(str_contains($source, "job_room_result'=>tr('applications.job_room_status')") && str_contains($source, "job_room_registration'=>tr('applications.job_room_registration')"), 'Job-Room report columns have user-facing labels instead of technical DB labels');
reportCheck(str_contains($source, "\$row['applied_at'] ?? null") && str_contains($source, "\$row['job_room_registration'] ?? null"), 'Report status uses both the application date and the actual registration state');
reportCheck(str_contains($source, "!empty(\$row['job_room_interview'])"), 'Report status uses the stored Job-Room interview flag');

$optionStart = strpos($source, 'function reportFieldOptions(string $base): array');
$optionEnd = strpos($source, 'function reportDefaultColumns(string $base): array', $optionStart);
$options = substr($source, $optionStart, $optionEnd - $optionStart);
foreach (['raw_import_data', 'job_room_registration', 'linkedin_url', 'sha256', 'completed_at', 'latitude', 'longitude'] as $field) {
    reportCheck(str_contains($options, "'{$field}'"), "Report field {$field} is available");
}
foreach (['owner_user_id', 'user_id', 'deleted_at', 'storage_path', 'active_unique'] as $field) {
    reportCheck(!str_contains($options, "'{$field}'"), "Internal field {$field} is excluded");
}

foreach (['FROM applications a', 'FROM companies WHERE', 'FROM contacts c', 'FROM user_documents d', 'FROM calendar_events ce', 'FROM jobs j'] as $queryFragment) {
    reportCheck(str_contains($source, $queryFragment), "Complete report dataset includes {$queryFragment}");
}
reportCheck(str_contains($source, 'COALESCE(c.job_id,a.job_id) job_id') && str_contains($source, 'COALESCE(j.title,aj.title) job'), 'A contact inherits the job relation from its linked application');
reportCheck(str_contains($source, 'richTextPlain((string)$value)'), 'Rich database content is exported as readable plain text');
reportCheck(str_contains($source, "JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT"), 'Field metadata embedded in JavaScript is safely encoded');

echo "Report field contract passed.\n";
