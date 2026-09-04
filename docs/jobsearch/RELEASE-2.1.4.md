# Version 2.1.4 – eindeutige KI-Textaktion

Stand: 04.09.2026. Deployed.

## Änderungen

- Leeres KI-Instruktionsfeld: Betreff, Begleit-E-Mail und Motivationsschreiben werden vollständig neu aus den verfügbaren Bewerbungsdaten erstellt.
- Die bisherigen Texte werden bei einer Neuerstellung nicht als Vorlage an die KI übermittelt.
- Ausgefülltes KI-Instruktionsfeld: Die vorhandenen Texte werden gemäß der Instruktion überarbeitet.

## Qualität und Deployment

PHP-Syntax, alle 28 Vertragstests, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff wurden
geprüft. Nach TOTP-Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` aus
Quell-Commit `30538fe` ersetzt. Die Produktionsdatei hat 1034125 Bytes, Modus 0644 und SHA-256
`a2bf954278a04966e64fe675596453115c75c5396a769e0d95089a26636f2519`; sie entspricht exakt den
geprüften lokalen Bytes. Die öffentliche Seite liefert HTTP 200, zeigt Version 2.1.4 und keinen
sichtbaren PHP-Laufzeitfehler.
