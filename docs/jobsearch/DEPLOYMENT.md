# JeMa Jobs - Deployment

Stand: 2026-06-19

Produktversion: `1.15.0`

Dieses Dokument beschreibt, wie JeMa Jobs produktiv oder in einer neuen
Umgebung wieder aufgebaut wird. Secrets werden absichtlich nicht dokumentiert.

## Zielumgebung

- Domain: `https://jobs.jema.business`
- PHP: 8.1+; produktiv wurde PHP 8.1.x beobachtet.
- Datenbank: MariaDB 10.6.
- Webroot enthaelt `index.php`, `assets/` und `storage/`.
- Deployment erfolgt aktuell per explizitem FTPS auf Port 21.
- FTP/FTPS-Server: `ftp.kerubina.net`.
- FTP/FTPS-Benutzer, Passwort, Datenbankpasswort, SMTP-Passwoerter und
  `app_key` gehoeren nicht in Git.

## Lokale Voraussetzungen

- Git.
- PHP CLI fuer Syntaxchecks.
- `curl.exe` oder ein anderer FTPS-faehiger Client.
- Zugriff auf das Repository `MLA62/JobSearch`.

Empfohlene Checks vor jedem Deployment:

```powershell
php -l public/index.php
git diff --check
git status --short --branch
```

Auf der lokalen Windows-Installation kann PHP auch ueber den installierten
Winget-Pfad laufen, falls `php` nicht im PATH liegt.

## Datenbank-Neuaufbau

1. In cPanel eine Datenbank `JeMaJobs` erstellen. Der produktive Name ist
   erwartungsgemaess `kerubina_JeMaJobs`.
2. Dedizierten Datenbankbenutzer mit starkem Passwort erstellen.
3. Benutzer der Datenbank zuweisen.
4. In phpMyAdmin `kerubina_JeMaJobs` auswaehlen.
5. Importieren:
   - `sql/jobsearch/01_schema.sql`
   - `sql/jobsearch/02_views.sql`
6. `sql/jobsearch/00_create_database.sql` nur verwenden, wenn die Umgebung
   `CREATE DATABASE` erlaubt.

Hinweis: `public/index.php` enthaelt zusaetzliche rueckwaertskompatible
Runtime-Migrationen fuer produktive Weiterentwicklung. Ein Neuaufbau sollte
trotzdem mit dem SQL-Schema beginnen.

## Konfiguration

Auf dem Zielserver:

1. `public/config.example.php` nach `public/config.php` kopieren.
2. Werte setzen:

```php
'app_name' => 'JeMa Jobs',
'app_url' => 'https://jobs.jema.business',
'app_version' => '1.15.0',
'app_key' => '64-random-hex-characters',
'admin_emails' => ['admin@jema.business'],
'db_host' => 'localhost',
'db_port' => 3306,
'db_name' => 'kerubina_JeMaJobs',
'db_user' => 'server-specific-user',
'db_password' => 'server-secret',
```

3. Optional zentralen SMTP-Fallback setzen. Normale Benutzer-E-Mails laufen
   ueber die SMTP-Einstellungen des jeweiligen Benutzers im Profil.
4. `config.php` niemals committen.
5. `app_key` nach Produktivstart nicht wechseln, sonst koennen verschluesselte
   SMTP-Secrets nicht mehr entschluesselt werden.

## Dateien deployen

Produktiv relevante Dateien:

- `public/index.php` -> `index.php`
- `public/assets/app.css` -> `assets/app.css`
- `public/assets/favicon.svg` -> `assets/favicon.svg`
- `public/assets/qrcode.min.js` -> `assets/qrcode.min.js`
- `public/assets/totp-qr.js` -> `assets/totp-qr.js`
- `deploy/` nur, wenn Worker oder Installer benoetigt werden.
- `sql/` nicht in den oeffentlichen Webroot deployen.
- `docs/` und Markdown-Dateien sind Projektdokumentation, nicht zwingend Teil
  des Webroots.

Beispiel mit Platzhaltern:

