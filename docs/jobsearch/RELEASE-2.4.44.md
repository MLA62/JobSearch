# Release 2.4.44 – Job-Room-API-Format beim Schnellimport

Stand: 21.09.2026. Der erste angemeldete Produktivtest von 2.4.43 erkannte
eine konkrete Job-Room-Detail-URL, erhielt beim anschliessenden API-Abruf
jedoch HTTP 406. Ursache: Der gemeinsame HTML-Abruf verlangte auch vom
JSON-Endpunkt `text/html,application/xhtml+xml`. Job-Room akzeptiert dort
`application/json` (lokaler Abruf gegen denselben öffentlichen Endpunkt:
HTTP 406 mit HTML-Accept, HTTP 200 mit JSON-Accept).

Der Detail-API-Abruf fordert jetzt JSON an; normale Inseratseiten behalten
ihre HTML-Accept-Kopfzeile. Ein Regressionstest prüft beide Pfade. Alle
lokalen PHP-Tests und die beiden Dokumentationsgeneratoren liefen erfolgreich.

Vor der Veröffentlichung wurde Version 2.4.43 als
`index.php.bak-20260921-2.4.43-pre-2.4.44` auf dem Server gesichert. Die
produktive Datei aus Commit `28568d5` hat denselben SHA-256-Wert wie das
lokale Release:
`883288210fb9856c53137321941f326b2bcf9e6650c509562198c28834d4709d`.
Die angemeldete App zeigt Version 2.4.44.

Anschliessend wurde die zuvor mit HTTP 406 fehlgeschlagene URL
`https://www.job-room.ch/job-search/d748fc6a-f08e-4f5f-bc81-84d4d1ac47ac`
im produktiven Schnellimport erneut verarbeitet. Der Job wurde als
`Account Manager 100%` bei `Rent.Group Swiss BRN AG` mit der Quell-URL und
Beschreibung unter Job-ID 335 gespeichert; die Anzahl der Jobs stieg von 28
auf 29. Der einzelne Detailimport ist damit produktiv bestätigt. Der Import
der gesamten kopierten Trefferliste bleibt separat zu prüfen.
