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

## Auslieferung

Produktiv ausgerollt am 05.10.2026 aus Commit `60c5a60` mit Approval-ID
`332ed5a023d6fb92e1e650b325e076dc`. Der Connector sicherte die ersetzte
Datei. Die produktive SHA-256-Prüfsumme von `index.php` entspricht lokal
exakt:
`1fa6fadd07214733fb843fd2041fdddfd6e92bcefdead4dfb6503df7c1b1cef7`.
Die öffentliche Seite bestätigt Version 2.4.56. Auf der angemeldeten
Startseite wurden der Zoom von 100 auf 135 Prozent und die Rücksetzung auf
exakt 100 Prozent produktiv geprüft.
