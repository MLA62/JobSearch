# Version 2.2.1 – manuelle Inserat-Adresse hat Vorrang

Stand: 07.09.2026. Deployment vorbereitet; bestätigter Live-Vorgänger ist Version 2.2.0.

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

PHP-Syntax, alle PHP-Testdateien, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff werden vor der TOTP-Freigabe geprüft. Das Deployment ersetzt ausschließlich `public_html/jobs.jema.business/index.php`; Konfiguration und Datenbankschema bleiben unverändert.
