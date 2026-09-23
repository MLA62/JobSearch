# Release 2.4.52 – Einheitliche Heatmap-Farbe

Stand: 23.09.2026

## Änderung

- Die Kernkreise der Firmen-Heatmap verwenden exakt die Farbe des ersten
  Segments im Jobs-Kuchendiagramm.
- Die Farbe stammt aus derselben zentralen Diagrammpalette und wird nicht
  separat dupliziert.
- Die Kernkreise sind vollständig deckend. Der transparente Halo bleibt
  als zusätzliche Darstellung der Konzentration erhalten.

## Datenbankwirkung

Keine Schema-, Stamm- oder Bewegungsdatenänderung.

## Prüfung

- PHP-Syntax und vollständige PHP-Vertragstests
- Chromium-Vergleich der berechneten Kreis- und Jobs-Legendenfarbe
- Hilfe- und Referenzgeneratoren mit `--check`

## Deploymentnachweis

- Quell-Commit: `dea329b3bb09af05fab6210ca24acb21ea027362`.
- Produktiv ausgerollt am 23.09.2026 mit Freigabe
  `f0ff1fc2b3f3478f530282129d6a233c`.
- Das Connector-Backup sichert beide vorherigen Dateien unter
  `approval.lauber.online/storage/file_backups/20260923_142502_a2857de5_public_html_jobs.jema.business_assets_app.css`
  und
  `approval.lauber.online/storage/file_backups/20260923_142502_2cb8aa64_public_html_jobs.jema.business_index.php`.
- Produktiver `index.php`-SHA-256:
  `f4dc5a011a845ce2f7aed3815b065b29eca7d0be4671468c96ff236026dac5c7`
  (1'430'721 Bytes).
- Produktiver `assets/app.css`-SHA-256:
  `044132aaa614dab75b7443f428f33202148848b3ae583fe10b605259d570453f`
  (59'260 Bytes).
- Beide produktiven Hashes stimmen exakt mit den lokal getesteten
  Release-Dateien überein.
- Öffentliche Kontrolle: HTTP 200, Version 2.4.52, kein sichtbarer
  PHP-Fehler; ausgeliefertes CSS enthält die gemeinsame
  Jobs-/Heatmap-Farbvariable und den deckenden Kernkreis.
