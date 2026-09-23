# Release 2.4.53 – Jobstatus je Orts-Bubble

Stand: 23.09.2026

## Änderung

- Die Schweiz-Karte aggregiert Jobs nach Arbeitsort statt Firmen nach
  Firmenadresse.
- Jede Bubble ist ein Kreisdiagramm; jeder Job am Ort bildet einen
  gleich grossen Anteil.
- Jobs ohne Bewerbung werden weiss dargestellt. Alle übrigen Sektoren
  verwenden dieselben Farben wie der entsprechende Status im
  Jobs-Kuchendiagramm.
- Kreisgrösse, Kartensumme und Ortsliste beziehen sich auf Jobs.
- Kartenpunkte und Ortsnamen öffnen die nach Arbeitsort gefilterte
  Jobliste.
- Tooltips und zugängliche Beschriftungen nennen die lokale Verteilung.

## Datenbankwirkung

Keine Schema-, Stamm- oder Bewegungsdatenänderung.

## Prüfung

- PHP-Vertragstests für vollständig weisse, vollständig farbige und
  anteilig geteilte Orts-Bubbles
- Chromium-Test für SVG-Sektoren, Farbübereinstimmung, Links,
  Scrollverhalten und responsive Darstellung
- vollständige PHP-Suite sowie Hilfe- und Referenzgeneratoren

## Deploymentnachweis

- Quell-Commit: `41232b1`.
- Produktiv ausgerollt wurden ausschliesslich `index.php` und
  `assets/app.css`; es gab keine Schemaänderung.
- Die bestehende Freigabe `dedf95f63ac3a895537500d7e7ee33e8`
  wurde für den Rollout wiederverwendet.
- Der Connector sicherte die Vorgängerdateien unter
  `approval.lauber.online/storage/file_backups/20260923_145756_271c17d9_public_html_jobs.jema.business_index.php`
  und
  `approval.lauber.online/storage/file_backups/20260923_145756_410ab103_public_html_jobs.jema.business_assets_app.css`.
- Produktive und lokale Dateien sind bytegleich. SHA-256:
  `index.php` `08dc7236710106f0e8215bd136a1b6364eb01aec8dce804bab6be8b64e4d908d`
  (1'435'992 Bytes), `assets/app.css`
  `4f7466085385131655975462c3713a94fd4ea682ed9215ccc06a6e4ed66d5623`
  (59'254 Bytes).
- Die öffentliche Seite liefert HTTP 200, die CSS-Datei HTTP 200 und
  weist Version 2.4.53 ohne sichtbaren PHP-Fehler aus. Die aktuelle
  Browsersitzung war bei der abschliessenden Kontrolle abgemeldet; die
  fachliche Darstellung wurde deshalb mit den lokalen PHP- und
  Chromium-Vertragstests verifiziert.
