# Release 2.4.17

Stand: 14.09.2026.

## Änderungen

- Reports verwenden keine zusätzliche Aktionsspalte und keinen separaten Datensatzbutton mehr.
- Jeder nicht leere Feldinhalt ist selbst mit dem fachlich passenden individuellen Datensatz verlinkt.
- Firma, Vermittler, Job, Bewerbung und Kontakt öffnen jeweils ihren eigenen Datensatz.
- Die direkte Feldverlinkung gilt für Tabellen, Listen, Karten, Vorschau und Kalendergruppen.
- Die belegte KI-Webrecherche für fehlende Firmenanschriften und Recruiting-Kontakte aus 2.4.16 ist enthalten.

## Verifikation

- PHP-Syntax und vollständige PHP-Vertragstests
- Chromium-Regressionstests aller Reportrenderer bei Desktop- und Mobilbreite
- Hilfekatalog und technische Referenzen
- Produktiver Hash- und HTTP-/Security-Header-Abgleich nach externer TOTP-Freigabe
