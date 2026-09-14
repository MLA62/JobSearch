<?php
declare(strict_types=1);

$source = (string)file_get_contents(__DIR__ . '/../public/index.php');

function calendarPdfCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

$toolbarStart = strpos($source, '<div class="calendar-toolbar">');
$toolbarEnd = strpos($source, '</div>', $toolbarStart === false ? 0 : $toolbarStart);
$toolbar = $toolbarStart === false || $toolbarEnd === false ? '' : substr($source, $toolbarStart, $toolbarEnd - $toolbarStart);
calendarPdfCheck($toolbar !== '', 'Calendar toolbar is present');
calendarPdfCheck(str_contains($toolbar, '>ICS</a><a class="button"') && str_contains($toolbar, '>PDF</a>'), 'PDF button is directly beside ICS');
calendarPdfCheck(str_contains($toolbar, 'page=export_pdf&amp;type=calendar'), 'PDF button opens the calendar PDF export');
calendarPdfCheck(str_contains($toolbar, 'view=<?= e($calendarView) ?>') && str_contains($toolbar, 'date=<?= e($anchor->format(\'Y-m-d\')) ?>'), 'PDF link preserves current view and date');

$calendarHandler = strpos($source, "if (\$type === 'calendar') {");
calendarPdfCheck($calendarHandler !== false, 'Calendar PDF export handler exists');
$calendarHandlerSource = substr($source, $calendarHandler === false ? 0 : $calendarHandler, 2500);
calendarPdfCheck(str_contains($calendarHandlerSource, 'calendarRange($calendarView, $anchor)'), 'PDF export uses the visible calendar range');
calendarPdfCheck(str_contains($calendarHandlerSource, "sfState('calendar_agenda'") && str_contains($calendarHandlerSource, 'sfApplyRows($calendarEvents'), 'Agenda PDF uses active agenda filters and sorting');
calendarPdfCheck(str_contains($calendarHandlerSource, "'kalender-' . \$calendarView . '-' . \$anchor->format('Y-m-d') . '.pdf'"), 'PDF filename identifies view and date');
calendarPdfCheck(str_contains($calendarHandlerSource, 'calendarPdfRows($calendarEvents, $currentUser)'), 'PDF receives the calendar result rows');

calendarPdfCheck(preg_match('/function calendarPdfRows\(array \$events, array \$currentUser\): array\s*\{.*?^\}/ms', $source, $match) === 1, 'Calendar PDF row conversion can be isolated');
if (!function_exists('displayDateTime')) {
    function displayDateTime(string $value, array $user): string
    {
        return $value . '|' . ($user['timezone'] ?? '');
    }
}
eval($match[0]);
$rows = calendarPdfRows([[
    'starts_at' => '2026-09-14 09:30:00',
    'title' => 'Vorstellungsgespräch',
    'type' => 'Termin',
    'status' => 'Geplant',
    'meta' => 'Firma · Kontakt',
]], ['timezone' => 'Europe/Zurich']);
calendarPdfCheck($rows === [['2026-09-14 09:30:00|Europe/Zurich', 'Vorstellungsgespräch', 'Termin', 'Geplant', 'Firma · Kontakt']], 'Calendar events map to the five visible PDF columns without data loss');

echo "Calendar PDF contract passed.\n";
