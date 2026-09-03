# Release 1.18.0

> Historischer Stand. Am 2026-09-03 als Archiv eingeordnet; damalige Aussagen und Testergebnisse sind kein Nachweis fuer die aktuelle Version. Aktuelle Regeln: [WORKFLOW.md](WORKFLOW.md), Aufbau: [REBUILD.md](REBUILD.md), Pruefstand: [DOCUMENTATION_AUDIT.md](DOCUMENTATION_AUDIT.md). Alte Freigaben und Deploymentanweisungen nicht erneut verwenden.

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

## Production Deployment

Executed approval 178140aff3037484ff97dfb81402eb20 on 2026-09-03.
Server modification time: 2026-09-03T15:38:53Z. Entry point: 770794 bytes.
Verified server SHA-256 equals local deployment bytes:
fcbc11c55cd882664a4c16c585576de78c8c42cf3669a5903b2a1697f13fce18.
Source release commit: c997dbb (release/workflow-1.18.0).
Unauthenticated HTTP check returned 200, version 1.18.0 and pinned assets;
no PHP fatal or parse error was visible. Authenticated production acceptance
and the separate existing-data migration are not yet completed.
