# Release 2.4.57 – Ortslinks nach Kartenzoom

Stand: 05.10.2026

## Fehlerbild

Nach dem Vergrössern der Schweizer Jobkarte öffnete ein kurzer Klick auf
einen Ortskreis die gefilterte Jobansicht nicht mehr. Der Zeiger wurde schon
beim Drücken von der Verschiebe-Logik übernommen, obwohl keine Karte
verschoben wurde.

## Korrektur

- Ein Zeigerkontakt bleibt zunächst vollständig beim Ortslink.
- Erst ab einer tatsächlichen Bewegung von sechs Bildpunkten wird daraus
  eine Drag-Geste und die Karte übernimmt den Zeiger.
- Nur eine erkannte Drag-Geste unterdrückt den anschliessenden Klick.
- Zoom, proportionale Kreisgrössen, Tooltips und sämtliche Filterlinks
  bleiben unverändert erhalten.

## Verifikation

- Der produktive Fehler wurde angemeldet mit dem Bern-Kreis bei 135 Prozent
  reproduziert.
- Der Chromium-Test zoomt die Karte und öffnet danach über einen kurzen
  Bubble-Klick den erwarteten Link.
- Der Vertragstest sichert Schwellwert, verzögerte Zeigerübernahme und
  ausschliessliche Klickunterdrückung bei einer echten Drag-Geste.
