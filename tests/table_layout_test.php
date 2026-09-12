<?php
declare(strict_types=1);
$source = file_get_contents($argv[1] ?? __DIR__.'/../public/index.php');
$css = file_get_contents(__DIR__.'/../public/assets/layout.css');
$js = file_get_contents(__DIR__.'/../public/assets/layout.js');
function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
    echo "OK: $message\n";
}
check(!str_contains($css, 'is-records') && !str_contains($js, 'is-records'), 'No automatic record-card mode');
check(str_contains($css, '.table-wrap .layout-table td { display: table-cell;'), 'Every data cell remains a table cell');
check(str_contains($css, '.panel.table-wrap,'), 'Mobile panel overflow rule is overridden');
check(str_contains($css, 'white-space: nowrap;'), 'Compact single-line cells');
check(str_contains($css, '.layout-table td.link-list > a {') && str_contains($css, 'display: block;') && str_contains($css, '.layout-table td.link-list > a + a { margin-top: 4px; }'), 'Company relation links use one row per complete label');
check(str_contains($css, '.sf-form { position: fixed;'), 'Filters are not clipped by table scrolling');
check(str_contains($source, '<a class="menu-trigger" href="/?page=calendar&view=agenda">'), 'Calendar is a direct menu link');
check(!str_contains($source, "class=\"menu-trigger\"><?= e(tr('nav.planning'))"), 'No one-item Planning submenu');
check(str_contains($source, "'companies.direct_none' => [\n            'de-CH' => 'Direkt'"), 'Technical none label replaced');
check(str_contains($source, 'class="job-room-details"') && str_contains($source, "querySelector('.job-room-details').hidden=!this.checked"), 'Job-Room detail visibility follows checkbox');
check(str_contains($css, '.job-room-details[hidden] { display: none; }'), 'Hidden Job-Room details do not occupy space');
check(str_contains($source, "sfHeader('applications','latest_workflow_at'"), 'Workflow date retains independent sort and filter');
check(str_contains($source, "sfHeader('jobs','created_at'"), 'Job date retains independent sort and filter');
check(str_contains($source, "\$companyApplicationCountExpr = '(SELECT COUNT(*) FROM applications a JOIN jobs j2 ON j2.id=a.job_id AND j2.owner_user_id=c.owner_user_id AND j2.deleted_at IS NULL WHERE a.user_id=c.owner_user_id AND a.deleted_at IS NULL AND j2.company_id=c.id)'"), 'Company application count includes only owned active direct applications');
check(!str_contains($source, '(j2.company_id=c.id OR a.intermediary_company_id=c.id)'), 'Intermediary relation is not counted as a company application');
check(str_contains($source, "'jobs_with'=>tr('sf.with_entries'") && str_contains($source, "'applications_without'=>tr('sf.without_entries'") && str_contains($source, "'contacts_with'=>tr('sf.with_entries'"), 'Links filter offers presence and absence criteria for all three relations');
check(preg_match('/function sfApplySql\(.*?^\}/ms', $source, $sfApplySqlMatch) === 1, 'SQL field filtering is isolated for regression testing');
eval($sfApplySqlMatch[0]);
$filterTypes = '';
$filterValues = [];
$filterSql = sfApplySql(
    ['filters'=>['links'=>['jobs_without','applications_with','contacts_without']]],
    ['links'=>[
        'expr'=>'total_count',
        'choices'=>['jobs_without'=>'Jobs ohne','applications_with'=>'Bewerbungen mit','contacts_without'=>'Kontakte ohne'],
        'choice_clauses'=>['jobs_without'=>'job_count = 0','applications_with'=>'application_count > 0','contacts_without'=>'contact_count = 0'],
    ]],
    $filterTypes,
    $filterValues
);
check($filterSql === ' AND ((job_count = 0) AND (application_count > 0) AND (contact_count = 0))', 'Selected relation criteria are combined and applied independently');
check($filterTypes === '' && $filterValues === [], 'Compound relation filters cannot inject SQL bind values');
check(str_contains($source, 'JOIN jobs j ON j.id=a.job_id AND j.owner_user_id=a.user_id AND j.deleted_at IS NULL JOIN companies c ON c.id=j.company_id AND c.owner_user_id=a.user_id AND c.deleted_at IS NULL'), 'Application lists include only owned active jobs and companies');
check(str_contains($source, "if(\$appCompanyFilter>0){ \$appSql.=' AND j.company_id=?';"), 'Company application link filters the same direct relation as its count');
check(str_contains($source, "if ($" . "action === 'apply_workflow_migration')"), 'Migration requires explicit reviewed action');
check(str_contains($source, "a.user_id=? AND a.deleted_at IS NULL AND a.applied_at IS NOT NULL"), 'Job-Room excludes applications without an application date');
check(str_contains($source, 'DATE_FORMAT(applied_at, "%Y-%m") month_key') && str_contains($source, 'deleted_at IS NULL AND applied_at IS NOT NULL ORDER BY month_key DESC'), 'Job-Room month filter is based only on actual application dates');
echo "All table layout checks passed.\n";
