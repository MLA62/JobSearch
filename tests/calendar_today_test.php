<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__.'/../public/index.php');
$start = strpos($source, "<?php elseif(in_array(\$calendarView, ['day','workweek','week'], true)): ?>");
$end = strpos($source, '</section><?php $calendarFormEvent', $start);
$template = substr($source, $start, $end - $start);
$template = preg_replace('/elseif/', 'if', $template, 1);
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function tr($key) { return $key; }
$weekdayNames = []; $weekNo = '36'; $hours = [8,9,10]; $eventsByDate = [];
$isDayEntry = static fn($event) => false;
$renderEvent = static fn($event) => '';
$newEntryUrl = static fn($value) => '#';
$calendarToday = '2026-09-03';
$anchor = new DateTimeImmutable('2026-09-03');
foreach (['month'=>[31,6], 'workweek'=>[31,4], 'week'=>[31,6]] as $calendarView => $range) {
    $rangeStart = new DateTimeImmutable('2026-08-31');
    $rangeEnd = $rangeStart->modify('+'.$range[1].' days');
    ob_start(); eval('?>'.$template); $html = ob_get_clean();
    $expected = $calendarView === 'month' ? 1 : 5;
    if (substr_count($html, 'is-today') !== $expected || substr_count($html, 'aria-current="date"') !== 1) {
        throw new RuntimeException('Incorrect current-day highlight: '.$calendarView);
    }
    echo "PASS $calendarView highlights only today, including its time cells\n";
}
$calendarToday = '2026-10-03';
ob_start(); eval('?>'.$template); $html = ob_get_clean();
if (str_contains($html, 'is-today')) { throw new RuntimeException('Selected date must not masquerade as today'); }
echo "PASS no highlight outside current date range\n";
