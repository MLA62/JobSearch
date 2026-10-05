# Release 2.4.56 – Proportionale Ortskreise beim Kartenzoom

Stand: 05.10.2026

## Ziel

Beim Hineinzoomen sollen sich die Arbeitsorte räumlich voneinander
entfernen, ohne dass die Ortskreise im selben Faktor anwachsen und erneut
grosse Teile der Karte oder benachbarte Orte überdecken.

## Umsetzung

- Die Kreiszentren folgen weiterhin exakt der gezoomten und verschobenen
  Schweizer Karte.
- Jeder Ortskreis erhält gleichzeitig den mathematischen Gegenfaktor zum
  aktuellen Zoom. Dadurch bleibt sein sichtbarer Durchmesser stabil.
- Die Grössenverhältnisse zwischen den Orten bleiben unverändert; grössere
  Jobkonzentrationen erscheinen weiterhin grösser als kleinere.
- Farbsegmente, Umrandungen, Tooltips und individuelle Ortslinks bleiben
  vollständig erhalten.

## Verifikation

- Der Dashboard-Vertrag prüft die inverse Kreis-Skalierung.
- Der Chromium-Test führt den produktiven Zoomcode bei Desktop- und
  Mobilbreite aus und bestätigt den Gegenfaktor nach dem Vergrössern.
- Der vollständige PHP-Testbestand sowie Dokumentations- und
  Generatorprüfungen werden vor der Auslieferung wiederholt.
