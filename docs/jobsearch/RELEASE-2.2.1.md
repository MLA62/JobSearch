# Version 2.2.1 – manuelle Inserat-Adresse hat Vorrang

Stand: 07.09.2026. Produktiv bereitgestellt und technisch bestätigt.

## Änderungen

- Eine im Schnellimport bewusst eingegebene HTTPS-Inserat-Adresse gilt als ausdrücklicher Importauftrag.
- Fehlende positive Aktualitätsbelege blockieren eine lesbare, auswertbare Anzeige nicht mehr.
- Die automatische Jobsuche bleibt streng und zeigt weiterhin nur nachweislich verfügbare Anzeigen.
- Drill-down, Originaltext, Firmen-/Kontaktdatenrecherche, Match-Neuberechnung und Dublettenbehandlung bleiben vollständig aktiv.
- Der Mini-Editor ergänzt Aufzählungen, nummerierte Listen, Ein-/Ausrücken und Format löschen.
- Karten und Tabellen zeigen Rich-Text als lesbaren Klartext; nur das Dossier rendert das bereinigte HTML.
- Bewerbungsfilter, Leerzustand und Firmenbezug sind vollständig lokalisiert und enthalten keine technischen Platzhalter mehr.
- KI-Instruktionen gelten erst als ausgeführt, wenn Begleit-E-Mail und Motivationsschreiben nachweislich geändert wurden; ein unveränderter erster Rücklauf wird automatisch wiederholt.

## Nachweis

PHP-Syntax, alle 30 PHP-Testdateien, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff wurden vor der TOTP-Freigabe geprüft. Commit `da4335b90eeda7c842ea811da7470617b169e8e6` wurde nach `public_html/jobs.jema.business/index.php` bereitgestellt. Lokale und produktive Datei sind mit SHA-256 `c40ecd95dc908b1d66449a6a972c4628cf1f4c942836a350faf466121c6805d3` bytegleich. Die öffentliche Seite lieferte HTTP 200 und Version 2.2.1; seit dem Deployment entstand kein neuer PHP-Fehlereintrag. Konfiguration und Datenbankschema blieben unverändert.
