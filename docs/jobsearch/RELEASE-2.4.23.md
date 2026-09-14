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

Wird nach der produktiven Freigabe ergänzt.
