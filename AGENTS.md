# JeMa Jobs Working Rules

- User requirement: application changes must be published to jobs.jema.business and checked live as part of completion, not merely implemented locally.
- Use the approved deployment process. Never bypass external approval requirements.
- If approval or authenticated verification is unavailable, explicitly report that deployment or live verification is pending; do not claim the change is live.
- Deploy only the reviewed scope. Keep unrelated or unfinished local changes out of a release.
- Preserve existing user data and configuration. Do not add credentials or production data to Git.

## Documentation and Help

- Read docs/jobsearch/REQUIREMENTS.md, WORKFLOW.md and PROGRAMMDOKUMENTATION.md before changing domain behavior.
- Keep current behavior, desired requirements, historical release evidence and unverified assumptions separate.
- Update help/source.json in all five locales; generate PHP seeds and help Markdown with tools/build_help.php. Never hand-edit generated output.
- Run both documentation generators with --check and the focused tests in docs/jobsearch/TESTING.md.
- Historical releases and i18n audits remain historical. Do not relabel old results as fresh verification.
- Record release commit, deployment hash, DB effects and authenticated acceptance separately. The workflow v6 data migration needs its own reviewed confirmation.
