# JeMa Jobs - Product Decisions

Status: 2026-06-15

## Identity and access

- Public registration with one-hour email verification.
- Administrator approval is required after email verification.
- 2FA is mandatory for every user.
- Primary 2FA methods: WebAuthn/security key or TOTP authenticator.
- Email codes are recovery-only and expire after 10 minutes for guest access.
- Initial administrator: Markus Lauber, German UI, `admin@jema.business`.
- Central outbound SMTP account: `admin@jobs.jema.business`.

## Privacy and sharing

- All user data is private and visible only to its creator by default.
- Guest links can target a record, folder/list, or complete user area.
- Guest permissions are read-only or read/write, with recipient email and expiry.
- Guest email verification and browser/device binding are mandatory.
- Owners can revoke individual guest device sessions.
- Write access permits create and edit but never delete.
- Guest changes save directly and can be restored through version history.
- Download policy per guest link: none, original, PDF, or both.
- Optional PDF/image watermarking is enabled by default for new guest links.
- Only owners can create bulk exports; guests may download permitted individual files.

## History, deletion and storage

- Audit log, trace-back, record versions and complete file versions are immutable.
- They are retained without limit until account deletion.
- Users can restore their own or guest changes; restoration creates another log entry.
- Account deletion is immediate after password and 2FA confirmation.
- A complete ZIP export can be requested before deletion and remains for 24 hours.
- Audit exports include CSV and PDF.
- General exports support CSV, Excel and PDF, filtered/current view or complete data.
- Default storage quota is 5 GB per user; increases require administrator approval.
- Warnings are sent at 80% and 95% usage.
- Administrators see usage and requests but not private file contents.
- User-requested cleanup defaults to data older than six months and requires full
  administrator approval or rejection. Audit/trace-back, active applications,
  future events, current documents and active guest shares are excluded.
- Cleanup preview is mandatory; approved cleanup runs without a second confirmation.
- Cleanup report is available in app, email and PDF for 24 hours.

## CRM and organization

- Extensive CRUD for profiles, documents, preferences, companies, jobs, contacts,
  contact logs and applications.
- Hierarchical folders and lists with manual/name/date/change sorting, colors,
  icons and favorites.
- Records may appear in multiple folders/lists without duplication.
- Global full-text search covers CRM data, notes, logs and document contents.
- PDF/Office extraction and OCR support German, English, Spanish and Portuguese.
- OCR text can be corrected with immutable version history.
- CVs, references and certificates yield confirmation-required suggestions for
  profile data, skills, employers, education and periods.

## Jobs, imports and matching

- External sources: job portals, company career pages, pasted job URLs and pasted
  email/job-ad text. RSS is excluded.
- Default sources: jobs.ch, jobup.ch, Job-Room/RAV, JobScout24, Indeed Switzerland,
  LinkedIn Jobs, publicjobs.ch, SwissDevJobs, myScience and eFinancialCareers.
- Sources and search URLs are editable/removable; reset restores defaults.
- Automatic collection is enabled only through permitted APIs or explicit permission.
- Blue-collar and unskilled jobs are shown only after activating the relevant filter.
- Duplicate/similar postings across portals are detected and can be consolidated.
- Matching considers skills, experience, preferences, location, salary, workload,
  work model and benefits with user-adjustable weights and multiple profiles.
- All jobs are shown by default; minimum-score filtering is opt-in.
- Match explanations show positive, missing, conflicting and uncertain factors.
- Requirements can be marked irrelevant for personal scoring.
- Learning from favorites, rejections, applications and ratings is on by default,
  confirmation-based, disableable and resettable.

## Language and translation

- UI languages: German, English, Spanish and Portuguese.
- Browser translation is offered first in lists and then for full job details.
- Original, translation and side-by-side views are available.
- Company, contact, product and place names remain unchanged.
- Browser-translated text can be pasted and stored per language.
- Stored translations are versioned, correctable and visible through guest links.

## Notifications

- Reminders use in-app, email and browser notifications.
- Default quiet hours are 22:00-07:00 in the user's timezone.
- Held notifications are sent together at 07:00.
- Users can change timezone, quiet hours and channels.

## Prototype scope

The first deployed prototype includes responsive login/registration, strict private
ownership checks, dashboard, company and job CRUD, assisted import from job URLs or
pasted email/job-ad text, search/status/blue-collar filters, duplicate warnings, a
transparent starter match score, application creation from jobs, editable application
status, channel, email and cover-letter text, follow-up dates, immutable status history
and immutable audit-log display.
Email verification, approval UI, 2FA, guest sharing, document processing and scheduled
portal imports are the next implementation phases.
