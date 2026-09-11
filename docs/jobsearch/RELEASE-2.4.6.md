# Version 2.4.6 – Vollständige Reportfelder

Stand: 11.09.2026.

## Änderungen

- Auswertungen bieten für Stellen, Bewerbungen, Firmen, Kontakte, Dokumente und Kalender alle fachlich
  auswertbaren Datenbankfelder, IDs und lesbaren Beziehungen als Reportspalten an.
- Pro Report können höchstens zwölf Felder gleichzeitig gewählt werden. Die Oberfläche zeigt den
  aktuellen Zähler; der Server validiert und begrenzt zusätzlich beim Speichern, Laden und Export.
- Beim Wechsel der Datenbasis werden Feldauswahl, Sortierfelder und Statusfilter sofort passend ersetzt.
- Rich Text erscheint im Export als lesbarer Text; Datum, Auswahlwerte, Länder, Sprachen, Ja/Nein-Werte
  und Dateigrößen werden lesbar formatiert.
- Interne Mandanten-, Soft-Delete-, Eindeutigkeits- und Dokumentpfadfelder bleiben ausgeschlossen.

## Prüfung

`tests/report_fields_test.php` deckt Feldumfang, Ausschlüsse, Obergrenze, Reihenfolge, Deduplizierung,
serverseitige Validierung, dynamische Oberfläche, feste SQL-Abfragen und sichere JavaScript-Einbettung ab.
Die vollständige Regression war erfolgreich: 36 PHP-Testdateien, 3'885 Hilfeprüfungen, 1'294
Hilfe-Seeds, 75 Markdown-Dateien mit 61 lokalen Links und sieben Chromium-Testdateien. Nach der
TOTP-Freigabe wurden lokale und produktive `index.php` bytegleich verifiziert; die öffentliche Anwendung
liefert HTTP 200, Version 2.4.6 und alle vorgesehenen Sicherheitsheader.
