# Release 1.18.0

## Scope

- Shared application workflow: draft, ready, sent, interviews, accepted or rejected.
- Workflow dates shared by tables, cards and exports; no invented sent date for drafts.
- Multiple dated interview appointments and explicit follow-up appointments.
- No automatic calendar entries from preparation or waiting task fields.
- Reviewed, transactional legacy-data migration with row backups; not run at startup.
- Remove automatic selection-checkbox columns. Preserve functional form checkboxes.
- Wrap table content and company addresses without ellipsis.
- Pin production styles and scripts to asset commit a7ab08cd447c48223a4b586ae2fa92d133fd5ca6.

## Verification

- PHP syntax validation and all 11 PHP test files passed using PHP 8.1 with -n.
- Workflow browser fixture: five languages, four widths, table and cards (40 cases).
- Company address browser fixture: three widths; separate street, town and phone lines.
- PDF wrapping and pagination regression tests passed.
- Immutable CDN app.css, layout.css and layout.js returned HTTP 200.
- Fixtures use synthetic records. Migration tests mock the database; no live MySQL
  migration or full authenticated production acceptance test has been performed.

## Deployment Gate

Target: public_html/jobs.jema.business/index.php. Baseline SHA-256:
8d5c12a49471c009154e8a9f4adeed7890389247ffec3e0cac7ebc8fc5e41946.

Replace the PHP entry point only through a fresh cPanel file-write approval.
Verify the resulting server hash and authenticated pages after execution.
Existing-data migration requires a separate admin preview at ?page=workflow_review
and explicit application of the reviewed plan. Unknown legacy values are preserved.
Google synchronization requires the completed v6 migration marker.

This document records a deployment candidate, not a completed production deployment.
