# Release 2.4.18

Stand: 2026-09-14.

## Ergebnis

`Bewerbung vorbereiten` wird nicht mehr wegen einer widersprüchlichen Firmenzuordnung oder einer
fehlgeschlagenen optionalen KI-/Webanreicherung abgebrochen. Die gewählte Stelle bleibt das Ziel;
eine verifiziert abweichende Arbeitgeberfirma korrigiert die Stellenzuordnung, ohne den bisherigen
Firmendatensatz zu überschreiben. Danach wird die Bewerbung angelegt beziehungsweise geöffnet und
mit KI-Texten oder bearbeitbaren lokalen Grundentwürfen fortgesetzt.

## Technische Änderungen

- `target_job_id` bindet die erneute Analyse an den ausdrücklich gewählten Job.
- Arbeitgeberkorrekturen ändern nur die Relation des Jobs und werden mit alter und neuer Firmen-ID
  auditiert.
- Analyse- und Empfängerrecherchefehler werden protokolliert, blockieren die Bewerbung aber nicht.
- Kontakte einer früheren, nicht mehr beteiligten Firma sind kein gültiger Empfänger mehr.
- Die frühere Verweigerungsmeldung für die Inseratanalyse wird nicht mehr verwendet.

## Datenwirkung

Keine Schema- oder pauschale Bestandsmigration. Beim bewussten Vorbereiten einer Bewerbung können
belegte fehlende Firmen-/Kontaktdaten ergänzt und der ausgewählte Job einer verifiziert richtigen
Firma zugeordnet werden. Bestehende Firmendaten werden nicht überschrieben, gelöschte Datensätze
werden nicht reaktiviert.

## Prüfung

PHP-Syntax, fokussierte Import-, Empfänger- und Bewerbungstests, vollständige PHP-Testreihe,
Hilfe-/Referenzgeneratoren, Dokumentationsprüfung und Browserregressionen sind vor dem Deployment
auszuführen. Produktiver Hash, Version und Sicherheitsheader werden nach externer Freigabe geprüft.
