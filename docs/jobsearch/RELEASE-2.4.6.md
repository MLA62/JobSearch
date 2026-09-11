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
Die vollständige PHP-, Hilfe-, Dokumentations- und Chromium-Regression wird vor dem Deployment ausgeführt.
