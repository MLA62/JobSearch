# Version 2.1.7 – gelöschte Datensätze blockieren keine Neuanlage

Stand: 07.09.2026. Bereitstellungskandidat.

## Änderungen

- Gelöschte Datensätze bleiben gelöscht und können durch neue fachlich gleiche Datensätze ersetzt werden.
- Aktive Eindeutigkeit gilt für Benutzer-E-Mail, Firmenbeziehungen, Jobportale, externe Stellenidentitäten und Bewerbungen pro Job.
- Gleichzeitige Klicks auf `Bewerbung vorbereiten` führen atomar zum selben aktiven Datensatz.
- Ein Fehler der KI-Textvorbereitung lässt die angelegte Bewerbung sichtbar und manuell bearbeitbar.
- Fehlermeldungen unterscheiden Datenbankspeicherung und Textvorbereitung, erklären die Datenwirkung und enthalten eine Fehlerreferenz.

## Qualität und Deployment

PHP-Syntax, alle 29 PHP-Testdateien, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff werden vor dem Deployment geprüft. Die Schemaanpassung ist idempotent und serialisiert. Nach TOTP-Freigabe wird ausschließlich `public_html/jobs.jema.business/index.php` ersetzt; der erste Request führt die geprüfte Laufzeitmigration aus.
