# Version 2.0.15 – Mehr aktuelle brauchbare Treffer

Stand: 2026-09-04. Nach externer TOTP-Freigabe deployed.

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

Deployed wurde ausschließlich `public_html/jobs.jema.business/index.php` inklusive generierter In-App-Hilfe aus Quell-Commit `e956d55d8a24a3472b1ed6e76f19c10178040805`. Keine Datenbank-, Konfigurations- oder Bestandsdatenänderung.

Produktiver Stand: geändert `2026-09-04T19:29:00+00:00`, 1006770 Bytes, Modus 0644, SHA-256 `0d25bdaec2f5d81e70618d0837fbe481d65aaa1429d0785b2ce691bced16ce75`. Der Remote-Hash stimmt exakt mit dem freigegebenen Inhalt überein. Die öffentliche Loginseite liefert HTTP 200, zeigt Version 2.0.15 und enthält keinen sichtbaren PHP-Laufzeitfehler. Der anonyme Debug-Download liefert die Loginseite und keine JSON-Datei. Ein neuer angemeldeter Suchlauf bleibt als produktiver Ausbeute- und Laufzeitnachweis offen.

Vorheriger bestätigter Live-Stand: Version 2.0.14. Rückweg ist ein separat freizugebender Upload der bestätigten 2.0.14-Datei. Keine Freigabe-ID in Git.
