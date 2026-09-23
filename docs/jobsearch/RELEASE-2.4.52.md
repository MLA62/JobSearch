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

Wird nach dem produktiven Rollout ergänzt.
