# Version 2.3.6 – Korrektes Admin-KI-Anfrageziel

Stand: 09.09.2026. Das Deployment ist produktiv ausgeführt und verifiziert.

## Änderungen

- Der Admin-KI-Fetch liest die Formularzieladresse nicht mehr über die überschattbare
  `form.action`-Eigenschaft.
- Ohne explizites Formularziel bleibt die vollständige aktuelle Admin-KI-Adresse einschließlich
  Query-Parameter erhalten.
- Nicht strukturierte HTTP-Fehler nennen den tatsächlich erreichten Endpunkt; die Eingabe bleibt
  weiterhin gespeichert.
- Der Chromium-Test bildet exakt das produktive Formular ohne `action`-Attribut nach und akzeptiert
  nur den korrekten Request-Pfad.

## Prüfung

- PHP-Lint und vollständige PHP-Regression
- Hilfe- und Referenzgenerator im Prüfmodus
- Chromium: exakter Request-Pfad, HTTP-422-Antwort, Erfolg, Eingabeerhalt, Markdown, Modal und
  fehlender Dokument-Scroll bei 390×800, 1366×768 und 2048×1080
- Produktive Dateihashes stimmen mit dem geprüften Release überein; HTTPS, Version und
  Security-Header sind bestätigt.
