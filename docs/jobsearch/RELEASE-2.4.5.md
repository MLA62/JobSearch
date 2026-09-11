# Version 2.4.5 – Eindeutige TOTP-Anmeldung

Stand: 11.09.2026. Produktiv bereitgestellt und verifiziert.

## Änderungen

- Die Code-Eingabe ist nur während einer ausdrücklich offenen Zwei-Faktor-Challenge aktiv.
- Bereits authentifizierte Sitzungen verwerfen veraltete TOTP-Formulare und kehren ohne falsche
  Ablehnung zum Dashboard zurück.
- Fehlende oder abgelaufene Challenges führen ohne irreführende Code-Meldung zur Anmeldung.
- Ein ungültiger Code kann niemals eine authentifizierte Sitzung erzeugen.
- Der normale Login ohne TOTP entfernt vorsorglich alte Zwei-Faktor-Challenge-Daten.

## Nachweis

Die Zustandslogik wird als reine Funktion für alle drei Zustände getestet. Alle 35 PHP-Testdateien,
3'862 Hilfeprüfungen, 1'279 Hilfe-Seeds, 74 Markdown-Dateien/61 lokale Links, beide Generatoren und alle
sieben Chromium-Testdateien sind erfolgreich. Nach TOTP sind lokale und produktive `index.php`
bytegleich. Die öffentliche Anwendung liefert HTTP 200 und Version 2.4.5; eine Anfrage an eine
veraltete TOTP-Seite ohne Challenge führt mit HTTP 302 direkt zur Anmeldung. Das produktive
Fehlerprotokoll blieb während Deployment und Abnahme unverändert.
