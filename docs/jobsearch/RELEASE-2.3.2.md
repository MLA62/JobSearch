# Version 2.3.2 – Admin-KI und statusbezogene Darstellung

Stand: 09.09.2026. Status: produktiv deployed und per Hash/HTTPS verifiziert. Commit: `a391e99b47120082955318411ea055866c2da518`.

## Änderungen

- Nur Jobs mit Status `rejected` sowie Bewerbungen, die einem solchen abgesagten Job zugeordnet sind, erhalten die hellere Inhaltsfarbe. Andere Karten und Tabellen bleiben im bisherigen Standardkontrast.
- Unter Konto steht ausschließlich Admins im eigenen Konto die JeMa-Jobs-KI-Konsole zur Verfügung.
- Plattformgebundene KI-Anweisungen unterstützen prüfbare Dry-Run-Pläne für Massenoperationen sowie öffentliche Adress- und Kontaktrecherche. Es gibt keine automatischen Datenbankänderungen, E-Mails oder externen Aktionen.
- Das Ausgabe-Feld nutzt 80% der Bildschirmhöhe, ist mehrzeilig, umbrechend und vertikal scrollbar; das Eingabefeld nutzt 10%.
- Hilfe, Versionierung und Cache-Busting wurden auf 2.3.2 aktualisiert.

- Deployment nach `public_html/jobs.jema.business` mit Approval-ID `520a958fd5ac813d3d2adcb723433f3b`; Backup der fünf überschriebenen Dateien wurde durch cPanel erstellt.
- Live geprüft: HTTP 200, Version 2.3.2, HSTS, CSP, `nosniff`, `DENY`, `no-referrer`, Admin-KI- und `is-rejected`-CSS.

## Sicherheit

- Admin-Eigenkonto und keine Support-Impersonation erforderlich.
- API-Antworten werden nicht gespeichert (`store=false`); Zugangsdaten, TOTP-Codes und private Konfiguration werden ausdrücklich ausgeschlossen.
