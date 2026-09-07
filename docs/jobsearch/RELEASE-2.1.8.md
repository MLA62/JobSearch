# Version 2.1.8 – Neuanlage nach dem Löschen

Stand: 07.09.2026. Deployed.

## Änderungen

- Gelöschte Datensätze bleiben gelöscht und blockieren keine fachlich gleiche Neuanlage.
- Aktive Dubletten bleiben verhindert; parallele Klicks erzeugen keinen zweiten aktiven Datensatz.
- Die Laufzeitmigration legt nötige Fremdschlüssel-Stützindizes vor dem Austausch bestehender Unique-Indizes an.
- Vermittlerbeziehungen werden nach dem Löschen neu angelegt und nicht reaktiviert.
- Fehler bei Speicherung und KI-Textvorbereitung werden getrennt, verständlich und mit Fehlerreferenz gemeldet.

## Qualität und Deployment

PHP-Syntax, alle 29 PHP-Testdateien, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff wurden geprüft. Nach TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` ersetzt. Die Produktionsdatei entspricht exakt dem lokalen Release-Stand. Die öffentliche Seite liefert HTTP 200, zeigt Version 2.1.8 und enthält keinen sichtbaren PHP-Fehler. Die idempotente, serialisierte Laufzeitmigration wurde ohne neuen Schemafehler ausgeführt.
