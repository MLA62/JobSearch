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

## Deployment-Nachweis

- Externe Freigabe: `ca7adda05026203024a383f57ea64204`
- Produktiver SHA-256: `1d454558b88227cba876c2c7f6f5ced007ab48265123c1516274d977a7e59eb7`
- Sicherung der Vorgängerversion:
  `approval.lauber.online/storage/file_backups/20260914_150129_c41f3127_public_html_jobs.jema.business_index.php`
- Live: HTTP 200, Version 2.4.22, vollständige Sicherheitsheader und keine neuen PHP-Logeinträge.
- Antwortzeit nach Initialisierung: 156–184 ms, Mittelwert 168 ms; rund 79 % unter dem Vorhermittel.
- Das Deployment erfolgte ohne Betriebsunterbruch und ohne pauschale Datenbankreorganisation.
