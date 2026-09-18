# Release 2.4.27 – ODER-Mehrfachauswahl in Reportfiltern

## Änderung

Auswahlfilter in geöffneten Reports lassen mehrere ankreuzbare Werte zu.
«Noch offen» und «Noch offen · Vorstellungsgespräch» können im Job-Room-Status
gemeinsam gewählt werden. Innerhalb eines Feldes gilt ODER, zwischen
verschiedenen Feldern weiterhin UND. Keine Auswahl bedeutet keine Einschränkung.
Der Ansichtswechsel zwischen Tabelle und Karten erhält alle gewählten Werte.

## Datenbank und Rückweg

Keine Migration und keine Änderung an Bestandsdaten oder gespeicherten Reports.
Der alte Einzelauswahl-URL-Parameter bleibt lesbar. Rückweg ist die gesicherte
Version 2.4.26 der produktiven Dateien.

## Prüfung und Bereitstellung

- Lokale Prüfung am 18.09.2026: `php -n -l` für die beiden PHP-Einstiegsdateien
  ohne Fehler; alle 44 PHP-Regressionstests bestanden; Hilfegenerierung und
  Referenzgenerierung mit `--check` bestanden. Der Chromium-Test
  `report_display_visual_test.cjs` bestand für Tabelle, Liste, Karten,
  Vorschau und Kalendergruppen in 390 und 1366 Pixel Breite, einschließlich
  Mehrfachauswahl, Rücknavigation, URL-Persistenz und geöffnetem Filter ohne
  horizontalen Überlauf.
- Quell-Commit, Sicherung, Freigabe und produktive Hashes: ausstehend.
- Authentifizierte Abnahme der Mehrfachauswahl: ausstehend.
