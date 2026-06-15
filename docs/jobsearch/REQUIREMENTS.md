# JeMa Jobs CRM - Initial Scope

## Hosting

- Public URL: `https://jobs.jema.business`
- PHP: 8.4
- Database: MariaDB 10.6
- Database name requested: `kerubina_JeMaJobs`
- Database encoding: application tables use `utf8mb4`, independent of the
  server's legacy `latin1` default.
- Deployment target and credentials are intentionally not stored in Git.

## Product requirements

- Responsive web application for desktop, mobile portrait and mobile landscape.
- Multiple users with role-based access and isolated user-owned CRM data.
- Login plus 2FA through email, TOTP authenticator or WebAuthn/security key.
- UI languages: German, English, Spanish and Portuguese.
- CRUD-first CRM for user profiles, documents, preferences, companies, jobs,
  contacts, communication logs and applications.
- Job records may be imported from external sources, subject to source terms,
  robots rules, copyright and applicable privacy law.
- Applications include cover letter files, email subject/body and attachments.
- Saved list/report generator with selectable columns, filters and sorting.
- Table, list, card/preview and day/week/month calendar presentations.
- PDF/file generation for reports and email attachments.
- Audit logging for security-relevant and data-changing operations.

## Security requirements

- Passwords use PHP `password_hash()` with Argon2id where available.
- 2FA secrets and source/API credentials are encrypted at application level.
- Authentication and reset tokens are stored only as hashes.
- Uploaded documents live outside `public_html` and are downloaded through an
  authorization-checked PHP endpoint.
- Every query for owned CRM data is scoped to the authenticated user unless an
  explicit shared-access record grants access.
- Use CSRF protection, secure/HttpOnly/SameSite cookies, output escaping,
  prepared statements, rate limiting and login lockout.
- Production secrets belong in a server-side `.env` file excluded from Git.

## Implementation phases

1. Bootstrap, environment config, migrations, authentication, roles and 2FA.
2. CRUD for profile documents, preferences, companies, jobs and contacts.
3. Applications, contact history, calendar and email workflow.
4. Saved lists/reports, PDF generation and exports.
5. Controlled job import connectors, matching, hardening and deployment.

