# Browser-gerenderte Job-PDFs - Migration vom 2026-06-16

Dieses Dokument ist ein historischer Produktionsnachweis. Es beschreibt die
Migration bestehender Job-PDFs von flachen Text-Snapshots auf echte
browsergerenderte PDFs.

## Anlass

Fruehe Job-PDFs waren einfache Text-PDFs. Fuer die produktive Nutzung mussten
die Original-Stellenanzeigen realistischer dokumentiert werden. Deshalb wurden
die vorhandenen PDFs mit einem Chromium-kompatiblen Browser neu gerendert und
als Dokumente zu den Jobs abgelegt.

## Ergebnis

- Gepruefte Jobs: 10
- Browser-gerenderte PDFs hochgeladen: 10
- Live-Dokumentdatensaetze erstellt: 10
- Vorherige Text-PDF-Datensaetze ersetzt: 10
- Fehlgeschlagen: 0

## Live-Dokumentdatensaetze

| Job ID | Document ID | Datei | Groesse in Byte |
| --- | ---: | --- | ---: |
| 3 | 11 | job-3-original-rendered.pdf | 21239 |
| 4 | 12 | job-4-original-rendered.pdf | 246179 |
| 5 | 13 | job-5-original-rendered.pdf | 1087513 |
| 6 | 14 | job-6-original-rendered.pdf | 187926 |
| 7 | 15 | job-7-original-rendered.pdf | 140765 |
| 8 | 16 | job-8-original-rendered.pdf | 339606 |
| 9 | 17 | job-9-original-rendered.pdf | 811544 |
| 10 | 18 | job-10-original-rendered.pdf | 138368 |
| 11 | 19 | job-11-original-rendered.pdf | 953199 |
| 12 | 20 | job-12-original-rendered.pdf | 110708 |

## Aktueller Produktstandard

- Neue Jobs speichern den Original-PDF-Status.
- Original-PDFs sollen serverseitig oder worker-seitig mit einem echten Browser
  gerendert werden.
- Der Ablauf darf nicht vom Geraet des Benutzers abhaengen.
- Der Worker `deploy/render-pending-job-pdfs.php` ist der vorgesehene
  Automatisierungspfad.
- Fehlgeschlagene Renderings duerfen den Jobimport nicht blockieren; der Status
  muss sichtbar bleiben.

## Betriebsnotizen

- Der temporaere Live-Registrierungsendpunkt aus der Migration wurde nach
  Verifikation geloescht.
- Die Migration ist abgeschlossen und soll nicht erneut ausgefuehrt werden.
- Fuer neue Umgebungen ist diese Datei nur Nachweis und Referenz, kein
  Installationsschritt.
