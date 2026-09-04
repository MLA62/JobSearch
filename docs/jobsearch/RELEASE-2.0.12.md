# Version 2.0.12 – Gefundene Ergebnisse tatsächlich anzeigen

Stand: 2026-09-04. Vorbereitet, noch nicht deployed.

## Änderung und Nachweis

Ergebnisse anzeigen lädt die Jobsuche neu und springt zur Tabelle. Der alte Fragmentwechsel lud die serverseitige Tabelle nicht neu und ließ das Modal offen. Ein neuer Browser-Klicktest reproduzierte diesen Fehler im alten Code; mit der Korrektur werden gespeicherte synthetische Treffer nach Erfolg, Begrenzung oder späterer Unterbrechung sichtbar. Keine neue KI-Suche, keine Änderung der Match-/Speicher-/Ablaufregeln.

Der Benutzerbericht aus 2.0.11 enthält drei akzeptierte Treffer, während das Bildschirmbild die alte leere Tabelle unter dem Modal zeigt. Der technische Grund des dortigen weiteren Suchabbruchs ist aus dem Teilbericht nicht abschließend belegt und wird mit dieser Navigationskorrektur nicht als behoben behauptet.

MDs, Version und Hilfe in fünf Sprachen aktualisiert. 26 PHP-Testdateien ohne Warnungen sowie zehn Chromium-Dialogfälle bestanden. Beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit --check und git diff --check bestanden. Angemeldete Produktionsabnahme der Ergebnisanzeige bleibt nach Deployment erforderlich.

## Deployment und Rückweg

Nur public_html/jobs.jema.business/index.php inklusive generierter Hilfe. Exakter Upload aus Quell-Commit edab25f7b356cc3c600b138f0e1135caf4a05389 vorgeschlagen; lokal und GitHub synchron. Neue TOTP-Freigabe erforderlich, noch nicht ausgeführt.
997272 Bytes, SHA-256 8f8b756d4558467a9c6d3c8588eed1beb91649fca286b0ff0d66bd61f989393b.
Remote-Vergleich vor Vorschlag bestätigte unveränderten Stand 2.0.11. Live-Verifikation folgt nach Freigabe und Ausführung.

Vorheriger bestätigter Stand 2.0.11: Quell-Commit e0a6c38b9edd73e5b6d93da43addb95a2631ece3,
996248 Bytes, Modus 0644, SHA-256 b95e3dccb675b2782649c1d5e1f6f5497217f8d172b80885595943c83a3122e2.
Rückweg: separat freigegebener Upload dieses Inhalts. Keine Konfiguration, Bestandsdatenänderung oder Migration. Keine Freigabe-IDs in Git.
