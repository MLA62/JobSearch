# Release 2.4.22

Stand: 2026-09-14.

## Änderungen

- Vollständige Schema-, Übersetzungs- und Seed-Prüfungen laufen nur noch einmal pro Release.
- Datenbanklocks verhindern parallele Initialisierung; Marker werden erst nach erfolgreichem Lauf gesetzt.
- Hilfe-, Sicherheits- und KI-Speichermigrationen besitzen einen gemeinsamen einmaligen Wartungslauf.
- Stellenportal-Seeds werden beim Öffnen von Verwaltungs- und Suchseiten nicht mehr geschrieben.
- Rollen und Orte verwenden gültige mehrzeilige Regulärausdrücke; wiederholte Warnungen entfallen.
- Das manuelle Speichern von Suchkriterien lädt die Profileinstellungen vor ihrer Verwendung.

## Betrieb

Die rund 10,6 MiB grosse Produktionsdatenbank benötigt nach den vorliegenden Befunden keine
pauschale Reorganisation. Der erste Request nach dem Deployment führt einmalig die geprüfte
Releaseinitialisierung aus und kann deshalb länger dauern. Danach besteht der Bootstrap nur noch
aus den beiden indexierten Markerprüfungen. Das Deployment selbst erfordert keine geplante
Betriebsunterbrechung.

## Prüfung

PHP-Syntax, der neue Laufzeit-Performancevertrag, alle 41 PHP- und zehn Chromium-Testdateien,
die Hilfe- und Referenzgeneratoren, 91 Markdown-Dateien mit 61 lokalen Verweisen sowie
`git diff --check` wurden vor dem Deployment erfolgreich ausgeführt.
