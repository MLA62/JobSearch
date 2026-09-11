# Version 2.4.8 – Eindeutiges Job-Room-Resultat und sortierbare Spalten

Stand: 11.09.2026.

## Fehlerursache

`job_room_result` besitzt technisch den Standardwert `open`. Dieser Wert wurde bisher auch dann als
«Noch offen» ausgegeben, wenn `job_room_registration` noch `unknown` oder `not_recorded` war. Dadurch
wirkte eine noch gar nicht im Job-Room erfasste Bewerbung wie ein bereits erfasster offener Vorgang.
Ausserdem zeigte der Report-Editor die Felder stets in Katalogreihenfolge und bot keine direkte
Sortierung der Spalten.

## Korrektur

- Das Resultatfeld prüft zuerst den Erfassungsstatus im Job-Room.
- Ohne bestätigte Erfassung erscheint «Noch nicht im Job-Room erfasst».
- «Noch offen», «Anstellung» und «Absage» werden erst nach bestätigter Erfassung ausgegeben.
- Die Korrektur gilt gezielt für das Job-Room-Resultat in Reports und Job-Room-Hilfe.
- Reportfelder lassen sich am Griff per Drag-and-drop neu anordnen.
- Die gespeicherte Reihenfolge wird beim Bearbeiten und Anzeigen exakt beibehalten.
- Nach Speichern oder Aktualisieren wird sofort die neue Reportansicht geladen.
- Alle anderen Felder und Inhalte bleiben unverändert.

## Prüfung

Der Regressionstest deckt `unknown`, `not_recorded` und `recorded` ab. Er prüft zusätzlich den
tatsächlichen Erfassungsstatus in beiden Ausgabewegen, die wiederhergestellte Spaltenreihenfolge und
die Drag-and-drop-Ereignisse des Editors.

Alle 36 PHP-Testdateien, 3'896 Hilfeprüfungen, 1'299 Hilfe-Seeds, beide Dokumentationsgeneratoren,
77 Markdown-Dateien mit 61 lokalen Links und alle sieben Chromium-Testdateien waren erfolgreich.

## Deployment

Nach externer TOTP-Freigabe wurden ausschließlich `public_html/jobs.jema.business/index.php` und
`public_html/jobs.jema.business/assets/app.css` ersetzt. Beide Dateien sind bytegleich zum getesteten
Stand. Seite und Stylesheet liefern HTTP 200; die Seite weist Version 2.4.8 sowie HSTS, CSP,
`nosniff`, `DENY` und `no-referrer` aus. Datenbank, Konfiguration und JavaScript-Dateien blieben unverändert.
