# Release 2.4.44 – Job-Room-API-Format beim Schnellimport

Stand: 21.09.2026. Der erste angemeldete Produktivtest von 2.4.43 erkannte
eine konkrete Job-Room-Detail-URL, erhielt beim anschliessenden API-Abruf
jedoch HTTP 406. Ursache: Der gemeinsame HTML-Abruf verlangte auch vom
JSON-Endpunkt `text/html,application/xhtml+xml`. Job-Room akzeptiert dort
`application/json` (lokaler Abruf gegen denselben öffentlichen Endpunkt:
HTTP 406 mit HTML-Accept, HTTP 200 mit JSON-Accept).

Der Detail-API-Abruf fordert jetzt JSON an; normale Inseratseiten behalten
ihre HTML-Accept-Kopfzeile. Ein Regressionstest prüft beide Pfade. Der
Schnellimport wird nach dem Deployment erneut angemeldet gegen die konkrete
URL und anschliessend mit mehreren Listeneinträgen geprüft. Erst der
sichtbare Importverlauf und der gespeicherte Jobbestand belegen Erfolg.
