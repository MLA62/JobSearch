# Version 2.1.9 – gültiger Versandzeitstempel

Stand: 07.09.2026. Bereitstellungskandidat.

## Änderungen

- «Gesendet am» akzeptiert die vom Server gespeicherten Sekundenwerte.
- Der vollständige Zeitstempel wird konsistent gerendert und nach automatischem Speichern weiterverwendet.
- Die irreführende Browsermeldung mit zwei zulässigen benachbarten Minuten entfällt.

## Qualität und Deployment

PHP-Syntax, alle 29 PHP-Testdateien, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff werden vor dem Deployment geprüft. Nach TOTP-Freigabe wird ausschließlich `public_html/jobs.jema.business/index.php` ersetzt.
