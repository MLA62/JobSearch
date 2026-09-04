# Version 2.1.2 – KI-Kennzeichnung ohne Kontingentschätzung

Stand: 2026-09-04. Nach externer TOTP-Freigabe deployed.

## Änderung

- Die Fusszeile nennt weiterhin OpenAI und das konfigurierte Modell.
- Die unzuverlässige Prozentangabe zum laufenden App-Kontingent wurde entfernt.
- Die dafür eingeführte lokale Token-/Kostenerfassung und ihre Konfigurationswerte wurden ebenfalls entfernt.
- Das modale Fenster `In Arbeit` mit Abbrechen bleibt unverändert bestehen.

## Prüfung und Deployment

Beide PHP-Syntaxprüfungen, alle 28 PHP-Testdateien, Hilfe und Referenzgeneratoren mit `--check` sowie `git diff --check` bestanden. Externe OpenAI-Aufrufe und die Produktionsdatenbank wurden dabei nicht verändert. Vorheriger bestätigter Live-Stand: Version 2.1.1.

Deployed wurde ausschließlich `public_html/jobs.jema.business/index.php` aus Quell-Commit `230c9da`. Die Produktionsdatei hat 1032117 Bytes, Modus 0644 und SHA-256 `2028a2132343e4175f9ff6e714b8295b042f82b594d36eb5d8414e44021a5a50`; sie entspricht exakt den geprüften lokalen Bytes. Die öffentliche Seite liefert HTTP 200, zeigt Version 2.1.2 und die KI-Kennzeichnung, enthält keine Kontingentanzeige und keinen sichtbaren PHP-Laufzeitfehler.
