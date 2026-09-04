# Version 2.0.15 – Mehr aktuelle brauchbare Treffer

Stand: 2026-09-04. Für das Deployment vorbereitet; externe TOTP-Freigabe, Upload und Live-Prüfung stehen noch aus.

## Diagnose

Der produktive 2.0.14-Bericht schloss alle 16 Quellen ab: 52 geprüfte Kandidaten, sieben akzeptiert, 45 nicht importiert und 15 technische Fehler. Von neun vollständig bewertbaren Anzeigen bestanden sieben den Match. Der Hauptverlust lag vor der Bewertung: 26 Anzeigen waren abgelaufen oder nicht belegbar verfügbar, 15 technisch nicht lesbar. Acht Prüfplätze des Limits 60 blieben mangels weiterer Kandidaten ungenutzt.

## Änderung

- Zusammenfassung bis zu 2000 Zeichen und höchstens zwölf sichtbare Tabellenzeilen in der Benutzersprache.
- Discovery erhält das aktuelle UTC-Datum und priorisiert aktuelle, individuell lesbare Anzeigen mit Gültigkeits- oder Bewerbungsnachweis.
- Erkennbar abgelaufene, geschlossene, entfernte, archivierte, generische und zugangsgesperrte Seiten sollen bereits vor der teuren Originalprüfung entfallen.
- Nicht genutztes Vertiefungsbudget wird an produktive Quellen zurückgegeben, die im vorigen Durchgang noch neue eindeutige URLs geliefert haben; maximal drei Vertiefungsdurchgänge und weiterhin höchstens 60 Prüfungen.
- Matchschwelle 70, Original-/Verfügbarkeitsprüfung, Belegpflicht, Ausschlüsse, Dubletten, Erstverteilung und Sortierung bleiben unverändert.
- Keine Datenbank-, Konfigurations- oder Bestandsdatenänderung.

## Prüfung und Deployment

Alle 26 PHP-Testdateien liefen ohne Warnungen durch. Die 46 Verifikations-, 27 Vertrags- und 27 Diagnoseprüfungen sowie der erweiterte integrierte Quellenlauf bestanden. Dieser gibt alle acht ungenutzten Plätze an eine weiterhin liefernde produktive Quelle zurück und erreicht das unveränderte Gesamtlimit 60. Beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit `--check` und `git diff --check` bestanden. Sechs Chromium-Suchdialog- und vier Chromium-Importdialogfälle bestanden. Externe Antworten sind simuliert; produktive Ausbeute und Laufzeit bleiben nach Deployment mit einem neuen angemeldeten Diagnosebericht zu prüfen.

Geplant ist ausschließlich `public_html/jobs.jema.business/index.php` inklusive generierter In-App-Hilfe. Quellcommit, Serverhash, öffentliche Versionsanzeige, Login und anonymer Zugriffsschutz werden nach Deployment ergänzt. Bestätigter vorheriger Live-Stand: Version 2.0.14. Keine Freigabe-ID in Git.
