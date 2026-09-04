# Version 2.1.6 – native Bewerbungsvorbereitung

Stand: 04.09.2026. Deployed.

## Änderungen

- Das Arbeitsmodal wird vor der Verarbeitung sichtbar gezeichnet.
- `Bewerbung vorbereiten` wird anschließend als normale Formularnavigation abgeschickt.
- Safari folgt der serverseitigen Weiterleitung unmittelbar zum erzeugten oder vorhandenen Bewerbungsdatensatz.
- `Abbrechen` kann eine noch laufende Browsernavigation stoppen.

## Qualität und Deployment

PHP-Syntax, alle 28 Vertragstests, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff wurden
geprüft. Nach TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` aus
dem geprüften Release-Commit ersetzt. Die Produktionsdatei entspricht exakt dem lokalen
Release-Stand. Die öffentliche Seite liefert HTTP 200, zeigt Version 2.1.6 und das KI-Modal und
enthält keinen sichtbaren PHP-Laufzeitfehler.
