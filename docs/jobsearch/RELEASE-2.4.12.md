# Version 2.4.12 – Beziehungsfilter und Report-Anzeigearten

Stand: 12.09.2026.

## Änderungen

- Die Firmenspalte «Links» filtert Jobs, Bewerbungen und Kontakte jeweils nach vorhandenen oder
  fehlenden Einträgen.
- Mehrere gewählte Beziehungsbedingungen gelten gleichzeitig.
- Die Filter verwenden exakt dieselben aktiven, benutzerisolierten Beziehungen wie die sichtbaren
  Zähler und keine fachfremde Textsuche mehr.
- Ausschließlich fest im Code definierte SQL-Bedingungen dürfen ausgeführt werden.
- Die noch nicht produktiv ausgerollte Korrektur 2.4.11 ist enthalten: Gespeicherte Report-
  Anzeigearten rendern nun tatsächlich Tabelle, Liste, Karten, Vorschau sowie Kalendergruppen.

## Prüfung

Alle 36 PHP-Testdateien sind erfolgreich. Darin enthalten sind 3'952 Inhaltsprüfungen des
Hilfesystems, 1'329 Prüfungen der Hilfe-Seeds sowie die Verträge für Filtersemantik,
SQL-Sicherheit, Report-Renderer und Datenbeziehungen. Alle acht Chromium-Tests sind erfolgreich.
Der Links-Filter wurde bei 390, 1'000 und 2'048 Pixel Breite mit allen sechs Optionen geprüft; der
Reporttest rendert weiterhin jede der sieben Anzeigearten bei zwei Bildschirmbreiten. Zusätzlich
wurden 81 Markdown-Dateien und 61 lokale Links geprüft.

## Deployment

Das Deployment ersetzt ausschließlich `public_html/jobs.jema.business/index.php` und
`public_html/jobs.jema.business/assets/app.css`. Produktive Hashes und HTTP-Prüfung werden nach der
externen TOTP-Freigabe ergänzt.
