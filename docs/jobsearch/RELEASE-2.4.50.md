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

- Quell-Commit: `97e7d66`
- Ziel: `public_html/jobs.jema.business`
- Einmalige Benutzerfreigabe: `3e4d3c6e56cb1ceaa17620f53995c5f7`;
  die dadurch eröffnete Freigabestunde deckte die einzelnen, jeweils
  protokollierten Sicherungs-, Upload-, Extraktions- und Aufräumaktionen.
- Vorab-Sicherungen:
  `index.php.bak-20260923-2.4.49-pre-2.4.50` und
  `assets/app.css.bak-20260923-2.4.49-pre-2.4.50`
- Produktive SHA-256-Prüfsummen, bytegleich zum Commit:
  - `index.php`: `5fba8de0e094bde00ff3bf0eddb1ff6d882d58613501f8dc33f6158978121adf`
  - `assets/app.css`: `b5357bc9b3e1ea418423d4b3ef66dfb82676f1249fb40f3ae26d9edbcbbed8dc`
  - `assets/data/swiss-postal-centroids.json`: `95b25b2c89a9021752bbf58951634e3b6cf31adc6831ea20aa94e2f584aeaf6b`
  - `assets/data/switzerland-outline.path`: `d0848fcfee0d3f1873839bffd4a414103c27c3d36d59efff67416a9169e2504c`
  - `assets/data/README.md`: `404f66d9b7168731147bb0ae99bde8d4d9b514944129d18de80928941ad116bc`
- Die öffentliche Seite liefert HTTP 200, Version 2.4.50 und die
  Sicherheitsheader HSTS, CSP, `nosniff`, `DENY` und `no-referrer`.
- Die Browser-Sitzung war bei der Abschlussprüfung abgemeldet. Die
  angemeldete Sichtprüfung der Diagramme und Karte bleibt daher getrennt
  offen; der neue Chromium-Test hat beide Ansichten lokal bestanden.

Der erste kombinierte Connector-Aufruf konnte den lokalen Windows-Pfad
nicht auf dem Connector-Host auflösen und änderte keine Produktionsdatei.
Das identische Archiv wurde danach innerhalb derselben Freigabestunde als
Inhalt hochgeladen, aus dem Remote-Archiv extrahiert und bytegenau geprüft.
Das temporäre Remote-Archiv wurde anschliessend gelöscht; die beiden
Vorab-Sicherungen bleiben erhalten.
