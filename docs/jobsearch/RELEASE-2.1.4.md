# Version 2.1.4 – eindeutige KI-Textaktion

Stand: 04.09.2026. Bereitstellungskandidat.

## Änderungen

- Leeres KI-Instruktionsfeld: Betreff, Begleit-E-Mail und Motivationsschreiben werden vollständig neu aus den verfügbaren Bewerbungsdaten erstellt.
- Die bisherigen Texte werden bei einer Neuerstellung nicht als Vorlage an die KI übermittelt.
- Ausgefülltes KI-Instruktionsfeld: Die vorhandenen Texte werden gemäß der Instruktion überarbeitet.

## Qualität und Deployment

PHP-Syntax, alle Vertragstests, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff werden vor dem Deployment geprüft. Nach TOTP-Freigabe wird ausschließlich `public_html/jobs.jema.business/index.php` ersetzt und anschließend verifiziert.
