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
check(str_contains($css, '.sf-form { position: fixed;'), 'Filters are not clipped by table scrolling');
check(str_contains($source, '<a class="menu-trigger" href="/?page=calendar&view=agenda">'), 'Calendar is a direct menu link');
check(!str_contains($source, "class=\"menu-trigger\"><?= e(tr('nav.planning'))"), 'No one-item Planning submenu');
check(str_contains($source, "'companies.direct_none' => [\n            'de-CH' => 'Direkt'"), 'Technical none label replaced');
check(str_contains($source, 'class="job-room-details"') && str_contains($source, "querySelector('.job-room-details').hidden=!this.checked"), 'Job-Room detail visibility follows checkbox');
check(str_contains($css, '.job-room-details[hidden] { display: none; }'), 'Hidden Job-Room details do not occupy space');
check(str_contains($source, "sfHeader('applications','latest_workflow_at'"), 'Workflow date retains independent sort and filter');
check(str_contains($source, "sfHeader('jobs','created_at'"), 'Job date retains independent sort and filter');
check(str_contains($source, "if ($" . "action === 'apply_workflow_migration')"), 'Migration requires explicit reviewed action');
echo "All table layout checks passed.\n";
