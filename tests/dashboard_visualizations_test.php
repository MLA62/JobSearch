<?php
declare(strict_types=1);

function tr(string $key): string { return $key; }
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value): string { return strtolower($value); }
}

$source = (string)file_get_contents(__DIR__ . '/../public/index.php');
$start = strpos($source, 'function jobStatusForApplicationStatus(');
$end = strpos($source, 'function applicationWorkflowDateSql(', $start);
if ($start === false || $end === false) { throw new RuntimeException('Dashboard helper block not found.'); }
$helpers = substr($source, $start, $end - $start);
$helpers = str_replace("__DIR__ . '/assets/data/", "dirname(__DIR__) . '/public/assets/data/", $helpers);
eval($helpers);

function dashboardCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS {$message}\n";
}

$segments = dashboardChartSegments([
    ['segment_key'=>'open','segment_count'=>6],
    ['segment_key'=>'interview','segment_count'=>3],
    ['segment_key'=>'closed','segment_count'=>0],
], ['open'=>'Offen','interview'=>'Gespräch']);
dashboardCheck(count($segments) === 2 && $segments[0]['label'] === 'Offen' && $segments[1]['count'] === 3, 'Pie segments contain labelled non-zero categories');
$gradient = dashboardPieGradient($segments);
dashboardCheck(str_starts_with($gradient, 'conic-gradient(') && str_contains($gradient, '66.6667%') && str_contains($gradient, '100.0000%'), 'Pie gradient reflects the category shares');
dashboardCheck(dashboardPieGradient([]) === 'conic-gradient(#d7dee8 0 100%)', 'Empty pie has a deterministic neutral fill');

$expectedJobStatuses = [
    'sent'=>'applied','confirmed'=>'applied','interview'=>'interview','assessment'=>'interview',
    'offer'=>'offer','accepted'=>'offer','rejected'=>'rejected','withdrawn'=>'closed','closed'=>'closed',
];
foreach ($expectedJobStatuses as $applicationStatus=>$jobStatus) {
    dashboardCheck(jobStatusForApplicationStatus($applicationStatus) === $jobStatus, "Application status {$applicationStatus} maps to job status {$jobStatus}");
}
dashboardCheck(jobStatusForApplicationStatus('draft') === null && jobStatusForApplicationStatus('ready') === null, 'Draft preparation does not falsely mark a job as applied');

$catalog = dashboardSwissPostalCentroids();
dashboardCheck(count((array)($catalog['places'] ?? [])) > 4000 && count((array)($catalog['postcodes'] ?? [])) > 3000, 'Official Swiss postcode centroids are bundled');
$heat = dashboardCompanyHeatPoints([
    ['city'=>'Bern','postal_code'=>'3005','country_code'=>'CH','latitude'=>null,'longitude'=>null],
    ['city'=>'Bern','postal_code'=>'3006','country_code'=>'CH','latitude'=>null,'longitude'=>null],
    ['city'=>'Zürich','postal_code'=>'8004','country_code'=>'CH','latitude'=>null,'longitude'=>null],
    ['city'=>'Paris','postal_code'=>'75001','country_code'=>'FR','latitude'=>48.86,'longitude'=>2.35],
]);
dashboardCheck(count($heat) === 2 && $heat[0]['label'] === 'Bern' && $heat[0]['count'] === 2, 'Heat map aggregates companies by place and excludes foreign records');
dashboardCheck($heat[0]['x'] >= 0 && $heat[0]['x'] <= 1000 && $heat[0]['y'] >= 0 && $heat[0]['y'] <= 640, 'Heat map projects Swiss coordinates into the SVG');
dashboardCheck(strlen(dashboardSwissOutlinePath()) > 3000, 'Official Switzerland outline is bundled');

foreach (['dashboard-charts','dashboard-pie','dashboard-heatmap','dashboard-swiss-map','dashboard-heat-list'] as $contract) {
    dashboardCheck(str_contains($source, $contract), "Dashboard markup includes {$contract}");
}
dashboardCheck(str_contains($source, 'GROUP BY status ORDER BY status') && str_contains($source, "CASE WHEN is_intermediary=1 THEN 'intermediary' ELSE 'direct' END"), 'Dashboard queries aggregate job, application and company categories');
dashboardCheck(substr_count($source, 'UPDATE jobs SET status=') === 1, 'All automatic job-status writes use the central synchronizer');
dashboardCheck(str_contains($source, 'syncJobStatusFromApplication($db, $userId, $applicationId);'), 'Workflow synchronization checks the related job status');

echo "Dashboard visualization contract passed.\n";
