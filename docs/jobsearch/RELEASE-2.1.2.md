# Version 2.1.2 – KI-Kennzeichnung ohne Kontingentschätzung

Stand: 2026-09-04. Zur externen TOTP-Freigabe vorbereitet.

## Änderung

- Die Fusszeile nennt weiterhin OpenAI und das konfigurierte Modell.
- Die unzuverlässige Prozentangabe zum laufenden App-Kontingent wurde entfernt.
- Die dafür eingeführte lokale Token-/Kostenerfassung und ihre Konfigurationswerte wurden ebenfalls entfernt.
- Das modale Fenster `In Arbeit` mit Abbrechen bleibt unverändert bestehen.

## Prüfung und Deployment

Beide PHP-Syntaxprüfungen, alle 28 PHP-Testdateien, Hilfe und Referenzgeneratoren mit `--check` sowie `git diff --check` bestanden. Externe OpenAI-Aufrufe und die Produktionsdatenbank wurden dabei nicht verändert. Vorheriger bestätigter Live-Stand: Version 2.1.1.
