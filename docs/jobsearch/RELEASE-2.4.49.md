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

## Deploymentnachweis

- Git-Commit: `9e70f8a`
- Ziel: `public_html/jobs.jema.business`
- Vorab-Backups:
  `index.php.bak-20260923-2.4.48-pre-2.4.49` und
  `assets/app.css.bak-20260923-2.4.48-pre-2.4.49`
- Backup-Freigaben: `8ba83679d707fb6610b3173d64d4884b`,
  `7d54b8d6a87edf1466827f5d0905665f`
- Deployment-Freigabe: `5cfcdf4819be392e28314880b9d967ec`
- Produktiver SHA-256 `index.php`:
  `a9aa674c2e4d4cf6153a3efd9e2bede01449ad569649af7b8c991badc7323af1`
- Produktiver SHA-256 `assets/app.css`:
  `fd783975959246ac44dcde3cdc7258d15a1b5dd135363d6b3be7d791a6136ed2`
- Beide Hashes stimmen mit dem lokalen Release überein. Die öffentliche
  Seite zeigt Version 2.4.49. Die bestehende Browsersitzung war bei der
  abschliessenden Prüfung abgemeldet; eine datensatzbezogene, angemeldete
  Sichtprüfung ist deshalb noch nicht als durchgeführt dokumentiert.

Der erste Einzeltext-Transfer von `index.php` erreichte die Connector-
Grössengrenze. Die gekürzte Datei wurde sofort aus dem unmittelbar zuvor
erstellten Backup wiederhergestellt. Danach wurde das geprüfte ZIP-Paket
mit integrierter Zusatzsicherung vollständig extrahiert; die nicht aktive
Teilübertragungsdatei wurde über Freigabe
`c31f726914f82d520282338954e626fe` entfernt. Es entstand keine
Datenbankwirkung.
