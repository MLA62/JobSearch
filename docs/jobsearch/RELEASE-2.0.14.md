# Version 2.0.14 – Frühe Resultate und belegte Lohnperiode

Stand: 2026-09-04. Nach externer TOTP-Freigabe deployed.

## Änderung

- Sobald der erste brauchbare Treffer gespeichert ist, ersetzt `Resultate` im Suchfenster den Button `Abbrechen`.
- Status, Quelle, Zähler, Fortschritt und Laufzeit bleiben während der weiteren Suche sichtbar. `Resultate` beendet weitere Suchschritte und lädt die bereits gespeicherten Treffer in der Tabelle.
- Kurzbeschreibungen dürfen bis zu 1000 Zeichen nutzen, werden in die aktive App-Sprache übersetzt und bleiben in der Tabelle auf maximal vier sichtbare Zeilen begrenzt.
- Lohnperioden werden gegen das exakte Originalzitat geprüft. Eindeutige Monats-, Jahres- und Stundenangaben korrigieren einen widersprechenden KI-Code; ohne eindeutigen Beleg wird keine Periode übernommen.
- Keine Lohnumrechnung, Datenbankmigration, Konfigurationsänderung oder automatische Änderung bestehender Jobs.

## Prüfung und Deployment

Alle 26 PHP-Testdateien liefen ohne Warnungen durch. Die 46 Verifikationsprüfungen enthalten den Monatslohnfall mit absichtlich falscher KI-Periode; zusätzlich bestanden 27 Vertrags- und 27 Diagnoseprüfungen. Beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit `--check` und `git diff --check` bestanden. Sechs Chromium-Suchdialog- und vier Chromium-Importdialogfälle bestanden; der Suchdialogtest hält die Suche nach dem ersten Treffer offen und prüft den sofortigen Buttonwechsel bei weiterhin sichtbarem Status. Externe Antworten sind simuliert; der konkrete produktive Import bleibt angemeldet abzunehmen.

Deployed wurde ausschließlich `public_html/jobs.jema.business/index.php` inklusive generierter In-App-Hilfe aus Quell-Commit `a43ee80505822ae0b73ed5f6c18ce377833549a4`. Keine Datenbank-, Konfigurations- oder Bestandsdatenänderung.

Produktiver Stand: geändert `2026-09-04T19:06:08+00:00`, 1005554 Bytes, Modus 0644, SHA-256 `919e2fd33a064fe69e79ce2a315686a1751701ea980998185312e205e33d261d`. Der Remote-Hash stimmt exakt mit dem freigegebenen Inhalt überein. Die öffentliche Loginseite liefert HTTP 200, zeigt Version 2.0.14 und enthält keinen sichtbaren PHP-Laufzeitfehler. Der anonyme Debug-Download liefert die Loginseite und keine JSON-Datei. Ein neuer angemeldeter Suchlauf sowie der konkrete Monatslohnimport bleiben als fachliche Produktionsabnahme offen.

Vorheriger bestätigter Live-Stand: Version 2.0.13. Rückweg ist ein separat freizugebender Upload der bestätigten 2.0.13-Datei. Keine Freigabe-ID in Git.
