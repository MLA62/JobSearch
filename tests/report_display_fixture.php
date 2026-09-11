<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tr(string $key, ?string $locale = null, array $replace = []): string
{
    return $key === 'common.no_entries' ? 'Keine Einträge' : $key;
}

$source = (string)file_get_contents(__DIR__ . '/../public/index.php');
$start = strpos($source, 'function reportCalendarDisplayMeta(');
$end = strpos($source, 'function calendarViewOptions(', $start);
if ($start === false || $end === false) {
    http_response_code(500);
    exit('Report renderer not found');
}
eval(substr($source, $start, $end - $start));

$allowed = ['table','list','cards','preview','calendar_day','calendar_week','calendar_month'];
$displayType = in_array((string)($_GET['type'] ?? ''), $allowed, true) ? (string)$_GET['type'] : 'table';
$headers = ['Stelle', 'Firma', 'Beschreibung'];
$rows = [
    ['Account Manager', 'Beispiel AG', "Erste Zeile\nZweite Zeile"],
    ['Projektleitung', 'Muster GmbH', 'Text mit <script>alert(1)</script>'],
];
$meta = [
    ['calendar_day'=>'11.09.2026', 'calendar_week'=>'37 / 2026', 'calendar_month'=>'09/2026'],
    ['calendar_day'=>'12.09.2026', 'calendar_week'=>'37 / 2026', 'calendar_month'=>'09/2026'],
];
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/app.css"></head><body><main class="container"><section class="panel" id="report-view" data-report-display-type="<?= e($displayType) ?>"><?= reportRowsHtml($headers, $rows, $displayType, $meta) ?></section></main></body></html>
