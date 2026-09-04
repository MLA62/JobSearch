# Version 2.0.8 – Downloadbare Suchdiagnose

Stand: 2026-09-04. Lokal vorbereitet; Deployment ausstehend.

## Änderungen

- Debug-Datei herunterladen auf der Jobsuche-Seite und im Suchfenster. JSON-Bericht der letzten Suche derselben Sitzung, manuell zur Analyse teilbar.
- Verarbeitungsphasen, Laufzeiten, technische Fehlercodes, HTTP-/cURL-Status, Domains und Match-Kriterienbewertungen. Fehler, unprüfbare/abgelaufene Anzeigen, fehlende Passung, Dubletten und Benutzerausschlüsse unterscheidbar.
- Keine vollständigen Profilwerte, Inserate, Kontakte, URLs, Zugangsdaten, Rohfehlermeldungen oder Session-IDs exportieren. Begrenzter Sitzungspuffer; neuer Lauf ersetzt den alten. Nur eigener angemeldeter Benutzer; kein automatischer Versand.
- Klarere Zähler und aktuelle Quelle im Popup. Ergebnisbutton bleibt während der Suche ausgeblendet, auch bei globalen Button-CSS-Regeln.
- Hilfe in fünf Sprachen und MDs aktualisiert. Keine Schema-, Match-Schwellen- oder Bestandsdatenänderung.

## Prüfung und Grenzen

24 PHP-Testdateien bestanden, darunter 25 Diagnoseprüfungen und 28 Prüfungen der
Suchverifikation. Beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit --check
und git diff --check bestanden. Acht Chromium-Dialogfälle bestanden; JSON-Download
in vier Suchszenarien mit Dateinamen/Inhalt geprüft. Mobile Darstellung visuell geprüft.

Diagnose-/Datenschutztests, PHP-Regressionstests und Chromium-Download-/Dialogtests gegen synthetische Daten. Angemeldeter Produktionsdownload bleibt gesondert zu prüfen. Laufender PHP-Request kann Download verzögern. Vorherige nicht protokollierte Ausschlüsse können nicht rekonstruiert werden. Diese Version liefert Diagnosefähigkeit, behauptet keine bereits behobene Ursache der 13 Ausschlüsse.

## Deployment und Rückweg

Upload vorbereitet aus Quell-Commit 9bd0764042c610f8d03aa65592971c2899f8ed5f.
Lokal und GitHub vor Vorschlag synchron, Arbeitsstand sauber.
987248 Bytes, SHA-256 2aabd0ea529d0e978f5748ebccc8f552cf46a2e5ae24bf420c6eb34ad358c785.
Remote-Vergleich bestätigt unveränderten vorherigen Stand 2.0.7. Ausführung wartet auf TOTP-Freigabe.

Nur public_html/jobs.jema.business/index.php inklusive generierter Hilfe nach externer TOTP-Freigabe.
Vorheriger bestätigter Stand 2.0.7: Quell-Commit 7d37e9a11bebcff060a5af511fb19151b8e53d93,
971638 Bytes, Modus 0644, SHA-256 7c5c1cbff4f38538989ad6296a97553e15d1305defb7f779304765a6c31cfe86.
Rückweg: separat freigegebener Upload dieses Inhalts. Keine Freigabe-IDs oder Links in Git.
