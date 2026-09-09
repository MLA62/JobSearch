# Version 2.3.7 – Belastbarer Admin-KI-Datenvertrag

Datum: 09.09.2026

## Behoben

- Firmenrecherchen mit UID oder Handelsregisternummer scheitern nicht mehr an einer vermeintlich
  fehlenden Datenbankspalte.
- Generische Firmenoperationen werden vor dem Schreiben an den spezialisierten Firmenimport
  übergeben; Identitäts-, Adress-, Web- und Kontaktdaten bleiben vollständig erhalten.
- Tabellen-, Feld- und Referenzaliasse werden für sämtliche freigegebenen JeMa-Datentabellen
  serverseitig normalisiert. Unbekannte recherchierte Zusatzangaben brechen die restliche Operation
  nicht ab und werden in vorhandenen Notizen nachvollziehbar erhalten.
- Teilergänzungen bestehender Datensätze werden nicht mehr fälschlich wegen fehlender
  Neuanlage-Pflichtfelder abgelehnt.
- Firmen und andere Stammdaten werden bei Mehrfachaufträgen vor referenzierenden Datensätzen
  verarbeitet; zusätzliche Firmen- und Kontakt-Referenzfelder werden zentral aufgelöst.

## Datenvertrag und Sicherheit

- Die KI erhält die tatsächliche serverseitige Allowlist mit beschreibbaren Feldern, Pflichtfeldern
  für Neuanlagen und zulässigen Abgleichfeldern.
- Tabellen- und Spaltenbezeichner stammen weiterhin ausschließlich aus fest codierten Allowlists;
  Werte werden als gebundene Parameter verarbeitet.
- Authentisierung, Geheimnisse, Audit, Löschungen und externer E-Mail-Versand bleiben gesperrt.

## Prüfung

- Neuer ausführbarer Regressionstest für den konkret beobachteten Cleeven-Fall.
- Feldvertrag aller 20 freigegebenen Tabellen und alle unterstützten Fremdschlüsselarten geprüft.
- Bestehende PHP-, Dokumentations-, Hilfe- und Chromium-Tests bleiben Bestandteil der Freigabe.

## Deployment

- Produktiv ausgerollt am 09.09.2026 über die externe TOTP-Freigabe.
- Alle fünf übertragenen Dateien wurden nach dem Upload bytegenau mit dem geprüften lokalen Release
  verglichen.
- `https://jobs.jema.business/` liefert HTTP 200, Version 2.3.7 und die vorgesehenen
  Security-Header.
