# Dashboard-Geodaten

Diese Dateien sind statische, für das JeMa-Jobs-Dashboard reduzierte
Ableitungen amtlicher swisstopo-Daten. Sie vermeiden externe Anfragen
und enthalten keine Benutzer- oder Firmendaten.

- `swiss-postal-centroids.json`: Orts- und PLZ-Mittelpunkte aus dem
  «Amtlichen Ortschaftenverzeichnis mit Postleitzahl», WGS84, Stand
  01.09.2026. Quelle:
  `https://data.geo.admin.ch/ch.swisstopo-vd.ortschaftenverzeichnis_plz/`
- `switzerland-outline.path`: vereinfachter SVG-Pfad des Schweizer
  Landesgebiets aus swissBOUNDARIES3D 2026-01. Quelle:
  `https://data.geo.admin.ch/ch.swisstopo.swissboundaries3d/`

Die produktive Anwendung lädt beide Dateien nur lokal. Aktualisierungen
müssen die Quelle, den Datenstand und die visuellen Regressionstests
erneut dokumentieren.
