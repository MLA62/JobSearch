# Version 2.4.1 – Eindeutige Firmen-Bewerbungszahl

Datum: 09.09.2026

## Korrektur

- Eine Firma zeigt nur Bewerbungen zu ihren eigenen aktiven Jobs.
- Gelöschte Bewerbungen, gelöschte Jobs und gelöschte Firmen werden nicht gezählt oder gelistet.
- Eine Vermittler-Verknüpfung wird nicht als Bewerbung bei der Vermittlerfirma gezählt.
- Der Link von der Firmenliste verwendet dieselbe Definition wie die angezeigte Anzahl.

## Prüfung

- Der Vertragstest prüft Zählabfrage, Vermittlerausschluss, Aktivfilter und Zielfilter gemeinsam.
- 34 PHP-Testdateien, Hilfe-/Dokumentationsprüfungen und alle sieben Chromium-Testdateien sind erfolgreich.
- Produktivhash, TOTP-Freigabe und HTTP-Abnahme werden nach dem Deployment ergänzt.
