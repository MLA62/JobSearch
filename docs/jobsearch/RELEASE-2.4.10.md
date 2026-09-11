# Version 2.4.10 – Eindeutiger Job-Room-Status

Stand: 11.09.2026.

## Fehlerursache

Der Report bezeichnete die Spalte technisch als `job_room_result · DB-Feld` und zeigte bei einer
bestätigten Erfassung nur das Resultat «Noch offen». Dadurch war nicht erkennbar, ob der Datensatz
wirklich als im Job-Room erfasst galt. Außerdem floss das zwingende Bewerbungsdatum nicht in diese
Reportausgabe ein.

## Korrektur

- Die Spalte heißt fachlich und lokalisiert «Job-Room Status».
- Ohne Bewerbungsdatum oder ohne bestätigte Erfassung erscheint «Noch nicht im Job-Room erfasst».
- Bei bestätigter Erfassung zeigt der Status zusätzlich das Resultat: «Im Job-Room erfasst – …».
- Erfassung und Vorstellungsgespräch besitzen ebenfalls verständliche, lokalisierte Spaltennamen.
- Die übrigen Reportfelder und gespeicherten Spaltenreihenfolgen bleiben unverändert.

## Prüfung

`php -l public/index.php` und `php tests/report_fields_test.php` prüfen Syntax, Statuslogik,
Bewerbungsdatum, Erfassungszustand und Spaltenbeschriftungen. Alle 36 PHP-Testdateien, 3'918
Hilfeprüfungen, 1'309 Hilfe-Seeds, beide Dokumentationsgeneratoren, 79 Markdown-Dateien mit 61
lokalen Links und alle sieben Chromium-Testdateien waren erfolgreich.

## Deployment

Nach externer TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` ersetzt.
Die lokale und produktive Datei sind bytegleich. Die öffentliche Seite liefert HTTP 200, Version
2.4.10 sowie HSTS, CSP, `nosniff`, `DENY` und `no-referrer`. Datenbank, Konfiguration und Assets
blieben unverändert.
