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
- Quell-Commit, cPanel-Freigabe, Backup- und Upload-Hash und angemeldete
  Live-Abnahme werden nach Ausführung getrennt ergänzt.
