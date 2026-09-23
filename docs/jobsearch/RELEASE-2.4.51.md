# Release 2.4.51 – Dashboard-Drilldowns

Stand: 23.09.2026

## Änderung

- Alle Job- und Bewerbungsstatus sowie Direkt/Vermittler im Dashboard
  öffnen die jeweilige Liste mit dem angeklickten Wert als Filter.
- Alle Heatmap-Kreise und Ortsnamen öffnen die Firmenliste mit exaktem
  Ortsfilter.
- Rechts erscheinen alle lokalisierten Orte in einer vertikal
  scrollbaren Liste; es gibt keine Begrenzung auf zehn Orte mehr.
- Die vier oberen Summenkarten für Jobs, Firmen, Bewerbungen und Kalender
  wurden entfernt. Dadurch entfällt auch eine Dashboard-Datenbankabfrage.
- Dashboard-Drilldowns ersetzen alte Sitzungsfilter, damit der angeklickte
  Wert zuverlässig das erwartete Ergebnis zeigt.

## Datenbankwirkung

Keine Schema- und keine fachliche Datenänderung. Beim ersten Request
aktualisiert der bestehende Hilfeseed den geänderten Überblickstext in
fünf Sprachen.

## Prüfung

- PHP-Syntax und vollständige PHP-Vertragstests
- gezielter Chromium-Test für Links, vollständige Ortsliste,
  Scrollverhalten und responsive Darstellung
- Hilfe- und Referenzgeneratoren mit `--check`
- Hashvergleich und Live-Kontrolle nach dem Deployment

## Deploymentnachweis

Wird nach der produktiven Ausführung ergänzt.
