# Release 2.4.26 – Job-Room-Gespräch im Report

## Änderung

Im Report «Job-Room Bewerbungen» zeigt die Spalte «Job-Room Status» bei bestätigter
Erfassung und vorhandenem Bewerbungsdatum sowohl das Resultat als auch das separat
gesetzte Kennzeichen «Vorstellungsgespräch». Der lokale Bewerbungsstatus bleibt
unverändert und separat. Die angezeigte Kombination ist in der Reportansicht
eigenständig filterbar.

## Datenbank und Risiko

Keine Migration, keine Bestandsdatenänderung und keine Änderung am gespeicherten
Report. Die neue Darstellung nutzt das bereits geladene Feld
`applications.job_room_interview`.

## Prüfung und Bereitstellung

- Lokale Prüfung am 18.09.2026: `php -l public/index.php` ohne Syntaxfehler;
  alle 44 PHP-Regressionsskripte unter `tests/*_test.php` bestanden;
  `php -n tools/build_help.php --check`,
  `php -n tools/build_reference.php --check` und `git diff --check` bestanden.
- Quell-Commit: `338bf539b90f00a057f0836bf056f1ad41441c0f`.
- Vorheriger produktiver Hash von `public_html/jobs.jema.business/index.php`:
  SHA-256 `64b97e43cdc45a0b5d68dd39851715ff3c622ec03f156100c50962e653ea97b3`;
  identisch mit dem bisherigen Git-HEAD. Sicherung als
  `index.php.bak-20260918-1204-2.4.25` mit demselben Hash verifiziert.
- Freigegebene Sicherungsaktion: `d44b78d057dea4141d89032ff9dbd78e`;
  freigegebener Datei-Upload: `9934bdfbf90248bbb6badacf8641c4a2`.
  Beides am 18.09.2026 über den cPanel-Connector ausgeführt. Die bestehende
  externe TOTP-Freigabe war für beide Einzelaktionen aktiv.
- Produktiver Hash nach Bereitstellung: SHA-256
  `be625184fd22de807307f4da7a520b756dc56e377e02e83442ceb155aa05a0b0`,
  bytegleich zur lokalen `public/index.php`, Dateiberechtigung weiterhin `0644`.
- Authentifizierte Sichtprüfung am 18.09.2026: Der gespeicherte Report
  `view_report=2` zeigt in der Cleeven-Zeile den separaten lokalen Status
  «Bewerbungsgespräche» und den Job-Room-Status
  «Im Job-Room erfasst – Noch offen · Vorstellungsgespräch». Dasselbe Ergebnis
  erscheint in der Kartenansicht; anschließend wurde zur Tabellenansicht
  zurückgekehrt. Die Fußzeile zeigt Version 2.4.26.
- DB-Effekt: keine Migration und keine Datenänderung. Die produktive
  Bewerbungsmaske zeigte vor der Änderung bereits beide gesetzten Job-Room-Felder.
