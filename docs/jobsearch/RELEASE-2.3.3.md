# Version 2.3.3 – Admin-KI führt Plattformoperationen aus

Stand: 2026-09-09. Dieses Release behebt den Fehler, dass die Admin-KI nur einen langen
Recherchetext lieferte, aber den ausdrücklich verlangten Datensatz nicht erfasste.

## Änderungen

- Die Admin-KI verlangt ein striktes JSON-Operationsschema statt unstrukturiertem Freitext.
- Ausdrücklich beauftragte Recherchen mit Erfassen, Speichern, Importieren, Aktualisieren oder
  Mehrfachoperationen werden vollständig in einer Transaktion ausgeführt.
- Die Operationen decken die freigegebenen Nutzer-Datentabellen ab: Profil-/Suchdaten, Firmen,
  Kontakte, Jobs, Bewerbungen, Dokumentmetadaten, Kalender, Logs, Tags, Vorlagen und Berichte.
- Tabellen, Spalten und Referenzen sind serverseitig allow-gelistet und werden über gebundene Werte
  geschrieben. Bestehende Datensätze werden nur ergänzt; gelöschte Datensätze werden nie reaktiviert.
- Sicherheits-, Geheimnis- und Auditdaten, Löschungen, Identitätswechsel und das Versenden von
  E-Mails bleiben gesperrt.
- Der Admin-Kontext und das kompakte Ausführungsprotokoll bleiben sitzungsgebunden bis
  «Gedächtnis löschen»; Ausgabe und Eingabe bleiben ohne Seitenwechsel sichtbar.
- Hilfequelle, fünf generierte Sprachfassungen, Anforderungen, Workflow, Tests und Audit sind
  auf 2.3.3 nachgeführt.

## Qualität

- PHP-Lint ohne Fehler.
- Kernverträge für KI-Fortschritt, strukturiertes Operationsschema, Bewerbungstexte und Security
  bestanden.
- Hilfe-Generator: 25 Themen in fünf Sprachen; 3'785 Inhaltsprüfungen bestanden.
- Produktivstatus und Hash werden nach der externen TOTP-Freigabe in `DEPLOYMENT.md` ergänzt.
