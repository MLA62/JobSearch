# Version 2.3.4 – Verbindliche Admin-Aufträge und Markdown-Ausgabe

Stand: 2026-09-09. Dieses Release korrigiert die Admin-KI-Ausführung und die Darstellung der
Ergebnisse.

## Änderungen

- Direkte Admin-Aufträge innerhalb der JeMa-Jobs-Plattform werden ausgeführt, einschließlich
  Recherche, Einzel-, Mehrfach- und Massenoperationen in den freigegebenen Datentabellen.
- Die Frage „Hast Du die Firma erfasst?“ wird als `record_lookup` gegen den eigenen Bestand
  beantwortet und meldet einen konkreten Treffer oder „nicht gefunden“.
- Die frühere Formulierung „unklare oder nicht autorisierte Änderungen werden nicht ausgeführt“
  wurde aus der Admin-Oberfläche entfernt.
- Die Ausgabe unterstützt Markdown sicher: `**Fettdruck**`, Überschriften, Listen, Links und
  Inline-Code werden gerendert; HTML aus der KI-Antwort wird nicht ausgeführt.
- Die Admin-KI-Seite verwendet keinen Seitenscroll. Eingabe und Ausgabe sind gleichzeitig sichtbar;
  nur das Ausgabefeld scrollt intern.
- Versionsnummer in App, Debug-Export, Beispielkonfiguration, Tests, Hilfequelle und Dokumentation
  auf 2.3.4 erhöht.

## Prüfung

- PHP-Lint und alle vorhandenen PHP-Regressionstests.
- Markdown-Renderer mit HTML-Escaping und Status-Lookup im Vertragstest.
- cPanel-Upload mit Approval `f60329e108af2ead3d48f52b5d3f8406` ausgeführt.
- Alle fünf Remote-Dateien stimmen bytegenau mit den lokalen Release-Dateien überein.
- Öffentliche Seite: HTTP 200, Version 2.3.4, keine sichtbaren PHP-Fatal-/Parse-/Uncaught-Fehler.
- HSTS, CSP, `nosniff`, `DENY` und `no-referrer` bestätigt.
