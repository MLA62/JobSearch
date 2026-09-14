# Release 2.4.20

Stand: 2026-09-14.

## Ergebnis

Der Schnellimport verliert keine Linkziele mehr, wenn formatierte Resultate aus einer Webseite
eingefügt werden. Stand vorher nur der sichtbare Text wie «Zur Arbeitgeber-Website» oder
«Job merken» im Feld, wird nun auch die tatsächlich hinterlegte vollständige HTTPS-Adresse als
Klartext übernommen und vom vorhandenen Importprozess verarbeitet.

## Verhalten

- Klartext- und Markdown-URLs funktionieren unverändert.
- HTML-Links werden als sichtbarer Text plus vollständige Ziel-URL eingefügt.
- Der Browserkanal `text/uri-list` wird als zweite Linkquelle berücksichtigt.
- Mehrere eindeutige URLs bleiben erhalten und werden nicht doppelt eingefügt.
- `javascript:` und andere unsichere Linkprotokolle werden verworfen.
- Bereits vorhandener Inhalt sowie Text vor und nach der Einfügeposition bleiben erhalten.

## Prüfung

Ein Chromium-Test simuliert eine formatierte Zwischenablage mit Arbeitgeber- und Inseratlink,
prüft beide übernommenen HTTPS-Ziele sowie die Ablehnung eines unsicheren Links. Der bestehende
Schnellimport-, Drill-down-, KI-Analyse- und Datenbankpfad bleibt unverändert.
