# Release 2.4.50 – Dashboard und Statuskonsistenz

Stand: 23.09.2026

## Änderung

- Die Seite «Guten Tag» zeigt Kuchendiagramme für die Statusanteile der
  Jobs, Direktfirmen/Vermittler und Bewerbungen.
- Eine Schweiz-Heatmap stellt darunter die Konzentration der Firmen nach
  Ort dar. Sie verwendet vorhandene Koordinaten oder lokal gebündelte
  amtliche PLZ-/Ortsmittelpunkte; unbekannte Orte werden nicht geraten.
- Jeder angezeigte Ort enthält Anzahl und relativen Heatmap-Radius. Die
  Kartenabdeckung weist lokalisierte und berücksichtigte Firmen aus.
- Bewerbungsstatusänderungen führen den Status der zugehörigen Stelle
  zentral und mandantengetrennt nach. Alle schreibenden Pfade verwenden
  dieselbe Abbildung; Entwurf und Bereit ändern den Jobstatus nicht.
- Die Oberfläche und Hilfe sind in de-CH, fr-CH, en-GB, pt-BR und es-MX
  ergänzt und responsiv für Desktop und Mobilgeräte umgesetzt.

## Geodaten

- PLZ-/Ortsmittelpunkte: Bundesamt für Landestopografie swisstopo,
  «Amtliches Ortschaftenverzeichnis mit Postleitzahl», Datensatz vom
  01.09.2026, WGS84.
- Landesumriss: swisstopo swissBOUNDARIES3D, Ausgabe 2026-01,
  Objekt `LANDESGEBIET` für die Schweiz, lokal vereinfacht.
- Beide Ableitungen sind statische Laufzeitdaten. Beim Öffnen des
  Dashboards werden keine Adressen und keine externen Dienste abgefragt.

## Datenbankwirkung

Keine Schemaänderung. Beim ersten Request werden die neuen übersetzten
Dashboard-Beschriftungen über den bestehenden Hilfeseed aktualisiert.
Die zentrale Synchronisation aktualisiert bei künftigen
Bewerbungsstatusänderungen ausschliesslich den Status der bereits
zugeordneten Stelle, sofern der Zielstatus abweicht.

## Prüfung

- PHP-Syntax und vollständige PHP-Vertragstests
- Chromium-Prüfung von Diagrammen, Karte und responsivem Layout
- Hilfe- und Referenzgeneratoren mit `--check`
- Hashvergleich, öffentliche Versionsprüfung und angemeldete Sichtprüfung
  nach dem Deployment

## Deploymentnachweis

Wird nach der produktiven Ausführung mit Commit, Freigabe, Dateihashes
und Live-Abnahme ergänzt.
