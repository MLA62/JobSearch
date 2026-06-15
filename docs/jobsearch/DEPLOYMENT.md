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
- TLS must be active for the final domain before login or file upload testing.

Never commit FTP, database, SMTP, API or application secrets to Git.

The FTP password supplied during initial planning was exposed in conversation
and must be replaced before it is used for deployment. Prefer SFTP/SSH with a
restricted key; otherwise use explicit FTPS with a newly generated password.
