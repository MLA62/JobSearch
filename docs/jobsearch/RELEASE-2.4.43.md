# Release 2.4.43 – Job-Room-Detailseiten im Schnellimport

Stand: 21.09.2026. Drei vom Benutzer angegebene URLs der Form
`https://www.job-room.ch/job-search/<UUID>` wurden als «keine einzelne
Stellenanzeige» abgewiesen. Job-Room liefert auf diesen URLs zunächst nur
eine JavaScript-App-Hülle. Die öffentliche Detailansicht lädt Titel,
Arbeitgeber, Originaltext und Publikationsdaten von der zugehörigen
Job-Room-Detail-API. Alle drei gemeldeten IDs waren dort lesend als
`PUBLISHED_PUBLIC` mit vollständigem Titel, Arbeitgeber und Text abrufbar.

Der Import erkennt nur konkrete HTTPS-Detail-UUIDs auf dem Job-Room-Host,
liest deren Detaildaten und prüft ID, öffentlichen Status und Mindestfelder.
Er führt die Angaben anschliessend durch den vorhandenen JobPosting-,
KI-Prüf- und Speicherpfad. Blosse Suchseiten und fremde Hosts bleiben
ausgeschlossen. Bestehende Jobs werden wie bisher nur in leeren Feldern
ergänzt; keine Datenbankmigration oder automatische Bestandsänderung.

## Verifikation und Deployment

Technische und angemeldete Produktivprüfung werden nach der Veröffentlichung
getrennt ergänzt.
