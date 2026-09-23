# Release 2.4.49 – sichtbare Absatzabstände

Stand: 23.09.2026

## Änderung

- Absatz und H1–H3 erhalten 9 pt Abstand danach. Bei 12 pt Grundschrift
  und Zeilenhöhe 1,5 ist das ungefähr eine halbe Textzeile.
- Begleittexte und Motivationsschreiben aus älteren Daten oder der KI,
  die nur `<br>`-Zeilen enthalten, werden im Editor als echte Absätze
  dargestellt.
- Anschrift sowie Schlussformel und Name bleiben jeweils kompakte Blöcke
  mit weichen Zeilenumbrüchen.
- Neue KI-Texte werden vor dem Speichern gleich normalisiert. Bestehende
  Daten werden nicht pauschal migriert; die normalisierte Form wird beim
  normalen Speichern übernommen.
- Die Joblisten-Sortierung «Match» verwendet wieder den numerischen
  Prozentwert statt versehentlich das Änderungsdatum.
- Importierter Klartext mit `**Fettschrift**` und Markdown-Aufzählungen
  wird im Editor als echte Rich-Text-Formatierung angezeigt.

## Datenbankwirkung

Keine Schemaänderung und keine direkte Datenmigration.

## Prüfung

- PHP-Syntax und vollständige PHP-Vertragstests
- Chromium-Prüfung der berechneten Abstände und Editorsemantik
- Hilfe- und Referenzgeneratoren
- Hashvergleich, öffentliche Versionsprüfung und angemeldete Sichtprüfung
  nach dem Deployment
