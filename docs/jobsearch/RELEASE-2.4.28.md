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
- Die angemeldete Live-Prüfung und der Deployment-Hash werden erst nach dem
  freigegebenen Upload als eigener Nachweis ergänzt.
