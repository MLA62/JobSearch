# Version 2.0.14 – Frühe Resultate und belegte Lohnperiode

Stand: 2026-09-04. Für das Deployment vorbereitet; externe TOTP-Freigabe, Upload und Live-Prüfung stehen noch aus.

## Änderung

- Sobald der erste brauchbare Treffer gespeichert ist, ersetzt `Resultate` im Suchfenster den Button `Abbrechen`.
- Status, Quelle, Zähler, Fortschritt und Laufzeit bleiben während der weiteren Suche sichtbar. `Resultate` beendet weitere Suchschritte und lädt die bereits gespeicherten Treffer in der Tabelle.
- Kurzbeschreibungen dürfen bis zu 1000 Zeichen nutzen, werden in die aktive App-Sprache übersetzt und bleiben in der Tabelle auf maximal vier sichtbare Zeilen begrenzt.
- Lohnperioden werden gegen das exakte Originalzitat geprüft. Eindeutige Monats-, Jahres- und Stundenangaben korrigieren einen widersprechenden KI-Code; ohne eindeutigen Beleg wird keine Periode übernommen.
- Keine Lohnumrechnung, Datenbankmigration, Konfigurationsänderung oder automatische Änderung bestehender Jobs.

## Prüfung und Deployment

Alle 26 PHP-Testdateien liefen ohne Warnungen durch. Die 46 Verifikationsprüfungen enthalten den Monatslohnfall mit absichtlich falscher KI-Periode; zusätzlich bestanden 27 Vertrags- und 27 Diagnoseprüfungen. Beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit `--check` und `git diff --check` bestanden. Sechs Chromium-Suchdialog- und vier Chromium-Importdialogfälle bestanden; der Suchdialogtest hält die Suche nach dem ersten Treffer offen und prüft den sofortigen Buttonwechsel bei weiterhin sichtbarem Status. Externe Antworten sind simuliert; der konkrete produktive Import bleibt angemeldet abzunehmen.

Geplant ist ausschließlich `public_html/jobs.jema.business/index.php` inklusive generierter In-App-Hilfe. Quellcommit, Serverhash, öffentliche Versionsanzeige, Login und anonymer Zugriffsschutz werden nach Deployment ergänzt. Bestätigter vorheriger Live-Stand: Version 2.0.13. Keine Freigabe-ID in Git.
