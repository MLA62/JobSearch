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
- TOTP-Deployment `c932bad9a92095ff16f94d5b5a76840e` wurde ausgeführt; alle fünf
  Produktivdateien sind bytegleich mit dem getesteten Stand.
- Die öffentliche Abnahme bestätigt HTTP 200, Version 2.4.1 und die vorgesehenen Sicherheitsheader.
- Das Release verändert weder Daten noch Datenbankschema.
