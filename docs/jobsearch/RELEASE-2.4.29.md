# Release 2.4.29 – Nicht erfasste Job-Room-Bewerbungen filtern

## Ursache und Änderung

Der geöffnete Report erzeugte seine Job-Room-Filteroptionen nur aus den
momentan geladenen Zeilen. Im produktiven Report «Job-Room Bewerbungen» ist
die gespeicherte Grundauswahl «Alle» ohne Suchtext. Da die aktuellen Zeilen
keinen unregistrierten Fall enthalten, fehlte dennoch das Kästchen «Noch
nicht im Job-Room erfasst». Der Anwender konnte den Zustand nicht anwählen.

Der Job-Room-Status stellt nun seine sechs atomaren Werte stets bereit,
unabhängig von den aktuell vorhandenen Zeilen. Einzig «Noch nicht im
Job-Room erfasst» auswählen und «Anwenden» zeigt genau die Bewerbungen ohne
Job-Room-Erfassung (auch solche ohne Bewerbungsdatum). Sind keine vorhanden,
bleibt das Kästchen sichtbar und das Ergebnis ist leer. Die ODER-Logik für
mehrere Job-Room-Werte und die UND-Verknüpfung mit anderen Feldern bleiben.
Andere Report-Auswahlfelder und gespeicherte Reportdefinitionen ändern sich
nicht.

## Datenwirkung und Rückweg

Nur `public/index.php` wird produktiv ausgetauscht; keine Migration, keine
Änderung an Bewerbungen oder gespeicherten Reports. Der Rückweg ist die
freigegebene Sicherung der vorherigen produktiven PHP-Datei.

## Prüfung

- 18.09.2026: PHP-Syntax und alle 45 PHP-Testdateien bestanden.
- Hilfekatalog in fünf Sprachen sowie DB-/Interface-Referenz generiert und
  mit `--check` verifiziert.
- Chromium-Test bei 390 und 1366 px für sieben Reportansichten bestanden:
  die nicht erfasste Option bleibt trotz fehlender passender Zeile sichtbar,
  eine Auswahl ohne Treffer bleibt leer, bestehende ODER-, Datums-, Zahlen-
  und Textfilter sowie Ansichtswechsel funktionieren.
- Quell-Commit `dac31827764df3b56c8681869863d3d13640d051`, auf
  `origin/feature/jema-jobs-ki-2.1.0` veröffentlicht.
- 18.09.2026, 12:52 UTC: Freigegebene Sicherung über Approval-ID
  `0fa723e636feb9117d941ff552bb77a8` angelegt:
  `public_html/jobs.jema.business/index.php.bak-20260918-1450-2.4.28`.
  SHA-256 `b6b19877da27b18ec4a2a5241b9b3a0da67bbd75cd11a4659d88ade3d43584fd`,
  1'329'595 Byte, Berechtigung `0644`; identisch mit der vorherigen Live-Datei.
- 18.09.2026, 12:52 UTC: Separat vorgeschlagener und bereits freigegebener
  Upload über Approval-ID `34166c650690982c7026aa4fea724aae`
  ausgeführt. Produktive Datei und lokal getestete Datei sind bytegleich:
  SHA-256 `eb973c5cdba9537496a75660a1d321d084dfe7a48203a5cee79ac10fbc40b3d1`,
  1'331'070 Byte, Berechtigung `0644`.
- Angemeldete Live-Abnahme um 12:53 UTC: Footer Version 2.4.29. Der Report
  `view_report=2` zeigt die sechs unabhängigen Job-Room-Optionen einschliesslich
  «Noch nicht im Job-Room erfasst». Nur dieser Wert angekreuzt und angewendet
  bleibt ausgewählt; die aktuelle Ergebnismenge zeigt korrekt «Keine Einträge».
- DB-Effekt: keiner. Keine Migration und keine Änderung an produktiven
  Datensätzen oder gespeicherten Reportdefinitionen ausgeführt.
