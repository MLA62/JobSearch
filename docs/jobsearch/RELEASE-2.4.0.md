# Version 2.4.0 – Selbstständig nachfassender Admin-KI-Agent

Datum: 09.09.2026

## Verhalten

- Admin-KI-Aufträge werden in bis zu vier selbstständigen Runden geplant, ausgeführt und geprüft.
- Konkrete Datenbankfehler gehen als strukturierte Rückmeldung an die nächste Runde; die KI korrigiert
  Feldnamen, Abgleichwerte und fehlende Abhängigkeiten selbst.
- Nach dem ersten erfolgreichen Schreibvorgang prüft eine weitere Runde den neu geladenen Datenbestand.
- Ein Änderungsauftrag gilt nur als abgeschlossen, wenn mindestens ein Schreibvorgang ausgeführt und die
  Wirkung anschliessend bestätigt wurde.
- Profil, aktive Suchpräferenzen, Sprachkenntnisse und der aktuelle Lebenslauf stehen der KI bei passenden
  Aufträgen als benutzerisolierter Kontext zur Verfügung.
- «Vorschlag erstellen» im Schnellimport öffnet sofort einen modalen Arbeitsdialog mit Laufzeit,
  Fortschrittszähler, chronologischem Verlauf je Anzeige und Abbrechen. Mehrfachimporte werden dafür
  Anzeige für Anzeige verarbeitet.

## Fehlerkorrekturen

- «Erstelle» und weitere Änderungsformulierungen werden zuverlässig als Schreibauftrag erkannt.
- Unvollständige `record_lookup`-Hilfsoperationen brechen gültige Schreiboperationen nicht mehr ab.
- Fehlende Abgleichwerte werden aus erlaubten Operationsfeldern abgeleitet; benutzereindeutige Tabellen
  können ohne künstliches Abgleichfeld geprüft werden.
- Die abschliessende Fehlermeldung nennt nach vier erfolglosen Runden das letzte konkrete Hindernis.

## Sicherheit und Grenzen

- Alle Tabellen- und Feldnamen bleiben serverseitig allowlist-gesteuert, Werte werden gebunden.
- Jede Runde arbeitet ausschliesslich mit Daten des angemeldeten Benutzers.
- Der Agentenlauf ist auf vier Runden begrenzt; Abbrechen im Arbeitsdialog beendet die Browseranfrage.

## Prüfung

- PHP-Syntax, vollständige PHP-Suite, Dokumentations-/Hilfetests und Chromium-Browsertests.
- Regressionstest für den gemeldeten generativen Jobprofil-Auftrag.
- Chromium-Regressionsprüfung für den zuvor reaktionslosen Schnellimport-Button und dessen Verlauf.
- 34 PHP-Testdateien sowie alle sieben Chromium-Testdateien sind erfolgreich.
- TOTP-Deployment `a1848c8c51dc460a8ae19a61e1933580` wurde ausgeführt; alle fünf
  Produktivdateien sind bytegleich mit dem getesteten Stand.
- Die öffentliche Abnahme bestätigt HTTP 200, Version 2.4.0 und die vorgesehenen Sicherheitsheader.
- Das Release verändert kein Datenbankschema; offene Schnellimport-Läufe liegen nur befristet in der
  jeweiligen Benutzersitzung.
