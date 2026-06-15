# JeMa Jobs

Responsive, multilingual job-search CRM for `jobs.jema.business`.

## Target platform

- PHP 8.1+ (production currently runs PHP 8.1)
- MariaDB 10.6
- Desktop, mobile portrait and mobile landscape
- Languages: German, English, Spanish and Portuguese

## Database setup

The requested database name is `kerubina_JeMaJobs`.

Preferred cPanel workflow:

1. Create database `JeMaJobs` in cPanel MySQL Databases.
2. Create a dedicated database user and assign it to the database.
3. Select `kerubina_JeMaJobs` in phpMyAdmin.
4. Import `sql/jobsearch/01_schema.sql`.
5. Import `sql/jobsearch/02_views.sql`.

If phpMyAdmin grants `CREATE DATABASE`, `sql/jobsearch/00_create_database.sql`
can be used before steps 4 and 5.

## Included design

- Multi-user authentication, roles and 2FA metadata
- User profiles, preferences and versioned documents
- Companies, jobs, contacts and contact history
- Applications, cover letters, email drafts and attachments
- Job-source/import tracking
- Saved list/report definitions with columns, filters and sorting
- Calendar day/week/month data source
- Generated PDF/export metadata
- Audit trail

See `docs/jobsearch/REQUIREMENTS.md` and
`docs/jobsearch/DEPLOYMENT.md` for the captured requirements and deployment
prerequisites.

## Prototype

The `public/` directory contains a responsive PHP prototype with registration,
login, private company/job CRUD, assisted import from job URLs or pasted
email/job-ad text, filters, duplicate warnings, a transparent starter match
score, application workflow with status history and follow-up dates, and an
immutable audit-log display.
Copy `public/config.example.php` to `public/config.php` only on the target
server and fill in secrets there.

## Security

Do not commit credentials. Production secrets belong in a server-side `.env`
file outside the public document root. Any password disclosed in chat or another
shared channel must be rotated before deployment.
