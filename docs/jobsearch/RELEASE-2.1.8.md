# Version 2.1.8 – Neuanlage nach dem Löschen

Stand: 07.09.2026. Bereitstellungskandidat.

## Änderungen

- Gelöschte Datensätze bleiben gelöscht und blockieren keine fachlich gleiche Neuanlage.
- Aktive Dubletten bleiben verhindert; parallele Klicks erzeugen keinen zweiten aktiven Datensatz.
- Die Laufzeitmigration legt nötige Fremdschlüssel-Stützindizes vor dem Austausch bestehender Unique-Indizes an.
- Vermittlerbeziehungen werden nach dem Löschen neu angelegt und nicht reaktiviert.
- Fehler bei Speicherung und KI-Textvorbereitung werden getrennt, verständlich und mit Fehlerreferenz gemeldet.

## Qualität und Deployment

PHP-Syntax, alle 29 PHP-Testdateien, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff werden vor dem Deployment geprüft. Nach TOTP-Freigabe wird ausschließlich `public_html/jobs.jema.business/index.php` ersetzt; der erste Request führt die idempotente, serialisierte Laufzeitmigration aus.
