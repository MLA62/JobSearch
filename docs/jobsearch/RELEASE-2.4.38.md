# Not-Update 2.4.38 – vorhandene Bewerbungstexte schützen

Stand: 21.09.2026. Anlass: Ein blosses Öffnen der Bewerbungsseite konnte
Begleit-E-Mail und Motivationsschreiben bereits bereinigen oder den
Empfängerblock ändern. Ein KI-Klick ohne Instruktion ersetzte alle Texte.

## Änderung

- Die Bewerbungsseite liest vorhandene Texte, ohne sie zu initialisieren.
- Die Vorbereitung ergänzt ausschliesslich leere Textfelder; gefüllte
  Felder behalten ihren exakten Inhalt.
- Der KI-Button ohne Instruktion ergänzt nur leere Felder. Sind alle drei
  Felder gefüllt, schreibt er nichts und fordert eine Änderungsanweisung an.
- Eine ausdrücklich eingegebene KI-Instruktion kann vorhandene Texte
  weiterhin gezielt überarbeiten. Manuelles Speichern bleibt möglich.
- Keine Datenmigration, kein automatischer E-Mail-Versand.

## Verifikation und Deployment

Lokale PHP-Syntax, 54 PHP-Tests sowie Hilfe- und Referenzgenerator wurden
geprüft. `application_text_preservation_test.php` sichert den lesenden
Aufruf und den Fill-only-Abgleich ab. Quell-Commit, Backup, Approval-ID,
produktiver SHA-256-Hash und Live-Verifikation werden nach der
Bereitstellung ergänzt. Eine authentifizierte funktionale Abnahme ist
separat auszuweisen.
