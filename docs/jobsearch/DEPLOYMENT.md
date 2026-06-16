# Deployment prerequisites

## Database setup

1. In cPanel, create database `JeMaJobs`. cPanel should prefix it as
   `kerubina_JeMaJobs`.
2. Create a dedicated database user with a newly generated strong password.
3. Assign that user to the database with the privileges required by migrations
   and normal application use.
4. In phpMyAdmin select `kerubina_JeMaJobs`, then import:
   - `sql/jobsearch/01_schema.sql`
   - `sql/jobsearch/02_views.sql`
5. Use `sql/jobsearch/00_create_database.sql` only if cPanel/phpMyAdmin grants
   the account `CREATE DATABASE` permission.

## Access still required for deployment

- SFTP/SSH access is strongly preferred over FTP/FTPS. Required details are
  hostname, port, username and authentication method. A restricted SSH key is
  preferred to a password.
- Confirm the actual document root. The supplied paths refer to both
  `/public_html/jobs.jema.business` and `/home/kerubina/jobs.jema.business/`.
- Database username and a newly generated database password.
- SMTP host, port, encryption mode, username, password and sender address for
  verification, 2FA and application email.
- cPanel cron capability for queued email, reminders and job imports.
- Confirmation that PHP CLI is available and whether Composer can run on the
  server. If not, vendor dependencies must be built locally and uploaded.
- Chromium, Google Chrome or another Chromium-compatible browser must be
  available to the PHP CLI for original job PDF rendering. Configure its path as
  `job_pdf_browser_path` in `public/config.php` if auto-detection is not enough.
- TLS must be active for the final domain before login or file upload testing.

Never commit FTP, database, SMTP, API or application secrets to Git.

## SMTP configuration

Set these values in `public/config.php` on the server when outbound mail should
be active:

```php
'mail_from' => 'admin@jobs.jema.business',
'mail_from_name' => 'JeMa Jobs',
'smtp_enabled' => true,
'smtp_host' => 'smtp.example.com',
'smtp_port' => 587,
'smtp_encryption' => 'tls',
'smtp_username' => 'admin@jobs.jema.business',
'smtp_password' => 'replace-on-server',
```

Supported `smtp_encryption` values are `tls`, `ssl` and `none`. While
`smtp_enabled` is false or SMTP details are missing, password reset links remain
visible in the prototype UI and no email is sent.

The FTP password supplied during initial planning was exposed in conversation
and must be replaced before it is used for deployment. Prefer SFTP/SSH with a
restricted key; otherwise use explicit FTPS with a newly generated password.

## One-time web installer

When SSH is unavailable, `deploy/installer/install.php` can apply the schema
through a short-lived FTPS deployment. It accepts POST requests only, requires
a random installation token, blocks direct access to SQL/config files, removes
its runtime configuration after success and writes a lock file. Delete the
entire installer directory from the server immediately after verification.

## Original job PDF worker

New jobs imported with a source URL are saved with `original_pdf_status =
pending`. A server-side worker renders those source pages with a headless browser
and attaches the resulting PDF as `Originale Stellenausschreibung`.

Run manually from the project root:

```sh
php deploy/render-pending-job-pdfs.php --limit=5
```

Recommended cron shape once PHP CLI and Chromium are confirmed:

```sh
*/10 * * * * cd /home/kerubina/jobs.jema.business && php deploy/render-pending-job-pdfs.php --limit=5 >> var/log/job-pdf-render.log 2>&1
```

Use `--dry-run` to list pending jobs without rendering. Use
`--browser=/path/to/chrome` or `job_pdf_browser_path` in `public/config.php`
when the browser is not in a standard location.

### Installation record

- Installed on: 2026-06-15
- Database: `kerubina_JeMaJobs`
- Result: 31 base tables and 4 reporting views
- Installer runtime configuration removed automatically after success
- Temporary installer directory removed after verification
- PHP observed through the domain: 8.1.34 (different from the 8.4.21 value
  previously shown in the hosting control panel)
