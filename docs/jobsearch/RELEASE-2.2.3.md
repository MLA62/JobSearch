# Version 2.2.3 – HTML-Änderungen zuverlässig übernehmen

Stand: 07.09.2026

## Änderungen

- In der HTML-Ansicht eingegebene Änderungen werden beim Zurückschalten sichtbar in den WYSIWYG-Editor übernommen.
- Speichern, Autosave und KI-Aktionen verwenden bei offener HTML-Ansicht deren aktuellen Inhalt statt des älteren visuellen Editorinhalts.
- HTML wird erst beim Synchronisieren bereinigt; unvollständiger Quelltext wird nicht mehr während jedes Tastendrucks normalisiert.
- Versionsnummer, Anforderungen, Programmdokumentation, Workflow, Prüfplan und In-App-Hilfe sind auf 2.2.3 nachgeführt.

## Prüfung und Bereitstellung

PHP-Syntax, alle 31 automatisierten PHP-Testdateien, 3'639 Hilfeinhalte, 1'204 Hilfe-Seeds sowie
die generierten Hilfe- und Referenzdokumente wurden geprüft. Quell-Commit
`c0a096170fa2fa5774778c58a0ed37a70619bbf3` wurde nach externer TOTP-Freigabe nach
`public_html/jobs.jema.business/index.php` bereitgestellt. Lokale und produktive Datei sind mit
SHA-256 `c23ee604d4948ca4ce1df92e0895a8d5ece75f53484dd5eb063eb2f161fa482e` bytegleich. Die
öffentliche Seite liefert HTTP 200 und Version 2.2.3; seit dem Deployment entstand kein neuer
PHP-Fehlereintrag. Konfiguration und Datenbankschema blieben unverändert.
