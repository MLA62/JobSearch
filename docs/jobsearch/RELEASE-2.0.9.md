# Version 2.0.9 – Korrekte Zuordnung der Match-Kriterien

Stand: 2026-09-04. In Vorbereitung; noch nicht deployed.

## Änderungen

- Ursache der sechs protokollierten technischen Match-Fehler korrigiert: Pflichtschlüssel für die aktiven Kriterien statt frei benennbarer Einträge. Zusätzliche serverseitige Validierung und expliziter Diagnosecode match_contract_error. Vertragsfehler stoppen die Suche sichtbar, statt weitere Jobs still abzulehnen.
- Zusätzliche lokalisierte Meldungen für geschlossene Bewerbungen; erkannte LinkedIn-Job-CTAs außerhalb des Hauptbereichs. Versteckte und deaktivierte Elemente sind kein Verfügbarkeitsbeleg. Unprüfbare Anzeigen bleiben ausgeschlossen.
- Quellenkette und Prüfschritt bleiben bei Verfügbarkeitsfehlern im bereinigten Debugbericht erhalten.
- Version, Dokumentation und Hilfe in fünf Sprachen aktualisiert. Keine Änderung an Match-Gewichten, Mindestscore, Suchbudget, Quellensuchreihenfolge, DB-Schema oder Bestandsdaten.

## Prüfung und Grenzen

Der Diagnosebericht belegt 23 unprüfbare Verfügbarkeiten, sechs Fehler im bisherigen Kriterienvertrag und einen HTTP-404; keine abgeschlossene Match-Prüfung. Keine Produktionsdaten in Tests/Git übernommen. Ohne vollständige URLs lassen sich die konkreten 23 Anzeigen nicht nachtesten.

Neue Integrationstests führen den Produktionsweg vom Import über das strikte Responses-Schema bis zur Scoreberechnung aus, mit simulierten externen HTML-/HTTP-Grenzen. Verfügbarkeit zusätzlich gegen zwei öffentlich abgerufene LinkedIn-Seiten geprüft: eine mit zukünftiger Frist als verfügbar, eine mit deutschem Bewerbungsschluss als abgelaufen. Diese Stichprobe beweist keine vollständige Portalabdeckung. Kein authentifizierter produktiver KI-Suchlauf als Test behauptet.

25 PHP-Testdateien bestanden, darunter 27 Prüfungen des tatsächlichen Antwortvertrags und 40 Verifikationsprüfungen. Acht Chromium-Dialogfälle für Suche/Übernehmen einschließlich Debugdownload bestanden. Beide PHP-Syntaxprüfungen, beide Dokumentationsgeneratoren mit --check und git diff --check bestanden.

Offen: TOTP-Deployment und neuer angemeldeter Suchlauf. Die vorhandenen Schutzgrenzen können weiterhin vor vollständiger Quellenausschöpfung greifen; dies wird als begrenzt angezeigt. KI-Interpretation bleibt modellabhängig. Kein zusätzlicher Browser-Host, kein bezahlter Render-Dienst.

## Deployment und Rückweg

Nur public_html/jobs.jema.business/index.php inklusive generierter Hilfe nach externer TOTP-Freigabe.
Upload vorbereitet aus Quell-Commit 8ba5f83dac37eeb9ef33a4dab2a990b641dca833; lokal und GitHub synchron.
990795 Bytes, SHA-256 86426e9f82a8b78a2b0bf7d983af916a9d7763deac506d01c298941dc5882b60.
Vor dem Vorschlag bestätigte der Remote-Hash den unveränderten Stand 2.0.8. Exakte Schreibaktion vorgeschlagen; Ausführung und anschließende Live-Prüfung warten auf TOTP. Keine Konfiguration oder DB-Dateien im Upload.

Vorheriger bestätigter Stand 2.0.8: Quell-Commit 9bd0764042c610f8d03aa65592971c2899f8ed5f,
987248 Bytes, Modus 0644, SHA-256 2aabd0ea529d0e978f5748ebccc8f552cf46a2e5ae24bf420c6eb34ad358c785.
Rückweg: separat freigegebener Upload dieses Inhalts. Keine Freigabe-IDs oder Links in Git.

Schema-Vertrag: [OpenAI Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs), Pflichtfelder und additionalProperties false.
