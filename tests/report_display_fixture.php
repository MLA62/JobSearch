<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tr(string $key, ?string $locale = null, array $replace = []): string
{
    return match ($key) {
        'common.no_entries' => 'Keine Einträge',
        'common.actions' => 'Aktionen',
        'reports.open_record' => 'Datensatz öffnen',
        'reports.filters' => 'Resultate filtern',
        'reports.filter_from' => 'Von',
        'reports.filter_to' => 'Bis',
        'reports.filter_min' => 'Minimum',
        'reports.filter_max' => 'Maximum',
        'sf.filter' => 'Filter',
        'sf.apply' => 'Anwenden',
        'sf.reset' => 'Filter zurücksetzen',
        'sf.contains_placeholder' => 'Enthält ' . (string)($replace['field'] ?? ''),
        'common.all' => 'Alle',
        'common.table' => 'Tabelle',
        'common.cards' => 'Karten',
        default => $key,
    };
}

if ((string)($_GET['layout'] ?? '') === '1') {
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/app.css"></head><body><main class="container"><div class="reports-layout"><section class="panel report-editor-panel" data-layout-editor><h2>Report speichern</h2></section><section class="panel report-saved-panel" data-layout-saved><h2>Gespeicherte Reports</h2></section></div></main></body></html><?php
    exit;
}

$source = (string)file_get_contents(__DIR__ . '/../public/index.php');
$start = strpos($source, 'function reportViewUrl(');
$end = strpos($source, 'function calendarViewOptions(', $start);
if ($start === false || $end === false) {
    http_response_code(500);
    exit('Report renderer not found');
}
eval(substr($source, $start, $end - $start));

$allowed = ['table','list','cards','preview','calendar_day','calendar_week','calendar_month'];
$requestedDisplayType = (string)($_GET['type'] ?? $_GET['report_as'] ?? '');
$displayType = in_array($requestedDisplayType, $allowed, true) ? $requestedDisplayType : 'table';
$columns = ['applied_at', 'match_score', 'job_room_result', 'title'];
$headers = ['Bewerbungsdatum', 'Match', 'Job-Room Status', 'Stelle'];
$rows = [
    ['11.09.2026', '85', 'Noch nicht im Job-Room erfasst', "Account Manager\nErste Zeile"],
    ['12.09.2026', '65', 'Im Job-Room erfasst – Noch offen', 'Projektleitung <script>alert(1)</script>'],
    ['13.09.2026', '45', 'Im Job-Room erfasst – Absage', 'Verkaufsberatung'],
    ['', '', '', 'Noch unvollständig'],
];
$meta = [
    ['calendar_day'=>'11.09.2026', 'calendar_week'=>'37 / 2026', 'calendar_month'=>'09/2026', 'record_url'=>'/?page=jobs&edit=11#new', 'filter_values'=>['applied_at'=>'2026-09-11 10:00:00','match_score'=>'85','job_room_result'=>'Noch nicht im Job-Room erfasst','title'=>'Account Manager']],
    ['calendar_day'=>'12.09.2026', 'calendar_week'=>'37 / 2026', 'calendar_month'=>'09/2026', 'record_url'=>'/?page=jobs&edit=12#new', 'filter_values'=>['applied_at'=>'2026-09-12 10:00:00','match_score'=>'65','job_room_result'=>'Im Job-Room erfasst – Noch offen','title'=>'Projektleitung']],
    ['calendar_day'=>'13.09.2026', 'calendar_week'=>'37 / 2026', 'calendar_month'=>'09/2026', 'record_url'=>'/?page=jobs&edit=13#new', 'filter_values'=>['applied_at'=>'2026-09-13 10:00:00','match_score'=>'45','job_room_result'=>'Im Job-Room erfasst – Absage','title'=>'Verkaufsberatung']],
    ['calendar_day'=>'—', 'calendar_week'=>'—', 'calendar_month'=>'—', 'record_url'=>'/?page=jobs&edit=14#new', 'filter_values'=>['applied_at'=>'','match_score'=>'','job_room_result'=>'','title'=>'Noch unvollständig']],
];
$definitions = reportViewFilterDefinitions($columns, $headers, $rows, $meta);
$filters = reportViewFilterState($columns, $_GET['report_filter'] ?? []);
[$rows, $meta] = reportViewApplyFilters($columns, $rows, $meta, $filters);
$filterQuery = $filters ? '&' . http_build_query(['report_filter'=>$filters]) : '';
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/app.css"></head><body><main class="container"><section class="panel" id="report-view" data-report-display-type="<?= e($displayType) ?>"><div class="actions report-view-switcher"><a class="button <?= $displayType === 'table' ? 'primary' : '' ?>" data-report-view-option="table" href="?type=table<?= e($filterQuery) ?>">Tabelle</a><a class="button <?= $displayType === 'cards' ? 'primary' : '' ?>" data-report-view-option="cards" href="?type=cards<?= e($filterQuery) ?>">Karten</a></div><?= reportViewFiltersHtml(7, $displayType, $definitions, $filters) ?><?= reportRowsHtml($headers, $rows, $displayType, $meta) ?></section></main></body></html>
