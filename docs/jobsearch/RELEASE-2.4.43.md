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

Nachträgliche Erweiterung: Auch kopierte Job-Room-Trefferlisten ohne URLs
werden in einzelne Karten zerlegt. Die App sucht zu jeder Karte das
veröffentlichte Original und unterscheidet gleichnamige Stellen mittels
Datum und Beschreibung. In der zweiten Benutzerprobe wurden 34 datierte
Karten erkannt; 33 enthalten Firma, Arbeitsort und Text, die letzte ist
bereits in der gelieferten Kopie abgeschnitten. Die 33 öffentlichen Suchen
lieferten passende Kandidaten; diese lesende Prüfung bestätigt keine
Produktivübernahme. Unvollständige oder mehrdeutige Karten werden im
Schnellimport einzeln als Fehler ausgewiesen.

Lokal geprüft: PHP-Syntax, 57 PHP-Tests und beide Dokumentationsgeneratoren.
Der separate Playwright-Dialogtest konnte in dieser Arbeitsumgebung mangels
installiertem `playwright`-Modul nicht gestartet werden.

## Verifikation und Deployment

Der zuvor öffentlich erreichbare Stand 2.4.42 wies alle drei Detail-URLs
weiterhin ab. Der Fehler lag damit auch an der fehlenden Produktivveröffentlichung
des bereits korrigierten Quellstands.

Vor dem Austausch wurde die bisherige produktive `index.php` als
`index.php.bak-20260921-2.4.42-pre-2.4.43` auf dem Server gesichert.
Danach wurde `public/index.php` aus Commit `03909af` als Version 2.4.43
veröffentlicht. Der SHA-256-Wert der produktiven Datei stimmt mit dem
lokalen Release überein:
`6ccbf04b17ee17b0c167f69ef8e377a329a9482db9b3a402aed8c0ce26d87a85`.
Die öffentliche Startseite antwortet mit HTTP 200 und zeigt Version 2.4.43.

Die App-Sitzung im Browser war bei der anschliessenden Kontrolle abgemeldet.
Ein angemeldeter Schnellimport und die Speicherung der 33 vollständigen
Treffer sind daher **noch nicht produktiv verifiziert**. Es wird nicht
behauptet, dass diese Jobs bereits importiert wurden.
