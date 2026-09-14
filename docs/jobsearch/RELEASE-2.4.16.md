# Release 2.4.16

Stand: 14.09.2026.

## Änderungen

- Fehlende Firmenanschriften und Recruiting-Kontakte lösen nach der KI-Inseratanalyse automatisch eine zweite KI-Webrecherche aus.
- Bevorzugt werden offizielle Unternehmens-, Karriere-, Impressums- und Registerseiten.
- Jede von der KI zitierte Fundstelle wird von der App selbst abgerufen und gegen den angegebenen Textbeleg geprüft.
- Belegte Daten ergänzen Firmen und Kontakte ausschließlich in leeren Feldern.
- Derselbe Recherchepfad läuft beim Öffnen oder Vorbereiten einer bestehenden Bewerbung.
- Empfängerblöcke enthalten keine technischen Ergänzungsplatzhalter mehr.

## Verifikation

- PHP-Syntax und vollständige PHP-Vertragstests
- Chromium-Regressionstests bei Desktop- und Mobilbreite
- Hilfekatalog und technische Referenzen
- Produktiver Hash- und HTTP-/Security-Header-Abgleich nach externer TOTP-Freigabe
