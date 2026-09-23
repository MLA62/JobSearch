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

Wird nach dem produktiven Rollout ergänzt.
