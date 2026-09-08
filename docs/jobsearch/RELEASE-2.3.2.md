# Version 2.3.2 – Admin-KI und statusbezogene Darstellung

Stand: 08.09.2026. Status: lokal geprüft; produktives Deployment wartet auf externe TOTP-Freigabe.

## Änderungen

- Nur Jobs mit Status `rejected` sowie Bewerbungen, die einem solchen abgesagten Job zugeordnet sind, erhalten die hellere Inhaltsfarbe. Andere Karten und Tabellen bleiben im bisherigen Standardkontrast.
- Unter Konto steht ausschließlich Admins im eigenen Konto die JeMa-Jobs-KI-Konsole zur Verfügung.
- Plattformgebundene KI-Anweisungen unterstützen prüfbare Dry-Run-Pläne für Massenoperationen sowie öffentliche Adress- und Kontaktrecherche. Es gibt keine automatischen Datenbankänderungen, E-Mails oder externen Aktionen.
- Das Ausgabe-Feld nutzt 80% der Bildschirmhöhe, ist mehrzeilig, umbrechend und vertikal scrollbar; das Eingabefeld nutzt 10%.
- Hilfe, Versionierung und Cache-Busting wurden auf 2.3.2 aktualisiert.

## Sicherheit

- Admin-Eigenkonto und keine Support-Impersonation erforderlich.
- API-Antworten werden nicht gespeichert (`store=false`); Zugangsdaten, TOTP-Codes und private Konfiguration werden ausdrücklich ausgeschlossen.
