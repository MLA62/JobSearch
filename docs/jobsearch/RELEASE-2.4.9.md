# Version 2.4.9 – Vollständige Reportbeziehungen

Stand: 11.09.2026.

## Fehlerursache

Einige Reportfelder lasen nur eine direkte Fremdschlüsselspalte. Dadurch blieb beispielsweise
«Vermittler» leer, wenn die Stellenfirma selbst als Vermittler markiert war. Dasselbe Muster konnte
bei weiteren eindeutig verknüpften Feldern zu leeren Ausgaben führen.

## Korrektur

- Vermittler wird aus der separaten Zuordnung oder ersatzweise aus der als Vermittler markierten
  Stellenfirma ermittelt.
- Ein zugewiesener Kontakt ohne Namen wird über seine E-Mail sichtbar.
- Der Job eines Dokuments wird auch über dessen Bewerbung ermittelt.
- Firma und Kontakt eines Kalendereintrags werden bei Bedarf über den verknüpften Kontakt ergänzt.
- Der Job eines Kontakts wird auch über dessen Bewerbung ermittelt.
- Unverbundene Firmen und Kontakte werden nicht automatisch zugeordnet.

## Prüfung

Der Regressionstest deckt jeden Alternativpfad sowie den negativen Fall eines normalen Arbeitgebers
ohne Vermittlerzuordnung ab.

Alle 36 PHP-Testdateien, 3'907 Hilfeprüfungen, 1'304 Hilfe-Seeds, beide Dokumentationsgeneratoren,
78 Markdown-Dateien mit 61 lokalen Links und alle sieben Chromium-Testdateien waren erfolgreich.

## Deployment

Nach externer TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` ersetzt.
Die lokale und produktive Datei sind bytegleich. Die öffentliche Seite liefert HTTP 200, Version 2.4.9
sowie HSTS, CSP, `nosniff`, `DENY` und `no-referrer`. Datenbank, Konfiguration und Assets blieben unverändert.
