# Version 2.1.3 – zuverlässige KI-Bewerbungstexte auf Mobilgeräten

Stand: 04.09.2026. Deployed.

## Änderungen

- Der Button `Texte mit KI erstellen/anpassen` wird direkt und damit auch in mobilen Browsern zuverlässig verarbeitet.
- Das modale Fenster `In Arbeit` wird sichtbar gezeichnet, bevor der KI-Request beginnt.
- Eine ausgefüllte KI-Instruktion muss in den relevanten Texten substanziell umgesetzt werden.
- Bei leerer Instruktion verbessert die KI Betreff, Begleit-E-Mail und Motivationsschreiben selbständig.
- Nach Erfolg verhindert ein Cache-Buster die Anzeige einer veralteten Seite.
- Abbrechen beendet die Browser-Anfrage und lässt die aktuelle Seite geöffnet.

## Qualität

PHP-Syntax, alle PHP-Vertragstests, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff werden vor dem Deployment geprüft. Die Tests rufen weder OpenAI noch die Produktionsdatenbank auf.

## Deployment

Nach externer TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` aus
Quell-Commit `9a248b1` ersetzt. Die Produktionsdatei hat 1033647 Bytes, Modus 0644 und SHA-256
`f40ed05750dd2ff9f0708c004f35f910cef8dce02842c19e8ec0156223818c3b`; sie entspricht exakt den
geprüften lokalen Bytes. Die öffentliche Seite liefert HTTP 200, zeigt Version 2.1.3, enthält
KI-Modal und mobile Klicklogik und zeigt keinen PHP-Laufzeitfehler.
