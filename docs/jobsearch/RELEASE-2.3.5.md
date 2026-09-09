# Version 2.3.5 – Zuverlässige Admin-KI-Interaktion

Stand: 09.09.2026. Das Deployment benötigt die externe TOTP-Freigabe.

## Änderungen

- Admin-KI-Anweisungen bleiben bei Erfolg und Fehler erhalten.
- HTTP-Fehler mit JSON werden im Ausgabefeld mit konkreter Ursache angezeigt; es gibt keinen
  automatischen Seiten-Reload mehr.
- Kontext, Ausgabeprotokoll, letzte Anweisung und Modell werden pro Benutzer in
  `admin_ai_memory` gespeichert und nur über «Gedächtnis löschen» entfernt.
- Ein Browser-Entwurf schützt noch nicht abgesendete Eingaben.
- Der einleitende Erklärungstext wurde ersatzlos entfernt.
- Die bildschirmfeste Aufteilung wurde auf Ausgabe und Eingabe vereinfacht.

## Prüfung

- PHP-Lint und vollständige PHP-Regression
- Hilfe- und Referenzgenerator im Prüfmodus
- Chromium: konkrete HTTP-422-Antwort, Erfolg, Eingabeerhalt, Markdown, Modal und fehlender
  Dokument-Scroll bei 390×800, 1366×768 und 2048×1080
- Nach Deployment: Dateihashes, HTTPS-/Security-Header, Datenbankmigration und angemeldeter
  Admin-KI-Ablauf getrennt verifizieren
