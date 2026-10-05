# Release 2.4.55 – Zoom für die Schweizer Jobkarte

Stand: 05.10.2026

## Ziel

Die stark belegten Regionen der Startseitenkarte sollen gezielt vergrössert
und verschoben werden können, ohne die Verknüpfung der einzelnen Ortsblasen
mit den gefilterten Joblisten zu verlieren.

## Umsetzung

- Die Karte bietet gut sichtbare Schaltflächen für Vergrössern, Verkleinern
  und Zurücksetzen.
- Der Zoom reicht kontrolliert von 100 bis 400 Prozent. Die Karte lässt sich
  innerhalb ihres sichtbaren Rahmens verschieben; ein Wegziehen aus dem
  Ausschnitt wird begrenzt.
- Mausrad und Touchpad zoomen am Zeiger. Nach dem Vergrössern kann die Karte
  mit Maus oder Zeigegerät verschoben werden.
- Tastaturbedienung ist mit `+`, `-`, `0` beziehungsweise `Escape` und den
  Pfeiltasten möglich. Fokus, Beschriftungen und Zoomstand sind zugänglich.
- Ein Verschieben löst keinen Ortslink aus. Ein normaler Klick auf eine
  Bubble öffnet weiterhin die passend gefilterte Jobliste.
- In der mobilen Ausgangsansicht bleibt vertikales Seitenscrollen möglich;
  erst eine vergrösserte Karte übernimmt die Verschiebegeste.

## Verifikation

- PHP-Syntaxprüfung für den Front Controller.
- Fachlicher Dashboard-Vertragstest für Steuerelemente, Eingabewege und
  Begrenzungen.
- Chromium-Prüfung bei Desktop- und Mobilbreite für Darstellung, Zoom,
  Rücksetzung, Bubble-Farben, Ortsliste und horizontalen Überlauf.
- Vollständiger automatisierter Testbestand sowie generierte Hilfe- und
  Referenzdokumentation vor Auslieferung.

## Auslieferung

Die produktive Auslieferung erfolgt gemäss dem dokumentierten cPanel-Prozess
mit externer Einmalfreigabe, Sicherung, Hashvergleich und anschliessender
öffentlicher sowie angemeldeter Prüfung.
