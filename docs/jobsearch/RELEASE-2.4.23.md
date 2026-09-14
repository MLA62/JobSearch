# Release 2.4.23

Stand: 2026-09-14.

## Änderungen

- In der oberen Kalendernavigation steht direkt neben `ICS` neu der Button `PDF`.
- Der Export übernimmt die aktuell gewählte Ansicht und deren Datum beziehungsweise Zeitraum.
- Bei der Agenda übernimmt das PDF zusätzlich die aktiven Feldfilter und die Sortierung.
- Das PDF enthält Zeit, Ereignis, Typ, Status und Bezug der im Resultat enthaltenen Termine.

## Datenbank und Betrieb

Es werden keine fachlichen Tabellen, Datensätze oder Indizes geändert. Die vorhandenen einmaligen
Laufzeitmarker werden für das neue Release fortgeschrieben. Das Deployment benötigt keinen
geplanten Betriebsunterbruch.

## Prüfung

PHP-Syntax, der Kalender-PDF-Vertrag, die vollständige PHP- und Chromium-Testsuite, Hilfe- und
Referenzgeneratoren, Dokumentationsverweise sowie `git diff --check` werden vor dem Deployment
ausgeführt.

## Deployment-Nachweis

- Quell-Commit: `a2c8cfcaa0d2ce02a7d8f007f4d06692a83888f8`
- Externe Freigabe: `327bf2ead428cd293695f24e11ba4a1a`
- Identische erneute Freigabe auf Benutzerwunsch: `6f0c035d215cba080f3e973f1c53bfa5`
- Produktiver SHA-256: `577e059c1814114d45dcad98a0702a2104e156a131a0dce2f5dabec157a2fc02`
- Produktive Dateigrösse: 1'312'019 Bytes, Modus 0644
- Sicherung der Vorgängerversion:
  `approval.lauber.online/storage/file_backups/20260914_155041_99d71b62_public_html_jobs.jema.business_index.php`
- Sicherung vor der identischen erneuten Bereitstellung:
  `approval.lauber.online/storage/file_backups/20260914_190200_d2ad3764_public_html_jobs.jema.business_index.php`
- Die öffentliche Seite liefert HTTP 200 und Version 2.4.23 sowie HSTS, CSP, `nosniff`, `DENY`
  und `no-referrer`. Das produktive Fehlerprotokoll blieb unverändert.
