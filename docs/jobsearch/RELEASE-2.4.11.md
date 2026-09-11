# Version 2.4.11 – Wirksame Report-Anzeigearten

Stand: 11.09.2026.

## Fehlerursache

Die Anzeigeart wurde im Reportformular gespeichert und in der Reportliste angezeigt. Beim Öffnen
renderte die Anwendung jedoch unabhängig vom gespeicherten Wert immer dieselbe Tabelle.

## Korrektur

- Tabelle, Liste, Karten und Vorschau besitzen jetzt eigenständige Bildschirmdarstellungen.
- Kalenderreports bieten zusätzlich nach Tag, Kalenderwoche und Monat gruppierte Ansichten.
- Kalenderansichten werden ausschließlich für die Datenbasis Kalender angeboten.
- Ein Wechsel der Datenbasis aktualisiert die Anzeigearten unmittelbar.
- Serverseitige Validierung verhindert ungültige Kombinationen und fällt sicher auf Tabelle zurück.
- Sämtliche sichtbaren Reportwerte bleiben HTML-escaped und mehrzeilig lesbar.

## Prüfung

Alle 36 PHP-Testdateien prüfen unter anderem Validierung, Speicherung, Renderer-Verknüpfung und alle
sieben Anzeigearten. 3'929 Hilfeprüfungen, 1'314 Hilfe-Seeds, beide Dokumentationsgeneratoren, 80
Markdown-Dateien mit 61 lokalen Links sowie alle acht Chromium-Testdateien waren erfolgreich. Der
neue Chromium-Test rendert jede Anzeigeart bei 390 und 1'366 Pixel Breite, prüft Überläufe und stellt
sicher, dass Dateninhalte nicht als ausführbares HTML interpretiert werden.

## Deployment

Das Deployment ersetzt ausschließlich `public_html/jobs.jema.business/index.php` und
`public_html/jobs.jema.business/assets/app.css`. Produktive Hashes und HTTP-Prüfung werden nach der
externen TOTP-Freigabe ergänzt.
