<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../sql/jobsearch/01_schema.sql');
$migration = file_get_contents(__DIR__ . '/../sql/jobsearch/19_application_rejection_reason.sql');

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}

foreach (['jobRoomStreetParts', 'applicationRejectionReason', 'jobRoomHelperFields'] as $name) {
    if (!preg_match('/^function ' . $name . '\(.*?(?=^function |\z)/ms', $source, $match)) {
        throw new RuntimeException('Missing function ' . $name);
    }
    eval(trim($match[0]));
}

function tr(string $key): string { return $key; }
function displayDateTime(string $value, array $user, bool $seconds = true): string { return $value; }
function jobRoomApplicationMethod(?string $value): string { return (string)$value; }
function jobRoomCompanyName(array $row): string { return (string)($row['company_name'] ?? ''); }
function jobRoomCountryLabel(?string $value): string { return (string)$value; }
function jobRoomWorkloadLabel(array $row): string { return ''; }
function jobRoomApplicationResult(?string $result, ?string $status, ?string $registration): string { return (string)$result; }

check(jobRoomStreetParts('Industriestrasse 22') === ['Industriestrasse', '22'], 'Swiss street and house number split');
check(jobRoomStreetParts('Rue du 1er Mars 22A') === ['Rue du 1er Mars', '22A'], 'Street name containing a number preserved');
check(jobRoomStreetParts('22A Industriestrasse') === ['Industriestrasse', '22A'], 'Leading house number split');
check(jobRoomStreetParts('Industriestrasse') === ['Industriestrasse', ''], 'No house number invented');

$row = ['applied_at'=>'2026-09-18 09:00:00','channel'=>'email','company_name'=>'Test AG',
    'address_line1'=>'Industriestrasse 22','address_line2'=>'Postfach 7','postal_code'=>'2545',
    'city'=>'Selzach','country_code'=>'CH','application_status'=>'rejected',
    'rejection_reason'=>'Stelle anderweitig besetzt','job_room_result'=>'rejected'];
$fields = jobRoomHelperFields($row, []);
$keys = array_keys($fields);
$streetIndex = array_search('job_room_helper.field.street', $keys, true);
check(array_slice($keys, $streetIndex, 4) === [
    'job_room_helper.field.street','job_room_helper.field.house_number',
    'job_room_helper.field.postal_code','job_room_helper.field.city'
], 'Address fields are individually copyable in requested order');
check($fields['job_room_helper.field.street'] === 'Industriestrasse'
    && $fields['job_room_helper.field.house_number'] === '22'
    && $fields['job_room_helper.field.postal_code'] === '2545'
    && $fields['job_room_helper.field.city'] === 'Selzach', 'Address values remain separate');
check($fields['applications.rejection_reason'] === 'Stelle anderweitig besetzt', 'Rejection reason is copyable in helper');
unset($row['rejection_reason']);
$row['application_status'] = 'sent';
check(!array_key_exists('applications.rejection_reason', jobRoomHelperFields($row, [])), 'Reason is hidden for non-rejected applications');

check(applicationRejectionReason('sent', '') === null, 'Other statuses do not require a rejection reason');
check(applicationRejectionReason('rejected', str_repeat('x', 249)) === str_repeat('x', 249), '249 characters accepted');
foreach (['', '  ', str_repeat('x', 250)] as $invalid) {
    try {
        applicationRejectionReason('rejected', $invalid);
        throw new RuntimeException('Invalid rejection reason accepted');
    } catch (InvalidArgumentException) {
        // Expected.
    }
}
check(true, 'Blank and overlong rejection reasons rejected');

check(str_contains($source, "a.job_room_registration <> 'recorded'"), 'Helper query excludes recorded applications');
check(str_contains($source, 'job_room_registration <> "recorded" ORDER BY month_key DESC'), 'Month list excludes recorded applications');
check(str_contains($source, 'a.rejection_reason, a.job_room_result'), 'Helper selects saved reason');
check(str_contains($source, 'data-application-rejection-reason') && str_contains($source, 'maxlength="249"'), 'Application form has conditional limited multi-line field');
check(str_contains($source, 'data-mail-rejection-reason') && str_contains($source, "applicationRejectionReason(\$newStatus"), 'Mail activity enforces reason before writing');
check(str_contains($source, "applicationRejectionReason(\$status") && str_contains($source, 'rejection_reason=?, channel=?'), 'Manual and autosave enforce and persist reason');
check(str_contains($source, 'a.primary_contact_id, a.status, a.rejection_reason, a.job_room_result')
    && str_contains($source, "e(\$applicationEdit['rejection_reason'] ?? '')"),
    'Application edit reloads the saved rejection reason into the form');
check(str_contains($source, "\$table === 'applications'") && str_contains($source, 'applicationRejectionReason($effectiveStatus, $effectiveReason)'), 'Admin AI write enforces reason');
check(str_contains($source, "'rejection_reason'=>tr('applications.rejection_reason')") && str_contains($source, 'a.status, a.rejection_reason, a.applied_at'), 'Reports can select the new field');
check(str_contains($schema, 'rejection_reason VARCHAR(249) NULL') && str_contains($migration, 'ADD COLUMN rejection_reason VARCHAR(249) NULL'), 'Base and migration schemas match');

echo "Job-Room and rejection-reason tests passed.\n";
