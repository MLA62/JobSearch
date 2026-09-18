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
- Quell-Commit: `26aa9108321a27066af0429871b4ddabbf64f69f`.
- Produktives Ziel: `public_html/jobs.jema.business/index.php` und
  `assets/app.css`, Document Root durch cPanel bestätigt. Vorheriger SHA-256
  für PHP: `be625184fd22de807307f4da7a520b756dc56e377e02e83442ceb155aa05a0b0`;
  für CSS: `4dbb17e913bac9862e8de9e5212dca36053058122b31820a68bf8598893e24be`.
  Der PHP-Hash entsprach dem vorherigen Git-Stand. Die entfernte CSS-Datei
  entsprach inhaltlich dem vorherigen Stand, abgesehen von Zeilenendungen.
- Vor dem Austausch am 18.09.2026 um 12:22 UTC je eine Sicherung erstellt und
  per Hash bestätigt: `index.php.bak-20260918-1422-2.4.26` und
  `assets/app.css.bak-20260918-1422-2.4.26`. Freigegebene Kopieraktionen:
  `5b007c655665cba16e98dc55663236eb` und
  `201ac451f87266d3a59571aa2764147c`.
- Freigegebene Schreibaktionen: `0737adcba2aee6f2f418099aa7576388`
  für PHP und `bb60834aace2ca8e03296a11e79dfa8d` für CSS. Die zuvor
  bestätigte externe TOTP-Sitzung war noch aktiv; alle vier Aktionen waren
  einzelne genehmigte Vorgänge. Nach dem Upload sind die produktiven Dateien
  bytegleich mit den lokalen Prüfdateien: PHP SHA-256
  `aca8793733fa4fc015abfbd4e6da5f86354b707cd36ea8a9fc5f77d1f4849708`,
  CSS SHA-256
  `362bdec86271f859b47fe60bfc1bbb1c1514f5ab75642febd7256149223d423c`.
  Die Berechtigungen blieben `0644`.
- Authentifizierte Live-Abnahme: Report `view_report=2` zeigte Version 2.4.27.
  Beide Ankreuzfelder «Noch offen» und «Noch offen · Vorstellungsgespräch»
  wurden gemeinsam angewandt. Der Report enthielt sowohl gewöhnliche offene
  Bewerbungen als auch Cleeven mit Gespräch; Absagen waren ausgeschlossen.
  Beide Häkchen blieben nach Wechsel zu Karten und zurück zur Tabelle gesetzt.
  Das Server-`error_log` blieb nach diesen Aufrufen unverändert
  (letzte Änderung 11:07 UTC). Keine Datenbankänderung.
