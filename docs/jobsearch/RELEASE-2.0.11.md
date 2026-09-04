# Version 2.0.11 – Sucherfolg und gleichmäßige Quellenverteilung

Stand: 2026-09-04. Vorbereitet, noch nicht deployed.

## Änderungen

- Ein brauchbarer Treffer reicht für „Suche erfolgreich“, auch bei nicht erreichter Zielzahl. Weitere Suche läuft bis Ziel/Abschluss weiter. Technische Unterbrechung und Begrenzung bleiben sichtbar; keine unprüfbaren Treffer freigeben.
- Die 60 Prüfplätze werden annähernd gleich auf die markierten Quellen verteilt. Bei 16 Quellen drei oder vier Rohresultate je Quelle statt zehn. Kein zusätzlicher API-Aufruf zur Verteilung; Gesamtbudget unverändert. Einzelquelle unverändert.
- Alte Zehnerwarteschlangen werden auf die kleinere Quote gekürzt, akzeptierte Treffer bleiben erhalten. Debugbericht: eigener successful-Wert neben technischem status. Keine DB-Migration, keine geänderten Match-Gewichte.
- MDs und Hilfe in fünf Sprachen nachgeführt.

## Tests und Grenzen

26 PHP-Testdateien ohne Warnungen bestanden, einschließlich Quoten-/Suchablauftests für zwei bis 60 Quellen, einen kompletten 16-Quellen-Lauf, Restlimits, Ausschlüsse und alte Sessions. Zehn Chromium-Dialogfälle bestanden: sechs für Suche (einschließlich Erfolg trotz Begrenzung/späterem Fehler und null Treffer), vier für Übernehmen. Mobile Erfolgsdarstellung visuell geprüft. Beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit --check und git diff --check bestanden.

Zielzahl, Abbruch, Dienstfehler oder Sitzungsablauf können vor vollständiger Quellenabdeckung beenden. Ungenutzte Kontingente werden nicht neu verteilt. Mehr als 60 Quellen können im Budget nicht alle geprüft werden. Vorhandene Portal-Abruffehler werden hier nicht behoben. Kein produktiver KI-Suchlauf als Test behauptet.

## Deployment und Rückweg

Nur public_html/jobs.jema.business/index.php inklusive generierter Hilfe, neue externe TOTP-Freigabe erforderlich. Quell-Commit und Uploadnachweis folgen.

Vorheriger bestätigter Stand 2.0.10: Quell-Commit 3e6524b3e5a874f255f5614584536279e6817597,
993558 Bytes, Modus 0644, SHA-256 aa8e9d3ae53fb2b7093be014c11c26109c92663b82c5b28f6dadfe3107c2be97.
Rückweg: separat freigegebener Upload dieses Inhalts. Keine Konfiguration oder DB-Dateien im Upload; keine Freigabe-IDs in Git.
