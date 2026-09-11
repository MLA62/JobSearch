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
