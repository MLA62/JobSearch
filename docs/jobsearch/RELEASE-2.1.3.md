# Version 2.1.3 – zuverlässige KI-Bewerbungstexte auf Mobilgeräten

Stand: 04.09.2026. Bereitstellungskandidat.

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

Nach TOTP-Freigabe wird ausschließlich `public_html/jobs.jema.business/index.php` ersetzt und anschließend per Hash sowie öffentlichem HTTP-Aufruf verifiziert.
