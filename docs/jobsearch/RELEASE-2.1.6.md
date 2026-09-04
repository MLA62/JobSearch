# Version 2.1.6 – native Bewerbungsvorbereitung

Stand: 04.09.2026. Bereitstellungskandidat.

## Änderungen

- Das Arbeitsmodal wird vor der Verarbeitung sichtbar gezeichnet.
- `Bewerbung vorbereiten` wird anschließend als normale Formularnavigation abgeschickt.
- Safari folgt der serverseitigen Weiterleitung unmittelbar zum erzeugten oder vorhandenen Bewerbungsdatensatz.
- `Abbrechen` kann eine noch laufende Browsernavigation stoppen.

## Qualität und Deployment

PHP-Syntax, alle Vertragstests, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff werden vor dem Deployment geprüft. Nach TOTP-Freigabe wird ausschließlich `public_html/jobs.jema.business/index.php` ersetzt und anschließend verifiziert.
