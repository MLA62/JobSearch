# Version 2.1.1 – KI-Arbeitsanzeige und App-Kontingent

Stand: 2026-09-04. Zur externen TOTP-Freigabe vorbereitet.

## Änderung

- Länger dauernde KI-Aktionen zeigen ein modales Fenster `In Arbeit` mit Aktivitätsanzeige und Abbrechen.
- Der Abbruch beendet die Browser-Anfrage und lädt die aktuelle Seite neu; die speziellen Such- und Importdialoge bleiben erhalten.
- Die Fusszeile aller Seiten nennt OpenAI, das konfigurierte Modell und den geschätzten verfügbaren Anteil des laufenden App-Kontingents.
- Abgeschlossene Responses-Antworten werden anhand ihrer eindeutigen Response-ID mit Input-, Cache-Input-, Output-Tokenzahlen und Websuchen erfasst.
- Die Berechnung verwendet die konfigurierten Modellpreise und ein konfigurierbares App-Budget. Sie ist ausdrücklich kein OpenAI-Abrechnungssaldo.
- Die Nutzungstelemetrie speichert keine Prompts, Antworten, API-Schlüssel oder fachlichen Inhalte.
- Neue Laufzeittabelle: `ai_usage_events`; sie wird beim Start idempotent angelegt.

## Prüfung und Deployment

Beide PHP-Syntaxprüfungen, alle 28 PHP-Testdateien, 3513 Hilfe-Inhaltsprüfungen, 1124 Hilfeseed-Prüfungen, beide Dokumentationsgeneratoren mit `--check` und `git diff --check` bestanden. Externe OpenAI-Aufrufe und die produktive MariaDB wurden dabei nicht verändert. Deploymentnachweis und Liveprüfung werden nach der TOTP-Freigabe ergänzt. Vorheriger bestätigter Live-Stand: Version 2.1.0.
