# Version 2.1.5 – Navigation nach Bewerbungsvorbereitung

Stand: 04.09.2026. Deployed.

## Änderungen

- `Bewerbung vorbereiten` liefert der asynchronen Oberfläche das Ziel auf den erzeugten oder bereits vorhandenen Bewerbungsdatensatz ausdrücklich als JSON zurück.
- Nach Abschluss öffnet der Browser dieses Ziel und kehrt nicht mehr aufgrund einer uneindeutigen Fetch-Weiterleitung zur Jobseite zurück.
- Fehlerpfade führen weiterhin zum betroffenen Job und zeigen die vorhandene Fehlermeldung.

## Qualität und Deployment

PHP-Syntax, alle 28 Vertragstests, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff wurden
geprüft. Nach TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` aus
dem geprüften Release-Commit ersetzt. Die Produktionsdatei entspricht exakt dem lokalen
Release-Stand. Die öffentliche Seite liefert HTTP 200, zeigt Version 2.1.5 und das KI-Modal und
enthält keinen sichtbaren PHP-Laufzeitfehler.
