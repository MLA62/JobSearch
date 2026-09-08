# Version 2.3.1 – hellere Tabellen- und Kartentexte

Stand: 08.09.2026. Status: produktiv deployed und nach externer TOTP-Freigabe verifiziert.

## Änderungen

- Inhaltsbereiche in Tabellen und Karten verwenden ein helleres Schiefergrau mit weiterhin gutem Kontrast.
- Überschriften bleiben mit einer separaten, etwas kräftigeren Textfarbe klar unterscheidbar.
- Die Versionsnummer 2.3.1 bustet den Browser-Cache für die geänderten CSS-Assets.

## Prüfung

- Responsive Button-, Firmenadress-, Hilfe- und Workflow-Tests erfolgreich.
- Keine Änderungen an Datenmodell, Benutzerdaten oder Secrets.

## Produktivnachweis

- Commit: `ecb39d2`.
- `index.php`: SHA-256 `683fe14737453319db535d210a128abb4e72d0775bd1e247cff0a8facf16abc`.
- `app.css`: SHA-256 `78efab6f67a36f00a5d71f545fa09e6cae408bbbe74766b48a01b7082d3179f5`.
- HTTPS-Prüfung: HTTP 200, Version 2.3.1, HSTS/CSP aktiv und neues CSS ausgeliefert.