```powershell
curl.exe -k --ssl-reqd --ftp-pasv --user "FTP_USER:FTP_PASSWORD" -T "public/index.php" "ftp://ftp.kerubina.net/index.php"
curl.exe -k --ssl-reqd --ftp-pasv --user "FTP_USER:FTP_PASSWORD" -T "public/assets/app.css" "ftp://ftp.kerubina.net/assets/app.css"
```

Keine echten Zugangsdaten in Shell-History, Chat, Markdown oder Git speichern.

## Live-Checks nach Deployment

```powershell
curl.exe -k -L -s -o NUL -w "%{http_code} %{size_download} %{url_effective}\n" "https://jobs.jema.business/?page=login"
curl.exe -k -L -s "https://jobs.jema.business/?page=login" | Select-String -Pattern "app.css?v=|footer"
```

Erwartung:

- HTTP `200`.
- HTML ist nicht leer.
- Footer zeigt die aktuelle Version.
- Geschuetzte Seiten leiten ohne HTTP 500 zum Login.

Nach manueller Anmeldung sollten stichprobenartig geprueft werden:

- Dashboard
- Hilfe mit Gluebirnen-Kontext-Hilfe
- Profil
- Jobsuche
- Schnellimport
- Bewerbungen
- Dokumente
- Pendent/Kalender
- Admin-Benutzerverwaltung

## Worker

### Original-PDFs fuer Jobs

Jobs mit Quell-URL koennen serverseitig als Original-PDF gerendert werden.

```sh
php deploy/render-pending-job-pdfs.php --limit=5
```

Cron-Beispiel:

```sh
*/10 * * * * cd /home/kerubina/jobs.jema.business && php deploy/render-pending-job-pdfs.php --limit=5 >> var/log/job-pdf-render.log 2>&1
```

Chromium oder ein kompatibler Browser muss verfuegbar sein. Falls Autodetektion
nicht reicht, `job_pdf_browser_path` in `public/config.php` setzen.

### Dokumenttextextraktion

```sh
php deploy/extract-document-texts.php --limit=20
```

Cron-Beispiel:

```sh
*/15 * * * * cd /home/kerubina/jobs.jema.business && php deploy/extract-document-texts.php --limit=20 >> var/log/document-texts.log 2>&1
```

PDF-Text benoetigt Poppler/`pdftotext`.

## Einmaliger Web-Installer

Wenn kein SSH verfuegbar ist, kann `deploy/installer/install.php` temporaer
genutzt werden.

Regeln:

- Nur kurzfristig deployen.
- Zufallstoken verwenden.
- Nach Erfolg komplett vom Server entfernen.
- SQL- und Config-Dateien nicht offen im Webroot belassen.

## Rollback

1. Vor Deployment die zuletzt funktionierende `index.php` und `app.css`
   sichern oder den letzten Git-Commit kennen.
2. Bei HTTP 500 sofort vorherige Datei per FTPS zurueckspielen.
3. Live-Check ausfuehren.
4. Danach Ursache lokal beheben, linten und erneut deployen.

Keine destruktiven Datenbank-Rollbacks ohne explizite Sicherung und Freigabe.

## GitHub-Synchronisation

Nach erfolgreichem Live-Deployment:

```powershell
git status --short --branch
git add <geaenderte-dateien>
git commit -m "Beschreibende Nachricht"
git push origin main
git status --short --branch
```

Arbeitsbaum und GitHub sollen nach produktiven Aenderungen synchron sein.

## Installationsnotizen

- Erstinstallation: 2026-06-15.
- Datenbank: `kerubina_JeMaJobs`.
- Urspruenglicher Import: 31 Basistabellen und 4 Reporting-Views.
- Runtime-Migrationen haben seitdem weitere Tabellen und Spalten ergaenzt,
  unter anderem User Sessions, Support Grants, SMTP Settings, Jobplattformen,
  Jobfragen, Kontaktlog-Anhaenge, Sharing, Uebersetzungen und Cleanup.
