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

- Quell-Commit: `e50acf4`
- Produktiv ausgerollt am 23.09.2026 über die bereits aktive einmalige
  TOTP-Freigabe; keine zweite Benutzerfreigabe war erforderlich.
- Vorheriger Stand 2.4.50 gesichert als
  `index.php.bak-20260923-2.4.50-pre-2.4.51` und
  `assets/app.css.bak-20260923-2.4.50-pre-2.4.51`.
- Produktiver `index.php`-SHA-256:
  `87b3b658065d931d77296f5351b27e61ae5bb3a859f3a978f28902d6c2ac79e6`
  (1'430'715 Bytes).
- Produktiver `assets/app.css`-SHA-256:
  `571bfffba1dd79ad4f9950e7bc90c8716fbdd00bf4db26a0255e784c86e7bbb6`
  (59'237 Bytes).
- Die beiden produktiven Hashes stimmen exakt mit den lokalen
  Release-Dateien überein.
- Öffentliche Kontrolle: Seite erreichbar; CSS-/JS-Revision und Footer
  weisen Version `2.4.51` aus.
- Das temporäre Deploymentarchiv wurde nach erfolgreicher Prüfung
  entfernt; die beiden Rücksicherungen bleiben erhalten.
- Connector-Aktionen: Upload `6f8c8da31a530f1843a96213684f680b`,
  Backups `0ca970f1541e1d8948b73d7189e05f5a` und
  `26988c67bd53d8e57f7f16f3ed843b54`, Extraktion
  `dd1b4cba9a9dcaaeb5ea46fb44effee3`, Aufräumen
  `231a80ec563611eacf4d07a319503b9f`.
