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

Das Deployment mit TOTP-Approval `b24d81f48591f82d663550e487ae27b3` ersetzte ausschließlich
`public_html/jobs.jema.business/index.php` und `public_html/jobs.jema.business/assets/app.css`.
Der produktive SHA-256 von `index.php` lautet
`4631f68e8210a0f7e71c7c717388d90f3060b917c3862fc1006f8b642e8a6d26`, jener von `app.css`
`549f36767ca7817f6689a6f24b7c3ec5096000ff57f5532d9560fe6744d61728`. Beide Dateien sind
bytegleich mit dem geprüften lokalen Stand. Die öffentliche Seite liefert HTTP 200, Version 2.4.12
und die vorgesehenen Sicherheitsheader.
