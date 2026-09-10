<?php
declare(strict_types=1);

$source = file_get_contents($argv[1] ?? __DIR__ . '/../public/index.php');

function calendarSyncCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK: {$message}\n";
}

if (!preg_match('/^function syncGoogleCalendarEventsLocked\(.*?(?=^function |\z)/ms', $source, $lockedMatch)) {
    throw new RuntimeException('Missing syncGoogleCalendarEventsLocked');
}
$lockedSource = $lockedMatch[0];
calendarSyncCheck(!str_contains($lockedSource, 'workflow_calendar_v6'), 'Google sync is not blocked by the separate v6 cleanup marker');
calendarSyncCheck(str_contains($lockedSource, 'calendarExportRows(calendarEventRows('), 'Google sync still uses the reviewed export filter');

if (!preg_match('/^function syncCalendarAutomatically\(.*?(?=^function |\z)/ms', $source, $automaticMatch)) {
    throw new RuntimeException('Missing syncCalendarAutomatically');
}
$automaticSource = $automaticMatch[0];
calendarSyncCheck(str_contains($automaticSource, 'UPDATE user_google_calendar_settings SET last_error=?'), 'Automatic sync failures are persisted for diagnosis');
calendarSyncCheck(str_contains($source, "header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate')"), 'Subscribed ICS feed is never served from an application cache');
calendarSyncCheck(str_contains($source, "(int)(\$old['primary_contact_id'] ?? 0) !== \$primaryContactId"), 'Changing an application contact triggers external calendar refresh');

foreach (['companies.saved', 'companies.deleted', 'jobs.saved', 'jobs.deleted', 'contacts.updated', 'contacts.deleted', 'contact_log.deleted', 'applications.deleted'] as $flashKey) {
    $needle = "syncCalendarAutomatically(\$db, \$config";
    $flashAt = strpos($source, "flash(tr('flash.{$flashKey}')");
    if ($flashKey === 'applications.deleted') {
        $flashAt = strpos($source, "flash(tr('applications.deleted')");
    }
    calendarSyncCheck($flashAt !== false && strrpos(substr($source, 0, $flashAt), $needle) >= $flashAt - 350, "{$flashKey} refreshes the external calendar");
}

calendarSyncCheck(substr_count($source, 'UPDATE user_google_calendar_settings SET last_error=?, updated_at=NOW() WHERE user_id=?') >= 2, 'Manual and automatic sync failures remain visible');

echo "All external calendar synchronization tests passed.\n";
