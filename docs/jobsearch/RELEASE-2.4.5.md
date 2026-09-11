# Version 2.4.5 – Eindeutige TOTP-Anmeldung

Stand: 11.09.2026. Für das Deployment vorbereitet.

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
sieben Chromium-Testdateien sind erfolgreich. Nach TOTP wird die Produktivdatei bytegenau mit dem
Release-Stand verglichen.
