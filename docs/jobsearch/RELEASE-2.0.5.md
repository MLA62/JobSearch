# Version 2.0.5 – Originalausschreibung und Arbeitgeberdaten

Stand: 2026-09-04. Lokale Vorbereitung; noch nicht deployed.

## Änderungen

- Erkannte Portal-Links bis zur Originalausschreibung verfolgen; offensichtliche Titelabweichungen ablehnen.
- Originaltext aus dem tatsächlichen Inserat, zusätzliche Kontaktpersonen und belegte Arbeitgeberwebsite übernehmen.
- Fehlende Firmenadressfelder aus dem eindeutig benannten Adressblock der verlinkten Arbeitgeberwebsite ergänzen. Bestehende Firmenwerte erhalten.
- Bewusster AI-Reimport korrigiert auch die Firmenzuordnung. Firma, Job, Kontakte und Audit werden im Übernehmen-Handler gemeinsam gespeichert oder zurückgerollt.
- Keine weitere Erzeugung künstlicher Tabellen-PDFs unter der Bezeichnung Originalausschreibung. Vorhandene Dateien bleiben erhalten; manueller Originaldatei-Upload bleibt möglich.
- Hilfe in allen fünf Sprachen, Anforderungen und Programmdokumentation aktualisiert.

## Infrastruktur und Grenzen

Playwright/Chromium ist lokal startbar, jedoch nicht auf dem geprüften cPanel-PHP-Hosting verfügbar.
Die am 04.09.2026 freigegebene lesende Laufzeitdiagnose meldete PHP 8.2.33, keine Node-/Chromium-Binärdatei an den geprüften Standardpfaden sowie gesperrte proc_open/exec/shell_exec.
Ein separater Host steht laut Benutzer nicht zur Verfügung; keine kostenpflichtigen Dienste zulässig.
Deshalb wird weder PDF noch PNG der tatsächlichen Browseransicht automatisch erzeugt.
JS-only-/gesperrte Originalanzeigen können weiterhin scheitern; die Erkennung ist auf die dokumentierten Strukturen begrenzt.
Die temporäre Diagnose ist token- und zeitgeschützt; Entfernung ist nicht Teil dieses App-Uploads.

## Prüfung und Freigabe

- Alle 21 PHP-Testdateien bestanden; 48 Speicherprüfungen mit simulierter DB.
- PHP-Syntax von index.php und config.example.php sowie beide Generatoren mit --check und git diff --check bestanden.
- Öffentliche Beispielseiten erneut lesend abgerufen: Der echte Produktionsparser findet Original-Link, tatsächlichen Arbeitgeber, beide Kontaktpersonen, die belegte Firmenadresse und den vollständigen Abschnitt. Keine echten Inserat-/Kontaktdaten als Testdateien im Repository gespeichert.
- cURL-Abruf auf dem Produktionsserver und echte DB-Transaktion nicht durch diese lokalen Fixtures nachgewiesen.
- Quell-Commit und Proposal werden nach dem Commit separat im Freigabe-Audit festgehalten.
Produktive angemeldete Funktionsabnahme ist nicht ausgeführt.
Keine Server-Secrets verändert, keine Schemaänderung, keine automatische Bestandsmigration.
Bestehende Firmen, Kontakte und Dokumente werden beim Deployment selbst nicht verändert.

## Deployment und Rückweg

Ziel ist ausschließlich public_html/jobs.jema.business/index.php, inklusive generierter Hilfetexte.
Vorhandener Stand: Release 2.0.4, Quell-Commit a55053f, SHA-256
42fa72191b5e1a237fa8225e8d050c300d9f1b8b2c1fabc171a2028ec100343b, Größe 913975 Bytes, Modus 0644.
Rückweg: exakte alte index.php aus diesem Commit nach erneuter TOTP-Freigabe wiederherstellen.
Der Code-Rollback macht bewusst vom Benutzer ausgeführte Imports nicht rückgängig.
Keine produktive Ausführung behaupten, bevor die exakte Proposal-Freigabe ausgeführt und der Zielhash geprüft wurde.
