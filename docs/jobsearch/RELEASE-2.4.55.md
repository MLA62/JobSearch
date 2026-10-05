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

Produktiv ausgerollt am 05.10.2026 aus Commit `4fa02f6` mit Approval-ID
`4d0db24eac2a2cfb0c043cb32611d4be`. Der Connector sicherte beide
überschriebenen Dateien. Die produktiven SHA-256-Prüfsummen entsprechen
lokal exakt: `index.php`
`4a7322090d364ac5b7ca1221b9906c1a30bb230a1efe44b1da811eb9a65a7cd5`
und `assets/app.css`
`55b00543cb8ff1fcefc35f2a48917cf160643d432e30b6b2a340beb2fd7deb80`.
Die öffentliche Seite bestätigt Version 2.4.55; eine angemeldete
Sichtprüfung bleibt mangels angemeldeter Browser-Sitzung separat offen.
