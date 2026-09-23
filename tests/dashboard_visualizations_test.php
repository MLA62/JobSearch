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
foreach (['sfSessionKey','sfState','sfApplySql'] as $name) {
    if (!preg_match('/^function ' . $name . '\\(.*?(?=^function |\\z)/ms', $source, $match)) { throw new RuntimeException('Missing ' . $name); }
    eval(trim($match[0]));
}

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
dashboardCheck(dashboardFilterUrl('jobs','jobs','status','open',true) === '/?page=jobs&sf_context=jobs&sf_dashboard=1&sf_field=status&sf_filter_multi%5B0%5D=open', 'Chart status link targets one list filter');
dashboardCheck(str_contains(dashboardFilterUrl('companies','companies','city','Zürich'), 'sf_filter=Z%C3%BCrich'), 'Heat-map place link safely encodes the exact city');

$_SESSION = ['sf_jobs'=>['filters'=>['title'=>'alt'],'sort'=>['field'=>'title','dir'=>'desc']]];
$_GET = ['sf_context'=>'jobs','sf_dashboard'=>'1','sf_field'=>'status','sf_filter_multi'=>['open']];
$dashboardState = sfState('jobs', ['title'=>['expr'=>'j.title'],'status'=>['expr'=>'j.status','choices'=>['open'=>'Offen']]], ['sort'=>'title','dir'=>'asc']);
dashboardCheck($dashboardState['filters'] === ['status'=>['open']] && $dashboardState['sort']['dir'] === 'asc', 'Dashboard link replaces stale list filters with its selected value');
$types = 'i'; $values = [1];
$citySql = sfApplySql(['filters'=>['city'=>'Bern']], ['city'=>['expr'=>'c.city','filter_mode'=>'exact']], $types, $values);
dashboardCheck(str_contains($citySql, 'c.city = ?') && $values === [1,'Bern'], 'Place link uses an exact city filter');

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
dashboardCheck(str_contains($source, 'class="dashboard-chart-link"') && str_contains($source, 'class="dashboard-heat-link"'), 'Every legend value and map bubble is linked');
dashboardCheck(str_contains($source, 'style="--dashboard-jobs-color:<?= e(dashboardChartPalette()[0]) ?>"'), 'Heat-map bubbles use the first Jobs chart palette colour');
dashboardCheck(!preg_match('/class="dashboard-heat-point"[^>]*opacity=/', $source), 'Heat-map bubble cores keep the exact chart colour without transparency');
dashboardCheck(str_contains($source, 'foreach($companyHeatPoints as $point)') && !str_contains($source, 'array_slice($companyHeatPoints,0,10)'), 'The scrollable place list includes every mapped place');
dashboardCheck(!str_contains($source, '<div class="stats">'), 'Redundant dashboard summary cards are removed');
dashboardCheck(substr_count($source, 'UPDATE jobs SET status=') === 1, 'All automatic job-status writes use the central synchronizer');
dashboardCheck(str_contains($source, 'syncJobStatusFromApplication($db, $userId, $applicationId);'), 'Workflow synchronization checks the related job status');

echo "Dashboard visualization contract passed.\n";
