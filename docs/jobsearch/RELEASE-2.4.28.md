# Release 2.4.28 – Einzelwerte im Job-Room-Filter

## Änderung und Ursache

Die Mehrfachauswahl in 2.4.27 verwendete den kompletten sichtbaren Status als
Filteroption. Dadurch wurde «Noch offen · Vorstellungsgespräch» fälschlich als
eigene Kombination angeboten und «Noch offen» schloss den Gesprächsfall aus.
Die neue Beschriftung `reports.filter_any_of` erschien als technischer Schlüssel,
weil der DB-Katalog keinen Eintrag hatte und `tr()` nicht auf den bereits
generierten fünfsprachigen Katalog zurückfiel.

2.4.28 filtert unabhängig nach Erfassung, Resultat und Gespräch. Innerhalb des
Feldes gilt weiterhin ODER, über verschiedene Felder UND. Die Reportanzeige
bleibt unverändert kombiniert. Der Übersetzungsfallback berücksichtigt nun
generierte Texte, ohne vorhandene DB-Übersetzungen zu überschreiben.

## Datenwirkung und Rückweg

Keine Schemaänderung, keine Änderung an produktiven Datensätzen oder gespeicherten
Reports. Bisherige URLs mit vollständigem Anzeigetext werden weiterhin exakt
gefiltert. Vor dem produktiven Austausch wird die Version 2.4.27 von `index.php`
im selben Document Root gesichert. Die CSS-Datei bleibt unverändert.

## Prüfung

- 18.09.2026: PHP-Syntax und alle 45 PHP-Testdateien ohne Fehler.
- Generierte Hilfe in fünf Sprachen und Referenzen mit `--check` bestätigt.
- Chromium-Test des Reportfilters in sieben Darstellungsarten bei 390 und 1366 px
  bestanden: Einzelwerte statt Kombinationen, offene Fälle inklusive Gespräch,
  ODER-Auswahl, Datum/Zahl/Text, Ansichtswechsel und kein Seitenüberlauf.
- Quell-Commit: `552b4cf80af86afe2fb64765bb40f4409cbc0ee6`.
- Produktiver Pfad: `public_html/jobs.jema.business/index.php`, Document Root
  durch cPanel als `/home/kerubina/public_html/jobs.jema.business` bestätigt.
  Vor dem Austausch: 2.4.27 mit SHA-256
  `aca8793733fa4fc015abfbd4e6da5f86354b707cd36ea8a9fc5f77d1f4849708`.
- 18.09.2026, 12:35 UTC: Sicherung
  `index.php.bak-20260918-1436-2.4.27` über freigegebenes Proposal
  `ed48e29decfe6ee73173e3e8bc067719` erstellt und mit demselben Hash
  bestätigt. Freigegebener Upload `f9eaaf04f6ecd8cfbb38178da1eda746`
  verwendete die noch aktive externe TOTP-Sitzung. Die produktive Datei stimmt
  bytegenau mit der lokalen Prüfdatei überein: SHA-256
  `b6b19877da27b18ec4a2a5241b9b3a0da67bbd75cd11a4659d88ade3d43584fd`,
  Dateigrösse 1'329'595 Byte, Berechtigung `0644`.
- Angemeldete Live-Abnahme: Footer Version 2.4.28; im geöffneten Report
  `view_report=2` sind «Absage», «Im Job-Room erfasst», «Noch offen» und
  «Vorstellungsgespräch» einzelne Optionen. `reports.filter_any_of` erscheint
  nicht mehr roh. Allein «Noch offen» lieferte zehn Zeilen, darunter Cleeven
  mit «Noch offen · Vorstellungsgespräch». Zusätzlich «Absage» ergab 16 Zeilen;
  beide Häkchen und Treffer blieben beim Wechsel zu Karten erhalten.
- DB-Effekt: keiner. Weder Migrations- noch Datensatzschreibaktion ausgeführt.
