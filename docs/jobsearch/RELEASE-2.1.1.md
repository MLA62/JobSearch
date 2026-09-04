# Version 2.1.1 – KI-Arbeitsanzeige und App-Kontingent

Stand: 2026-09-04. Nach externer TOTP-Freigabe deployed.

## Änderung

- Länger dauernde KI-Aktionen zeigen ein modales Fenster `In Arbeit` mit Aktivitätsanzeige und Abbrechen.
- Der Abbruch beendet die Browser-Anfrage und lädt die aktuelle Seite neu; die speziellen Such- und Importdialoge bleiben erhalten.
- Die Fusszeile aller Seiten nennt OpenAI, das konfigurierte Modell und den geschätzten verfügbaren Anteil des laufenden App-Kontingents.
- Abgeschlossene Responses-Antworten werden anhand ihrer eindeutigen Response-ID mit Input-, Cache-Input-, Output-Tokenzahlen und Websuchen erfasst.
- Die Berechnung verwendet die konfigurierten Modellpreise und ein konfigurierbares App-Budget. Sie ist ausdrücklich kein OpenAI-Abrechnungssaldo.
- Die Nutzungstelemetrie speichert keine Prompts, Antworten, API-Schlüssel oder fachlichen Inhalte.
- Neue Laufzeittabelle: `ai_usage_events`; sie wird beim Start idempotent angelegt.

## Prüfung und Deployment

Beide PHP-Syntaxprüfungen, alle 28 PHP-Testdateien, 3513 Hilfe-Inhaltsprüfungen, 1124 Hilfeseed-Prüfungen, beide Dokumentationsgeneratoren mit `--check` und `git diff --check` bestanden. Externe OpenAI-Aufrufe und die produktive MariaDB wurden dabei nicht verändert.

Nach externer TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` aus Quell-Commit `86973ef5927ff43794b66c1dde5807d66994a76a` deployed. Die Produktionsdatei hat 1037096 Bytes, Modus 0644 und SHA-256 `26e253c85e23a6dd93731ae3b2f8c62bfe0ced9367963a22822241c66eb8876e`; sie entspricht exakt den geprüften lokalen Bytes. Die öffentliche Seite liefert HTTP 200, zeigt Version 2.1.1 und die KI-Fusszeile und enthält keinen sichtbaren PHP-Laufzeitfehler. Die neue Tabelle `ai_usage_events` wird beim Start idempotent angelegt. Vorheriger bestätigter Live-Stand: Version 2.1.0.
