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

reportCheck(preg_match('/function limitReportColumns\(.*?^\}/ms', $source, $match) === 1, 'Report column limiter is isolated for regression testing');
eval($match[0]);
$allowed = array_combine(array_map(static fn(int $number): string => 'field_' . $number, range(1, 20)), range(1, 20));
$selection = array_merge(['field_1', 'unknown', 'field_1'], array_map(static fn(int $number): string => 'field_' . $number, range(2, 20)));
$limited = limitReportColumns($allowed, $selection, 12);
reportCheck(count($limited) === 12, 'At most twelve report fields are accepted');
reportCheck($limited[0] === 'field_1' && $limited[11] === 'field_12', 'Report fields retain their selected order');
reportCheck(count(array_unique($limited)) === count($limited) && !in_array('unknown', $limited, true), 'Unknown and duplicate report fields are rejected');

reportCheck(str_contains($source, 'function reportColumnLimit(): int') && str_contains($source, 'return 12;'), 'The report field maximum is explicit');
reportCheck(str_contains($source, 'reportSelectedColumns($base, $_POST[\'report_columns\'] ?? [])'), 'The server validates saved report columns');
reportCheck(str_contains($source, 'reportSelectedColumns($base, $requestedColumns)'), 'The server validates exported report columns');
reportCheck(str_contains($source, 'data-report-column-picker') && str_contains($source, 'data-report-column-count'), 'The editor displays the full field picker and live selection count');
reportCheck(str_contains($source, "base.addEventListener('change'") && str_contains($source, "picker.addEventListener('change',enforceLimit)"), 'Changing the data source refreshes fields and enforces the maximum immediately');

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
reportCheck(str_contains($source, 'richTextPlain((string)$value)'), 'Rich database content is exported as readable plain text');
reportCheck(str_contains($source, "JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT"), 'Field metadata embedded in JavaScript is safely encoded');

echo "Report field contract passed.\n";
