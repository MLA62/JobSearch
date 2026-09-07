# Version 2.3.0 – Security Hardening

Stand: 07.09.2026. Status: lokal geprüft, produktives Deployment ausstehend.

## Änderungen

- Kritischen Browser-Fallback des Passwort-Resets entfernt; alte offene Reset-Tokens werden entwertet.
- Login, TOTP, Passwort-Reset und Registrierung pro Identität und IP begrenzt.
- TOTP-Secrets mit AES-256-GCM und verpflichtendem separatem App-Schlüssel geschützt.
- Sitzungsentwertung nach Passwort- und Zwei-Faktor-Änderungen sowie feste Laufzeitgrenzen ergänzt.
- Registrierung auf zehn aktive Benutzer begrenzt und per Konfiguration abschaltbar gemacht.
- SMTP/IMAP auf öffentliche Ziele, sichere Ports und TLS beschränkt.
- Uploads durch Endungs-/MIME-Abgleich und Downloads durch sichere Header gehärtet.
- CSP, HSTS, Clickjacking-, Referrer-, MIME- und Permissions-Schutz ergänzt.
- CSS und JavaScript ohne externe CDN-Laufzeitabhängigkeit lokal eingebunden.
- SQL-Fallback verwendet auch ohne mysqlnd Prepared Statements statt Stringinterpolation.
- Datenmigration `16_security_hardening.sql` und Security-Regressionsuite ergänzt.

## Deployment

Vor dem PHP-Austausch werden Backup und `sql/jobsearch/16_security_hardening.sql` angewendet.
Der produktive `config.php` muss einen eigenen `app_key` mit mindestens 32 Zeichen besitzen.
Nach dem Deployment werden Login, TOTP, Reset, Sicherheitsheader, lokale Assets und Fehlerlog geprüft.
