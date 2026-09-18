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
- Produktiver Dateihash, Backup, Freigabe und Bereitstellung: noch einzutragen.
- Authentifizierte Sichtprüfung der Cleeven-Zeile nach Deployment: noch einzutragen.
