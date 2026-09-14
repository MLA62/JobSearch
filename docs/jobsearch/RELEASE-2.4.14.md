# Release 2.4.14

Stand: 14.09.2026.

## Änderungen

- Auswahlfilter in geöffneten Reports verwenden den tatsächlich dargestellten fachlichen Wert.
  Dadurch werden gleich codierte, aber fachlich verschiedene Zustände nicht mehr vermischt.
- Datum, Zahl, Text, Leerwert, jede vorhandene Auswahloption und kombinierte Filter besitzen
  eigenständige Regressionstests; der Browser-Test prüft jede Job-Room-Auswahl in Karten und Tabelle.
- Ist einer Bewerbung ein Primärkontakt zugeordnet, beginnt das KI-generierte Motivationsschreiben
  zwingend mit Firma, Kontaktperson, Strasse sowie PLZ/Ort.
- Die App prüft den Empfängerblock nach der KI-Antwort und ergänzt ihn bei Bedarf. Der lokale
  Ausfallentwurf verwendet dieselbe Regel; vorhandene Blöcke werden nicht dupliziert.

## Daten- und Betriebswirkung

Keine Schema- oder Datenmigration. Das Release ändert Anwendungscode, Tests, Hilfe und Dokumentation.
