# Version 2.1.5 – Navigation nach Bewerbungsvorbereitung

Stand: 04.09.2026. Bereitstellungskandidat.

## Änderungen

- `Bewerbung vorbereiten` liefert der asynchronen Oberfläche das Ziel auf den erzeugten oder bereits vorhandenen Bewerbungsdatensatz ausdrücklich als JSON zurück.
- Nach Abschluss öffnet der Browser dieses Ziel und kehrt nicht mehr aufgrund einer uneindeutigen Fetch-Weiterleitung zur Jobseite zurück.
- Fehlerpfade führen weiterhin zum betroffenen Job und zeigen die vorhandene Fehlermeldung.

## Qualität und Deployment

PHP-Syntax, alle Vertragstests, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff werden vor dem Deployment geprüft. Nach TOTP-Freigabe wird ausschließlich `public_html/jobs.jema.business/index.php` ersetzt und anschließend verifiziert.
