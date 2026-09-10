# Version 2.4.2 – Lesbare Firmenlinks und datierter Job-Room

Datum: 10.09.2026

## Korrekturen

- In der Firmenliste stehen Jobs, Bewerbungen und Kontakte jeweils vollständig in einer eigenen Zeile.
- Zähler und Bezeichnung eines Links bleiben auch bei schmalen Ansichten zusammen.
- Der Job-Room-Helper zeigt nur Bewerbungen mit einem tatsächlichen Bewerbungsdatum.
- Bewerbungen ohne `applied_at` werden vollständig ausgeblendet; es wird weder ein Ersatzdatum noch
  ein Hinweisdatensatz angezeigt.

## Prüfung

- PHP-Vertragstests prüfen CSS-Vertrag, Job-Room-Abfrage und Monatsauswahl.
- Chromium misst die Linkdarstellung bei 390, 1000 und 2048 Pixeln.
- Alle 34 PHP-Testdateien, Hilfe-/Dokumentationsprüfungen und alle sieben Chromium-Testdateien sind erfolgreich.
- Das TOTP-Deployment und die anschließende Produktivprüfung stehen noch aus.
- Das Release verändert weder Daten noch Datenbankschema.
