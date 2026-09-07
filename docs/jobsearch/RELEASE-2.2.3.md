# Version 2.2.3 – HTML-Änderungen zuverlässig übernehmen

Stand: 07.09.2026

## Änderungen

- In der HTML-Ansicht eingegebene Änderungen werden beim Zurückschalten sichtbar in den WYSIWYG-Editor übernommen.
- Speichern, Autosave und KI-Aktionen verwenden bei offener HTML-Ansicht deren aktuellen Inhalt statt des älteren visuellen Editorinhalts.
- HTML wird erst beim Synchronisieren bereinigt; unvollständiger Quelltext wird nicht mehr während jedes Tastendrucks normalisiert.
- Versionsnummer, Anforderungen, Programmdokumentation, Workflow, Prüfplan und In-App-Hilfe sind auf 2.2.3 nachgeführt.

## Prüfung und Bereitstellung

Die Bereitstellung erfolgt nach PHP-Syntaxprüfung, vollständiger automatisierter Testsuite sowie
Prüfung der generierten Hilfe und Referenzdokumente über den externen TOTP-Freigabeablauf.
