# Version 2.0.13 – Adaptive Quellen für mehr brauchbare Treffer

Stand: 2026-09-04. Vorbereitet, noch nicht deployed.

## Diagnose

Der bereitgestellte produktive Bericht aus 2.0.12 zeigt bei Ziel 20 insgesamt 53 Versuche: sechs akzeptierte Treffer, acht fachliche Profilablehnungen, 14 abgelaufene oder nicht belegbar verfügbare Anzeigen und 25 technische Lesefehler. Nur 14 Kandidaten gelangten bis zur Match-Bewertung. Wiederholte Portalzugriffssperren verbrauchten Kandidatenquoten, während Quellen mit akzeptierten Treffern nach ihrer kleinen Quote nicht nochmals genutzt wurden.

## Änderung

- Erste Phase: alle gewählten Suchmaschinen annähernd gleich prüfen; bei 16 Quellen je zwei rohe Kandidaten.
- Wiederholt blockierte Quelle ohne lesbare Anzeige nach zwei gleichen Zugriffs-/Lesefehlern im aktuellen Lauf beenden.
- Zweite Phase: restliches Gesamtbudget bis 60 gleichmäßig an Quellen mit akzeptierten Treffern geben; wenn noch kein Treffer akzeptiert wurde, an Quellen mit vollständig lesbaren Anzeigen.
- Discovery bevorzugt verlinkte öffentliche Arbeitgeber-Originalanzeigen und soll Suchseiten, Aggregator-Redirects und Zugangswände nicht als Inserat zurückgeben.
- Zielzahl, Original-/Verfügbarkeitsprüfung, belegbasierter Match, Schwelle 70, Ausschlüsse, Dubletten, Sortierung und Einzelsuche bleiben unverändert.

## Prüfung und Deployment

Alle 26 PHP-Testdateien liefen ohne Warnungen durch. Die 41 Verifikationsprüfungen, der adaptive Quellenlauf, 27 Diagnoseprüfungen, beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit `--check` und `git diff --check` bestanden. Sechs Chromium-Suchdialog- und vier Chromium-Importdialogfälle bestanden. Die adaptiven Netzwerkergebnisse sind deterministisch simuliert; ein neuer produktiver Suchlauf samt Diagnosebericht bleibt nach Deployment erforderlich.

Keine Datenbank-, Konfigurations- oder Bestandsdatenänderung. Deploymentziel bleibt ausschließlich `public_html/jobs.jema.business/index.php` inklusive generierter Hilfe. Exakter Quell-Commit, Hash, externe TOTP-Freigabe, Remotevergleich und öffentliche Prüfung folgen nach vollständiger lokaler Verifikation.

Vorheriger bestätigter Live-Stand: Version 2.0.12. Rückweg ist ein separat freizugebender Upload der bestätigten 2.0.12-Datei. Keine Freigabe-ID in Git.
