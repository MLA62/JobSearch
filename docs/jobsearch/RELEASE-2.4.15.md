# Release 2.4.15

Stand: 14.09.2026.

## Änderungen

- Vor jeder Bewerbungsvorbereitung wird die Originalausschreibung erneut über die KI-API analysiert.
- Belegte Firmen-, Adress-, Kontakt- und Jobdaten ergänzen bestehende Datensätze fill-only.
- Vorhandene Kontakte werden zentral aus Primärkontakt, Bewerbung, Job oder Firma aufgelöst.
- Der Empfängerblock wird auch in bereits vorhandenen Motivationsschreiben geprüft und korrigiert.
- Ein alter unvollständiger Adressblock wird ersetzt und nicht dupliziert.
- Manuelle Inseratimporte benötigen ebenfalls ein gültiges strukturiertes KI-Ergebnis.

## Verifikation

- PHP-Syntax und vollständige PHP-Vertragstests
- Chromium-Regressionstests bei Desktop- und Mobilbreite
- Hilfekatalog und technische Referenzen
- Produktiver Hash- und HTTP-/Security-Header-Abgleich nach externer TOTP-Freigabe
