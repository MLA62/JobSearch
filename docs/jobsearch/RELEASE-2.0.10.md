# Version 2.0.10 – Quellenwechsel nach zehn rohen Treffern

Stand: 2026-09-04. Vorbereitet, noch nicht deployed.

## Änderungen

- Bei mehreren ausgewählten Suchmaschinen maximal zehn rohe URL-Treffer je Quelle vor dem Wechsel. Die Zählung erfolgt vor Dubletten-/Benutzerausschluss, Verfügbarkeits- und Match-Prüfung. Bereits eingereihte Treffer dieser Zehnergruppe werden geprüft; danach nächste Quelle. Bei kleinen Antworten wird nur die Restanzahl angefragt, höchstens drei Runden.
- Einzelsuche unverändert. Trefferziel und bisherige Gesamtgrenze 60 bleiben wirksam. Früherer Wechsel ohne neue Kandidaten möglich. Eine begrenzte Quelle ist nicht vollständig ausgeschöpft; die Suche weist weiterhin auf Begrenzung hin.
- Alte laufende Mehrquellensuchen ohne Rohzähler überspringen die restliche alte Quellenwarteschlange, vorhandene passende Treffer bleiben erhalten. Keine Datenbankänderung, keine neuen Match-Regeln.
- MDs und In-App-Hilfe in fünf Sprachen nachgeführt.

## Diagnose und Tests

Der neue Teilbericht von 2.0.9 zeigt fünf akzeptierte Treffer, zwei Profilablehnungen, 24 unverfügbare/unprüfbare Kandidaten und keine technischen Fehler bei 31 Prüfungen. Erste Quelle noch aktiv. Keine privaten Berichtsinhalte in Git übernommen; aggregierte Ergebnisse sind keine fachliche Vollabnahme.

26 PHP-Testdateien ohne Warnungen bestanden, einschließlich gezielter Tests für Rohzählung, Restlimit, Ausschlüsse, null passende Treffer, Einzelquelle und alte Session. Acht Chromium-Such-/Importdialogfälle bestanden. Beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit --check und git diff --check bestanden. Kein produktiver API-Suchlauf als Test behauptet.

## Deployment und Rückweg

Nur public_html/jobs.jema.business/index.php inklusive generierter Hilfe. Exakter Upload aus Quell-Commit 3e6524b3e5a874f255f5614584536279e6817597 vorgeschlagen; lokal und GitHub synchron. Neue externe TOTP-Freigabe erforderlich, noch nicht ausgeführt.
993558 Bytes, SHA-256 aa8e9d3ae53fb2b7093be014c11c26109c92663b82c5b28f6dadfe3107c2be97.
Remote-Vergleich vor Vorschlag bestätigte unveränderten Stand 2.0.9. Keine Konfigurationsdatei im Upload. Live-Verifikation folgt nach Freigabe und Ausführung.

Vorheriger bestätigter Stand 2.0.9: Quell-Commit 8ba5f83dac37eeb9ef33a4dab2a990b641dca833,
990795 Bytes, Modus 0644, SHA-256 86426e9f82a8b78a2b0bf7d983af916a9d7763deac506d01c298941dc5882b60.
Rückweg durch separat freigegebenen Upload dieses Inhalts. Keine Konfiguration, DB-Dateien oder Freigabe-IDs in Git.
