# Version 2.4.7 – Korrekte Reporttabelle

Stand: 11.09.2026.

## Fehlerursache

Der Link «Anzeigen» verwendete die Datenbasis und Darstellungsart des Reports nur zur Weiterleitung auf
die allgemeine Modulseite. Dort erschien deshalb eine fest definierte Standardtabelle statt der im Report
gespeicherten Felder.

## Korrektur

- «Anzeigen» öffnet den konkreten gespeicherten Report auf der Auswertungsseite.
- Der Report lädt mandantenbegrenzt seine gespeicherten Spalten, Filter und Sortierung.
- Kopfzeile und Daten werden in exakt derselben gewählten Feldreihenfolge gerendert.
- Bearbeitung und PDF-Export sind direkt an der sichtbaren Reporttabelle erreichbar.
- Nicht vorhandene oder fremde Report-IDs liefern keine Daten.

## Prüfung

Der Regressionstest reproduziert die alte Fehlleitung und prüft Route, Report-ID, gespeicherte Einstellungen,
Datensatzaufbereitung sowie die gemeinsame Spaltenquelle für Tabellenkopf und Tabellenzeilen.

Alle 36 PHP-Testdateien, 3'885 Hilfeprüfungen, 1'294 Hilfe-Seeds, beide Dokumentationsgeneratoren,
76 Markdown-Dateien mit 61 lokalen Links und alle sieben Chromium-Testdateien waren erfolgreich.

## Deployment

Nach externer TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` ersetzt.
Die lokale und produktive Datei sind bytegleich. Die öffentliche Seite liefert HTTP 200, Version 2.4.7
sowie HSTS, CSP, `nosniff`, `DENY` und `no-referrer`. Datenbank, Konfiguration und Assets blieben unverändert.
